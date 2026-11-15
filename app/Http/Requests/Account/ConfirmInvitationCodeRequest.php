<?php

namespace App\Http\Requests\Account;

use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 招待コードの入力（確認）。形式が正しくない入力も、存在しないコードと同じメッセージにする
 * （理由を出し分けない。回数制限の対象にも数える。セキュリティ設計書6.3）。
 */
class ConfirmInvitationCodeRequest extends FormRequest
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
        $alphabet = preg_quote(InvitationCode::ALPHABET, '/');

        return [
            'code' => ['required', 'string', 'regex:/^['.$alphabet.']{'.InvitationCode::LENGTH.'}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => '招待コードを入力してください。',
            'code.string' => __('auth.invitation_code_invalid'),
            'code.regex' => __('auth.invitation_code_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => '招待コード'];
    }

    /**
     * 小文字で入力されても大文字として扱う。
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }
}
