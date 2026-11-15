<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSession>
 */
class WorkoutSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'performed_on' => fake()->unique()->dateTimeBetween('-2 years', 'yesterday')->format('Y-m-d'),
            'created_by' => fn (array $attributes) => $attributes['user_id'],
        ];
    }
}
