<?php

namespace Database\Seeders;

use App\Support\Demo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * 初期データ（マスタ・デフォルト種目・ジム・オーナー）。
     * local 環境では、動作確認用のテストユーザーも作成する（本番では実行しない）。
     * デモ環境（DEMO_MODE=true）では、デモ用のユーザーと記録を作成する。パスワードは毎回ランダム（ボタンでログインするため）。
     */
    public function run(): void
    {
        if (Demo::enabled()) {
            config(['trainingmax.seed.owner_password' => Str::random(40)]);
        }

        $this->call([
            BodyPartSeeder::class,
            ExerciseCategorySeeder::class,
            DefaultExerciseSeeder::class,
            GymSeeder::class,
            OwnerSeeder::class,
        ]);

        if (Demo::enabled()) {
            $this->call(DemoSeeder::class);
        } elseif (app()->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
