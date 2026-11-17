<?php

namespace App\Http\Requests\Records;

use App\Support\Graph\GraphPeriod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * グラフの表示期間のURLパラメータ（period・from・to。セキュリティ設計書6.9）。
 * 不正な値は、エラー画面にせず初期値（直近90日）に置き換える（URLを手で書き換えただけの場合に操作を止めないため）。
 */
trait GraphPeriodInput
{
    /**
     * @return array<string, mixed>
     */
    protected function periodRules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(array_keys(GraphPeriod::OPTIONS))],
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => '表示期間は、全期間・直近30日・直近90日・期間指定のいずれかを選択してください。',
            'from.date_format' => '開始日は日付の形式で入力してください。',
            'from.after_or_equal' => '開始日は2000/01/01以降にしてください。',
            'from.before_or_equal' => '開始日は今日以前にしてください。',
            'to.date_format' => '終了日は日付の形式で入力してください。',
            'to.after_or_equal' => '終了日は開始日以降にしてください。',
            'to.before_or_equal' => '終了日は今日以前にしてください。',
            'part.integer' => '部位を選択してください。',
            'part.exists' => '部位を選択してください。',
            'exercise.integer' => '種目を選択してください。',
        ];
    }

    /**
     * 入力エラーでもリダイレクトせず、画面を表示する（不正な値は初期値に置き換える）。
     */
    protected function failedValidation(Validator $validator): void
    {
        // 何もしない（period()・graphPeriod() が、不正な項目を無視する）
    }

    /**
     * 検証に通った項目だけを返す（エラーのある項目は null）。
     */
    protected function validInput(string $key): mixed
    {
        return $this->validator->errors()->has($key) ? null : $this->input($key);
    }

    public function graphPeriod(): GraphPeriod
    {
        $key = $this->validInput('period') ?? GraphPeriod::DEFAULT;

        // 期間指定は、開始日・終了日がどちらも正しい場合だけ有効にする
        return GraphPeriod::make($key, $this->validInput('from'), $this->validInput('to'));
    }
}
