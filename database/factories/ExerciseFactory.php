<?php

namespace Database\Factories;

use App\Enums\ExerciseScope;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    /**
     * 既定はデフォルト種目。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => ExerciseScope::Default,
            'gym_id' => null,
            'owner_user_id' => null,
            'body_part_id' => BodyPart::factory(),
            'exercise_category_id' => ExerciseCategory::factory(),
            'name' => fake()->unique()->lexify('種目??????'),
            'is_hidden' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'scope' => ExerciseScope::Default,
            'gym_id' => null,
            'owner_user_id' => null,
        ]);
    }

    public function gym(?Gym $gym = null): static
    {
        return $this->state(fn () => [
            'scope' => ExerciseScope::Gym,
            'gym_id' => $gym?->id ?? Gym::factory(),
            'owner_user_id' => null,
        ]);
    }

    public function personal(?User $owner = null): static
    {
        return $this->state(fn () => [
            'scope' => ExerciseScope::Personal,
            'gym_id' => null,
            'owner_user_id' => $owner?->id ?? User::factory(),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true]);
    }
}
