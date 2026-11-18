<?php

namespace App\Http\Requests\Records;

use App\Http\Requests\IgnoresInvalidInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ホーム（カレンダー。S-10）のURLパラメータ：month（YYYY-MM）。
 * 範囲は 2000-01 〜 今月の1年後。不正な値は今月にする（セキュリティ設計書6.9）。認可は基底の RecordsController で済んでいる。
 */
class HomeRequest extends FormRequest
{
    use IgnoresInvalidInput;

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
            'month' => [
                'nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                function (string $attribute, mixed $value, Closure $fail) {
                    // 年月の文字列は、そのまま大小を比べられる（YYYY-MM）
                    if ($value < '2000-01' || $value > now()->addYear()->format('Y-m')) {
                        $fail('表示できる年月の範囲外です。');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.regex' => '年月は YYYY-MM の形式で指定してください。',
        ];
    }

    /**
     * 表示する年月（YYYY-MM）。不正・範囲外は今月。
     */
    public function month(): string
    {
        return $this->validInput('month') ?? now()->format('Y-m');
    }
}
