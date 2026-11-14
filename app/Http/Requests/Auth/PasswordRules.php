<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * パスワードの入力ルール（8〜72文字、確認用と一致）。値は config/trainingmax.php。
 */
trait PasswordRules
{
    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(config('trainingmax.password_min'))->max(config('trainingmax.password_max')),
            'confirmed',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function passwordMessages(): array
    {
        return [
            'password.required' => 'パスワードを入力してください。',
            'password.min' => 'パスワードは:min文字以上で入力してください。',
            'password.max' => 'パスワードは:max文字以内で入力してください。',
            'password.confirmed' => 'パスワード（確認）が一致しません。',
        ];
    }
}
