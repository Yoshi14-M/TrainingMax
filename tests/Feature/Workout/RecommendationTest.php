<?php

namespace Tests\Feature\Workout;

use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSet;
use App\Queries\RecommendationBaseQuery;
use App\Queries\RecommendationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private User $client;

    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client(Gym::factory()->create())->create();
        $category = ExerciseCategory::factory()->create(['rep_min' => 6, 'rep_max' => 8, 'rir_min' => 1, 'rir_max' => 2]);
        $this->exercise = Exercise::factory()->create(['exercise_category_id' => $category->id]);
    }

    /** 推定1RMが $estimate になるセット（レップ10・RIR0 → 重量 × (1 + 10 ÷ 30)）を1つ記録する。 */
    private function recordEstimate(string $date, float $estimate, ?User $user = null, ?Exercise $exercise = null): SessionExercise
    {
        $user ??= $this->client;
        $session = $user->workoutSessions()->where('performed_on', $date)->first() ?? $this->makeSession($user, $date);

        return $this->addSets($session, $exercise ?? $this->exercise, [[$estimate / (1 + 10 / 30), 10, 0, 0]]);
    }

    private function bases(): RecommendationBaseQuery
    {
        return app(RecommendationBaseQuery::class);
    }

    #[Test]
    public function 前回セッションの最大は直近の実施日の最大推定1RMである(): void
    {
        $this->recordEstimate('2026-10-01', 100);
        $this->recordEstimate('2026-10-20', 70);
        $this->recordEstimate('2026-10-20', 80);
        $today = $this->makeSession($this->client, '2026-11-01');

        $this->assertEqualsWithDelta(80.0, $this->bases()->lastSessionMax($this->client, $this->exercise, $today), 0.01);
    }

    #[Test]
    public function 全期間の最大はセッション日より前の最大推定1RMである(): void
    {
        $this->recordEstimate('2026-10-01', 100);
        $this->recordEstimate('2026-10-20', 80);
        $today = $this->makeSession($this->client, '2026-11-01');

        $this->assertEqualsWithDelta(100.0, $this->bases()->allTimeMax($this->client, $this->exercise, $today), 0.01);
    }

    #[Test]
    public function 同じ日や未来の記録は基準にならない(): void
    {
        $this->recordEstimate('2026-11-01', 120);
        $this->recordEstimate('2026-11-10', 130);
        $today = $this->client->workoutSessions()->where('performed_on', '2026-11-01')->sole();

        $this->assertNull($this->bases()->lastSessionMax($this->client, $this->exercise, $today));
        $this->assertNull($this->bases()->allTimeMax($this->client, $this->exercise, $today));
    }

    #[Test]
    public function 過去日のセッションではその日より前の記録だけが基準になる(): void
    {
        $this->recordEstimate('2026-09-01', 60);
        $this->recordEstimate('2026-10-01', 100);
        $past = $this->makeSession($this->client, '2026-09-15');

        $this->assertEqualsWithDelta(60.0, $this->bases()->lastSessionMax($this->client, $this->exercise, $past), 0.01);
        $this->assertEqualsWithDelta(60.0, $this->bases()->allTimeMax($this->client, $this->exercise, $past), 0.01);
    }

    #[Test]
    public function 他の種目や他の利用者の記録は基準にならない(): void
    {
        $this->recordEstimate('2026-10-01', 100, null, Exercise::factory()->create());
        $this->recordEstimate('2026-10-01', 110, User::factory()->client(Gym::factory()->create())->create());
        $today = $this->makeSession($this->client, '2026-11-01');

        $this->assertNull($this->bases()->lastSessionMax($this->client, $this->exercise, $today));
        $this->assertNull($this->bases()->allTimeMax($this->client, $this->exercise, $today));
    }

    #[Test]
    public function この種目を行っていない日は前回セッションにならない(): void
    {
        $this->recordEstimate('2026-10-01', 90);
        // 別の種目だけを行った、より新しい日
        $this->recordEstimate('2026-10-20', 50, null, Exercise::factory()->create());
        $today = $this->makeSession($this->client, '2026-11-01');

        $this->assertEqualsWithDelta(90.0, $this->bases()->lastSessionMax($this->client, $this->exercise, $today), 0.01);
    }

    #[Test]
    public function 直前のセットは最後のセットの推定1RMである(): void
    {
        $session = $this->makeSession($this->client, '2026-11-01');
        $sessionExercise = $this->addSets($session, $this->exercise, [[60, 8, 2, 2], [62.5, 8, 2, 1]]);

        $last = $sessionExercise->sets()->orderByDesc('set_number')->first();

        $this->assertSame($last->estimated_1rm, $this->bases()->previousSet($sessionExercise));
        $this->assertNull($this->bases()->previousSet(null));
    }

    #[Test]
    public function 編集で値が変わると直前のセットの基準も変わる(): void
    {
        $session = $this->makeSession($this->client, '2026-11-01');
        $sessionExercise = $this->addSets($session, $this->exercise, [[60, 8, 2, 2]]);
        $before = $this->bases()->previousSet($sessionExercise);

        $set = WorkoutSet::sole();
        $set->fill(['weight' => 70])->save();

        $this->assertGreaterThan($before, $this->bases()->previousSet($sessionExercise->fresh()));
    }

    #[Test]
    public function 記録がなければ候補はない(): void
    {
        $session = $this->makeSession($this->client, '2026-11-01');

        $this->assertSame([], app(RecommendationQuery::class)->candidates($this->client, $this->exercise->load('category'), $session, null));
    }

    #[Test]
    public function 最初のセットで前回の最大と全期間の最大が違えば2つの候補が並ぶ(): void
    {
        $this->recordEstimate('2026-10-01', 100);
        $this->recordEstimate('2026-10-20', 80);
        $session = $this->makeSession($this->client, '2026-11-01');

        $candidates = app(RecommendationQuery::class)->candidates($this->client, $this->exercise->load('category'), $session, null);

        $this->assertSame(['last_session', 'all_time'], array_column($candidates, 'key'));
        $this->assertSame(['前回の最大から', '自己ベストから'], array_column($candidates, 'label'));
        $this->assertSame([false, false], array_column($candidates, 'reference'));
        // 80 ÷ (1 + (6 + 2) ÷ 30) ＝ 63.15… → 刻み2.5kgで 62.5
        $this->assertSame(62.5, $candidates[0]['table']['2.5'][2]);
        $this->assertSame(63.0, $candidates[0]['table']['0.5'][2]);
        // 100 ÷ (1 + (6 + 2) ÷ 30) ＝ 78.9… → 77.5
        $this->assertSame(77.5, $candidates[1]['table']['2.5'][2]);
    }

    #[Test]
    public function 前回の最大と全期間の最大が同じ値なら1つにまとめる(): void
    {
        $this->recordEstimate('2026-10-01', 80);
        $this->recordEstimate('2026-10-20', 80);
        $session = $this->makeSession($this->client, '2026-11-01');

        $candidates = app(RecommendationQuery::class)->candidates($this->client, $this->exercise->load('category'), $session, null);

        $this->assertSame(['history'], array_column($candidates, 'key'));
        $this->assertSame('前回の最大・自己ベストから', $candidates[0]['label']);
    }

    #[Test]
    public function 記録済みのセットがあれば直前のセットが主の推奨で前回と全期間の最大は参考として並ぶ(): void
    {
        $this->recordEstimate('2026-10-01', 100);
        $this->recordEstimate('2026-10-20', 80);
        $session = $this->makeSession($this->client, '2026-11-01');
        $sessionExercise = $this->addSets($session, $this->exercise, [[60, 8, 2, 2]]);

        $candidates = app(RecommendationQuery::class)->candidates($this->client, $this->exercise->load('category'), $session, $sessionExercise->load('sets'));

        $this->assertSame(['previous', 'last_session', 'all_time'], array_column($candidates, 'key'));
        $this->assertSame([false, true, true], array_column($candidates, 'reference'));
        $this->assertSame('直前のセットから', $candidates[0]['label']);
        // 60 × (1 + (8 + 2) ÷ 30) ＝ 80 → 62.5
        $this->assertEqualsWithDelta(80.0, $candidates[0]['base'], 1e-9);
        $this->assertSame(62.5, $candidates[0]['table']['2.5'][2]);
    }

    #[Test]
    public function 記録済みのセットがあり過去の記録がなければ直前のセットだけが候補になる(): void
    {
        $session = $this->makeSession($this->client, '2026-11-01');
        $sessionExercise = $this->addSets($session, $this->exercise, [[60, 8, 2, 2]]);

        $candidates = app(RecommendationQuery::class)->candidates($this->client, $this->exercise->load('category'), $session, $sessionExercise->load('sets'));

        $this->assertSame(['previous'], array_column($candidates, 'key'));
        $this->assertSame([false], array_column($candidates, 'reference'));
    }

    #[Test]
    public function 推奨重量の表は種目の分類の推奨レップ数の最小値で計算される(): void
    {
        $category = ExerciseCategory::factory()->create(['rep_min' => 12, 'rep_max' => 15]);
        $light = Exercise::factory()->create(['exercise_category_id' => $category->id]);
        $this->recordEstimate('2026-10-01', 80, null, $light);
        $session = $this->makeSession($this->client, '2026-11-01');

        $candidates = app(RecommendationQuery::class)->candidates($this->client, $light->load('category'), $session, null);

        // 80 ÷ (1 + (12 + 0) ÷ 30) ＝ 57.14… → 刻み2.5kgで 55
        $this->assertSame(55.0, $candidates[0]['table']['2.5'][0]);
    }
}
