<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\IgnoresInvalidInput;
use App\Models\User;
use App\Queries\ClientListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 利用者一覧（S-20）のURLパラメータ：q（検索語）・sort（並び順）・page。
 * 検索語は50文字以内、並び順は許可リスト（last_trained / name）。不正な値は初期値にする（セキュリティ設計書6.9）。
 */
class ClientListRequest extends FormRequest
{
    use IgnoresInvalidInput;

    public function authorize(): bool
    {
        return $this->user()?->can('accessStaffArea', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', 'string', Rule::in(ClientListQuery::SORTS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => '検索語は:max文字以内で入力してください。',
            'sort.in' => '並び順は、最終トレーニング日または名前順から選択してください。',
        ];
    }

    public function keyword(): ?string
    {
        $value = $this->validInput('q');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function sort(): string
    {
        return $this->validInput('sort') ?? ClientListQuery::DEFAULT_SORT;
    }
}
