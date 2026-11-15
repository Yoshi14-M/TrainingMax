<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ExerciseInputRules;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGymExerciseRequest extends FormRequest
{
    use ExerciseInputRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('exercise')) ?? false;
    }

    /**
     * @return Builder<Exercise>
     */
    protected function visibleExercises(): Builder
    {
        return Exercise::query()->visibleToStaff($this->user());
    }

    protected function editingExercise(): ?Exercise
    {
        return $this->route('exercise');
    }

    /**
     * 入力エラーで戻るときも、編集中の種目の位置に戻る（ページ上部に移動しない）。
     */
    protected function getRedirectUrl(): string
    {
        return parent::getRedirectUrl().'#exercise-'.$this->editingExercise()->id;
    }
}
