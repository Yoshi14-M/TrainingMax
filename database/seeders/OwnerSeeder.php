<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class OwnerSeeder extends Seeder
{
    /**
     * オーナー（メール認証済み）。メールアドレス・パスワードは .env（SEED_OWNER_*）から読む。
     */
    public function run(): void
    {
        $email = config('trainingmax.seed.owner_email');
        $password = config('trainingmax.seed.owner_password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('SEED_OWNER_EMAIL と SEED_OWNER_PASSWORD を .env に設定してください。');
        }

        $email = mb_strtolower($email);

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $owner = new User;
        $owner->fill([
            'name' => config('trainingmax.seed.owner_name'),
            'email' => $email,
        ]);
        $owner->forceFill([
            'role' => UserRole::Owner,
            'gym_id' => Gym::query()->firstOrFail()->id,
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();
    }
}
