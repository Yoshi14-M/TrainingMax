<?php

namespace App\Actions\Workout;

use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class DeleteWorkoutSession
{
    /**
     * セッションを削除する（中の種目・セットは外部キーの CASCADE で削除される）。
     * 操作ログには、削除したセット数だけを記録する（値は出さない）。
     */
    public function handle(User $actor, WorkoutSession $session): void
    {
        DB::transaction(function () use ($actor, $session) {
            $sets = $session->workoutSets()->count();

            $session->delete();

            Audit::log('workout_session.deleted', [
                'actor_id' => $actor->id,
                'client_id' => $session->user_id,
                'session_id' => $session->id,
                'sets' => $sets,
            ]);
        });
    }
}
