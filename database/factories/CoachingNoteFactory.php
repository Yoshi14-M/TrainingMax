<?php

namespace Database\Factories;

use App\Enums\CoachingNoteCategory;
use App\Models\CoachingNote;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachingNote>
 */
class CoachingNoteFactory extends Factory
{
    /**
     * 既定は、ジム所属の利用者に対する、トレーナーの注意点メモ。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_user_id' => User::factory()->client(),
            'gym_id' => fn (array $attributes) => User::query()->find($attributes['client_user_id'])->gym_id,
            'category' => CoachingNoteCategory::Caution,
            'body' => fake()->realText(40),
            'created_by' => fn (array $attributes) => User::factory()->trainer(Gym::query()->find($attributes['gym_id'])),
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }
}
