<?php

namespace Tests\Support;

use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;

/**
 * テスト用に、セッション・種目・セットを作る。
 */
trait BuildsWorkouts
{
    protected function makeSession(User $client, string $date, ?User $by = null): WorkoutSession
    {
        $session = new WorkoutSession;
        $session->forceFill([
            'user_id' => $client->id,
            'performed_on' => $date,
            'created_by' => ($by ?? $client)->id,
        ])->save();

        return $session;
    }

    /**
     * セッションに種目とセットを記録する。
     *
     * @param  array<int, array{0: float|int, 1: int, 2: int, 3: int}>  $sets  [重量, レップ, 目標RIR, 実績RIR]
     */
    protected function addSets(WorkoutSession $session, Exercise $exercise, array $sets, ?User $by = null): SessionExercise
    {
        $by ??= $session->user;

        $sessionExercise = SessionExercise::query()
            ->where('workout_session_id', $session->id)
            ->where('exercise_id', $exercise->id)
            ->first();

        if ($sessionExercise === null) {
            $sessionExercise = new SessionExercise;
            $sessionExercise->forceFill([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'sort_order' => $session->sessionExercises()->count() + 1,
            ])->save();
        }

        $number = (int) $sessionExercise->sets()->max('set_number');

        foreach ($sets as [$weight, $reps, $targetRir, $actualRir]) {
            $set = new WorkoutSet;
            $set->fill(['weight' => $weight, 'reps' => $reps, 'target_rir' => $targetRir, 'actual_rir' => $actualRir]);
            $set->forceFill([
                'session_exercise_id' => $sessionExercise->id,
                'set_number' => ++$number,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ])->save();
        }

        return $sessionExercise;
    }
}
