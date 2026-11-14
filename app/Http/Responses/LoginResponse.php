<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * ログイン後は、元の画面（intended）があればそこへ、なければトップへ。
     * ロール別のトップ（利用者：/clients/{id}、スタッフ：/staff/clients）は後続のイシューで切り替える。
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended(route('home'));
    }
}
