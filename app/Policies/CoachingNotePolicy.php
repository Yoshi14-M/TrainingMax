<?php

namespace App\Policies;

use App\Models\CoachingNote;
use App\Models\User;

/**
 * 指導メモ（セキュリティ設計書4.4）。利用者本人には、閲覧も含めてすべて不可。
 * 条件：操作者の gym_id ＝ メモの gym_id ＝ 対象の利用者の現在の gym_id（有効なスタッフのみ）。
 */
class CoachingNotePolicy
{
    /**
     * 利用者の指導メモを閲覧できるか（一覧・セッション画面の要約）。
     */
    public function viewAny(User $user, User $client): bool
    {
        return $this->isSameGymStaff($user, $client);
    }

    public function create(User $user, User $client): bool
    {
        return $this->isSameGymStaff($user, $client);
    }

    public function update(User $user, CoachingNote $note): bool
    {
        return $this->isSameGymStaff($user, $note->client) && $note->gym_id === $user->gym_id;
    }

    public function delete(User $user, CoachingNote $note): bool
    {
        return $this->update($user, $note);
    }

    private function isSameGymStaff(User $user, User $client): bool
    {
        return $user->isActiveStaff()
            && $client->isClient()
            && $user->gym_id !== null
            && $user->gym_id === $client->gym_id;
    }
}
