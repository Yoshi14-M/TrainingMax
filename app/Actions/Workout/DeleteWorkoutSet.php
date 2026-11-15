<?php

namespace App\Actions\Workout;

use App\Models\SessionExercise;
use App\Models\WorkoutSet;
use Illuminate\Support\Facades\DB;

class DeleteWorkoutSet
{
    /**
     * セットを削除し、後ろのセット番号を繰り上げる。種目のセットが0件になったら、その種目もセッションから消す
     * （データ設計書5.5）。番号の繰り上げは、同時操作でずれないよう種目の行をロックし、
     * 一意制約に引っかからないよう小さい順に1件ずつ更新する。
     */
    public function handle(WorkoutSet $set): void
    {
        DB::transaction(function () use ($set) {
            $sessionExercise = SessionExercise::query()
                ->whereKey($set->session_exercise_id)
                ->lockForUpdate()
                ->firstOrFail();

            $set->delete();

            $following = $sessionExercise->sets()
                ->where('set_number', '>', $set->set_number)
                ->orderBy('set_number')
                ->get();

            foreach ($following as $next) {
                $next->forceFill(['set_number' => $next->set_number - 1])->save();
            }

            if (! $sessionExercise->sets()->exists()) {
                $sessionExercise->delete();
            }
        });
    }
}
