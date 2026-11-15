<?php

namespace App\Queries;

use App\Enums\ExerciseScope;
use App\Models\BodyPart;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 種目一覧の表示用データ：部位ごと（部位の表示順）にまとめ、各部位の中は層（デフォルト・ジム・個人）→名前の順に並べる。
 */
class ExerciseListQuery
{
    /**
     * @param  Builder<Exercise>  $query  表示する種目（見える範囲・非表示の扱いは呼び出し側で絞る）
     * @return Collection<int, array{bodyPart: BodyPart, exercises: Collection<int, Exercise>}>
     */
    public function groups(Builder $query): Collection
    {
        $layerOrder = [ExerciseScope::Default->value => 0, ExerciseScope::Gym->value => 1, ExerciseScope::Personal->value => 2];

        return $query
            ->with(['bodyPart', 'category'])
            ->get()
            ->groupBy('body_part_id')
            ->map(fn (Collection $exercises) => [
                'bodyPart' => $exercises->first()->bodyPart,
                'exercises' => $exercises
                    ->sortBy([
                        fn (Exercise $a, Exercise $b) => $layerOrder[$a->scope->value] <=> $layerOrder[$b->scope->value],
                        fn (Exercise $a, Exercise $b) => strcmp($a->name, $b->name),
                    ])
                    ->values(),
            ])
            ->sortBy(fn (array $group) => $group['bodyPart']->sort_order)
            ->values();
    }
}
