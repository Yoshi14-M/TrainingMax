<?php

namespace App\Http\Controllers\Records;

use App\Actions\Workout\DeleteWorkoutSet;
use App\Actions\Workout\RecordWorkoutSet;
use App\Actions\Workout\UpdateWorkoutSet;
use App\Http\Requests\Records\StoreWorkoutSetRequest;
use App\Http\Requests\Records\UpdateWorkoutSetRequest;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * セットの登録・編集・削除（S-11）。
 */
class WorkoutSetController extends RecordsController
{
    public function store(StoreWorkoutSetRequest $request, User $client, WorkoutSession $workoutSession, RecordWorkoutSet $action): RedirectResponse
    {
        $set = $action->handle($request->user(), $workoutSession, $request->validated());

        // 続けてセットを追加しやすいよう、種目の選択を保持して戻る
        return redirect(route('records.sessions.show', [$client, $workoutSession]).'?exercise='.$set->sessionExercise->exercise_id.'#set-'.$set->id)
            ->with('status', 'セットを記録しました。');
    }

    public function update(UpdateWorkoutSetRequest $request, User $client, WorkoutSession $workoutSession, WorkoutSet $workoutSet, UpdateWorkoutSet $action): RedirectResponse
    {
        $action->handle($request->user(), $workoutSet, $request->validated());

        return redirect(route('records.sessions.show', [$client, $workoutSession]).'?exercise='.$workoutSet->sessionExercise->exercise_id.'#set-'.$workoutSet->id)
            ->with('status', 'セットを更新しました。');
    }

    public function destroy(Request $request, User $client, WorkoutSession $workoutSession, WorkoutSet $workoutSet, DeleteWorkoutSet $action): RedirectResponse
    {
        $exerciseId = $workoutSet->sessionExercise->exercise_id;

        $action->handle($workoutSet);

        return redirect(route('records.sessions.show', [$client, $workoutSession]).'?exercise='.$exerciseId)
            ->with('status', 'セットを削除しました。');
    }
}
