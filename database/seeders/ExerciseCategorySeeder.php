<?php

namespace Database\Seeders;

use App\Models\ExerciseCategory;
use Illuminate\Database\Seeder;

class ExerciseCategorySeeder extends Seeder
{
    /**
     * 種目分類マスタ（推奨値は固定。データ設計書4.5）。
     */
    public function run(): void
    {
        $categories = [
            ['name' => '高重量コンパウンド', 'rep_min' => 6, 'rep_max' => 8, 'rir_min' => 1, 'rir_max' => 2, 'rest_seconds' => 180],
            ['name' => '中重量コンパウンド', 'rep_min' => 8, 'rep_max' => 10, 'rir_min' => 1, 'rir_max' => 2, 'rest_seconds' => 120],
            ['name' => '標準アイソレート', 'rep_min' => 10, 'rep_max' => 12, 'rir_min' => 0, 'rir_max' => 1, 'rest_seconds' => 90],
            ['name' => '高レップアイソレート', 'rep_min' => 12, 'rep_max' => 15, 'rir_min' => 0, 'rir_max' => 0, 'rest_seconds' => 60],
        ];

        foreach ($categories as $index => $category) {
            ExerciseCategory::updateOrCreate(
                ['name' => $category['name']],
                [...$category, 'sort_order' => $index + 1],
            );
        }
    }
}
