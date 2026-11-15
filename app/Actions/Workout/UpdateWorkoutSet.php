<?php

namespace App\Actions\Workout;

use App\Models\User;
use App\Models\WorkoutSet;

class UpdateWorkoutSet
{
    /**
     * セットの値を更新する（推定1RM・ボリュームはモデルの保存時に再計算される）。最終更新者を記録する。
     *
     * @param  array{weight: numeric, reps: int, target_rir: int, actual_rir: int}  $data
     */
    public function handle(User $actor, WorkoutSet $set, array $data): WorkoutSet
    {
        $set->fill($data);
        $set->forceFill(['updated_by' => $actor->id])->save();

        return $set;
    }
}
