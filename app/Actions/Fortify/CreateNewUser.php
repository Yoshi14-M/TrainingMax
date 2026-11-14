<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * 新規登録。作られるのは必ず role = client、gym_id = NULL の個人利用者（セキュリティ設計書4.6）。
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $request = new RegisterRequest;

        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        $validated = Validator::make(
            $input,
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        )->validate();

        $user = new User;
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);
        $user->forceFill([
            'password' => $validated['password'],
            'role' => UserRole::Client,
            'gym_id' => null,
        ])->save();

        return $user;
    }
}
