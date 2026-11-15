<?php

namespace App\Http\Requests\Records;

/**
 * セットの入力ルール（セキュリティ設計書6.5）。
 */
trait WorkoutSetInputRules
{
    /**
     * @return array<string, mixed>
     */
    protected function setRules(): array
    {
        return [
            'weight' => ['required', 'numeric', 'between:0.5,500', 'decimal:0,2'],
            'reps' => ['required', 'integer', 'between:1,100'],
            'target_rir' => ['required', 'integer', 'between:0,10'],
            'actual_rir' => ['required', 'integer', 'between:0,10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exercise_id.required' => '種目を選択してください。',
            'exercise_id.integer' => 'この種目は選択できません。',
            'weight.required' => '重量を入力してください。',
            'weight.numeric' => '重量は数値で入力してください。',
            'weight.between' => '重量は:min〜:maxkgで入力してください。',
            'weight.decimal' => '重量は小数第2位までで入力してください。',
            'reps.required' => 'レップ数を入力してください。',
            'reps.integer' => 'レップ数は整数で入力してください。',
            'reps.between' => 'レップ数は:min〜:maxで入力してください。',
            'target_rir.required' => '目標RIRを入力してください。',
            'target_rir.integer' => '目標RIRは整数で入力してください。',
            'target_rir.between' => '目標RIRは:min〜:maxで入力してください。',
            'actual_rir.required' => '実績RIRを入力してください。',
            'actual_rir.integer' => '実績RIRは整数で入力してください。',
            'actual_rir.between' => '実績RIRは:min〜:maxで入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'exercise_id' => '種目',
            'weight' => '重量',
            'reps' => 'レップ数',
            'target_rir' => '目標RIR',
            'actual_rir' => '実績RIR',
        ];
    }
}
