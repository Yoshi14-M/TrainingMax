<?php

namespace Database\Seeders;

use App\Models\BodyPart;
use Illuminate\Database\Seeder;

class BodyPartSeeder extends Seeder
{
    /**
     * 部位マスタ（何度実行しても重複しない）。
     */
    public function run(): void
    {
        foreach (['胸', '背中', '肩', '腕', '脚', '腹'] as $index => $name) {
            BodyPart::updateOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
