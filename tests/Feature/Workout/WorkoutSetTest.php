<?php

namespace Tests\Feature\Workout;

use App\Actions\Workout\RecordWorkoutSet;
use App\Enums\ExerciseScope;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class WorkoutSetTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    private WorkoutSession $session;

    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create();
        $this->trainer = User::factory()->trainer($this->gym)->create();
        $this->session = $this->makeSession($this->client, '2026-11-01');
        $this->exercise = Exercise::factory()->create(['name' => 'ベンチプレス']);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function input(array $override = []): array
    {
        return array_merge([
            'exercise_id' => $this->exercise->id,
            'weight' => '60',
            'reps' => '8',
            'target_rir' => '2',
            'actual_rir' => '1',
        ], $override);
    }

    private function storeUrl(?WorkoutSession $session = null): string
    {
        return route('records.sets.store', [$this->client, $session ?? $this->session]);
    }

    #[Test]
    public function 種目の1セット目を記録すると種目とセットが作られ推定1RMとボリュームが保存される(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), $this->input())
            ->assertRedirectContains('?exercise='.$this->exercise->id)
            ->assertSessionHas('status', 'セットを記録しました。');

        $sessionExercise = SessionExercise::sole();
        $this->assertSame($this->session->id, $sessionExercise->workout_session_id);
        $this->assertSame($this->exercise->id, $sessionExercise->exercise_id);
        $this->assertSame(1, $sessionExercise->sort_order);

        $set = WorkoutSet::sole();
        $this->assertSame($sessionExercise->id, $set->session_exercise_id);
        $this->assertSame(1, $set->set_number);
        $this->assertSame('60.00', $set->weight);
        $this->assertSame(8, $set->reps);
        $this->assertSame(2, $set->target_rir);
        $this->assertSame(1, $set->actual_rir);
        // 60 × (1 + (8 + 1) ÷ 30)
        $this->assertEqualsWithDelta(78.0, $set->estimated_1rm, 1e-9);
        $this->assertSame('480.00', $set->set_volume);
        $this->assertSame($this->client->id, $set->created_by);
        $this->assertSame($this->client->id, $set->updated_by);
    }

    #[Test]
    public function セット番号は順に採番され2つ目の種目は表示順が続く(): void
    {
        $other = Exercise::factory()->create();
        $this->actingAs($this->client);

        $this->post($this->storeUrl(), $this->input());
        $this->post($this->storeUrl(), $this->input(['weight' => '62.5']));
        $this->post($this->storeUrl(), $this->input(['exercise_id' => $other->id]));
        $this->post($this->storeUrl(), $this->input(['weight' => '65']));

        $this->assertSame([1, 2, 3], WorkoutSet::query()
            ->whereHas('sessionExercise', fn ($q) => $q->where('exercise_id', $this->exercise->id))
            ->orderBy('set_number')->pluck('set_number')->all());
        $this->assertSame(1, SessionExercise::where('exercise_id', $this->exercise->id)->value('sort_order'));
        $this->assertSame(2, SessionExercise::where('exercise_id', $other->id)->value('sort_order'));
        $this->assertSame(2, SessionExercise::count());
    }

    #[Test]
    public function スタッフが入力すると入力者はスタッフで記録の持ち主は利用者のままになる(): void
    {
        $this->actingAs($this->trainer)->post($this->storeUrl(), $this->input())->assertRedirect();

        $set = WorkoutSet::sole();
        $this->assertSame($this->trainer->id, $set->created_by);
        $this->assertSame($this->trainer->id, $set->updated_by);
        $this->assertSame($this->client->id, $set->sessionExercise->workoutSession->user_id);
    }

    #[Test]
    public function リクエストの推定1RMやセット番号や入力者は使われない(): void
    {
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($this->client)->post($this->storeUrl(), $this->input([
            'estimated_1rm' => 999, 'set_volume' => 999, 'set_number' => 9, 'created_by' => $other->id,
            'updated_by' => $other->id, 'session_exercise_id' => 999, 'user_id' => $other->id,
        ]));

        $set = WorkoutSet::sole();
        $this->assertSame(1, $set->set_number);
        $this->assertEqualsWithDelta(78.0, $set->estimated_1rm, 1e-9);
        $this->assertSame('480.00', $set->set_volume);
        $this->assertSame($this->client->id, $set->created_by);
        $this->assertSame($this->client->id, $set->updated_by);
    }

    #[Test]
    public function 必須項目が空だと日本語のエラーになる(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), [])
            ->assertSessionHasErrors([
                'exercise_id' => '種目を選択してください。',
                'weight' => '重量を入力してください。',
                'reps' => 'レップ数を入力してください。',
                'target_rir' => '目標RIRを入力してください。',
                'actual_rir' => '実績RIRを入力してください。',
            ]);

        $this->assertSame(0, WorkoutSet::count());
    }

    #[Test]
    public function 重量は0_5から500までで小数第2位まで許可される(): void
    {
        $this->actingAs($this->client);

        foreach (['0.5', '500', '60.25', '60', '62.5'] as $weight) {
            $this->post($this->storeUrl(), $this->input(['weight' => $weight]))->assertSessionHasNoErrors();
        }
        foreach (['0.49', '0', '-5', '500.01', '501', 'abc', '60.123', '60.001'] as $weight) {
            $this->post($this->storeUrl(), $this->input(['weight' => $weight]))->assertSessionHasErrors('weight');
        }
    }

    #[Test]
    public function レップ数は1から100までの整数である(): void
    {
        $this->actingAs($this->client);

        foreach (['1', '100'] as $reps) {
            $this->post($this->storeUrl(), $this->input(['reps' => $reps]))->assertSessionHasNoErrors();
        }
        foreach (['0', '101', '-1', '8.5', 'abc'] as $reps) {
            $this->post($this->storeUrl(), $this->input(['reps' => $reps]))->assertSessionHasErrors('reps');
        }
    }

    #[Test]
    public function RIRは0から10までの整数である(): void
    {
        $this->actingAs($this->client);

        foreach (['0', '10'] as $rir) {
            $this->post($this->storeUrl(), $this->input(['target_rir' => $rir, 'actual_rir' => $rir]))->assertSessionHasNoErrors();
        }
        foreach (['-1', '11', '1.5', 'abc'] as $rir) {
            $this->post($this->storeUrl(), $this->input(['target_rir' => $rir]))->assertSessionHasErrors('target_rir');
            $this->post($this->storeUrl(), $this->input(['actual_rir' => $rir]))->assertSessionHasErrors('actual_rir');
        }
    }

    #[Test]
    public function 対象の利用者に見えない種目ではセットを登録できない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $othersPersonal = Exercise::factory()->personal($other)->create();
        $otherGymExercise = Exercise::factory()->gym()->create();
        $hidden = Exercise::factory()->hidden()->create();
        $this->actingAs($this->client);

        foreach ([$othersPersonal->id, $otherGymExercise->id, $hidden->id, 999999, 'abc'] as $id) {
            $this->post($this->storeUrl(), $this->input(['exercise_id' => $id]))
                ->assertSessionHasErrors(['exercise_id']);
        }

        $this->assertSame(0, WorkoutSet::count());
        $this->assertSame(0, SessionExercise::count());
    }

    #[Test]
    public function 自分の個人種目とジム共通種目は使える(): void
    {
        $personal = Exercise::factory()->personal($this->client)->create();
        $gymExercise = Exercise::factory()->gym($this->gym)->create();
        $this->actingAs($this->client);

        $this->post($this->storeUrl(), $this->input(['exercise_id' => $personal->id]))->assertSessionHasNoErrors();
        $this->post($this->storeUrl(), $this->input(['exercise_id' => $gymExercise->id]))->assertSessionHasNoErrors();

        $this->assertSame(2, SessionExercise::count());
    }

    #[Test]
    public function スタッフが入力する場合も対象の利用者に見える種目で判定される(): void
    {
        $clientsPersonal = Exercise::factory()->personal($this->client)->create();
        $other = User::factory()->client($this->gym)->create();
        $othersPersonal = Exercise::factory()->personal($other)->create();
        $this->actingAs($this->trainer);

        // スタッフ自身には見えない「利用者の個人種目」でも、利用者に見えるので登録できる
        $this->post($this->storeUrl(), $this->input(['exercise_id' => $clientsPersonal->id]))->assertSessionHasNoErrors();
        // 別の利用者の個人種目は、利用者にも見えないので登録できない
        $this->post($this->storeUrl(), $this->input(['exercise_id' => $othersPersonal->id]))->assertSessionHasErrors('exercise_id');
    }

    #[Test]
    public function セッションに追加できる種目は30種目までである(): void
    {
        $exercises = Exercise::factory()->count(30)->create();
        foreach ($exercises as $exercise) {
            $this->addSets($this->session, $exercise, [[50, 8, 2, 2]]);
        }
        $thirtyFirst = Exercise::factory()->create();
        $this->actingAs($this->client);

        $this->post($this->storeUrl(), $this->input(['exercise_id' => $thirtyFirst->id]))
            ->assertSessionHasErrors(['exercise_id' => '1セッションに追加できる種目は30種目までです。']);
        // 既にある種目へのセットは追加できる
        $this->post($this->storeUrl(), $this->input(['exercise_id' => $exercises->first()->id]))->assertSessionHasNoErrors();

        $this->assertSame(30, SessionExercise::count());
    }

    #[Test]
    public function 種目に記録できるセットは30セットまでである(): void
    {
        $this->addSets($this->session, $this->exercise, array_fill(0, 30, [50, 8, 2, 2]));
        $this->actingAs($this->client);

        $this->post($this->storeUrl(), $this->input())
            ->assertSessionHasErrors(['exercise_id' => '1種目に記録できるセットは30セットまでです。']);

        $this->assertSame(30, WorkoutSet::count());
    }

    #[Test]
    public function 同時操作で上限を超える場合も記録の処理が拒否する(): void
    {
        $this->addSets($this->session, $this->exercise, array_fill(0, 30, [50, 8, 2, 2]));

        $this->expectException(ValidationException::class);

        app(RecordWorkoutSet::class)->handle($this->client, $this->session, [
            'exercise_id' => $this->exercise->id, 'weight' => 50, 'reps' => 8, 'target_rir' => 2, 'actual_rir' => 2,
        ]);
    }

    #[Test]
    public function 他の利用者のセッションにはセットを登録できない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $othersSession = $this->makeSession($other, '2026-11-05');

        // URLの利用者と、セッションの持ち主が違う（親子が違う）：404
        $this->actingAs($this->client)->post(route('records.sets.store', [$this->client, '2026-11-05']), $this->input())->assertNotFound();
        // 他の利用者の記録への操作：403
        $this->actingAs($this->client)->post(route('records.sets.store', [$other, $othersSession]), $this->input())->assertForbidden();

        $this->assertSame(0, WorkoutSet::count());
    }

    #[Test]
    public function 他ジムのスタッフや未ログインはセットを登録できない(): void
    {
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($outsider)->post($this->storeUrl(), $this->input())->assertForbidden();
        auth()->logout();
        $this->post($this->storeUrl(), $this->input())->assertRedirect(route('login'));

        $this->assertSame(0, WorkoutSet::count());
    }

    #[Test]
    public function 認可は入力の検証より先に行われる(): void
    {
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($other)->post($this->storeUrl(), [])->assertForbidden();
    }

    #[Test]
    public function セットを編集すると推定1RMとボリュームが再計算され更新者が記録される(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $url = route('records.sets.update', [$this->client, $this->session, $set]);

        $this->actingAs($this->trainer)
            ->put($url, ['weight' => '65', 'reps' => '6', 'target_rir' => '1', 'actual_rir' => '0'])
            ->assertRedirectContains('#set-'.$set->id)
            ->assertSessionHas('status', 'セットを更新しました。');

        $set->refresh();
        $this->assertSame('65.00', $set->weight);
        $this->assertSame(6, $set->reps);
        $this->assertEqualsWithDelta(65 * (1 + 6 / 30), $set->estimated_1rm, 1e-9);
        $this->assertSame('390.00', $set->set_volume);
        $this->assertSame($this->client->id, $set->created_by);
        $this->assertSame($this->trainer->id, $set->updated_by);
        $this->assertSame(1, $set->set_number);
    }

    #[Test]
    public function 更新では種目やセット番号を変更できない(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $other = Exercise::factory()->create();

        $this->actingAs($this->client)->put(route('records.sets.update', [$this->client, $this->session, $set]), [
            'weight' => '65', 'reps' => '6', 'target_rir' => '1', 'actual_rir' => '0',
            'exercise_id' => $other->id, 'set_number' => 5, 'session_exercise_id' => 999,
        ]);

        $set->refresh();
        $this->assertSame(1, $set->set_number);
        $this->assertSame($this->exercise->id, $set->sessionExercise->exercise_id);
    }

    #[Test]
    public function 更新の入力エラーでは編集中のセットの位置に戻る(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $edit = route('records.sessions.show', [$this->client, $this->session]).'?exercise='.$this->exercise->id.'&edit='.$set->id;

        $this->actingAs($this->client)->from($edit)
            ->put(route('records.sets.update', [$this->client, $this->session, $set]), ['weight' => '', 'reps' => '8', 'target_rir' => '2', 'actual_rir' => '2'])
            ->assertRedirect($edit.'#set-'.$set->id)
            ->assertSessionHasErrors('weight');
    }

    #[Test]
    public function 他のセッションや他の利用者のセットは編集も削除もできず404になる(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $mine = WorkoutSet::sole();
        $otherSession = $this->makeSession($this->client, '2026-11-02');
        $this->addSets($otherSession, $this->exercise, [[70, 8, 2, 2]]);
        $inOther = WorkoutSet::where('weight', 70)->sole();
        $other = User::factory()->client($this->gym)->create();
        $this->addSets($this->makeSession($other, '2026-11-03'), $this->exercise, [[80, 8, 2, 2]]);
        $others = WorkoutSet::where('weight', 80)->sole();
        $data = ['weight' => '99', 'reps' => '8', 'target_rir' => '2', 'actual_rir' => '2'];

        foreach ([$inOther, $others] as $set) {
            $this->actingAs($this->client)->put(route('records.sets.update', [$this->client, $this->session, $set->id]), $data)->assertNotFound();
            $this->actingAs($this->client)->delete(route('records.sets.destroy', [$this->client, $this->session, $set->id]))->assertNotFound();
        }

        $this->assertSame('60.00', $mine->fresh()->weight);
        $this->assertSame(3, WorkoutSet::count());
    }

    #[Test]
    public function 他の利用者はセットを編集も削除もできない(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($other)->put(route('records.sets.update', [$this->client, $this->session, $set]), ['weight' => '99', 'reps' => '8', 'target_rir' => '2', 'actual_rir' => '2'])->assertForbidden();
        $this->actingAs($other)->delete(route('records.sets.destroy', [$this->client, $this->session, $set]))->assertForbidden();

        $this->assertSame('60.00', $set->fresh()->weight);
    }

    #[Test]
    public function セットを削除すると後ろのセット番号が繰り上がる(): void
    {
        $sessionExercise = $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2], [62.5, 8, 2, 2], [65, 6, 1, 1], [67.5, 5, 1, 1]]);
        $second = $sessionExercise->sets()->where('set_number', 2)->sole();

        $this->actingAs($this->client)->delete(route('records.sets.destroy', [$this->client, $this->session, $second]))
            ->assertRedirectContains('?exercise='.$this->exercise->id)
            ->assertSessionHas('status', 'セットを削除しました。');

        $this->assertSame(
            [1 => '60.00', 2 => '65.00', 3 => '67.50'],
            $sessionExercise->sets()->orderBy('set_number')->pluck('weight', 'set_number')->all(),
        );
    }

    #[Test]
    public function 最後のセットを削除すると種目もセッションから消える(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $other = $this->addSets($this->session, Exercise::factory()->create(), [[40, 10, 1, 1]]);
        $set = WorkoutSet::where('weight', 60)->sole();

        $this->actingAs($this->trainer)->delete(route('records.sets.destroy', [$this->client, $this->session, $set]));

        $this->assertSame(0, SessionExercise::where('exercise_id', $this->exercise->id)->count());
        $this->assertNotNull(SessionExercise::find($other->id));
        $this->assertSame(1, WorkoutSet::count());
        // セッション自体は残る
        $this->assertNotNull(WorkoutSession::find($this->session->id));
    }

    #[Test]
    public function 削除した種目はまた最初のセットから記録できる(): void
    {
        $this->addSets($this->session, $this->exercise, [[60, 8, 2, 2]]);
        $this->actingAs($this->client)->delete(route('records.sets.destroy', [$this->client, $this->session, WorkoutSet::sole()]));

        $this->post($this->storeUrl(), $this->input());

        $this->assertSame(1, WorkoutSet::sole()->set_number);
    }

    #[Test]
    public function 個人種目をscope以外から設定する経路はない(): void
    {
        // 個人種目（scope = personal）の持ち主が利用者本人のセットの記録に使える
        $personal = Exercise::factory()->personal($this->client)->create();
        $this->assertSame(ExerciseScope::Personal, $personal->scope);

        $this->actingAs($this->client)->post($this->storeUrl(), $this->input(['exercise_id' => $personal->id]))->assertSessionHasNoErrors();
    }

    #[Test]
    public function 推奨レップ数の異なる分類の種目でも記録できる(): void
    {
        $category = ExerciseCategory::factory()->create(['rep_min' => 12, 'rep_max' => 15]);
        $exercise = Exercise::factory()->create(['exercise_category_id' => $category->id]);

        $this->actingAs($this->client)->post($this->storeUrl(), $this->input(['exercise_id' => $exercise->id, 'reps' => '15']))
            ->assertSessionHasNoErrors();

        $this->assertSame(15, WorkoutSet::sole()->reps);
    }
}
