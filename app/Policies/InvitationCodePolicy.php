<?php

namespace App\Policies;

use App\Models\InvitationCode;
use App\Models\User;

class InvitationCodePolicy
{
    /**
     * 招待コードの一覧・発行（有効なスタッフ）。
     */
    public function create(User $user): bool
    {
        return $user->isActiveStaff() && $user->gym_id !== null;
    }

    /**
     * 招待コードの取消（同じジムの有効なスタッフが、未使用・未取消・期限内のコードを。発行者は問わない）。
     */
    public function revoke(User $user, InvitationCode $invitationCode): bool
    {
        return $user->isActiveStaff()
            && $user->gym_id !== null
            && $user->gym_id === $invitationCode->gym_id
            && $invitationCode->isUsable();
    }
}
