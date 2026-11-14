<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AddTrainerRequest extends FormRequest
{
    use AccountInputRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manageTrainers', User::class) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            'email.unique' => 'このメールアドレスは既に登録されています。',
        ];
    }
}
