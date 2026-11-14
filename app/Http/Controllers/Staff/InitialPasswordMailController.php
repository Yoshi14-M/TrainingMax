<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Account\SendInitialPasswordMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

class InitialPasswordMailController extends StaffController
{
    /**
     * パスワード設定メールの再送（パスワード未設定の間のみ）。
     *
     * 再送の回数制限（対象の利用者ごとに1回／分）は、認可の後にここで行う。
     * ルートの throttle ミドルウェアは認可より先に実行されるため、権限のない人の操作で回数を消費してしまう。
     */
    public function __invoke(Request $request, User $client, SendInitialPasswordMail $action): RedirectResponse
    {
        $this->authorize('sendInitialPasswordMail', $client);

        $status = RateLimiter::attempt(
            'initial-password-mail:'.$client->id,
            config('trainingmax.rate_limits.initial_password_mail'),
            fn () => $action->handle($client, $request->user()),
            60,
        );

        if ($status === false || $status === Password::RESET_THROTTLED) {
            return back()->with('error', __('auth.resend_wait'));
        }

        return back()->with('status', "{$client->name}さんにパスワード設定メールを再送しました。");
    }
}
