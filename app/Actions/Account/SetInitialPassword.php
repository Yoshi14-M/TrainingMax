<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SetInitialPassword
{
    /**
     * メールのリンクからパスワードを設定する。設定の完了でメール認証も完了扱いにする（未認証の場合）。
     *
     * @param  array{token: string, email: string, password: string, password_confirmation: string}  $input
     * @return string Password::PASSWORD_RESET など
     */
    public function handle(array $input): string
    {
        return Password::broker('initial')->reset(
            $input,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );
    }
}
