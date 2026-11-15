<?php

namespace Database\Seeders;

use App\Enums\ExerciseScope;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use Illuminate\Database\Seeder;

class DefaultExerciseSeeder extends Seeder
{
    /**
     * デフォルト種目（要件定義書 付録A）。部位・分類のマスタが先に必要。
     * 既にある種目は変更しない（編集不可の種目のため、再実行で重複させない）。
     */
    public function run(): void
    {
        $exercises = [
            '高重量コンパウンド' => [
                '胸' => ['ベンチプレス'],
                '背中' => ['懸垂', 'Tバーロウ'],
                '脚' => ['スクワット'],
            ],
            '中重量コンパウンド' => [
                '肩' => ['フロントプレス'],
                '胸' => ['ディップス'],
            ],
            '標準アイソレート' => [
                '胸' => ['フライ'],
                '腕' => ['EZバーカール', 'スカルクラッシャー'],
            ],
            '高レップアイソレート' => [
                '肩' => ['サイドレイズ', 'リアレイズ'],
                '腕' => ['ケーブルプレスダウン', 'ケーブルカール'],
            ],
        ];

        $bodyParts = BodyPart::pluck('id', 'name');
        $categories = ExerciseCategory::pluck('id', 'name');

        foreach ($exercises as $categoryName => $byPart) {
            foreach ($byPart as $partName => $names) {
                foreach ($names as $name) {
                    $exists = Exercise::query()
                        ->where('scope', ExerciseScope::Default)
                        ->where('name', $name)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    (new Exercise)->forceFill([
                        'scope' => ExerciseScope::Default,
                        'body_part_id' => $bodyParts[$partName],
                        'exercise_category_id' => $categories[$categoryName],
                        'name' => $name,
                    ])->save();
                }
            }
        }
    }
}
