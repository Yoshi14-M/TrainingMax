<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Gym\UnlinkClientFromGym;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MembershipController extends StaffController
{
    /**
     * スタッフによる、利用者のジムとの紐付け解除。
     */
    public function destroy(Request $request, User $client, UnlinkClientFromGym $action): RedirectResponse
    {
        $this->authorize('unlink', $client);

        $action->handle($request->user(), $client);

        return back()->with('status', "{$client->name}さんのジムとの紐付けを解除しました。");
    }
}
