<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * 対象の利用者の記録（/clients/{client}）にアクセスできるか。
     */
    public function accessRecords(User $user, User $client): bool
    {
        return $user->canAccessRecordsOf($client);
    }

    /**
     * スタッフ専用エリア（/staff）にアクセスできるか（有効なスタッフのみ）。
     */
    public function accessStaffArea(User $user): bool
    {
        return $user->isActiveStaff();
    }

    /**
     * トレーナー管理ができるか（有効なオーナーのみ）。
     */
    public function manageTrainers(User $user): bool
    {
        return $user->isOwner() && ! $user->isSuspended();
    }
}
