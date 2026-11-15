<?php

namespace App\Http\Requests\Account;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ジムへの紐付け（確認画面からの送信）。招待コードはフォームから受け取らず、
 * 確認で有効と分かったコードのIDをサーバー側のセッションから使う。
 */
class LinkToGymRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('linkToGym', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => '共有について確認し、チェックを入れてください。',
        ];
    }
}
