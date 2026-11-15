<?php

namespace Tests\Feature\Gym;

use App\Models\Gym;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class LinkClientToGymTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    private const INVALID = 'この招待コードは使用できません。';

    private Gym $gym;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create(['name' => 'テストジム']);
        $this->client = User::factory()->personal()->create();
        $this->fakeAuditLog();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function code(array $attributes = []): InvitationCode
    {
        return InvitationCode::factory()->create(['gym_id' => $this->gym->id, ...$attributes]);
    }

    #[Test]
    public function 個人利用者にはコード入力画面が表示される(): void
    {
        $this->actingAs($this->client)->get(route('gym-link.show'))
            ->assertOk()
            ->assertSee('招待コード')
            ->assertDontSee('ジムとの紐付けを解除');
    }

    #[Test]
    public function 有効なコードを確認すると確認画面にジム名と共有の注意が表示される(): void
    {
        $invitation = $this->code();

        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $invitation->code])
            ->assertRedirect(route('gym-link.confirm.show'))
            ->assertSessionHas('invitation_code_id', $invitation->id);

        $this->get(route('gym-link.confirm.show'))
            ->assertOk()
            ->assertSee('テストジム')
            ->assertSee('これまでの記録（トレーニング・体組成）もジムのトレーナーに共有されます')
            ->assertSee('トレーナーもあなたの記録を入力・編集できるようになります');
    }

    #[Test]
    public function 小文字で入力しても大文字として扱われる(): void
    {
        $invitation = $this->code(['code' => 'ABCD2345']);

        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => ' abcd2345 '])
            ->assertRedirect(route('gym-link.confirm.show'));
    }

    #[Test]
    public function 確認して同意すると紐付けられコードは使用済みになる(): void
    {
        $invitation = $this->code();

        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $invitation->code]);
        $this->post(route('gym-link.store'), ['consent' => '1'])
            ->assertRedirect(route('gym-link.show'))
            ->assertSessionHas('status', 'ジムに紐付けました。')
            ->assertSessionMissing('invitation_code_id');

        $this->assertSame($this->gym->id, $this->client->fresh()->gym_id);
        $invitation->refresh();
        $this->assertSame($this->client->id, $invitation->used_by);
        $this->assertNotNull($invitation->used_at);
        $this->assertSame(
            [['message' => 'client.linked', 'context' => ['client_id' => $this->client->id, 'invitation_code_id' => $invitation->id]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function 同意のチェックがないと紐付けられない(): void
    {
        $invitation = $this->code();

        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $invitation->code]);
        $this->from(route('gym-link.confirm.show'))->post(route('gym-link.store'))
            ->assertRedirect(route('gym-link.confirm.show'))
            ->assertSessionHasErrors(['consent' => '共有について確認し、チェックを入れてください。']);

        $this->assertNull($this->client->fresh()->gym_id);
        $this->assertNull($invitation->fresh()->used_at);
    }

    #[Test]
    public function 確認画面を経ずに直接送信しても紐付けられない(): void
    {
        $invitation = $this->code();

        $this->actingAs($this->client)->post(route('gym-link.store'), ['consent' => '1', 'code' => $invitation->code])
            ->assertRedirect(route('gym-link.show'))
            ->assertSessionHasErrors(['code' => self::INVALID]);

        $this->assertNull($this->client->fresh()->gym_id);
        $this->assertNull($invitation->fresh()->used_at);
    }

    #[Test]
    public function 紐付けるときフォームのコードやIDは使われずセッションのコードだけが使われる(): void
    {
        $confirmed = $this->code();
        $other = $this->code();

        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $confirmed->code]);
        $this->post(route('gym-link.store'), ['consent' => '1', 'code' => $other->code, 'invitation_code_id' => $other->id]);

        $this->assertNotNull($confirmed->fresh()->used_at);
        $this->assertNull($other->fresh()->used_at);
    }

    #[Test]
    public function 確認後に取消や期限切れになったコードでは紐付けられない(): void
    {
        $revoked = $this->code();
        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $revoked->code]);
        $revoked->forceFill(['revoked_at' => now()])->save();

        $this->post(route('gym-link.store'), ['consent' => '1'])->assertSessionHasErrors(['code' => self::INVALID]);
        $this->assertNull($this->client->fresh()->gym_id);

        $expiring = $this->code();
        $this->post(route('gym-link.confirm'), ['code' => $expiring->code]);
        Carbon::setTestNow(now()->addDays(8));

        $this->post(route('gym-link.store'), ['consent' => '1'])->assertSessionHasErrors(['code' => self::INVALID]);
        $this->assertNull($this->client->fresh()->gym_id);
    }

    #[Test]
    public function 確認後に他の人が使用したコードでは紐付けられない(): void
    {
        $invitation = $this->code();
        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $invitation->code]);

        $invitation->forceFill(['used_by' => User::factory()->personal()->create()->id, 'used_at' => now()])->save();

        $this->post(route('gym-link.store'), ['consent' => '1'])->assertSessionHasErrors(['code' => self::INVALID]);
        $this->assertNull($this->client->fresh()->gym_id);
    }

    #[Test]
    public function 使えないコードは理由を出し分けず同じメッセージになる(): void
    {
        $cases = [
            'nonexistent' => 'ZZZZZZZZ',
            'used' => $this->code(['used_by' => User::factory()->personal()->create()->id, 'used_at' => now()])->code,
            'revoked' => $this->code(['revoked_at' => now()])->code,
            'expired' => $this->code(['expires_at' => now()->subMinute()])->code,
            'short' => 'ABC',
            'long' => 'ABCDEFGHJ',
            'bad characters' => 'AAAAAAA0',
            'symbols' => 'AB-CD-EF',
        ];

        Carbon::setTestNow(now());

        foreach ($cases as $code) {
            // 回数制限（5回／分）にかからないよう、1回ごとに時間を進める
            Carbon::setTestNow(now()->addMinutes(2));
            $this->actingAs($this->client)->from(route('gym-link.show'))
                ->post(route('gym-link.confirm'), ['code' => $code])
                ->assertRedirect(route('gym-link.show'))
                ->assertSessionHasErrors(['code' => self::INVALID]);
        }

        $this->assertNull($this->client->fresh()->gym_id);
    }

    #[Test]
    public function コードが空の場合は入力を促すメッセージになる(): void
    {
        $this->actingAs($this->client)->post(route('gym-link.confirm'), [])
            ->assertSessionHasErrors(['code' => '招待コードを入力してください。']);
    }

    #[Test]
    public function 一度使われたコードは別の利用者が使えない(): void
    {
        $invitation = $this->code();
        $second = User::factory()->personal()->create();

        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $invitation->code]);
        $this->post(route('gym-link.store'), ['consent' => '1']);
        auth()->logout();

        $this->actingAs($second)->post(route('gym-link.confirm'), ['code' => $invitation->code])
            ->assertSessionHasErrors(['code' => self::INVALID]);
    }

    #[Test]
    public function 他のジムのコードでもそのジムに紐付けられる(): void
    {
        $otherGym = Gym::factory()->create();
        $invitation = InvitationCode::factory()->create(['gym_id' => $otherGym->id]);

        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $invitation->code]);
        $this->post(route('gym-link.store'), ['consent' => '1']);

        $this->assertSame($otherGym->id, $this->client->fresh()->gym_id);
    }

    #[Test]
    public function 紐付け後は個人時代の記録がスタッフからアクセスできるようになる(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();
        $this->assertFalse($trainer->canAccessRecordsOf($this->client));

        $invitation = $this->code();
        $this->actingAs($this->client)->post(route('gym-link.confirm'), ['code' => $invitation->code]);
        $this->post(route('gym-link.store'), ['consent' => '1']);

        $this->assertTrue($trainer->canAccessRecordsOf($this->client->fresh()));
    }

    #[Test]
    public function 既にジム所属の利用者はコード入力できず解除の画面になる(): void
    {
        $member = User::factory()->client($this->gym)->create();
        $invitation = $this->code();

        $this->actingAs($member)->get(route('gym-link.show'))
            ->assertOk()
            ->assertSee('テストジム')
            ->assertSee('ジムとの紐付けを解除');
        $this->actingAs($member)->post(route('gym-link.confirm'), ['code' => $invitation->code])->assertForbidden();
        $this->actingAs($member)->post(route('gym-link.store'), ['consent' => '1'])->assertForbidden();
        $this->actingAs($member)->get(route('gym-link.confirm.show'))->assertForbidden();
    }

    #[Test]
    public function スタッフはジム連携の画面を使えない(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($trainer)->get(route('gym-link.show'))->assertForbidden();
        $this->actingAs($trainer)->post(route('gym-link.confirm'), ['code' => 'AAAAAAAA'])->assertForbidden();
    }

    #[Test]
    public function メール未認証の利用者は認証のお願い画面へ移る(): void
    {
        $unverified = User::factory()->personal()->unverified()->create();

        $this->actingAs($unverified)->get(route('gym-link.show'))->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)->post(route('gym-link.confirm'), ['code' => 'AAAAAAAA'])
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function 未ログインではログイン画面へ移る(): void
    {
        $this->get(route('gym-link.show'))->assertRedirect(route('login'));
    }

    #[Test]
    public function 入力は1分間に6回目から回数制限されメッセージが表示され操作ログに残る(): void
    {
        $this->actingAs($this->client);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('gym-link.confirm'), ['code' => 'ZZZZZZZZ'])->assertSessionHasErrors(['code' => self::INVALID]);
        }

        $this->from(route('gym-link.show'))
            ->post(route('gym-link.confirm'), ['code' => $this->code()->code])
            ->assertRedirect(route('gym-link.show'))
            ->assertSessionHas('error', '入力回数が多すぎます。しばらくしてからお試しください。');

        $this->assertNull($this->client->fresh()->gym_id);
        $this->assertSame(
            [['message' => 'invitation_code.rate_limited', 'context' => ['user_id' => $this->client->id, 'ip' => '127.0.0.1']]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function 形式が正しくない入力も回数に数えられる(): void
    {
        $this->actingAs($this->client);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('gym-link.confirm'), ['code' => 'bad']);
        }

        $this->post(route('gym-link.confirm'), ['code' => 'bad'])->assertSessionHas('error');
    }

    #[Test]
    public function 入力は1時間に21回目から回数制限される(): void
    {
        $this->actingAs($this->client);
        Carbon::setTestNow(now());

        // 1分に5回ずつ、4分間で20回（分ごとの制限は超えない）
        for ($minute = 0; $minute < 4; $minute++) {
            for ($i = 0; $i < 5; $i++) {
                $this->post(route('gym-link.confirm'), ['code' => 'ZZZZZZZZ'])->assertSessionMissing('error');
            }
            Carbon::setTestNow(now()->addMinutes(2));
        }

        $this->post(route('gym-link.confirm'), ['code' => 'ZZZZZZZZ'])
            ->assertSessionHas('error', '入力回数が多すぎます。しばらくしてからお試しください。');
    }

    #[Test]
    public function 回数制限は利用者ごとに数えられる(): void
    {
        $other = User::factory()->personal()->create();

        $this->actingAs($this->client);
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('gym-link.confirm'), ['code' => 'ZZZZZZZZ']);
        }

        $this->actingAs($other)->post(route('gym-link.confirm'), ['code' => 'ZZZZZZZZ'])->assertSessionMissing('error');
    }
}
