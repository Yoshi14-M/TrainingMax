<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 初期データ（マスタ・デフォルト種目・ジム・オーナー）。
     */
    public function run(): void
    {
        $this->call([
            BodyPartSeeder::class,
            ExerciseCategorySeeder::class,
            DefaultExerciseSeeder::class,
            GymSeeder::class,
            OwnerSeeder::class,
        ]);
    }
}
