<?php

namespace App\Http\Requests\Records;

use App\Http\Requests\ExerciseInputRules;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonalExerciseRequest extends FormRequest
{
    use ExerciseInputRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('personalExercise')) ?? false;
    }

    /**
     * @return Builder<Exercise>
     */
    protected function visibleExercises(): Builder
    {
        return Exercise::query()->visibleTo($this->route('client'));
    }

    protected function editingExercise(): ?Exercise
    {
        return $this->route('personalExercise');
    }

    /**
     * 入力エラーで戻るときも、編集中の種目の位置に戻る（ページ上部に移動しない）。
     */
    protected function getRedirectUrl(): string
    {
        return parent::getRedirectUrl().'#exercise-'.$this->editingExercise()->id;
    }
}
