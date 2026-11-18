<?php

namespace Tests\Feature\Workout;

use App\Models\BodyComposition;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class SessionPageTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    private Exercise $exercise;

    private WorkoutSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->trainer = User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
        $part = BodyPart::factory()->create(['name' => '胸', 'sort_order' => 1]);
        $category = ExerciseCategory::factory()->create([
            'name' => '高重量コンパウンド', 'rep_min' => 6, 'rep_max' => 8, 'rir_min' => 1, 'rir_max' => 2, 'rest_seconds' => 180,
        ]);
        $this->exercise = Exercise::factory()->create(['name' => 'ベンチプレス', 'body_part_id' => $part->id, 'exercise_category_id' => $category->id]);
        $this->session = $this->makeSession($this->client, '2026-11-01');
    }

    private function page(array $query = []): TestResponse
    {
        return $this->get(route('records.sessions.show', [$this->client, $this->session]).($query ? '?'.http_build_query($query) : ''));
    }

    /**
     * 画面に埋め込まれた、セット入力フォーム（Alpine.js）の設定を取り出す。
     *
     * @return array<int, array<string, mixed>>
     */
    private function formConfigs(string $html): array
    {
        preg_match_all("/x-data=\"setForm\(JSON\.parse\('(.*?)'\)\)\"/s", $html, $matches);

        return array_map(function (string $raw) {
            $json = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', fn ($m) => mb_chr(hexdec($m[1])), $raw);

            return json_decode($json, true);
        }, $matches[1]);
    }

    #[Test]
    public function セット一覧に重量_レップ_RIR_推定1RM_ボリューム_総ボリュームが表示される(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2], [61.25, 6, 2, 1]]);

        $this->actingAs($this->client);
        $html = $this->page()->assertOk()->getContent();

        $this->assertStringContainsString('ベンチプレス', $html);
        $this->assertStringContainsString('高重量コンパウンド', $html);
        $this->assertStringContainsString('60.0kg', $html);
        $this->assertStringContainsString('61.25kg', $html);
        // RIR：目標と実績が同じなら1つ、違えば「目標○ / 実績○」
        $this->assertStringContainsString('目標2 / 実績1', $html);
        // 60 × (1 + 10 ÷ 30) ＝ 80.0、60 × 8 ＝ 480.0
        $this->assertStringContainsString('80.0', $html);
        $this->assertStringContainsString('480.0', $html);
        // 総ボリューム：480 + 61.25 × 6 ＝ 847.5
        $this->assertStringContainsString('総ボリューム：847.5', $html);
    }

    #[Test]
    public function セットごとに入力者と最終更新者が表示される(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $this->addSets($this->session, $this->exercise, [[62.5, 8, 2, 2]], $this->trainer);
        WorkoutSet::where('weight', 60)->sole()->forceFill(['updated_by' => $this->trainer->id])->save();

        $this->actingAs($this->client)->get(route('records.sessions.show', [$this->client, $this->session]))
            ->assertSee('入力：本人／更新：田中トレーナー')
            ->assertSee('入力：田中トレーナー／更新：田中トレーナー');
    }

    #[Test]
    public function 停止中のトレーナーの入力者には停止中と表示される(): void
    {
        $suspended = User::factory()->trainer($this->gym)->suspended()->create(['name' => '佐藤']);
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]], $suspended);

        $this->actingAs($this->client)->get(route('records.sessions.show', [$this->client, $this->session]))
            ->assertSee('入力：佐藤（停止中）／更新：佐藤（停止中）');
    }

    #[Test]
    public function スタッフが見るとき利用者本人の入力は本人でなく名前で表示される(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);

        $this->actingAs($this->trainer)->get(route('records.sessions.show', [$this->client, $this->session]))
            ->assertSee('入力：本人／更新：本人');
    }

    #[Test]
    public function 種目の選択肢は対象の利用者に見える種目だけで非表示の種目は含まれない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        Exercise::factory()->personal($this->client)->create(['name' => '自分の個人種目']);
        Exercise::factory()->personal($other)->create(['name' => '他人の個人種目']);
        Exercise::factory()->hidden()->create(['name' => '非表示の種目']);
        Exercise::factory()->gym()->create(['name' => '他ジムの種目']);
        Exercise::factory()->gym($this->gym)->create(['name' => 'ジムの種目']);

        $this->actingAs($this->client);
        $html = $this->page()->getContent();

        foreach (['ベンチプレス', '自分の個人種目', 'ジムの種目'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        foreach (['他人の個人種目', '非表示の種目', '他ジムの種目'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
    }

    #[Test]
    public function スタッフが入力する画面でも選択肢は利用者に見える種目になる(): void
    {
        Exercise::factory()->personal($this->client)->create(['name' => '利用者の個人種目']);

        $this->actingAs($this->trainer)->get(route('records.sessions.show', [$this->client, $this->session]))
            ->assertSee('利用者の個人種目');
    }

    #[Test]
    public function 種目を選ぶと分類と推奨値とYouTubeリンクと入力フォームが表示される(): void
    {
        $this->actingAs($this->client);

        $this->page(['exercise' => $this->exercise->id])->assertOk()
            ->assertSee('高重量コンパウンド／推奨 6〜8回・RIR 1〜2・休憩3分')
            ->assertSee('search_query='.urlencode('ベンチプレス やり方'), false)
            ->assertSee('name="exercise_id" value="'.$this->exercise->id.'"', false)
            ->assertSee('初回のため推奨重量はありません。')
            ->assertSee('体組成で体重を登録してください');
    }

    #[Test]
    public function 種目を選ばなければ入力フォームは表示されない(): void
    {
        $this->actingAs($this->client)->get(route('records.sessions.show', [$this->client, $this->session]))
            ->assertDontSee('name="exercise_id"', false)
            ->assertDontSee('重量の刻み');
    }

    #[Test]
    public function 見えない種目や非表示の種目を指定すると403になる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->actingAs($this->client);

        foreach ([
            Exercise::factory()->personal($other)->create()->id,
            Exercise::factory()->hidden()->create()->id,
            Exercise::factory()->gym()->create()->id,
            999999,
            'abc',
        ] as $id) {
            $this->page(['exercise' => $id])->assertForbidden();
        }
    }

    #[Test]
    public function 初期値は1セット目が目標RIRの大きい方で2セット目以降は小さい方でレップ数は最小値になる(): void
    {
        $this->actingAs($this->client);

        $first = $this->formConfigs($this->page(['exercise' => $this->exercise->id])->getContent())[0];
        $this->assertSame(2, $first['targetRir']);
        $this->assertSame(2, $first['actualRir']);
        $this->assertSame(6, $first['reps']);
        $this->assertSame(6, $first['minReps']);

        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);

        $second = $this->formConfigs($this->page(['exercise' => $this->exercise->id])->getContent())[0];
        $this->assertSame(1, $second['targetRir']);
        $this->assertSame(1, $second['actualRir']);
        $this->assertSame(6, $second['reps']);
    }

    #[Test]
    public function 初めて行う種目では推奨重量なしで重量欄は空である(): void
    {
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id]);

        $this->assertSame([], $response->viewData('candidates'));
        $config = $this->formConfigs($response->getContent())[0];
        $this->assertSame('', $config['weight']);
        $this->assertNull($config['selected']);
    }

    #[Test]
    public function 最初のセットで前回と全期間の最大が違えば2つの候補が並び選択は未定になる(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-10-01'), $this->exercise, [[80, 8, 0, 0]]);
        $this->addSets($this->makeSession($this->client, '2026-10-20'), $this->exercise, [[60, 8, 2, 2]]);
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id]);

        $this->assertSame(['last_session', 'all_time'], array_column($response->viewData('candidates'), 'key'));
        $html = $response->getContent();
        $this->assertStringContainsString('推奨重量を選んでください', $html);
        $this->assertNull($this->formConfigs($html)[0]['selected']);
        $this->assertStringNotContainsString('初回のため', $html);
    }

    #[Test]
    public function 前回と全期間の最大が同じなら1つの候補が選択済みで表示される(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-10-01'), $this->exercise, [[60, 8, 2, 2]]);
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id]);

        $this->assertSame(['history'], array_column($response->viewData('candidates'), 'key'));
        $this->assertSame('history', $this->formConfigs($response->getContent())[0]['selected']);
    }

    #[Test]
    public function 記録済みのセットがあれば直前のセットが選択済みで前回と全期間の最大が参考として並ぶ(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-10-01'), $this->exercise, [[80, 8, 0, 0]]);
        $this->addSets($this->makeSession($this->client, '2026-10-20'), $this->exercise, [[60, 8, 2, 2]]);
        $this->addSets($this->session, $this->exercise, [[62.5, 8, 2, 2]]);
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id]);

        $this->assertSame(['previous', 'last_session', 'all_time'], array_column($response->viewData('candidates'), 'key'));
        $this->assertSame([false, true, true], array_column($response->viewData('candidates'), 'reference'));
        $this->assertSame('previous', $this->formConfigs($response->getContent())[0]['selected']);
    }

    #[Test]
    public function 過去日のセッションではその日より後の記録は基準にならない(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-11-10'), $this->exercise, [[100, 8, 0, 0]]);
        $this->actingAs($this->client);

        $this->assertSame([], $this->page(['exercise' => $this->exercise->id])->viewData('candidates'));
    }

    #[Test]
    public function 推奨重量の表は全ての刻みと目標RIRの分が画面に渡される(): void
    {
        $this->addSets($this->makeSession($this->client, '2026-10-01'), $this->exercise, [[60, 8, 2, 2]]);
        $this->actingAs($this->client);

        $candidate = $this->page(['exercise' => $this->exercise->id])->viewData('candidates')[0];

        // 60 × (1 + 10 ÷ 30) ＝ 80 → 80 ÷ (1 + (6 + 目標RIR) ÷ 30)
        $this->assertSame(62.5, $candidate['table']['2.5'][2]);
        $this->assertSame(63.0, $candidate['table']['1'][2]);
        $this->assertSame(60.0, $candidate['table']['5'][2]);
        $this->assertCount(11, $candidate['table']['2.5']);
    }

    #[Test]
    public function 刻みの選択肢が表示され種目の設定ではなく画面で切り替える(): void
    {
        $this->actingAs($this->client);

        $html = $this->page(['exercise' => $this->exercise->id])->getContent();

        foreach (['0.5', '1', '1.25', '2.5', '5'] as $step) {
            $this->assertStringContainsString('<option value="'.$step.'">'.$step.'</option>', $html);
        }
    }

    #[Test]
    public function 編集の指定でセットの値が入った編集フォームが表示される(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2], [62.5, 7, 1, 1]]);
        $set = WorkoutSet::where('weight', 62.5)->sole();
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id, 'edit' => $set->id]);

        $response->assertSee(route('records.sets.update', [$this->client, $this->session, $set]), false)
            ->assertSee('更新');
        // 編集フォームがセット一覧の中に先に表示され、続いて新しいセットの入力フォームが表示される
        $edit = $this->formConfigs($response->getContent())[0];
        $this->assertSame(62.5, $edit['weight']);
        $this->assertSame(7, $edit['reps']);
        $this->assertSame(1, $edit['targetRir']);
        $this->assertSame([], $edit['candidates']);
    }

    #[Test]
    public function 他のセッションのセットを編集に指定しても編集フォームは表示されない(): void
    {
        $other = $this->makeSession($this->client, '2026-11-02');
        $this->addSets($other, $this->exercise, [[70, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $this->actingAs($this->client);

        $this->assertNull($this->page(['exercise' => $this->exercise->id, 'edit' => $set->id])->viewData('editingSet'));
    }

    #[Test]
    public function セットの削除にはモーダルの確認がある(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id, 'edit' => $set->id]);

        $response->assertSee(route('records.sets.destroy', [$this->client, $this->session, $set]), false)
            ->assertSee('種目のセットがなくなるため、この種目もセッションから消えます。')
            ->assertSee('role="dialog"', false);
    }

    #[Test]
    public function 完了メッセージが表示される(): void
    {
        $this->actingAs($this->client)->post(route('records.sets.store', [$this->client, $this->session]), [
            'exercise_id' => $this->exercise->id, 'weight' => '60', 'reps' => '8', 'target_rir' => '2', 'actual_rir' => '2',
        ]);

        $this->get(route('records.sessions.show', [$this->client, $this->session]).'?exercise='.$this->exercise->id)
            ->assertSee('セットを記録しました。');
    }

    #[Test]
    public function ナビに記録の項目が表示される(): void
    {
        $this->actingAs($this->client)->get(route('records.sessions.show', [$this->client, $this->session]))
            ->assertSee('記録')
            ->assertSee(route('records.sessions.today', $this->client), false);
    }

    #[Test]
    public function 種目名のHTMLはエスケープされて表示される(): void
    {
        $evil = Exercise::factory()->create(['name' => '<script>alert(1)</script>', 'body_part_id' => $this->exercise->body_part_id]);
        $this->addSets($this->session, $evil, [[60, 8, 2, 2]]);

        $this->actingAs($this->client)->get(route('records.sessions.show', [$this->client, $this->session]))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    #[Test]
    public function 画面にBladeの記述が文字として表示されない(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $this->actingAs($this->client);

        foreach ([[], ['exercise' => $this->exercise->id]] as $query) {
            $html = $this->page($query)->getContent();

            $this->assertStringNotContainsString('@php', $html);
            $this->assertStringNotContainsString('$errors', $html);
            $this->assertStringNotContainsString('@if', $html);
        }
    }

    #[Test]
    public function 重量の刻みの切り替えはその種目の最初のセットだけに表示される(): void
    {
        $this->actingAs($this->client);

        $this->assertStringContainsString('weight_step_select', $this->page(['exercise' => $this->exercise->id])->getContent());

        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);

        $second = $this->page(['exercise' => $this->exercise->id])->getContent();
        $this->assertStringNotContainsString('weight_step_select', $second);
        $this->assertStringNotContainsString('重量の刻み', $second);
        // 別の種目の最初のセットには表示される
        $other = Exercise::factory()->create(['body_part_id' => $this->exercise->body_part_id]);
        $this->assertStringContainsString('weight_step_select', $this->page(['exercise' => $other->id])->getContent());
    }

    #[Test]
    public function セットの編集フォームには重量の刻みの切り替えを表示しない(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $this->actingAs($this->client);

        $this->assertStringNotContainsString('weight_step_select', $this->page(['exercise' => $this->exercise->id, 'edit' => $set->id])->getContent());
    }

    #[Test]
    public function セットの編集と削除はセットと同じ行の右端に並んで表示され編集画面を開かなくても押せる(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $this->actingAs($this->client);

        $html = $this->page()->getContent();

        preg_match('/<tr id="set-'.$set->id.'".*?<\/tr>/s', $html, $row);
        $destroy = route('records.sets.destroy', [$this->client, $this->session, $set]);

        $this->assertNotEmpty($row);
        // 重量などの値と同じ行の中に、編集 → 削除の順で並ぶ
        $this->assertStringContainsString('60.0kg', $row[0]);
        $this->assertStringContainsString('justify-end', $row[0]);
        $editAt = strpos($row[0], '&edit='.$set->id);
        $this->assertNotFalse($editAt);
        $this->assertGreaterThan($editAt, strpos($row[0], $destroy));
        // 入力者の行には操作を置かない
        preg_match('/入力：本人.*?<\/tr>/s', $html, $authorRow);
        $this->assertStringNotContainsString($destroy, $authorRow[0]);
    }

    #[Test]
    public function 種目の削除は種目名と分類の横に表示される(): void
    {
        $sessionExercise = $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2], [62.5, 8, 2, 2]]);
        $this->actingAs($this->client);

        $this->page()->assertSeeInOrder([
            'ベンチプレス', '高重量コンパウンド', 'この種目を削除', '「ベンチプレス」の記録（2セット）をすべて削除します。',
            route('records.session-exercises.destroy', [$this->client, $this->session, $sessionExercise]),
        ], false);
    }

    #[Test]
    public function 種目の削除は種目名と分類を左に右端へ寄せて表示される(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $this->actingAs($this->client);

        $this->assertMatchesRegularExpression(
            '/justify-between[^>]*>\s*<div[^>]*>\s*<h2.*?ベンチプレス.*?高重量コンパウンド.*?<\/div>\s*<div x-data/s',
            $this->page()->getContent(),
        );
    }

    #[Test]
    public function この日の記録の削除は日付の横に表示される(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $this->actingAs($this->client);

        $html = $this->page()->getContent();

        $this->assertLessThan(strpos($html, '重量</th>'), strpos($html, 'この日の記録を削除'));
        $this->assertLessThan(strpos($html, 'この日の記録を削除'), strpos($html, '2026/11/01（日）</h1>'));
        // ページの下部には削除の操作を置かない
        $this->assertSame(1, substr_count($html, 'この日の記録を削除'));
    }

    private function recordBodyWeight(string $date, ?float $weight, ?User $user = null): void
    {
        $user ??= $this->client;
        $record = new BodyComposition;
        $record->fill(['measured_on' => $date, 'weight_kg' => $weight, 'waist_cm' => $weight === null ? 80 : null]);
        $record->forceFill(['user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id])->save();
    }

    #[Test]
    public function 体重の記録がなければ自重ボタンは押せず体組成での登録を案内する(): void
    {
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id]);

        $response->assertSee('体組成で体重を登録してください');
        $this->assertNull($this->formConfigs($response->getContent())[0]['bodyWeight']);
        $this->assertMatchesRegularExpression('/<button type="button" disabled title="体組成で体重を登録してください"[^>]*>自重<\/button>/', $response->getContent());
    }

    #[Test]
    public function 体重の記録があれば自重ボタンが使え直近の体重が渡される(): void
    {
        $this->recordBodyWeight('2026-10-20', 70.5);
        $this->recordBodyWeight('2026-10-25', 70.0);
        $this->actingAs($this->client);

        $response = $this->page(['exercise' => $this->exercise->id]);
        $html = $response->getContent();

        // セッション日（2026-11-01）以前で最新の体重
        $this->assertEquals(70.0, $this->formConfigs($html)[0]['bodyWeight']);
        $this->assertStringContainsString('@click="toggleBodyWeight()"', $html);
        $this->assertStringContainsString('体重（70.0kg・2026/10/25の記録）が入ります', $html);
        $this->assertDoesNotMatchRegularExpression('/disabled title="体組成で体重を登録してください"/', $html);
    }

    #[Test]
    public function セッション日より後の体重や体重のない記録は自重に使われない(): void
    {
        $this->recordBodyWeight('2026-11-05', 65.0);
        $this->recordBodyWeight('2026-10-28', null);
        $this->actingAs($this->client);

        $this->assertNull($this->formConfigs($this->page(['exercise' => $this->exercise->id])->getContent())[0]['bodyWeight']);

        $this->recordBodyWeight('2026-10-01', 71.0);

        $this->assertEquals(71.0, $this->formConfigs($this->page(['exercise' => $this->exercise->id])->getContent())[0]['bodyWeight']);
    }

    #[Test]
    public function 他の利用者の体重は自重に使われない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->recordBodyWeight('2026-10-20', 99.0, $other);
        $this->actingAs($this->client);

        $this->assertNull($this->formConfigs($this->page(['exercise' => $this->exercise->id])->getContent())[0]['bodyWeight']);
    }

    #[Test]
    public function スタッフが入力する画面でも利用者の体重が自重に使われる(): void
    {
        $this->recordBodyWeight('2026-10-20', 70.5);

        $html = $this->actingAs($this->trainer)
            ->get(route('records.sessions.show', [$this->client, $this->session]).'?exercise='.$this->exercise->id)
            ->getContent();

        $this->assertSame(70.5, $this->formConfigs($html)[0]['bodyWeight']);
    }

    #[Test]
    public function セットの編集フォームでも自重ボタンが使える(): void
    {
        $this->recordBodyWeight('2026-10-20', 70.5);
        $this->addSets($this->session, $this->exercise, [[80, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $this->actingAs($this->client);

        $configs = $this->formConfigs($this->page(['exercise' => $this->exercise->id, 'edit' => $set->id])->getContent());

        $this->assertSame(70.5, $configs[0]['bodyWeight']);
        $this->assertSame(70.5, $configs[1]['bodyWeight']);
    }

    #[Test]
    public function 自重ボタンの状態は保存されず重量の値だけが保存される(): void
    {
        $this->recordBodyWeight('2026-10-20', 70.5);

        $this->actingAs($this->client)->post(route('records.sets.store', [$this->client, $this->session]), [
            'exercise_id' => $this->exercise->id, 'weight' => '80', 'reps' => '6', 'target_rir' => '2', 'actual_rir' => '2',
            'body_weight' => '1', 'bodyWeightOn' => '1',
        ])->assertSessionHasNoErrors();

        $set = WorkoutSet::sole();
        $this->assertSame('80.00', $set->weight);
        $this->assertArrayNotHasKey('body_weight', $set->getAttributes());
    }
}
