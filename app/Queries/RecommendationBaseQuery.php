<?php

namespace App\Queries;

use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;

/**
 * 推奨重量の基準にする推定1RMの取得（データ設計書6章）。
 * 過去日のセッションでは「その日より前」の記録だけを基準にする。
 */
class RecommendationBaseQuery
{
    /**
     * 直前のセット（同じセッション・同じ種目の、最後のセット）の推定1RM。
     */
    public function previousSet(?SessionExercise $sessionExercise): ?float
    {
        if ($sessionExercise === null) {
            return null;
        }

        $set = $sessionExercise->sets()->orderByDesc('set_number')->first();

        return $set?->estimated_1rm;
    }

    /**
     * 前回セッション（セッション日より前で、この種目を行った直近の日）の最大推定1RM。
     */
    public function lastSessionMax(User $client, Exercise $exercise, WorkoutSession $session): ?float
    {
        $lastDate = $client->workoutSessions()
            ->where('performed_on', '<', $session->performed_on->toDateString())
            ->whereHas('sessionExercises', fn ($query) => $query->where('exercise_id', $exercise->id))
            ->max('performed_on');

        if ($lastDate === null) {
            return null;
        }

        $max = WorkoutSet::query()
            ->whereHas('sessionExercise', fn ($query) => $query
                ->where('exercise_id', $exercise->id)
                ->whereHas('workoutSession', fn ($query) => $query
                    ->where('user_id', $client->id)
                    ->where('performed_on', $lastDate)))
            ->max('estimated_1rm');

        return $max === null ? null : (float) $max;
    }

    /**
     * 全期間（セッション日より前）の最大推定1RM。
     */
    public function allTimeMax(User $client, Exercise $exercise, WorkoutSession $session): ?float
    {
        $max = WorkoutSet::query()
            ->whereHas('sessionExercise', fn ($query) => $query
                ->where('exercise_id', $exercise->id)
                ->whereHas('workoutSession', fn ($query) => $query
                    ->where('user_id', $client->id)
                    ->where('performed_on', '<', $session->performed_on->toDateString())))
            ->max('estimated_1rm');

        return $max === null ? null : (float) $max;
    }
}
