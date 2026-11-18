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

        // 解除後は、同じジムの利用者ではなくなるため、詳細ではなく一覧へ戻る
        return redirect()->route('staff.clients.index')->with('status', "{$client->name}さんのジムとの紐付けを解除しました。");
    }
}
