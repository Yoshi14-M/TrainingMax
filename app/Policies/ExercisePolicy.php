<?php

namespace App\Policies;

use App\Enums\ExerciseScope;
use App\Models\Exercise;
use App\Models\User;

class ExercisePolicy
{
    /**
     * ジム共通種目を登録できるか（そのジムの有効なスタッフ）。
     */
    public function createGym(User $user): bool
    {
        return $user->isActiveStaff() && $user->gym_id !== null;
    }

    /**
     * 個人種目を登録できるか（利用者本人が、自分の分だけ。スタッフは利用者の個人種目を登録できない）。
     */
    public function createPersonal(User $user, User $client): bool
    {
        return $user->isClient() && $user->is($client);
    }

    /**
     * 種目を編集・非表示にできるか。
     * デフォルト種目は誰も編集できない。ジム共通種目はそのジムの有効なスタッフ、個人種目は持ち主本人のみ。
     */
    public function update(User $user, Exercise $exercise): bool
    {
        return match ($exercise->scope) {
            ExerciseScope::Default => false,
            ExerciseScope::Gym => $user->isActiveStaff()
                && $user->gym_id !== null
                && $user->gym_id === $exercise->gym_id,
            ExerciseScope::Personal => $user->isClient()
                && $exercise->owner_user_id === $user->id,
        };
    }

    /**
     * 種目を削除（使用中なら非表示）できるか。編集と同じ条件。
     */
    public function delete(User $user, Exercise $exercise): bool
    {
        return $this->update($user, $exercise);
    }
}
