<?php

namespace App\Http\Requests\Records;

use App\Models\SessionComment;
use Illuminate\Foundation\Http\FormRequest;

class StoreSessionCommentRequest extends FormRequest
{
    use SessionCommentInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', [SessionComment::class, $this->route('workoutSession')]) ?? false;
    }

    protected function getRedirectUrl(): string
    {
        return route('records.sessions.show', [$this->route('client'), $this->route('workoutSession')]).'#comments';
    }
}
