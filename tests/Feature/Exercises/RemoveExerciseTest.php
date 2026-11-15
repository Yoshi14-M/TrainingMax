<?php

namespace Tests\Feature\Exercises;

use App\Actions\Exercise\RemoveExercise;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoveExerciseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 使われていない種目は削除される(): void
    {
        $exercise = Exercise::factory()->create();

        $this->assertTrue(app(RemoveExercise::class)->handle($exercise));
        $this->assertNull(Exercise::find($exercise->id));
    }

    #[Test]
    public function 記録で使われている種目は非表示になり記録は残る(): void
    {
        $exercise = Exercise::factory()->create();
        $session = WorkoutSession::factory()->create(['performed_on' => '2026-11-01']);
        $sessionExercise = SessionExercise::factory()->create(['workout_session_id' => $session->id, 'exercise_id' => $exercise->id]);

        $this->assertFalse(app(RemoveExercise::class)->handle($exercise));
        $this->assertTrue($exercise->fresh()->is_hidden);
        $this->assertNotNull(SessionExercise::find($sessionExercise->id));
    }
}
