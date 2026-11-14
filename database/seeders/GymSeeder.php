<?php

namespace Database\Seeders;

use App\Models\Gym;
use Illuminate\Database\Seeder;

class GymSeeder extends Seeder
{
    /**
     * ジム（1ジム運用のため1件のみ。既にあれば作らない）。
     */
    public function run(): void
    {
        if (Gym::query()->exists()) {
            return;
        }

        Gym::create(['name' => config('trainingmax.seed.gym_name')]);
    }
}
