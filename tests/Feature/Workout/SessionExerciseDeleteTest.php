<?php

namespace Tests\Feature\Workout;

use App\Models\Exercise;
use App\Models\Gym;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class SessionExerciseDeleteTest extends TestCase
{
    use BuildsWorkouts;
    use FakesAuditLog;
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    private WorkoutSession $session;

    private Exercise $bench;

    private Exercise $squat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create();
        $this->trainer = User::factory()->trainer($this->gym)->create();
        $this->session = $this->makeSession($this->client, '2026-11-01');
        $this->bench = Exercise::factory()->create(['name' => 'ベンチプレス']);
        $this->squat = Exercise::factory()->create(['name' => 'スクワット']);
        $this->fakeAuditLog();
    }

    private function url(SessionExercise|int $sessionExercise, ?WorkoutSession $session = null): string
    {
        return route('records.session-exercises.destroy', [$this->client, $session ?? $this->session, $sessionExercise]);
    }

    #[Test]
    public function 種目を削除すると記録したセットもすべて削除される(): void
    {
        $bench = $this->addSets($this->session, $this->bench, [[60, 8, 2, 2], [62.5, 8, 1, 1], [65, 6, 1, 1]]);
        $squat = $this->addSets($this->session, $this->squat, [[80, 8, 2, 2]]);

        $this->actingAs($this->client)->delete($this->url($bench))
            ->assertRedirect(route('records.sessions.show', [$this->client, $this->session]))
            ->assertSessionHas('status', '「ベンチプレス」の記録を削除しました。');

        $this->assertNull(SessionExercise::find($bench->id));
        $this->assertSame(0, WorkoutSet::where('session_exercise_id', $bench->id)->count());
        // 他の種目・セッションは残る
        $this->assertNotNull(SessionExercise::find($squat->id));
        $this->assertSame(1, WorkoutSet::count());
        $this->assertNotNull(WorkoutSession::find($this->session->id));
    }

    #[Test]
    public function 削除した種目はまた最初のセットから記録できる(): void
    {
        $bench = $this->addSets($this->session, $this->bench, [[60, 8, 2, 2]]);
        $this->actingAs($this->client)->delete($this->url($bench));

        $this->post(route('records.sets.store', [$this->client, $this->session]), [
            'exercise_id' => $this->bench->id, 'weight' => '60', 'reps' => '8', 'target_rir' => '2', 'actual_rir' => '2',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, WorkoutSet::sole()->set_number);
    }

    #[Test]
    public function スタッフも利用者の種目を削除できる(): void
    {
        $bench = $this->addSets($this->session, $this->bench, [[60, 8, 2, 2]]);

        $this->actingAs($this->trainer)->delete($this->url($bench))->assertRedirect();

        $this->assertNull(SessionExercise::find($bench->id));
    }

    #[Test]
    public function 操作ログにはセット数だけが記録される(): void
    {
        $bench = $this->addSets($this->session, $this->bench, [[60, 8, 2, 2], [62.5, 8, 1, 1]]);

        $this->actingAs($this->trainer)->delete($this->url($bench));

        $this->assertSame(
            [['message' => 'workout_exercise.deleted', 'context' => [
                'actor_id' => $this->trainer->id, 'client_id' => $this->client->id, 'session_id' => $this->session->id,
                'exercise_id' => $this->bench->id, 'sets' => 2,
            ]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function 他の利用者や他ジムのスタッフや未ログインは削除できない(): void
    {
        $bench = $this->addSets($this->session, $this->bench, [[60, 8, 2, 2]]);
        $other = User::factory()->client($this->gym)->create();
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($other)->delete($this->url($bench))->assertForbidden();
        $this->actingAs($outsider)->delete($this->url($bench))->assertForbidden();
        auth()->logout();
        $this->delete($this->url($bench))->assertRedirect(route('login'));

        $this->assertNotNull(SessionExercise::find($bench->id));
    }

    #[Test]
    public function 他のセッションや他の利用者の種目は404になる(): void
    {
        $otherSession = $this->makeSession($this->client, '2026-11-02');
        $inOther = $this->addSets($otherSession, $this->bench, [[70, 8, 2, 2]]);
        $someone = User::factory()->client($this->gym)->create();
        $others = $this->addSets($this->makeSession($someone, '2026-11-03'), $this->bench, [[80, 8, 2, 2]]);

        $this->actingAs($this->client)->delete($this->url($inOther))->assertNotFound();
        $this->actingAs($this->client)->delete($this->url($others))->assertNotFound();
        $this->actingAs($this->client)->delete($this->url(999999))->assertNotFound();

        $this->assertSame(2, WorkoutSet::count());
    }
}
