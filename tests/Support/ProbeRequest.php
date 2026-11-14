<?php

namespace Tests\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * テスト用：認可が検証より先に行われることを確認するための FormRequest。
 */
class ProbeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return ['value' => 'required'];
    }
}
