<?php

namespace App\Http\Requests\Records;

/**
 * セッションコメントの入力ルール（本文は 1〜1000 文字）。
 */
trait SessionCommentInput
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:1', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'コメントを入力してください。',
            'body.max' => 'コメントは:max文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'コメント'];
    }
}
