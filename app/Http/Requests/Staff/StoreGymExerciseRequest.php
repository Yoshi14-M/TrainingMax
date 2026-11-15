<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ExerciseInputRules;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class StoreGymExerciseRequest extends FormRequest
{
    use ExerciseInputRules;

    public function authorize(): bool
    {
        return $this->user()?->can('createGym', Exercise::class) ?? false;
    }

    /**
     * 重複チェックの範囲：スタッフに見える種目（デフォルト、自分のジムの共通種目）。
     *
     * @return Builder<Exercise>
     */
    protected function visibleExercises(): Builder
    {
        return Exercise::query()->visibleToStaff($this->user());
    }
}
