<?php

namespace App\Http\Requests\Account;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * プロフィール（S-16）の入力チェック。Fortify の UpdateUserProfileInformation から rules()・messages() を使う。
 * メールアドレスを変えるときだけ現在のパスワードを必須にし、身長・生年月日・性別は利用者のときだけ受け付ける。
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        $rules = [
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'current_password' => [
                Rule::requiredIf($this->emailChanges($user)),
                'nullable',
                'string',
                'current_password:web',
            ],
        ];

        if ($user->isClient()) {
            $rules += [
                'height_cm' => ['nullable', 'numeric', 'between:100,250', 'decimal:0,1'],
                'birth_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
                'gender' => ['nullable', Rule::enum(Gender::class)],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '名前を入力してください。',
            'name.max' => '名前は:max文字以内で入力してください。',
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式で入力してください。',
            'email.max' => 'メールアドレスは:max文字以内で入力してください。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
            'current_password.required' => 'メールアドレスを変更するには、現在のパスワードを入力してください。',
            'current_password.current_password' => '現在のパスワードが正しくありません。',
            'height_cm.numeric' => '身長は数値で入力してください。',
            'height_cm.between' => '身長は:min〜:maxcmの範囲で入力してください。',
            'height_cm.decimal' => '身長は小数第1位まで入力してください。',
            'birth_date.date_format' => '生年月日は日付で入力してください。',
            'birth_date.after_or_equal' => '生年月日は1900年1月1日以降で入力してください。',
            'birth_date.before_or_equal' => '生年月日は今日以前の日付を入力してください。',
            'gender.enum' => '性別の選択が正しくありません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '名前',
            'email' => 'メールアドレス',
            'current_password' => '現在のパスワード',
            'height_cm' => '身長',
            'birth_date' => '生年月日',
            'gender' => '性別',
        ];
    }

    /**
     * 入力のメールアドレスが、現在のものと違うか（小文字にして比べる）。
     */
    private function emailChanges(User $user): bool
    {
        $email = $this->input('email');

        return is_string($email) && mb_strtolower(trim($email)) !== $user->email;
    }
}
