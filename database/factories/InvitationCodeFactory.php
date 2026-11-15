<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvitationCode>
 */
class InvitationCodeFactory extends Factory
{
    /**
     * 既定は未使用で、7日後まで有効なコード。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'code' => $this->randomCode(),
            'memo' => null,
            'issued_by' => fn (array $attributes) => User::factory()->trainer(Gym::find($attributes['gym_id'])),
            'expires_at' => now()->addDays(7),
            'used_by' => null,
            'used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function used(?User $client = null): static
    {
        return $this->state(fn () => [
            'used_by' => $client?->id ?? User::factory()->personal(),
            'used_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()->subHour()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    private function randomCode(): string
    {
        $code = '';

        for ($i = 0; $i < InvitationCode::LENGTH; $i++) {
            $code .= InvitationCode::ALPHABET[random_int(0, strlen(InvitationCode::ALPHABET) - 1)];
        }

        return $code;
    }
}
