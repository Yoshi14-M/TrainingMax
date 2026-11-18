<?php

namespace App\Policies;

use App\Models\SessionComment;
use App\Models\User;
use App\Models\WorkoutSession;

/**
 * セッションコメント（セキュリティ設計書4.5）。スタッフのコメントと、利用者本人のトレーニングメモを兼ねる。
 * いずれも、記録にアクセスできること（利用者本人、または現在同じジムの有効なスタッフ）が前提。
 *
 * - 登録・返信：記録にアクセスできる人（利用者本人・スタッフ）。返信は最初のコメントにだけできる（1段階）
 * - 編集：書いた本人のみ
 * - 削除：書いた本人。オーナーはスタッフが書いたものだけ（利用者本人のメモは本人のみ）
 * - 紐付け解除後：スタッフは書いた本人でも編集・削除できない。利用者本人は自分の記録として引き続き操作できる
 */
class SessionCommentPolicy
{
    public function create(User $user, WorkoutSession $session): bool
    {
        return $user->canAccessRecordsOf($session->user);
    }

    public function reply(User $user, SessionComment $comment): bool
    {
        return ! $comment->isReply() && $this->create($user, $comment->workoutSession);
    }

    public function update(User $user, SessionComment $comment): bool
    {
        return $comment->created_by === $user->id
            && $this->create($user, $comment->workoutSession);
    }

    public function delete(User $user, SessionComment $comment): bool
    {
        if (! $this->create($user, $comment->workoutSession)) {
            return false;
        }

        return $comment->created_by === $user->id
            || ($user->isOwner() && $comment->author->isStaff());
    }
}
