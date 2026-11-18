<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Queries\ClientSummaryQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * プロフィール・アカウント設定（S-16）。ログインしている本人の情報だけを扱う（URLにIDを含めない）。
 * 更新は Fortify のルート（PUT /user/profile-information、PUT /user/password）。
 */
class ProfileController extends Controller
{
    public function show(Request $request, ClientSummaryQuery $summary): View
    {
        $user = $request->user();

        return view('account.profile', [
            'user' => $user,
            // 体重は表示のみ（体組成の最新値）。利用者だけに表示する
            'weight' => $user->isClient() ? $summary->handle($user)['weight'] : null,
        ]);
    }
}
