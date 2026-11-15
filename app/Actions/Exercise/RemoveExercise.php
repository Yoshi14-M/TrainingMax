<?php

namespace App\Actions\Exercise;

use App\Models\Exercise;

class RemoveExercise
{
    /**
     * 種目を削除する。記録で使われている種目は削除できないため、非表示にする
     * （種目選択に出なくなるが、過去の記録・グラフには残る）。
     *
     * @return bool 削除した場合は true、非表示にした場合は false
     */
    public function handle(Exercise $exercise): bool
    {
        if ($exercise->sessionExercises()->exists()) {
            $exercise->forceFill(['is_hidden' => true])->save();

            return false;
        }

        $exercise->delete();

        return true;
    }
}
