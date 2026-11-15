<?php

namespace App\Rules;

use App\Models\Exercise;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * 種目名の重複チェック。「その人に見える範囲」の中で、同じ名前の種目がないこと（編集時は自分自身を除く）。
 * 比較は DB の照合順序（utf8mb4_0900_as_ci）に従い、英字の大文字小文字は同じ、濁点・半濁点は区別する。
 */
class UniqueExerciseName implements ValidationRule
{
    /**
     * @param  Builder<Exercise>  $visibleExercises  見える範囲の種目（Exercise::visibleTo() など）
     * @param  int|null  $ignoreId  編集中の種目のID
     */
    public function __construct(
        private readonly Builder $visibleExercises,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = (clone $this->visibleExercises)
            ->where('name', (string) $value)
            ->when($this->ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('同じ名前の種目が既にあります。');
        }
    }
}
