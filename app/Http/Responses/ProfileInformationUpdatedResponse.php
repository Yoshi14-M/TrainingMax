<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\ProfileInformationUpdatedResponse as ProfileInformationUpdatedResponseContract;

class ProfileInformationUpdatedResponse implements ProfileInformationUpdatedResponseContract
{
    /**
     * 更新後はプロフィール画面へ戻す。メールアドレスを変えた場合は、未認証になるため認証のお願い画面へ移る。
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 200);
        }

        return redirect()->route('profile.show')->with('status', 'プロフィールを更新しました。');
    }
}
