<?php

namespace App\Http\Requests\Records;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * セッションの作成。実施日は2000-01-01〜今日（Asia/Tokyo）で、その利用者の同じ日付のセッションがないこと。
 * 実施日が省略された場合は今日とする。認可は基底の RecordsController で済んでいる。
 */
class StoreWorkoutSessionRequest extends FormRequest
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
            'performed_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'performed_on.required' => '実施日を入力してください。',
            'performed_on.date_format' => '実施日は日付の形式で入力してください。',
            'performed_on.after_or_equal' => '実施日は2000/01/01以降にしてください。',
            'performed_on.before_or_equal' => '未来の日付のセッションは作成できません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['performed_on' => '実施日'];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('performed_on')) {
            $this->merge(['performed_on' => now()->toDateString()]);
        }
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('performed_on')) {
                    return;
                }

                $exists = $this->route('client')->workoutSessions()->where('performed_on', $this->input('performed_on'))->exists();

                if ($exists) {
                    $validator->errors()->add('performed_on', 'この日のセッションは既にあります。');
                }
            },
        ];
    }
}
