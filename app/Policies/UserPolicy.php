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

    /**
     * 利用者アカウントを発行できるか（有効なスタッフ）。
     */
    public function issueClient(User $user): bool
    {
        return $user->isActiveStaff();
    }

    /**
     * パスワード設定メールを再送できるか（有効なスタッフが、同じジムの、パスワード未設定の利用者に対して）。
     */
    public function sendInitialPasswordMail(User $user, User $client): bool
    {
        return $user->isActiveStaff()
            && $client->isClient()
            && $user->gym_id !== null
            && $user->gym_id === $client->gym_id
            && $client->password === null;
    }

    /**
     * トレーナーを停止できるか（有効なオーナーが、同じジムのトレーナーに対して。自分自身・他のオーナーは対象外）。
     */
    public function suspend(User $user, User $trainer): bool
    {
        return $this->manageTrainers($user)
            && ! $user->is($trainer)
            && $trainer->isTrainer()
            && $user->gym_id !== null
            && $user->gym_id === $trainer->gym_id;
    }

    /**
     * 停止中のトレーナーを再開できるか（停止できる人と同じ条件）。
     */
    public function resume(User $user, User $trainer): bool
    {
        return $this->suspend($user, $trainer);
    }
}
