<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Gym\IssueInvitationCode;
use App\Actions\Gym\RevokeInvitationCode;
use App\Http\Requests\Staff\IssueInvitationCodeRequest;
use App\Models\InvitationCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 招待コード管理（S-23）。
 */
class InvitationCodeController extends StaffController
{
    public function index(Request $request): View
    {
        $this->authorize('create', InvitationCode::class);

        $codes = InvitationCode::query()
            ->with(['issuer', 'usedBy'])
            ->where('gym_id', $request->user()->gym_id)
            ->latest('id')
            ->paginate(20);

        return view('staff.invitation-codes.index', ['codes' => $codes]);
    }

    public function store(IssueInvitationCodeRequest $request, IssueInvitationCode $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated('memo'));

        return redirect()->route('staff.invitation-codes.index')->with('status', '招待コードを発行しました。');
    }

    public function destroy(Request $request, InvitationCode $invitationCode, RevokeInvitationCode $action): RedirectResponse
    {
        $this->authorize('revoke', $invitationCode);

        $action->handle($request->user(), $invitationCode);

        return redirect()->route('staff.invitation-codes.index')->with('status', '招待コードを取り消しました。');
    }
}
