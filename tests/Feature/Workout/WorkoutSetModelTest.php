<?php

namespace Tests\Feature\Workout;

use App\Models\Exercise;
use App\Models\Gym;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class WorkoutSetModelTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    #[Test]
    public function 保存時に推定1RMとセットボリュームが計算される(): void
    {
        $client = User::factory()->client(Gym::factory()->create())->create();
        $session = $this->makeSession($client, '2026-11-01');
        $this->addSets($session, Exercise::factory()->create(), [[62.5, 5, 2, 1]]);

        $set = WorkoutSet::sole();

        $this->assertEqualsWithDelta(62.5 * (1 + (5 + 1) / 30), $set->estimated_1rm, 1e-9);
        $this->assertSame('312.50', $set->set_volume);
    }

    #[Test]
    public function 更新時に再計算される(): void
    {
        $client = User::factory()->client(Gym::factory()->create())->create();
        $this->addSets($this->makeSession($client, '2026-11-01'), Exercise::factory()->create(), [[60, 8, 2, 2]]);
        $set = WorkoutSet::sole();

        $set->fill(['weight' => 100, 'reps' => 3, 'actual_rir' => 0])->save();

        $this->assertEqualsWithDelta(110.0, $set->fresh()->estimated_1rm, 1e-9);
        $this->assertSame('300.00', $set->fresh()->set_volume);
    }

    #[Test]
    public function ファクトリーで作ったセットも計算される(): void
    {
        $set = WorkoutSet::factory()->create(['weight' => 40, 'reps' => 10, 'actual_rir' => 0]);

        $this->assertEqualsWithDelta(40 * (1 + 10 / 30), $set->estimated_1rm, 1e-9);
        $this->assertSame('400.00', $set->set_volume);
    }

    #[Test]
    public function 一括代入できるのは重量とレップ数とRIRだけである(): void
    {
        $set = (new WorkoutSet)->fill([
            'weight' => 60, 'reps' => 8, 'target_rir' => 2, 'actual_rir' => 1,
            'estimated_1rm' => 999, 'set_volume' => 999, 'set_number' => 9, 'created_by' => 9, 'updated_by' => 9, 'session_exercise_id' => 9,
        ]);

        $this->assertEqualsCanonicalizing(['weight', 'reps', 'target_rir', 'actual_rir'], array_keys($set->getAttributes()));
    }
}
