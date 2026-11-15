<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/**
 * セットの更新（種目は変えられない）。認可は基底の RecordsController で済んでいる。
 */
class UpdateWorkoutSetRequest extends FormRequest
{
    use WorkoutSetInputRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->setRules();
    }

    /**
     * 入力エラーで戻るときも、編集中のセットの位置に戻る。
     */
    protected function getRedirectUrl(): string
    {
        return parent::getRedirectUrl().'#set-'.$this->route('workoutSet')->id;
    }
}
