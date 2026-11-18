<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionCommentRequest extends FormRequest
{
    use SessionCommentInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('sessionComment')) ?? false;
    }

    /**
     * 入力エラーで戻るときも、編集中のコメントの位置に戻る。
     */
    protected function getRedirectUrl(): string
    {
        $comment = $this->route('sessionComment');

        return route('records.sessions.show', [$this->route('client'), $this->route('workoutSession')]).'?edit-comment='.$comment->id.'#comment-'.$comment->id;
    }
}
