<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 初期データ（マスタ・デフォルト種目・ジム・オーナー）。
     * local 環境では、動作確認用のテストユーザーも作成する（本番では実行しない）。
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

        if (app()->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
