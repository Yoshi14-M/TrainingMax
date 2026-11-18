<?php

namespace Tests\Feature\Home;

use App\Models\BodyComposition;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class HomeCalendarTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    private Exercise $bench;

    private Exercise $row;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->trainer = User::factory()->trainer($this->gym)->create();
        $chest = BodyPart::factory()->create(['name' => '胸', 'sort_order' => 1]);
        $back = BodyPart::factory()->create(['name' => '背中', 'sort_order' => 2]);
        $this->bench = Exercise::factory()->create(['name' => 'ベンチプレス', 'body_part_id' => $chest->id]);
        $this->row = Exercise::factory()->create(['name' => 'ロウ', 'body_part_id' => $back->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(array $query = [], ?User $client = null): string
    {
        return route('records.home', $client ?? $this->client).($query ? '?'.http_build_query($query) : '');
    }

    private function bodyRecord(string $date, ?User $user = null): void
    {
        $user ??= $this->client;
        $record = new BodyComposition;
        $record->fill(['measured_on' => $date, 'weight_kg' => 70]);
        $record->forceFill(['user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id])->save();
    }

    #[Test]
    public function 初期表示は今月のカレンダーで曜日と今日の強調が表示される(): void
    {
        $html = $this->actingAs($this->client)->get($this->url())->assertOk()
            ->assertSee('2026年11月')
            ->assertSeeInOrder(['日', '月', '火', '水', '木', '金', '土'])
            ->getContent();

        // 今日（13日）だけが強調される
        $this->assertSame(1, substr_count($html, 'ring-2 ring-inset ring-blue-600'));
        $this->assertStringContainsString('font-semibold text-blue-700', $html);
    }

    #[Test]
    public function カレンダーは日曜日始まりで前後の月の日を含めた週ごとに並ぶ(): void
    {
        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        // 2026年11月は日曜日（1日）から始まり、30日（月曜）を含む週は12/5（土）まで
        $this->assertSame(5, substr_count($html, 'grid grid-cols-7 border-b last:border-b-0'));
        // 前後の月の日は見た目だけ（リンクにしない）
        $this->assertSame(5, substr_count($html, 'bg-gray-50 p-1 text-center text-sm text-gray-300'));
    }

    #[Test]
    public function トレーニングした日に部位名がカンマ区切りで表示されセッションへのリンクになる(): void
    {
        $session = $this->makeSession($this->client, '2026-11-05');
        $this->addSets($session, $this->bench, [[60, 8, 2, 2]]);
        $this->addSets($session, $this->row, [[50, 10, 1, 1]]);
        $this->addSets($this->makeSession($this->client, '2026-11-10'), $this->bench, [[60, 8, 2, 2]]);

        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        $this->assertStringContainsString('胸,背中', $html);
        $this->assertStringContainsString(route('records.sessions.show', [$this->client, '2026-11-05']), $html);
        $this->assertStringContainsString(route('records.sessions.show', [$this->client, '2026-11-10']), $html);
    }

    #[Test]
    public function 同じ部位の種目が複数あっても部位名は1回だけ表示される(): void
    {
        $second = Exercise::factory()->create(['name' => 'フライ', 'body_part_id' => $this->bench->body_part_id]);
        $session = $this->makeSession($this->client, '2026-11-05');
        $this->addSets($session, $this->bench, [[60, 8, 2, 2]]);
        $this->addSets($session, $second, [[20, 12, 1, 1]]);

        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        $this->assertStringContainsString('>胸</span>', $html);
        $this->assertStringNotContainsString('胸,胸', $html);
    }

    #[Test]
    public function 体組成を記録した日にマークが表示される(): void
    {
        $this->bodyRecord('2026-11-03');
        $this->bodyRecord('2026-11-04');
        $this->bodyRecord('2026-10-20');

        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        // 今月の2日分だけ（凡例の●は別）
        $this->assertSame(2, substr_count($html, 'title="体組成を記録"'));
        $this->assertStringContainsString('は体組成を記録した日、', $html);
    }

    #[Test]
    public function 記録のない今日と過去の日は作成のリンクになり未来の日はリンクにならない(): void
    {
        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        $create = route('records.sessions.create', $this->client);
        // 1日〜13日（今日まで）が作成のリンク
        $this->assertSame(13, substr_count($html, 'title="この日の記録を作成"'));
        $this->assertStringContainsString($create.'?date=2026-11-01', $html);
        $this->assertStringContainsString($create.'?date=2026-11-13', $html);
        // 未来の日（14日以降）はリンクにしない
        $this->assertStringNotContainsString($create.'?date=2026-11-14', $html);
        $this->assertStringNotContainsString($create.'?date=2026-11-30', $html);
    }

    #[Test]
    public function 記録のある日は作成のリンクにならない(): void
    {
        $this->makeSession($this->client, '2026-11-05');

        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        $this->assertStringNotContainsString(route('records.sessions.create', $this->client).'?date=2026-11-05', $html);
        $this->assertSame(12, substr_count($html, 'title="この日の記録を作成"'));
    }

    #[Test]
    public function 他の利用者の記録はカレンダーに表示されない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->addSets($this->makeSession($other, '2026-11-05'), $this->bench, [[60, 8, 2, 2]]);
        $this->bodyRecord('2026-11-06', $other);

        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        $this->assertStringNotContainsString('>胸</span>', $html);
        $this->assertStringNotContainsString('title="体組成を記録"', $html);
    }

    #[Test]
    public function 月を指定すると前月と翌月の移動ができる(): void
    {
        $html = $this->actingAs($this->client)->get($this->url(['month' => '2026-05']))->assertOk()
            ->assertSee('2026年5月')
            ->getContent();

        $this->assertStringContainsString('?month=2026-04"', $html);
        $this->assertStringContainsString('?month=2026-06"', $html);
        // 過去の月は今日の強調がない
        $this->assertStringNotContainsString('ring-2 ring-inset ring-blue-600', $html);
        // 過去の月は全日が作成のリンク（31日分）
        $this->assertSame(31, substr_count($html, 'title="この日の記録を作成"'));
    }

    #[Test]
    public function 初期表示の月は前月と翌月へのリンクを持つ(): void
    {
        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        $this->assertStringContainsString('?month=2026-10"', $html);
        $this->assertStringContainsString('?month=2026-12"', $html);
    }

    #[Test]
    public function 月の範囲は2000年1月から今月の1年後までである(): void
    {
        $this->actingAs($this->client);

        $first = $this->get($this->url(['month' => '2000-01']))->assertOk()->assertSee('2000年1月')->getContent();
        $this->assertStringNotContainsString('?month=1999-12', $first);
        $this->assertStringContainsString('?month=2000-02"', $first);

        $last = $this->get($this->url(['month' => '2027-11']))->assertOk()->assertSee('2027年11月')->getContent();
        $this->assertStringNotContainsString('?month=2027-12', $last);
        $this->assertStringContainsString('?month=2027-10"', $last);
    }

    #[Test]
    public function 不正な月や範囲外の月は今月として表示される(): void
    {
        $this->actingAs($this->client);

        foreach (['abc', '2026-13', '2026-00', '1999-12', '2027-12', '202611', '2026-1', '2026-11-01', '', ['x']] as $month) {
            $this->get($this->url(['month' => $month]))->assertOk()->assertSee('2026年11月');
        }
    }

    #[Test]
    public function 今日のセッションがなければ今日のトレーニングを始めるが表示される(): void
    {
        $this->actingAs($this->client)->get($this->url())
            ->assertSee('今日のトレーニングを始める')
            ->assertSee(route('records.sessions.today', $this->client), false)
            ->assertDontSee('今日の記録を開く');
    }

    #[Test]
    public function 今日のセッションがあれば今日の記録を開くが表示される(): void
    {
        $this->makeSession($this->client, '2026-11-13');

        $this->actingAs($this->client)->get($this->url())
            ->assertSee('今日の記録を開く')
            ->assertSee(route('records.sessions.show', [$this->client, '2026-11-13']), false)
            ->assertDontSee('今日のトレーニングを始める');
    }

    #[Test]
    public function 他の月を見ていても今日のボタンは今日のセッションを指す(): void
    {
        $this->makeSession($this->client, '2026-11-13');

        $this->actingAs($this->client)->get($this->url(['month' => '2026-05']))->assertSee('今日の記録を開く');
    }

    #[Test]
    public function スタッフも利用者のホームを表示できる(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-05'), $this->bench, [[60, 8, 2, 2]]);

        $this->actingAs($this->trainer)->get($this->url())->assertOk()
            ->assertSee('▶ 山田太郎さんの記録を表示中')
            ->assertSee('利用者一覧へ戻る')
            ->assertSee('>胸</span>', false);
    }

    #[Test]
    public function 他の利用者や他ジムのスタッフは表示できない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($other)->get($this->url())->assertForbidden();
        $this->actingAs($outsider)->get($this->url())->assertForbidden();
        auth()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    #[Test]
    public function メール未認証の利用者は表示できない(): void
    {
        $unverified = User::factory()->personal()->unverified()->create();

        $this->actingAs($unverified)->get($this->url([], $unverified))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function 紐付け解除後はスタッフがホームを表示できなくなる(): void
    {
        $this->client->forceFill(['gym_id' => null])->save();

        $this->actingAs($this->trainer)->get($this->url())->assertForbidden();
    }

    #[Test]
    public function スタッフのIDを指定すると404になる(): void
    {
        $this->actingAs($this->trainer)->get($this->url([], $this->trainer))->assertNotFound();
    }

    #[Test]
    public function ナビのホームは利用者のホームを指す(): void
    {
        $this->actingAs($this->client)->get($this->url())
            ->assertSee('ホーム')
            ->assertSee(route('records.home', $this->client), false);
    }

    #[Test]
    public function 画面にBladeの記述が文字として表示されない(): void
    {
        $this->actingAs($this->client);

        foreach ([[], ['month' => '2026-05']] as $query) {
            $html = $this->get($this->url($query))->getContent();

            $this->assertStringNotContainsString('@if', $html);
            $this->assertStringNotContainsString('@php', $html);
            $this->assertStringNotContainsString('@class', $html);
        }
    }
}
