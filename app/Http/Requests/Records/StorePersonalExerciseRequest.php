<?php

namespace App\Http\Requests\Records;

use App\Http\Requests\ExerciseInputRules;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class StorePersonalExerciseRequest extends FormRequest
{
    use ExerciseInputRules;

    public function authorize(): bool
    {
        return $this->user()?->can('createPersonal', [Exercise::class, $this->route('client')]) ?? false;
    }

    /**
     * 重複チェックの範囲：利用者に見える種目（デフォルト、所属ジムの共通種目、本人の個人種目）。
     *
     * @return Builder<Exercise>
     */
    protected function visibleExercises(): Builder
    {
        return Exercise::query()->visibleTo($this->route('client'));
    }
}
