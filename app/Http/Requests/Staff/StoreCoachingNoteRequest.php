<?php

namespace App\Http\Requests\Staff;

use App\Models\CoachingNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoachingNoteRequest extends FormRequest
{
    use CoachingNoteInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', [CoachingNote::class, $this->route('client')]) ?? false;
    }

    protected function getRedirectUrl(): string
    {
        return route('staff.clients.show', $this->route('client')).'#notes';
    }
}
