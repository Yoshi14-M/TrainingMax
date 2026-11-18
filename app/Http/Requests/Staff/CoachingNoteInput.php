<?php

namespace App\Http\Requests\Staff;

use App\Enums\CoachingNoteCategory;
use Illuminate\Validation\Rule;

/**
 * 指導メモの入力ルール（分類は Enum の値、本文は 1〜1000 文字）。
 */
trait CoachingNoteInput
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(CoachingNoteCategory::class)],
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => '分類を選択してください。',
            'category.enum' => '分類の選択が正しくありません。',
            'body.required' => '本文を入力してください。',
            'body.max' => '本文は:max文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['category' => '分類', 'body' => '本文'];
    }
}
