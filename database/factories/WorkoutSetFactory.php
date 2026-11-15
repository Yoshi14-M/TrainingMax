<?php

namespace Database\Factories;

use App\Models\SessionExercise;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSet>
 */
class WorkoutSetFactory extends Factory
{
    /**
     * 推定1RM・セットボリュームは、保存時にモデルが計算する。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_exercise_id' => SessionExercise::factory(),
            'set_number' => 1,
            'weight' => 60,
            'reps' => 8,
            'target_rir' => 2,
            'actual_rir' => 2,
            'created_by' => fn (array $attributes) => SessionExercise::find($attributes['session_exercise_id'])->workoutSession->user_id,
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }
}
