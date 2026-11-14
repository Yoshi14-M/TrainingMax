<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Notifications\SetInitialPasswordNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\Password;

class SendInitialPasswordMail
{
    /**
     * パスワード設定メールを送る（専用の `initial` ブローカー。有効期限72時間）。
     *
     * @param  User|null  $actor  再送したスタッフ（再送のときだけ操作ログに記録する）
     * @return string Password::RESET_LINK_SENT など（再送の間隔が短いときは Password::RESET_THROTTLED）
     */
    public function handle(User $user, ?User $actor = null): string
    {
        $status = Password::broker('initial')->sendResetLink(
            ['email' => $user->email],
            fn (User $user, string $token) => $user->notify(new SetInitialPasswordNotification($token)),
        );

        if ($actor !== null && $status === Password::RESET_LINK_SENT) {
            Audit::log('initial_password_mail.resent', ['actor_id' => $actor->id, 'target_id' => $user->id]);
        }

        return $status;
    }
}
