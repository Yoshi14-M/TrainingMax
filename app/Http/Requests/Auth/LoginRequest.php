<?php

namespace App\Http\Requests\Auth;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * ログインの入力チェック。Fortify の LoginRequest を差し替える（FortifyServiceProvider で bind）。
 */
class LoginRequest extends FortifyLoginRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスを入力してください。',
            'password.required' => 'パスワードを入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'メールアドレス',
            'password' => 'パスワード',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['remember' => $this->boolean('remember')]);
    }
}
