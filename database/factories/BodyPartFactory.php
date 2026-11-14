<?php

namespace Database\Factories;

use App\Models\BodyPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyPart>
 */
class BodyPartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('部位??????'),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
