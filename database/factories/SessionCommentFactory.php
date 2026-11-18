<?php

namespace Database\Factories;

use App\Models\SessionComment;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionComment>
 */
class SessionCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_session_id' => WorkoutSession::factory(),
            'body' => fake()->realText(40),
            'created_by' => User::factory()->trainer(),
        ];
    }
}
