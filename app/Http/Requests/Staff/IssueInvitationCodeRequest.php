<?php

namespace App\Http\Requests\Staff;

use App\Models\InvitationCode;
use Illuminate\Foundation\Http\FormRequest;

class IssueInvitationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InvitationCode::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'memo' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'memo.max' => 'メモは:max文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['memo' => 'メモ'];
    }
}
