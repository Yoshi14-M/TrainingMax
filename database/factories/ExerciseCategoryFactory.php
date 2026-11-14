<?php

namespace Database\Factories;

use App\Models\ExerciseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseCategory>
 */
class ExerciseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('分類??????'),
            'rep_min' => 8,
            'rep_max' => 10,
            'rir_min' => 1,
            'rir_max' => 2,
            'rest_seconds' => 120,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
