<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * パスワードリセット申請の結果。メールアドレスの登録有無・送信間隔の制限中でも、
 * 成功時と同じメッセージを返す（セキュリティ設計書5.5）。
 */
class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        $message = __('passwords.link_requested');

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message], 200);
        }

        return back()->with('status', $message);
    }
}
