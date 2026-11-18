<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 「この日の記録を作成」の確認画面（カレンダーの日付から）のURLパラメータ：date（YYYY-MM-DD。2000-01-01〜今日）。
 * 認可は基底の RecordsController で済んでいる。
 */
class NewWorkoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => '日付を指定してください。',
            'date.date_format' => '日付は YYYY-MM-DD の形式で指定してください。',
            'date.after_or_equal' => '日付は2000/01/01以降にしてください。',
            'date.before_or_equal' => '未来の日付のセッションは作成できません。',
        ];
    }
}
