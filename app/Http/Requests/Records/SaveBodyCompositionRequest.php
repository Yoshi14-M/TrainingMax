<?php

namespace App\Http\Requests\Records;

use App\Models\BodyComposition;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 体組成の保存（登録・同じ日付の更新）。認可は基底の RecordsController で済んでいる。
 * 全項目が任意だが、体重〜周囲径のどれか1項目以上は必須（セキュリティ設計書6.7）。
 */
class SaveBodyCompositionRequest extends FormRequest
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
        $rules = [
            'measured_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
        ];

        foreach (BodyComposition::RANGES as $field => [$min, $max]) {
            $rules[$field] = ['nullable', 'numeric', "between:{$min},{$max}", 'decimal:0,1'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'measured_on.required' => '測定日を入力してください。',
            'measured_on.date_format' => '測定日は日付の形式で入力してください。',
            'measured_on.after_or_equal' => '測定日は2000/01/01以降にしてください。',
            'measured_on.before_or_equal' => '未来の日付は記録できません。',
        ];

        foreach (BodyComposition::RANGES as $field => [$min, $max]) {
            $label = $this->attributes()[$field];
            $unit = ($this->unit($field));

            $messages["{$field}.numeric"] = "{$label}は数値で入力してください。";
            $messages["{$field}.between"] = "{$label}は{$min}〜{$max}（{$unit}）の範囲で入力してください。";
            $messages["{$field}.decimal"] = "{$label}は小数第1位までで入力してください。";
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['measured_on' => '測定日'];

        foreach ([...BodyComposition::MAIN_FIELDS, ...BodyComposition::GIRTH_FIELDS] as $field => $definition) {
            $attributes[$field] = $definition['label'];
        }

        return $attributes;
    }

    /**
     * 体重〜周囲径が、すべて空の場合はエラーにする。
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $filled = collect(array_keys(BodyComposition::RANGES))->contains(fn (string $field) => $this->filled($field));

                if (! $filled) {
                    $validator->errors()->add('measurements', '体重・体脂肪率・筋肉量・周囲径のうち、1項目以上を入力してください。');
                }
            },
        ];
    }

    private function unit(string $field): string
    {
        return (BodyComposition::MAIN_FIELDS + BodyComposition::GIRTH_FIELDS)[$field]['unit'];
    }
}
