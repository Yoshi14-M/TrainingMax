<?php

namespace Tests\Feature\Staff;

use App\Enums\Gender;
use App\Models\BodyComposition;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class ClientDetailTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $trainer;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->trainer = User::factory()->trainer($this->gym)->create();
        $this->client = User::factory()->client($this->gym)->create([
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(?User $client = null): string
    {
        return route('staff.clients.show', $client ?? $this->client);
    }

    private function body(string $date, array $values): void
    {
        $record = new BodyComposition;
        $record->fill(['measured_on' => $date, ...$values]);
        $record->forceFill(['user_id' => $this->client->id, 'created_by' => $this->trainer->id, 'updated_by' => $this->trainer->id])->save();
    }

    #[Test]
    public function プロフィールが表示される(): void
    {
        $this->client->forceFill([
            'height_cm' => 172.5,
            'birth_date' => '1990-05-20',
            'gender' => Gender::Male,
        ])->save();

        $html = $this->actingAs($this->trainer)->get($this->url())->assertOk()
            ->assertSee('山田太郎')
            ->assertSee('yamada@example.com')
            ->assertSee('172.5cm')
            ->assertSee('男性')
            ->getContent();

        // 生年月日と年齢（2026-11-13 時点で36歳）
        $this->assertStringContainsString('1990/05/20（36歳）', $html);
    }

    #[Test]
    public function 未登録の項目は未登録と表示される(): void
    {
        $html = $this->actingAs($this->trainer)->get($this->url())->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '未登録'));
        $this->assertStringContainsString('記録なし', $html);
    }

    #[Test]
    public function 最新の体重と体脂肪率は値が入っている最新の記録から表示される(): void
    {
        $this->body('2026-10-20', ['weight_kg' => 71.0, 'body_fat_percent' => 19.0]);
        $this->body('2026-11-05', ['weight_kg' => 70.2]);
        $this->body('2026-11-10', ['waist_cm' => 80]);

        $html = $this->actingAs($this->trainer)->get($this->url())->getContent();
        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertStringContainsString('最新の体重 70.2kg（2026/11/05）', $text);
        $this->assertStringContainsString('最新の体脂肪率 19.0%（2026/10/20）', $text);
    }

    #[Test]
    public function 最終トレーニング日が経過日数つきで表示される(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-10'), Exercise::factory()->create(), [[50, 8, 2, 2]]);
        $this->addSets($this->makeSession($this->client, '2026-10-01'), Exercise::factory()->create(), [[50, 8, 2, 2]]);

        $this->actingAs($this->trainer)->get($this->url())->assertSee('11/10（3日前）');
    }

    #[Test]
    public function この利用者の記録を開くボタンは利用者のホームへのリンクである(): void
    {
        $this->actingAs($this->trainer)->get($this->url())
            ->assertSee('この利用者の記録を開く')
            ->assertSee(route('records.home', $this->client), false);
    }

    #[Test]
    public function パスワード未設定の間だけ設定メールの再送ボタンが表示される(): void
    {
        $pending = User::factory()->client($this->gym)->passwordNotSet()->create(['name' => '未設定の人']);

        $this->actingAs($this->trainer)->get($this->url($pending))
            ->assertSee('パスワード設定メールを再送')
            ->assertSee(route('staff.clients.initial-password-mail', $pending), false)
            ->assertSee('パスワード未設定');
        $this->get($this->url())
            ->assertDontSee('パスワード設定メールを再送')
            ->assertDontSee('パスワード未設定');
    }

    #[Test]
    public function 紐付けの解除には記録の扱いを説明する確認がある(): void
    {
        $html = $this->actingAs($this->trainer)->get($this->url())->getContent();

        $this->assertStringContainsString('紐付けを解除', $html);
        $this->assertStringContainsString(route('staff.clients.membership.destroy', $this->client), $html);
        $this->assertStringContainsString('記録は利用者に残り、個人利用者として使い続けられます。', $html);
        $this->assertStringContainsString('ジムのトレーナーは記録を閲覧できなくなります。', $html);
        $this->assertStringContainsString('role="dialog"', $html);
    }

    #[Test]
    public function 解除すると利用者一覧へ戻り詳細は表示できなくなる(): void
    {
        $this->actingAs($this->trainer)->delete(route('staff.clients.membership.destroy', $this->client))
            ->assertRedirect(route('staff.clients.index'))
            ->assertSessionHas('status', '山田太郎さんのジムとの紐付けを解除しました。');

        $this->get($this->url())->assertForbidden();
    }

    #[Test]
    public function メールの再送の後は詳細に戻る(): void
    {
        $pending = User::factory()->client($this->gym)->passwordNotSet()->create();

        $this->actingAs($this->trainer)->from($this->url($pending))
            ->post(route('staff.clients.initial-password-mail', $pending))
            ->assertRedirect($this->url($pending));
    }

    #[Test]
    public function オーナーも詳細を表示できる(): void
    {
        $this->actingAs(User::factory()->owner($this->gym)->create())->get($this->url())->assertOk()->assertSee('山田太郎');
    }

    #[Test]
    public function 他ジムや個人利用者や利用者本人は詳細を表示できない(): void
    {
        $otherGym = User::factory()->client()->create();
        $personal = User::factory()->personal()->create();
        $another = User::factory()->client($this->gym)->create();

        $this->actingAs($this->trainer)->get($this->url($otherGym))->assertForbidden();
        $this->actingAs($this->trainer)->get($this->url($personal))->assertForbidden();
        $this->actingAs($this->client)->get($this->url())->assertForbidden();
        $this->actingAs($this->client)->get($this->url($another))->assertForbidden();
        $this->actingAs(User::factory()->trainer()->create())->get($this->url())->assertForbidden();
    }

    #[Test]
    public function 停止中のスタッフと未ログインは詳細を表示できない(): void
    {
        $this->actingAs(User::factory()->trainer($this->gym)->suspended()->create())->get($this->url())->assertRedirect(route('login'));
        auth()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    #[Test]
    public function スタッフのIDを指定すると404になる(): void
    {
        $this->actingAs($this->trainer)->get($this->url($this->trainer))->assertNotFound();
        $this->get(route('staff.clients.show', 999999))->assertNotFound();
    }

    #[Test]
    public function 利用者発行の画面は詳細より優先して表示される(): void
    {
        $this->actingAs($this->trainer)->get(route('staff.clients.create'))->assertOk()->assertSee('利用者アカウント発行');
    }

    #[Test]
    public function 利用者のHTMLはエスケープされて表示される(): void
    {
        $evil = User::factory()->client($this->gym)->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($this->trainer)->get($this->url($evil))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    #[Test]
    public function 利用者一覧へ戻るリンクがある(): void
    {
        $this->actingAs($this->trainer)->get($this->url())
            ->assertSee('利用者一覧へ戻る')
            ->assertSee(route('staff.clients.index'), false);
    }
}
