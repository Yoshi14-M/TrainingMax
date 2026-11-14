<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Account\IssueClientAccount;
use App\Http\Requests\Staff\IssueClientRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ClientController extends StaffController
{
    /**
     * 利用者アカウント発行の入力画面（S-22）。
     */
    public function create(): View
    {
        return view('staff.clients.create');
    }

    public function store(IssueClientRequest $request, IssueClientAccount $action): RedirectResponse
    {
        $client = $action->handle($request->user(), $request->validated());

        return redirect()->route('staff.clients.create')
            ->with('status', "{$client->name}さんのアカウントを発行しました。パスワード設定メールを送信しました。");
    }
}
