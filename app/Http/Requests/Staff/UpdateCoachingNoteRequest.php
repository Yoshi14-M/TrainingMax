<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachingNoteRequest extends FormRequest
{
    use CoachingNoteInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('coachingNote')) ?? false;
    }

    /**
     * 入力エラーで戻るときも、編集中のメモの位置に戻る。
     */
    protected function getRedirectUrl(): string
    {
        return route('staff.clients.show', $this->route('client')).'?edit-note='.$this->route('coachingNote')->id.'#note-'.$this->route('coachingNote')->id;
    }
}
