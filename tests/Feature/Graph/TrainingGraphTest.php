<?php

namespace Tests\Feature\Graph;

use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\Support\ReadsAlpineConfig;
use Tests\TestCase;

class TrainingGraphTest extends TestCase
{
    use BuildsWorkouts;
    use ReadsAlpineConfig;
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    private BodyPart $chest;

    private BodyPart $back;

    private Exercise $bench;

    private Exercise $fly;

    private Exercise $row;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->trainer = User::factory()->trainer($this->gym)->create();
        $this->chest = BodyPart::factory()->create(['name' => '胸', 'sort_order' => 1]);
        $this->back = BodyPart::factory()->create(['name' => '背中', 'sort_order' => 2]);
        $category = ExerciseCategory::factory()->create();
        $this->bench = Exercise::factory()->create(['name' => 'ベンチプレス', 'body_part_id' => $this->chest->id, 'exercise_category_id' => $category->id]);
        $this->fly = Exercise::factory()->create(['name' => 'フライ', 'body_part_id' => $this->chest->id, 'exercise_category_id' => $category->id]);
        $this->row = Exercise::factory()->create(['name' => 'ロウ', 'body_part_id' => $this->back->id, 'exercise_category_id' => $category->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(array $query = []): string
    {
        return route('records.graphs.training', $this->client).($query ? '?'.http_build_query($query) : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function chart(array $query = [], ?User $as = null): array
    {
        $html = $this->actingAs($as ?? $this->client)->get($this->url($query))->assertOk()->getContent();

        return $this->alpineConfig($html, 'lineChart');
    }

    private function seedRecords(): void
    {
        $day1 = $this->makeSession($this->client, '2026-11-01');
        $this->addSets($day1, $this->bench, [[60, 8, 2, 2], [62.5, 8, 2, 1], [65, 6, 1, 0]]);
        $this->addSets($day1, $this->fly, [[20, 12, 1, 1]]);
        $this->addSets($day1, $this->row, [[50, 10, 1, 1]]);

        $day2 = $this->makeSession($this->client, '2026-11-08');
        $this->addSets($day2, $this->bench, [[65, 8, 2, 2], [65, 8, 1, 1]]);
    }

    #[Test]
    public function 部位を選ばなければ記録のある最初の部位の部位全体のグラフが表示される(): void
    {
        $this->seedRecords();

        $html = $this->actingAs($this->client)->get($this->url())->assertOk()
            ->assertSee('胸（部位全体）')
            ->getContent();
        $chart = $this->alpineConfig($html, 'lineChart');

        $this->assertSame(['sets', 'exercises', 'total_volume'], array_column($chart['datasets'], 'key'));
        $this->assertSame(['2026-11-01', '2026-11-08'], $chart['labels']);
    }

    #[Test]
    public function 部位だけを選ぶとその日のセット数と種目数と総ボリュームが表示される(): void
    {
        $this->seedRecords();

        $chart = $this->chart(['part' => $this->chest->id]);

        // 11/1：ベンチプレス3セット＋フライ1セット（背中の種目は含まない）、11/8：ベンチプレス2セット
        $this->assertSame(['2026-11-01', '2026-11-08'], $chart['labels']);
        $this->assertEquals([4, 2], $this->dataset($chart, 'sets')['data']);
        $this->assertEquals([2, 1], $this->dataset($chart, 'exercises')['data']);
        // 総ボリューム：11/1 ＝ 60×8 + 62.5×8 + 65×6 + 20×12 ＝ 480 + 500 + 390 + 240 ＝ 1610、11/8 ＝ 65×8×2 ＝ 1040
        $this->assertEquals([1610, 1040], $this->dataset($chart, 'total_volume')['data']);
    }

    #[Test]
    public function 部位のグラフは他の部位の記録を含まない(): void
    {
        $this->seedRecords();

        $chart = $this->chart(['part' => $this->back->id]);

        $this->assertSame(['2026-11-01'], $chart['labels']);
        $this->assertEquals([1], $this->dataset($chart, 'sets')['data']);
        $this->assertEquals([1], $this->dataset($chart, 'exercises')['data']);
        $this->assertEquals([500], $this->dataset($chart, 'total_volume')['data']);
    }

    #[Test]
    public function 種目を選ぶと推定1RMと最大重量とセットボリュームと総ボリュームが表示される(): void
    {
        $this->seedRecords();

        $chart = $this->chart(['part' => $this->chest->id, 'exercise' => $this->bench->id]);

        $this->assertSame(['estimated_1rm', 'max_weight', 'max_set_volume', 'total_volume'], array_column($chart['datasets'], 'key'));
        $this->assertSame(['2026-11-01', '2026-11-08'], $chart['labels']);
        // 推定1RM：その日の最大。11/1 ＝ max(60×(1+10/30)=80, 62.5×(1+9/30)=81.25, 65×(1+6/30)=78) ＝ 81.25 → 81.3、
        // 11/8 ＝ max(65×(1+10/30)=86.67, 65×(1+9/30)=84.5) ＝ 86.7
        $this->assertEquals([81.3, 86.7], $this->dataset($chart, 'estimated_1rm')['data']);
        $this->assertEquals([65, 65], $this->dataset($chart, 'max_weight')['data']);
        // セットボリュームの最大：11/1 ＝ max(480, 500, 390) ＝ 500、11/8 ＝ 520
        $this->assertEquals([500, 520], $this->dataset($chart, 'max_set_volume')['data']);
        $this->assertEquals([1370, 1040], $this->dataset($chart, 'total_volume')['data']);
    }

    #[Test]
    public function ひとつのグラフに全ての指標が含まれ縦軸は左右に分かれる(): void
    {
        $this->seedRecords();

        $exercise = $this->chart(['exercise' => $this->bench->id]);

        $this->assertSame(
            ['left', 'left', 'right', 'right'],
            array_column($exercise['datasets'], 'axis'),
        );
        $this->assertSame(['kg', 'kg', '', ''], array_column($exercise['datasets'], 'unit'));
        $this->assertSame(['推定1RM', '最大重量', 'セットボリューム（最大）', '総ボリューム'], array_column($exercise['datasets'], 'label'));
        $this->assertSame('推定1RM・最大重量（kg）', $exercise['axes']['left']);
        $this->assertSame('ボリューム', $exercise['axes']['right']);

        $part = $this->chart(['part' => $this->chest->id]);

        $this->assertSame(['left', 'left', 'right'], array_column($part['datasets'], 'axis'));
        $this->assertSame(['セット', '種目', ''], array_column($part['datasets'], 'unit'));
        $this->assertSame('セット数・種目数', $part['axes']['left']);
        $this->assertSame('総ボリューム', $part['axes']['right']);
    }

    #[Test]
    public function 全ての指標が初期状態で表示され各指標に色がある(): void
    {
        $this->seedRecords();

        $chart = $this->chart(['exercise' => $this->bench->id]);

        $this->assertSame([false, false, false, false], array_column($chart['datasets'], 'hidden'));
        $this->assertCount(4, array_unique(array_column($chart['datasets'], 'color')));
    }

    #[Test]
    public function 種目を選ぶと種目の部位が優先される(): void
    {
        $this->seedRecords();

        $html = $this->actingAs($this->client)->get($this->url(['part' => $this->back->id, 'exercise' => $this->bench->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('<option value="'.$this->chest->id.'" selected>胸</option>', $html);
        $this->assertSame('estimated_1rm', $this->alpineConfig($html, 'lineChart')['datasets'][0]['key']);
    }

    #[Test]
    public function 種目の選択肢は部位の中で記録がある種目だけである(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-01'), $this->bench, [[60, 8, 2, 2]]);
        Exercise::factory()->create(['name' => '記録のない胸の種目', 'body_part_id' => $this->chest->id]);

        $html = $this->actingAs($this->client)->get($this->url(['part' => $this->chest->id]))->assertOk()->getContent();

        $this->assertStringContainsString('部位全体（セット数・種目数・総ボリューム）', $html);
        $this->assertStringContainsString('ベンチプレス', $html);
        $this->assertStringNotContainsString('記録のない胸の種目', $html);
        $this->assertStringNotContainsString('フライ', $html);
    }

    #[Test]
    public function 部位の選択肢は記録のある部位だけである(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-01'), $this->bench, [[60, 8, 2, 2]]);
        BodyPart::factory()->create(['name' => '記録のない部位', 'sort_order' => 3]);

        $html = $this->actingAs($this->client)->get($this->url())->getContent();

        $this->assertStringContainsString('<option value="'.$this->chest->id.'" selected>胸</option>', $html);
        $this->assertStringNotContainsString('記録のない部位', $html);
        $this->assertStringNotContainsString('>背中</option>', $html);
    }

    #[Test]
    public function 他の利用者の記録はグラフに含まれない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->addSets($this->makeSession($other, '2026-11-01'), $this->bench, [[200, 8, 2, 2]]);
        $this->addSets($this->makeSession($this->client, '2026-11-02'), $this->bench, [[60, 8, 2, 2]]);

        $chart = $this->chart(['exercise' => $this->bench->id]);

        $this->assertSame(['2026-11-02'], $chart['labels']);
        $this->assertEquals([60], $this->dataset($chart, 'max_weight')['data']);
    }

    #[Test]
    public function 期間の初期値は直近90日で全期間と直近30日と期間指定で絞り込める(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-07-01'), $this->bench, [[50, 8, 2, 2]]);   // 135日前
        $this->addSets($this->makeSession($this->client, '2026-09-20'), $this->bench, [[55, 8, 2, 2]]);   // 54日前
        $this->addSets($this->makeSession($this->client, '2026-11-05'), $this->bench, [[60, 8, 2, 2]]);   // 8日前
        $query = ['exercise' => $this->bench->id];

        $this->assertSame(['2026-09-20', '2026-11-05'], $this->chart($query)['labels']);
        $this->assertSame(['2026-09-20', '2026-11-05'], $this->chart([...$query, 'period' => '90'])['labels']);
        $this->assertSame(['2026-11-05'], $this->chart([...$query, 'period' => '30'])['labels']);
        $this->assertSame(['2026-07-01', '2026-09-20', '2026-11-05'], $this->chart([...$query, 'period' => 'all'])['labels']);
        $this->assertSame(
            ['2026-09-20'],
            $this->chart([...$query, 'period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30'])['labels'],
        );
    }

    #[Test]
    public function 期間の境界の日付も含まれる(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-10-14'), $this->bench, [[50, 8, 2, 2]]);  // ちょうど30日前
        $this->addSets($this->makeSession($this->client, '2026-10-13'), $this->bench, [[55, 8, 2, 2]]);  // 31日前
        $this->addSets($this->makeSession($this->client, '2026-11-13'), $this->bench, [[60, 8, 2, 2]]);  // 今日

        $this->assertSame(
            ['2026-10-14', '2026-11-13'],
            $this->chart(['exercise' => $this->bench->id, 'period' => '30'])['labels'],
        );
        $this->assertSame(
            ['2026-10-13', '2026-10-14'],
            $this->chart(['exercise' => $this->bench->id, 'period' => 'custom', 'from' => '2026-10-13', 'to' => '2026-10-14'])['labels'],
        );
    }

    #[Test]
    public function 不正な期間の指定は初期値の直近90日に置き換わる(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-07-01'), $this->bench, [[50, 8, 2, 2]]);
        $this->addSets($this->makeSession($this->client, '2026-11-05'), $this->bench, [[60, 8, 2, 2]]);
        $expected = ['2026-11-05'];

        foreach ([
            ['period' => 'abc'],
            ['period' => 'custom'],
            ['period' => 'custom', 'from' => '2026-11-10', 'to' => '2026-11-01'],
            ['period' => 'custom', 'from' => '2026-07-01', 'to' => '2026-11-14'],
            ['period' => 'custom', 'from' => '1999-01-01', 'to' => '2026-11-01'],
            ['period' => 'custom', 'from' => 'x', 'to' => 'y'],
            ['period' => ['a']],
        ] as $query) {
            $this->assertSame($expected, $this->chart(['exercise' => $this->bench->id, ...$query])['labels'], json_encode($query));
        }
    }

    #[Test]
    public function 不正な部位や種目の指定は初期値に置き換わる(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-05'), $this->bench, [[60, 8, 2, 2]]);

        foreach ([['part' => 'abc'], ['part' => '999999'], ['exercise' => 'abc'], ['exercise' => ''], ['exercise' => '1.5'], ['part' => ['x']]] as $query) {
            $chart = $this->chart($query);

            $this->assertSame(['sets', 'exercises', 'total_volume'], array_column($chart['datasets'], 'key'), json_encode($query));
        }
    }

    #[Test]
    public function 見えない種目のIDは403になる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $othersPersonal = Exercise::factory()->personal($other)->create();
        $otherGym = Exercise::factory()->gym()->create();
        $this->actingAs($this->client);

        foreach ([$othersPersonal->id, $otherGym->id, 999999] as $id) {
            $this->get($this->url(['exercise' => $id]))->assertForbidden();
        }
    }

    #[Test]
    public function 非表示の種目も過去の記録を見るために表示できる(): void
    {
        $hidden = Exercise::factory()->hidden()->create(['name' => '非表示の種目', 'body_part_id' => $this->chest->id]);
        $this->addSets($this->makeSession($this->client, '2026-11-05'), $hidden, [[60, 8, 2, 2]]);

        $chart = $this->chart(['exercise' => $hidden->id]);

        $this->assertSame(['2026-11-05'], $chart['labels']);
    }

    #[Test]
    public function 自分の個人種目とジム共通種目のグラフを表示できる(): void
    {
        $personal = Exercise::factory()->personal($this->client)->create(['body_part_id' => $this->chest->id]);
        $gymExercise = Exercise::factory()->gym($this->gym)->create(['body_part_id' => $this->chest->id]);
        $session = $this->makeSession($this->client, '2026-11-05');
        $this->addSets($session, $personal, [[40, 10, 1, 1]]);
        $this->addSets($session, $gymExercise, [[30, 10, 1, 1]]);

        $this->assertSame(['2026-11-05'], $this->chart(['exercise' => $personal->id])['labels']);
        $this->assertSame(['2026-11-05'], $this->chart(['exercise' => $gymExercise->id])['labels']);
    }

    #[Test]
    public function 記録が全くなければ案内が表示される(): void
    {
        $this->actingAs($this->client)->get($this->url())->assertOk()
            ->assertSee('トレーニングの記録がありません。')
            ->assertDontSee('x-ref="canvas"', false);
    }

    #[Test]
    public function 期間内に記録がなければこの期間の記録はありませんと表示される(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-01-05'), $this->bench, [[60, 8, 2, 2]]);

        $html = $this->actingAs($this->client)->get($this->url(['exercise' => $this->bench->id]))->assertOk()
            ->assertSee('この期間の記録はありません。')
            ->getContent();

        $this->assertStringNotContainsString('x-ref="canvas"', $html);
        // 全期間にすると表示される
        $this->get($this->url(['exercise' => $this->bench->id, 'period' => 'all']))->assertDontSee('この期間の記録はありません。');
    }

    #[Test]
    public function 画面にグラフの操作の説明と切り替えタブが表示される(): void
    {
        $this->seedRecords();

        $this->actingAs($this->client)->get($this->url())
            ->assertSee('点にカーソルを合わせる（スマホはタップする）と、その日の各指標の値が表示されます。')
            ->assertSee('凡例を押すと、指標ごとに表示・非表示を切り替えられます。')
            ->assertSee(route('records.graphs.body', $this->client), false)
            ->assertSee('トレーニング')
            ->assertSee('<canvas', false);
    }

    #[Test]
    public function 画面に数値の一覧を並べない(): void
    {
        $this->seedRecords();

        $html = $this->actingAs($this->client)->get($this->url(['exercise' => $this->bench->id]))->getContent();

        // 数値は吹き出しで表示するため、画面に表（table）や各日の値の一覧は出さない
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('81.3', strip_tags(preg_replace('/x-data="lineChart\(.*?\)"/s', '', $html)));
    }

    #[Test]
    public function 期間指定を選ぶと開始日と終了日の入力が表示される(): void
    {
        $this->seedRecords();

        $html = $this->actingAs($this->client)
            ->get($this->url(['exercise' => $this->bench->id, 'period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-11-10']))
            ->getContent();

        $this->assertStringContainsString('name="from" type="date"', $html);
        $this->assertStringContainsString('value="2026-10-01"', $html);
        $this->assertStringContainsString('value="2026-11-10"', $html);
        $this->assertStringContainsString('max="2026-11-13"', $html);
    }

    #[Test]
    public function スタッフも利用者のグラフを表示できる(): void
    {
        $this->seedRecords();

        $html = $this->actingAs($this->trainer)->get($this->url())->assertOk()
            ->assertSee('▶ 山田太郎さんの記録を表示中')
            ->getContent();

        $this->assertSame(['2026-11-01', '2026-11-08'], $this->alpineConfig($html, 'lineChart')['labels']);
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
    public function 紐付け解除後はスタッフがグラフを表示できなくなる(): void
    {
        $this->client->forceFill(['gym_id' => null])->save();

        $this->actingAs($this->trainer)->get($this->url())->assertForbidden();
    }

    #[Test]
    public function ナビにグラフの項目が表示される(): void
    {
        $this->actingAs($this->client)->get($this->url())
            ->assertSee('グラフ')
            ->assertSee(route('records.graphs.training', $this->client), false);
    }

    #[Test]
    public function 値は小数第1位に丸めて渡される(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-05'), $this->bench, [[61.25, 7, 2, 1]]);

        $chart = $this->chart(['exercise' => $this->bench->id]);

        // 61.25 × (1 + 8 ÷ 30) ＝ 77.5833… → 77.6、セットボリューム 61.25 × 7 ＝ 428.75 → 428.8
        $this->assertEquals([77.6], $this->dataset($chart, 'estimated_1rm')['data']);
        $this->assertEquals([61.3], $this->dataset($chart, 'max_weight')['data']);
        $this->assertEquals([428.8], $this->dataset($chart, 'max_set_volume')['data']);
    }

    #[Test]
    public function 日付は昇順で並び記録のない日は含まれない(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-10'), $this->bench, [[60, 8, 2, 2]]);
        $this->addSets($this->makeSession($this->client, '2026-10-20'), $this->bench, [[55, 8, 2, 2]]);
        $this->addSets($this->makeSession($this->client, '2026-11-01'), $this->row, [[50, 8, 2, 2]]);

        $this->assertSame(['2026-10-20', '2026-11-10'], $this->chart(['exercise' => $this->bench->id])['labels']);
    }

    #[Test]
    public function 画面にBladeの記述が文字として表示されない(): void
    {
        $this->seedRecords();
        $this->actingAs($this->client);

        foreach ([[], ['exercise' => $this->bench->id], ['period' => 'custom']] as $query) {
            $html = $this->get($this->url($query))->getContent();

            $this->assertStringNotContainsString('@if', $html);
            $this->assertStringNotContainsString('@include', $html);
            $this->assertStringNotContainsString('$errors', $html);
        }
    }
}
