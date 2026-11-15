<?php

namespace App\Http\Controllers\Account;

use App\Actions\Gym\LinkClientToGym;
use App\Actions\Gym\UnlinkClientFromGym;
use App\Exceptions\InvalidInvitationCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmInvitationCodeRequest;
use App\Http\Requests\Account\LinkToGymRequest;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ジム連携（S-17）。個人利用者は招待コードで紐付け、ジム所属の利用者は解除できる。
 */
class GymLinkController extends Controller
{
    /**
     * 確認で有効と分かった招待コードのIDを入れるセッションのキー。
     * 「紐付ける」ではフォームからコードを受け取らず、このIDだけを使う（回数制限のすり抜けを防ぐ）。
     */
    private const SESSION_KEY = 'invitation_code_id';

    public function show(Request $request): View
    {
        $user = $request->user();

        if ($user->can('unlinkSelf', User::class)) {
            return view('account.gym-link', ['gym' => $user->gym]);
        }

        $this->authorize('linkToGym', User::class);

        return view('account.gym-link', ['gym' => null]);
    }

    /**
     * 招待コードの確認。有効なら、コードのIDをセッションに保存して確認画面へ。
     */
    public function confirm(ConfirmInvitationCodeRequest $request): RedirectResponse
    {
        $invitation = InvitationCode::query()->where('code', $request->validated('code'))->usable()->first();

        if ($invitation === null) {
            return back()->withErrors(['code' => __('auth.invitation_code_invalid')]);
        }

        $request->session()->put(self::SESSION_KEY, $invitation->id);

        return redirect()->route('gym-link.confirm.show');
    }

    public function showConfirm(Request $request): View|RedirectResponse
    {
        $this->authorize('linkToGym', User::class);

        $invitation = InvitationCode::query()
            ->with('gym')
            ->usable()
            ->find($request->session()->get(self::SESSION_KEY));

        if ($invitation === null) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('gym-link.show')->withErrors(['code' => __('auth.invitation_code_invalid')]);
        }

        return view('account.gym-link-confirm', ['gym' => $invitation->gym]);
    }

    public function store(LinkToGymRequest $request, LinkClientToGym $action): RedirectResponse
    {
        $invitationId = $request->session()->get(self::SESSION_KEY);

        try {
            if ($invitationId === null) {
                throw new InvalidInvitationCodeException;
            }

            $action->handle($request->user(), $invitationId);
        } catch (InvalidInvitationCodeException $e) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('gym-link.show')->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('gym-link.show')->with('status', 'ジムに紐付けました。');
    }

    public function destroy(Request $request, UnlinkClientFromGym $action): RedirectResponse
    {
        $this->authorize('unlinkSelf', User::class);

        $action->handle($request->user(), $request->user());

        return redirect()->route('gym-link.show')->with('status', 'ジムとの紐付けを解除しました。');
    }
}
