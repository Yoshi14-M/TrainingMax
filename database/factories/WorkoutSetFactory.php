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
     * 推定1RM・ボリュームの計算は #7 で実装するまでの間、Factory で値を入れる。
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
            'estimated_1rm' => 60 * (1 + (8 + 2) / 30),
            'set_volume' => 60 * 8,
            'created_by' => fn (array $attributes) => SessionExercise::find($attributes['session_exercise_id'])->workoutSession->user_id,
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }
}
