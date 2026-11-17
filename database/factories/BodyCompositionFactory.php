<?php

namespace Database\Factories;

use App\Models\BodyComposition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyComposition>
 */
class BodyCompositionFactory extends Factory
{
    /**
     * 既定は、体重だけを記録した1件。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'measured_on' => fake()->unique()->dateTimeBetween('-2 years', 'yesterday')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(1, 50, 100),
            'created_by' => fn (array $attributes) => $attributes['user_id'],
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }
}
