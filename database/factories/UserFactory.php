<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * 既定は個人利用者（ジム未所属）。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => null,
            'role' => UserRole::Client,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(?Gym $gym = null): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Owner,
            'gym_id' => $gym?->id ?? Gym::factory(),
        ]);
    }

    public function trainer(?Gym $gym = null): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Trainer,
            'gym_id' => $gym?->id ?? Gym::factory(),
        ]);
    }

    /**
     * ジム所属の利用者。
     */
    public function client(?Gym $gym = null): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Client,
            'gym_id' => $gym?->id ?? Gym::factory(),
        ]);
    }

    /**
     * 個人利用者（ジム未所属）。
     */
    public function personal(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Client,
            'gym_id' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'suspended_at' => now(),
        ]);
    }

    public function passwordNotSet(): static
    {
        return $this->state(fn () => [
            'password' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
