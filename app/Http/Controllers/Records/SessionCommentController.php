<?php

namespace App\Http\Controllers\Records;

use App\Http\Requests\Records\StoreSessionCommentRequest;
use App\Http\Requests\Records\StoreSessionReplyRequest;
use App\Http\Requests\Records\UpdateSessionCommentRequest;
use App\Models\SessionComment;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Http\RedirectResponse;

/**
 * セッションコメント（S-11）。スタッフのコメントと利用者本人のトレーニングメモを兼ね、どちらにも返信できる。
 */
class SessionCommentController extends RecordsController
{
    public function store(StoreSessionCommentRequest $request, User $client, WorkoutSession $workoutSession): RedirectResponse
    {
        $comment = new SessionComment($request->validated());
        $comment->forceFill([
            'workout_session_id' => $workoutSession->id,
            'created_by' => $request->user()->id,
        ])->save();

        return $this->back($client, $workoutSession, 'コメントを書き込みました。', $comment);
    }

    public function reply(StoreSessionReplyRequest $request, User $client, WorkoutSession $workoutSession, SessionComment $sessionComment): RedirectResponse
    {
        $reply = new SessionComment($request->validated());
        $reply->forceFill([
            'workout_session_id' => $workoutSession->id,
            'parent_id' => $sessionComment->id,
            'created_by' => $request->user()->id,
        ])->save();

        return $this->back($client, $workoutSession, '返信を書き込みました。', $reply);
    }

    public function update(UpdateSessionCommentRequest $request, User $client, WorkoutSession $workoutSession, SessionComment $sessionComment): RedirectResponse
    {
        $sessionComment->fill($request->validated())->save();

        return $this->back($client, $workoutSession, 'コメントを更新しました。', $sessionComment);
    }

    public function destroy(User $client, WorkoutSession $workoutSession, SessionComment $sessionComment): RedirectResponse
    {
        $this->authorize('delete', $sessionComment);

        $sessionComment->delete();

        return $this->back($client, $workoutSession, 'コメントを削除しました。');
    }

    private function back(User $client, WorkoutSession $session, string $status, ?SessionComment $comment = null): RedirectResponse
    {
        $anchor = $comment === null ? '#comments' : '#comment-'.$comment->id;

        return redirect(route('records.sessions.show', [$client, $session]).$anchor)->with('status', $status);
    }
}
