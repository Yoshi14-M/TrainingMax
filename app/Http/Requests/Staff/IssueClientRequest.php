<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IssueClientRequest extends FormRequest
{
    use AccountInputRules;

    public function authorize(): bool
    {
        return $this->user()?->can('issueClient', User::class) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            // スタッフ向けの画面のため、登録の有無を表示してよい（セキュリティ設計書5.5）
            'email.unique' => 'このメールアドレスは既に登録されています。個人利用者の場合は招待コードで紐付けてください。',
        ];
    }
}
