<?php

namespace App\Http\Requests;

use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Rules\UniqueExerciseName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * 種目の登録・編集の入力ルール（セキュリティ設計書6.6）。
 * 使うクラスは、重複チェックの範囲（visibleExercises）と編集中の種目（editingExercise）を定義する。
 */
trait ExerciseInputRules
{
    /**
     * 名前の重複チェックの範囲（その人に見える種目）。
     *
     * @return Builder<Exercise>
     */
    abstract protected function visibleExercises(): Builder;

    /**
     * 編集中の種目（登録のときは null）。
     */
    protected function editingExercise(): ?Exercise
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body_part_id' => ['required', 'integer', Rule::exists(BodyPart::class, 'id')],
            'name' => ['required', 'string', 'min:1', 'max:50', new UniqueExerciseName($this->visibleExercises(), $this->editingExercise()?->id)],
            'exercise_category_id' => ['required', 'integer', Rule::exists(ExerciseCategory::class, 'id')],
            'is_hidden' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body_part_id.required' => '部位を選択してください。',
            'body_part_id.exists' => '部位を選択してください。',
            'name.required' => '種目名を入力してください。',
            'name.max' => '種目名は:max文字以内で入力してください。',
            'exercise_category_id.required' => '分類を選択してください。',
            'exercise_category_id.exists' => '分類を選択してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body_part_id' => '部位',
            'name' => '種目名',
            'exercise_category_id' => '分類',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_hidden' => $this->boolean('is_hidden')]);
    }
}
