<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/**
 * コメントへの返信（トレーナーも利用者も返信できる）。
 */
class StoreSessionReplyRequest extends FormRequest
{
    use SessionCommentInput;

    /** 返信フォームのエラーは、通常のコメント欄とは別の入れ物に入れる */
    protected $errorBag = 'reply';

    public function authorize(): bool
    {
        return $this->user()?->can('reply', $this->route('sessionComment')) ?? false;
    }

    /**
     * 入力エラーで戻るときは、返信しようとしたコメントの位置に戻る。
     */
    protected function getRedirectUrl(): string
    {
        $comment = $this->route('sessionComment');

        return route('records.sessions.show', [$this->route('client'), $this->route('workoutSession')]).'?reply-to='.$comment->id.'#comment-'.$comment->id;
    }
}
