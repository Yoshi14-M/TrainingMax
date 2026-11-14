<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * スタッフが利用者・トレーナーを作るときの入力ルール（名前50文字以内、メールアドレスは255文字以内で一意）。
 */
trait AccountInputRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '名前',
            'email' => 'メールアドレス',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function commonMessages(): array
    {
        return [
            'name.required' => '名前を入力してください。',
            'name.max' => '名前は:max文字以内で入力してください。',
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式で入力してください。',
            'email.max' => 'メールアドレスは:max文字以内で入力してください。',
        ];
    }
}
