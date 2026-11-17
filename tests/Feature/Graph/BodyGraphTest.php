<?php

namespace Tests\Feature\Graph;

use App\Models\BodyComposition;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsAlpineConfig;
use Tests\TestCase;

class BodyGraphTest extends TestCase
{
    use ReadsAlpineConfig;
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->trainer = User::factory()->trainer($this->gym)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(array $query = []): string
    {
        return route('records.graphs.body', $this->client).($query ? '?'.http_build_query($query) : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function chart(array $query = [], ?User $as = null): array
    {
        return $this->alpineConfig($this->actingAs($as ?? $this->client)->get($this->url($query))->assertOk()->getContent(), 'lineChart');
    }

    private function record(string $date, array $values, ?User $user = null): void
    {
        $user ??= $this->client;
        $record = new BodyComposition;
        $record->fill(['measured_on' => $date, ...$values]);
        $record->forceFill(['user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id])->save();
    }

    #[Test]
    public function ひとつのグラフに体重から周囲径まで全ての項目が含まれる(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.2]);

        $chart = $this->chart();

        $this->assertSame(
            ['weight_kg', 'body_fat_percent', 'muscle_mass_kg', 'waist_cm', 'chest_cm', 'upper_arm_cm', 'thigh_cm', 'hip_cm', 'calf_cm', 'neck_cm'],
            array_column($chart['datasets'], 'key'),
        );
        $this->assertSame(
            ['体重', '体脂肪率', '筋肉量', 'ウエスト', '胸囲', '腕囲（上腕）', '太もも囲', 'ヒップ', 'ふくらはぎ', '首'],
            array_column($chart['datasets'], 'label'),
        );
    }

    #[Test]
    public function 測定日の昇順で項目が入力された日だけに値がありそれ以外はnullになる(): void
    {
        $this->record('2026-11-10', ['weight_kg' => 69.8, 'waist_cm' => 80.5]);
        $this->record('2026-11-01', ['weight_kg' => 70.2, 'body_fat_percent' => 18.4]);
        $this->record('2026-11-05', ['muscle_mass_kg' => 55.1]);

        $chart = $this->chart();

        $this->assertSame(['2026-11-01', '2026-11-05', '2026-11-10'], $chart['labels']);
        $this->assertSame([70.2, null, 69.8], $this->dataset($chart, 'weight_kg')['data']);
        $this->assertSame([18.4, null, null], $this->dataset($chart, 'body_fat_percent')['data']);
        $this->assertSame([null, 55.1, null], $this->dataset($chart, 'muscle_mass_kg')['data']);
        $this->assertSame([null, null, 80.5], $this->dataset($chart, 'waist_cm')['data']);
        $this->assertSame([null, null, null], $this->dataset($chart, 'neck_cm')['data']);
    }

    #[Test]
    public function 縦軸はkgが左で百分率と長さが右に分かれる(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.2]);

        $chart = $this->chart();

        $this->assertSame('left', $this->dataset($chart, 'weight_kg')['axis']);
        $this->assertSame('left', $this->dataset($chart, 'muscle_mass_kg')['axis']);
        $this->assertSame('right', $this->dataset($chart, 'body_fat_percent')['axis']);
        foreach (['waist_cm', 'chest_cm', 'upper_arm_cm', 'thigh_cm', 'hip_cm', 'calf_cm', 'neck_cm'] as $key) {
            $this->assertSame('right', $this->dataset($chart, $key)['axis']);
            $this->assertSame('cm', $this->dataset($chart, $key)['unit']);
        }
        $this->assertSame('%', $this->dataset($chart, 'body_fat_percent')['unit']);
        $this->assertSame('kg', $this->dataset($chart, 'weight_kg')['unit']);
        $this->assertSame('体重・筋肉量（kg）', $chart['axes']['left']);
        $this->assertSame('体脂肪率（%）・周囲径（cm）', $chart['axes']['right']);
    }

    #[Test]
    public function 初期表示は体重と体脂肪率と筋肉量で周囲径は凡例で表示する(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.2, 'waist_cm' => 80]);

        $chart = $this->chart();

        foreach (['weight_kg', 'body_fat_percent', 'muscle_mass_kg'] as $key) {
            $this->assertFalse($this->dataset($chart, $key)['hidden'], $key);
        }
        foreach (['waist_cm', 'chest_cm', 'upper_arm_cm', 'thigh_cm', 'hip_cm', 'calf_cm', 'neck_cm'] as $key) {
            $this->assertTrue($this->dataset($chart, $key)['hidden'], $key);
        }
        $this->assertCount(10, array_unique(array_column($chart['datasets'], 'color')));
    }

    #[Test]
    public function 期間の初期値は直近90日で絞り込める(): void
    {
        $this->record('2026-07-01', ['weight_kg' => 72]);
        $this->record('2026-09-20', ['weight_kg' => 71]);
        $this->record('2026-11-05', ['weight_kg' => 70]);

        $this->assertSame(['2026-09-20', '2026-11-05'], $this->chart()['labels']);
        $this->assertSame(['2026-11-05'], $this->chart(['period' => '30'])['labels']);
        $this->assertSame(['2026-07-01', '2026-09-20', '2026-11-05'], $this->chart(['period' => 'all'])['labels']);
        $this->assertSame(['2026-09-20'], $this->chart(['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30'])['labels']);
    }

    #[Test]
    public function 不正な期間の指定は初期値の直近90日に置き換わる(): void
    {
        $this->record('2026-07-01', ['weight_kg' => 72]);
        $this->record('2026-11-05', ['weight_kg' => 70]);

        foreach ([
            ['period' => 'abc'],
            ['period' => 'custom'],
            ['period' => 'custom', 'from' => '2026-11-10', 'to' => '2026-11-01'],
            ['period' => 'custom', 'from' => '2026-07-01', 'to' => '2026-11-14'],
            ['period' => ['a']],
        ] as $query) {
            $this->assertSame(['2026-11-05'], $this->chart($query)['labels'], json_encode($query));
        }
    }

    #[Test]
    public function 他の利用者の記録は含まれない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->record('2026-11-01', ['weight_kg' => 99.9], $other);
        $this->record('2026-11-02', ['weight_kg' => 70.0]);

        $chart = $this->chart();

        $this->assertSame(['2026-11-02'], $chart['labels']);
        $this->assertEquals([70.0], $this->dataset($chart, 'weight_kg')['data']);
    }

    #[Test]
    public function 記録がなければこの期間の記録はありませんと表示される(): void
    {
        $html = $this->actingAs($this->client)->get($this->url())->assertOk()
            ->assertSee('この期間の記録はありません。')
            ->getContent();

        $this->assertStringNotContainsString('x-ref="canvas"', $html);
    }

    #[Test]
    public function 画面にグラフの操作の説明と切り替えタブが表示され数値の一覧は並ばない(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.25, 'body_fat_percent' => 18.4]);

        $html = $this->actingAs($this->client)->get($this->url())
            ->assertSee('点にカーソルを合わせる（スマホはタップする）と、その日の各指標の値が表示されます。')
            ->assertSee('凡例を押すと、指標ごとに表示・非表示を切り替えられます。')
            ->assertSee(route('records.graphs.training', $this->client), false)
            ->assertSee('<canvas', false)
            ->getContent();

        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('18.4', strip_tags(preg_replace('/x-data="lineChart\(.*?\)"/s', '', $html)));
    }

    #[Test]
    public function 体組成のグラフには部位や種目の選択はない(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70]);

        $this->actingAs($this->client)->get($this->url(['part' => 1, 'exercise' => 1]))->assertOk()
            ->assertDontSee('name="part"', false)
            ->assertDontSee('name="exercise"', false);
    }

    #[Test]
    public function 利用者もスタッフも表示でき他の人は表示できない(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70]);
        $other = User::factory()->client($this->gym)->create();
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($this->trainer)->get($this->url())->assertOk()->assertSee('▶ 山田太郎さんの記録を表示中');
        $this->actingAs($other)->get($this->url())->assertForbidden();
        $this->actingAs($outsider)->get($this->url())->assertForbidden();
        auth()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    #[Test]
    public function 紐付け解除後はスタッフが表示できなくなる(): void
    {
        $this->client->forceFill(['gym_id' => null])->save();

        $this->actingAs($this->trainer)->get($this->url())->assertForbidden();
    }

    #[Test]
    public function メール未認証の利用者は表示できない(): void
    {
        $unverified = User::factory()->personal()->unverified()->create();

        $this->actingAs($unverified)->get(route('records.graphs.body', $unverified))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function 値は小数第1位で渡される(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.2, 'body_fat_percent' => 18.0]);

        $chart = $this->chart();

        $this->assertEquals([70.2], $this->dataset($chart, 'weight_kg')['data']);
        $this->assertEquals([18.0], $this->dataset($chart, 'body_fat_percent')['data']);
    }
}
