<?php

namespace Tests\Feature\Gym;

use App\Models\Gym;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class StaffInvitationCodeTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    private Gym $gym;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->trainer = User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
        $this->fakeAuditLog();
    }

    #[Test]
    public function スタッフが招待コードを発行できる(): void
    {
        $this->actingAs($this->trainer)
            ->post(route('staff.invitation-codes.store'), ['memo' => '山田さん用'])
            ->assertRedirect(route('staff.invitation-codes.index'))
            ->assertSessionHas('status', '招待コードを発行しました。');

        $invitation = InvitationCode::sole();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $invitation->code);
        $this->assertSame($this->gym->id, $invitation->gym_id);
        $this->assertSame($this->trainer->id, $invitation->issued_by);
        $this->assertSame('山田さん用', $invitation->memo);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $invitation->expires_at->timestamp, 5);
        $this->assertNull($invitation->used_at);
        $this->assertNull($invitation->revoked_at);
    }

    #[Test]
    public function オーナーも発行できる(): void
    {
        $this->actingAs(User::factory()->owner($this->gym)->create())
            ->post(route('staff.invitation-codes.store'))
            ->assertRedirect();

        $this->assertSame(1, InvitationCode::count());
    }

    #[Test]
    public function メモは任意で100文字まで入力できる(): void
    {
        $this->actingAs($this->trainer)->post(route('staff.invitation-codes.store'), ['memo' => str_repeat('あ', 100)])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->trainer)->post(route('staff.invitation-codes.store'), ['memo' => str_repeat('あ', 101)])
            ->assertSessionHasErrors(['memo' => 'メモは100文字以内で入力してください。']);
        $this->actingAs($this->trainer)->post(route('staff.invitation-codes.store'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, InvitationCode::count());
    }

    #[Test]
    public function リクエストにgym_idやcodeやissued_byを含めても無視される(): void
    {
        $otherGym = Gym::factory()->create();

        $this->actingAs($this->trainer)->post(route('staff.invitation-codes.store'), [
            'gym_id' => $otherGym->id,
            'code' => 'AAAAAAAA',
            'issued_by' => 999,
            'expires_at' => now()->addYear()->toDateTimeString(),
        ])->assertRedirect();

        $invitation = InvitationCode::sole();
        $this->assertSame($this->gym->id, $invitation->gym_id);
        $this->assertNotSame('AAAAAAAA', $invitation->code);
        $this->assertSame($this->trainer->id, $invitation->issued_by);
        $this->assertLessThan(now()->addDays(8)->timestamp, $invitation->expires_at->timestamp);
    }

    #[Test]
    public function 発行するコードは使用文字だけで重複しない(): void
    {
        $this->actingAs($this->trainer);

        for ($i = 0; $i < 50; $i++) {
            $this->post(route('staff.invitation-codes.store'));
        }

        $codes = InvitationCode::pluck('code');
        $this->assertCount(50, $codes);
        $this->assertCount(50, $codes->unique());
        $codes->each(fn ($code) => $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $code));
    }

    #[Test]
    public function 一覧には自分のジムのコードだけが状態つきで表示される(): void
    {
        $client = User::factory()->personal()->create(['name' => '使用者さん']);
        $unused = InvitationCode::factory()->create(['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id, 'memo' => '未使用メモ']);
        InvitationCode::factory()->used($client)->create(['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id]);
        InvitationCode::factory()->revoked()->create(['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id]);
        InvitationCode::factory()->expired()->create(['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id]);
        $other = InvitationCode::factory()->create();

        $this->actingAs($this->trainer)->get(route('staff.invitation-codes.index'))
            ->assertOk()
            ->assertSee($unused->code)
            ->assertSee('未使用メモ')
            ->assertSee('田中トレーナー')
            ->assertSee('使用済み')
            ->assertSee('使用者さん')
            ->assertSee('取消')
            ->assertSee('期限切れ')
            ->assertDontSee($other->code);
    }

    #[Test]
    public function 未使用のコードは発行者以外のスタッフでも取り消せる(): void
    {
        $invitation = InvitationCode::factory()->create(['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id]);
        $owner = User::factory()->owner($this->gym)->create();

        $this->actingAs($owner)->delete(route('staff.invitation-codes.destroy', $invitation))
            ->assertRedirect(route('staff.invitation-codes.index'))
            ->assertSessionHas('status', '招待コードを取り消しました。');

        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    #[Test]
    public function 使用済み_取消済み_期限切れのコードは取り消せない(): void
    {
        $attributes = ['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id];

        foreach ([
            InvitationCode::factory()->used()->create($attributes),
            InvitationCode::factory()->revoked()->create($attributes),
            InvitationCode::factory()->expired()->create($attributes),
        ] as $invitation) {
            $this->actingAs($this->trainer)->delete(route('staff.invitation-codes.destroy', $invitation))->assertForbidden();
        }
    }

    #[Test]
    public function 他のジムのコードは取り消せない(): void
    {
        $invitation = InvitationCode::factory()->create();

        $this->actingAs($this->trainer)->delete(route('staff.invitation-codes.destroy', $invitation))->assertForbidden();

        $this->assertNull($invitation->fresh()->revoked_at);
    }

    #[Test]
    public function 利用者は一覧も発行も取消もできない(): void
    {
        $client = User::factory()->client($this->gym)->create();
        $invitation = InvitationCode::factory()->create(['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id]);

        $this->actingAs($client)->get(route('staff.invitation-codes.index'))->assertForbidden();
        $this->actingAs($client)->post(route('staff.invitation-codes.store'))->assertForbidden();
        $this->actingAs($client)->delete(route('staff.invitation-codes.destroy', $invitation))->assertForbidden();

        $this->assertSame(1, InvitationCode::count());
    }

    #[Test]
    public function 停止中のスタッフは発行できない(): void
    {
        $suspended = User::factory()->trainer($this->gym)->suspended()->create();

        $this->actingAs($suspended)->post(route('staff.invitation-codes.store'))->assertRedirect(route('login'));

        $this->assertSame(0, InvitationCode::count());
    }

    #[Test]
    public function 操作ログにはコードの文字列を出さない(): void
    {
        $this->actingAs($this->trainer)->post(route('staff.invitation-codes.store'));
        $invitation = InvitationCode::sole();
        $this->actingAs($this->trainer)->delete(route('staff.invitation-codes.destroy', $invitation));

        $records = $this->auditRecords();

        $this->assertSame(
            [
                ['message' => 'invitation_code.issued', 'context' => ['actor_id' => $this->trainer->id, 'invitation_code_id' => $invitation->id]],
                ['message' => 'invitation_code.revoked', 'context' => ['actor_id' => $this->trainer->id, 'invitation_code_id' => $invitation->id]],
            ],
            $records,
        );
        $this->assertStringNotContainsString($invitation->code, json_encode($records));
    }
}
