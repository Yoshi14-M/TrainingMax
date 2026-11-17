<?php

namespace App\Queries;

use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSet;
use App\Support\Graph\GraphPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * トレーニンググラフ（S-13）のデータ。1日（1セッション）を1点として、指標を日付ごとに集計する（機能設計書F-12）。
 * 集計は Eloquent で取得した結果をコレクションで行う（生SQLは使わない）。
 *
 * 返す形：['labels' => 日付（YYYY-MM-DD）の昇順, 'datasets' => 指標ごとの値（記録のない日は null）]
 */
class TrainingGraphQuery
{
    /**
     * 利用者が記録している部位（表示順）。
     *
     * @return EloquentCollection<int, BodyPart>
     */
    public function partsWithRecords(User $client): EloquentCollection
    {
        return BodyPart::query()
            ->whereHas('exercises.sessionExercises.workoutSession', fn (Builder $query) => $query->where('user_id', $client->id))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * 部位のうち、利用者が記録している（見える）種目。非表示の種目も、過去の記録を見るため含める。
     *
     * @return EloquentCollection<int, Exercise>
     */
    public function exercisesWithRecords(User $client, BodyPart $part): EloquentCollection
    {
        return Exercise::query()
            ->visibleTo($client)
            ->where('body_part_id', $part->id)
            ->whereHas('sessionExercises.workoutSession', fn (Builder $query) => $query->where('user_id', $client->id))
            ->orderBy('name')
            ->get();
    }

    /**
     * 種目のグラフ：推定1RM（その日の最大）・最大重量・セットボリューム（その日の最大）・総ボリューム。
     *
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function exerciseSeries(User $client, Exercise $exercise, GraphPeriod $period): array
    {
        $byDate = $this->setsByDate(
            $client,
            $period,
            fn (Builder $query) => $query->where('exercise_id', $exercise->id),
        );

        $labels = $byDate->keys()->all();

        return [
            'labels' => $labels,
            'datasets' => [
                $this->dataset('estimated_1rm', '推定1RM', 'kg', 'left', '#2563eb', $byDate->map(fn (Collection $sets) => $sets->max('estimated_1rm'))),
                $this->dataset('max_weight', '最大重量', 'kg', 'left', '#16a34a', $byDate->map(fn (Collection $sets) => $sets->max(fn (WorkoutSet $set) => (float) $set->weight))),
                $this->dataset('max_set_volume', 'セットボリューム（最大）', '', 'right', '#f59e0b', $byDate->map(fn (Collection $sets) => $sets->max(fn (WorkoutSet $set) => (float) $set->set_volume))),
                $this->dataset('total_volume', '総ボリューム', '', 'right', '#dc2626', $byDate->map(fn (Collection $sets) => $sets->sum(fn (WorkoutSet $set) => (float) $set->set_volume))),
            ],
        ];
    }

    /**
     * 部位のグラフ（その部位の全種目の合計）：セット数・種目数・総ボリューム。
     *
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function partSeries(User $client, BodyPart $part, GraphPeriod $period): array
    {
        $byDate = $this->setsByDate(
            $client,
            $period,
            fn (Builder $query) => $query->whereHas('exercise', fn (Builder $exercise) => $exercise->where('body_part_id', $part->id)),
        );

        $labels = $byDate->keys()->all();

        return [
            'labels' => $labels,
            'datasets' => [
                $this->dataset('sets', 'セット数', 'セット', 'left', '#2563eb', $byDate->map(fn (Collection $sets) => $sets->count())),
                $this->dataset('exercises', '種目数', '種目', 'left', '#16a34a', $byDate->map(fn (Collection $sets) => $sets->pluck('session_exercise_id')->unique()->count())),
                $this->dataset('total_volume', '総ボリューム', '', 'right', '#dc2626', $byDate->map(fn (Collection $sets) => $sets->sum(fn (WorkoutSet $set) => (float) $set->set_volume))),
            ],
        ];
    }

    /**
     * 期間内のセットを、実施日ごとにまとめる（日付の昇順）。
     *
     * @param  callable(Builder): mixed  $sessionExerciseFilter  session_exercises の絞り込み（種目・部位）
     * @return Collection<string, Collection<int, WorkoutSet>>
     */
    private function setsByDate(User $client, GraphPeriod $period, callable $sessionExerciseFilter): Collection
    {
        $sets = WorkoutSet::query()
            ->whereHas('sessionExercise', function (Builder $query) use ($client, $period, $sessionExerciseFilter) {
                $sessionExerciseFilter($query);

                $query->whereHas('workoutSession', function (Builder $session) use ($client, $period) {
                    $session->where('user_id', $client->id);

                    if ($period->from !== null) {
                        $session->where('performed_on', '>=', $period->from->toDateString());
                    }
                    if ($period->to !== null) {
                        $session->where('performed_on', '<=', $period->to->toDateString());
                    }
                });
            })
            ->with('sessionExercise.workoutSession')
            ->get();

        return $sets
            ->groupBy(fn (WorkoutSet $set) => $set->sessionExercise->workoutSession->performed_on->toDateString())
            ->sortKeys();
    }

    /**
     * @param  Collection<string, mixed>  $values  実施日 → 値
     * @return array<string, mixed>
     */
    private function dataset(string $key, string $label, string $unit, string $axis, string $color, Collection $values): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'unit' => $unit,
            'axis' => $axis,
            'color' => $color,
            'hidden' => false,
            // 表示は小数第1位まで（計算は丸めずに行い、最後に丸める）
            'data' => $values->values()->map(fn ($value) => round((float) $value, 1))->all(),
        ];
    }
}
