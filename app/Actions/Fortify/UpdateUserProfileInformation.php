<?php

namespace App\Actions\Fortify;

use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * プロフィールの更新。メールアドレスを変えた場合は未認証に戻し、認証メールを送る。
     * 身長・生年月日・性別は利用者のときだけ受け付ける（スタッフが送っても無視する）。
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        $request = new UpdateProfileRequest;
        $request->setUserResolver(fn () => $user);
        $request->merge($input);

        $validated = Validator::make(
            $input,
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        )->validateWithBag('updateProfileInformation');

        $emailChanged = $validated['email'] !== $user->email;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            ...array_intersect_key($validated, array_flip(['height_cm', 'birth_date', 'gender'])),
        ]);

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        // 値は残さない（変更したか、だけ）
        Audit::log('profile.updated', ['user_id' => $user->id, 'email_changed' => $emailChanged]);
    }
}
