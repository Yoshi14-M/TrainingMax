<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ログイン後のトップ（/）：利用者は自分のホーム（/clients/{自分のID}）、スタッフは利用者一覧（/staff/clients）へ移す。
 */
class HomeRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return $user->isClient()
            ? redirect()->route('records.home', $user)
            : redirect()->route('staff.clients.index');
    }
}
