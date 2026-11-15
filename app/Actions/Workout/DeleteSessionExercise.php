<?php

namespace App\Actions\Workout;

use App\Models\SessionExercise;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class DeleteSessionExercise
{
    /**
     * セッションから種目を、記録したセットごと削除する（セットは外部キーの CASCADE で削除される）。
     * 操作ログには、削除したセット数だけを記録する（値は出さない）。
     */
    public function handle(User $actor, SessionExercise $sessionExercise): int
    {
        return DB::transaction(function () use ($actor, $sessionExercise) {
            $session = $sessionExercise->workoutSession;
            $sets = $sessionExercise->sets()->count();

            $sessionExercise->delete();

            Audit::log('workout_exercise.deleted', [
                'actor_id' => $actor->id,
                'client_id' => $session->user_id,
                'session_id' => $session->id,
                'exercise_id' => $sessionExercise->exercise_id,
                'sets' => $sets,
            ]);

            return $sets;
        });
    }
}
