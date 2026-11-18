<?php

namespace App\Actions\Fortify;

use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    /**
     * パスワード変更。他の端末のログインは、`auth.session` ミドルウェアが次のリクエストで解除する。
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $request = new UpdatePasswordRequest;
        $request->setUserResolver(fn () => $user);

        $validated = Validator::make(
            $input,
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        )->validateWithBag('updatePassword');

        $user->forceFill(['password' => $validated['password']])->save();

        Audit::log('password.changed', ['user_id' => $user->id]);
    }
}
