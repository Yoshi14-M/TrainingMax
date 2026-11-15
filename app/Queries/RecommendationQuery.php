<?php

namespace App\Queries;

use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Training\OneRepMax;

/**
 * セット入力フォームに渡す、推奨重量の候補（アーキテクチャ設計書9.3・11.2）。
 * 候補ごとに、刻み×目標RIR の推奨重量の表をサーバーで計算して渡す（画面側は表から選ぶだけ）。
 */
class RecommendationQuery
{
    public function __construct(private readonly RecommendationBaseQuery $bases) {}

    /**
     * 推奨重量の候補。同じセッションに記録済みのセットがある場合は、直前のセットを主の推奨にし、
     * 前回セッションの最大・全期間の最大からの推奨を参考として並べる（機能設計書F-09）。
     * 前回の最大と全期間の最大が同じ値なら、1つにまとめる。記録が全くなければ空。
     *
     * @return array<int, array{key: string, label: string, reference: bool, base: float, table: array<string, array<int, float>>}>
     */
    public function candidates(User $client, Exercise $exercise, WorkoutSession $session, ?SessionExercise $sessionExercise): array
    {
        $minReps = $exercise->category->rep_min;

        $previous = $this->bases->previousSet($sessionExercise);
        $last = $this->bases->lastSessionMax($client, $exercise, $session);
        $all = $this->bases->allTimeMax($client, $exercise, $session);

        $entries = [];

        if ($previous !== null) {
            $entries[] = ['previous', '直前のセットから', $previous];
        }

        if ($last !== null && $all !== null && abs($last - $all) < 1e-9) {
            $entries[] = ['history', '前回の最大・自己ベストから', $last];
        } else {
            if ($last !== null) {
                $entries[] = ['last_session', '前回の最大から', $last];
            }
            if ($all !== null) {
                $entries[] = ['all_time', '自己ベストから', $all];
            }
        }

        return array_map(fn (array $entry) => [
            'key' => $entry[0],
            'label' => $entry[1],
            'reference' => $previous !== null && $entry[0] !== 'previous',
            'base' => $entry[2],
            'table' => OneRepMax::recommendTables($entry[2], $minReps),
        ], $entries);
    }
}
