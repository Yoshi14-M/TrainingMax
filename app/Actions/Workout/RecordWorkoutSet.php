<?php

namespace App\Actions\Workout;

use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordWorkoutSet
{
    public const MAX_EXERCISES = 30;

    public const MAX_SETS = 30;

    /**
     * セットを記録する。その種目の session_exercises がなければ作成し（30種目まで）、
     * セット番号を採番してセットを作成する（30セットまで）。トランザクション内で、
     * 同時に操作されても番号がずれないよう、セッションの行をロックしてから行う。
     *
     * @param  array{exercise_id: int, weight: numeric, reps: int, target_rir: int, actual_rir: int}  $data
     *
     * @throws ValidationException 上限を超える場合
     */
    public function handle(User $actor, WorkoutSession $session, array $data): WorkoutSet
    {
        return DB::transaction(function () use ($actor, $session, $data) {
            WorkoutSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            $sessionExercise = SessionExercise::query()
                ->where('workout_session_id', $session->id)
                ->where('exercise_id', $data['exercise_id'])
                ->first();

            if ($sessionExercise === null) {
                $count = SessionExercise::query()->where('workout_session_id', $session->id)->count();

                if ($count >= self::MAX_EXERCISES) {
                    throw ValidationException::withMessages([
                        'exercise_id' => '1セッションに追加できる種目は'.self::MAX_EXERCISES.'種目までです。',
                    ]);
                }

                $sessionExercise = new SessionExercise;
                $sessionExercise->forceFill([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $data['exercise_id'],
                    'sort_order' => $count + 1,
                ])->save();
            }

            $lastNumber = (int) $sessionExercise->sets()->max('set_number');

            if ($lastNumber >= self::MAX_SETS) {
                throw ValidationException::withMessages([
                    'exercise_id' => '1種目に記録できるセットは'.self::MAX_SETS.'セットまでです。',
                ]);
            }

            $set = new WorkoutSet;
            $set->fill([
                'weight' => $data['weight'],
                'reps' => $data['reps'],
                'target_rir' => $data['target_rir'],
                'actual_rir' => $data['actual_rir'],
            ]);
            $set->forceFill([
                'session_exercise_id' => $sessionExercise->id,
                'set_number' => $lastNumber + 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            return $set;
        });
    }
}
