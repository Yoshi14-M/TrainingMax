<?php

namespace App\Http\Controllers\Records;

use App\Actions\Workout\DeleteSessionExercise;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * セッション内の種目（S-11）。種目ごとの削除（記録したセットも削除する）。
 */
class SessionExerciseController extends RecordsController
{
    public function destroy(Request $request, User $client, WorkoutSession $workoutSession, SessionExercise $sessionExercise, DeleteSessionExercise $action): RedirectResponse
    {
        $name = $sessionExercise->exercise->name;

        $action->handle($request->user(), $sessionExercise);

        return redirect()->route('records.sessions.show', [$client, $workoutSession])
            ->with('status', "「{$name}」の記録を削除しました。");
    }
}
