<?php

namespace App\Actions\Fortify;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        $request = new ResetPasswordRequest;

        $validated = Validator::make(
            $input,
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        )->validate();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();
    }
}
