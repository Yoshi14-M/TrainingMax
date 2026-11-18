<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Account\IssueClientAccount;
use App\Http\Requests\Staff\ClientListRequest;
use App\Http\Requests\Staff\IssueClientRequest;
use App\Models\User;
use App\Queries\ClientListQuery;
use App\Queries\ClientSummaryQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ClientController extends StaffController
{
    /**
     * 利用者一覧（S-20）：同じジムの利用者を、検索・並び順・ページ分けつきで表示する。
     */
    public function index(ClientListRequest $request, ClientListQuery $query): View
    {
        return view('staff.clients.index', [
            'clients' => $query->handle($request->user(), $request->keyword(), $request->sort()),
            'keyword' => $request->keyword(),
            'sort' => $request->sort(),
        ]);
    }

    /**
     * 利用者詳細（S-21）。
     */
    public function show(User $client, ClientSummaryQuery $summary): View
    {
        $this->authorize('viewClient', $client);

        return view('staff.clients.show', ['client' => $client, ...$summary->handle($client)]);
    }

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

        return redirect()->route('staff.clients.show', $client)
            ->with('status', "{$client->name}さんのアカウントを発行しました。パスワード設定メールを送信しました。");
    }
}
