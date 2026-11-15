<?php

namespace App\Actions\Workout;

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateWorkoutSession
{
    /**
     * セッション（1日分）を作る。user_id は対象の利用者（URL）から、created_by は操作者から決める。
     * 同時に2回送信されて一意制約（user_id, performed_on）に引っかかった場合は、既にあるセッションを返す
     * （セキュリティ設計書6.4）。
     */
    public function handle(User $actor, User $client, string $performedOn): WorkoutSession
    {
        try {
            $session = new WorkoutSession;
            $session->forceFill([
                'user_id' => $client->id,
                'performed_on' => $performedOn,
                'created_by' => $actor->id,
            ])->save();

            return $session;
        } catch (UniqueConstraintViolationException) {
            return $client->workoutSessions()->where('performed_on', $performedOn)->firstOrFail();
        }
    }
}
