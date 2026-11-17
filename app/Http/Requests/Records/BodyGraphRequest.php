<?php

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 体組成グラフ（S-14）のURLパラメータ：期間。認可は基底の RecordsController で済んでいる。
 */
class BodyGraphRequest extends FormRequest
{
    use GraphPeriodInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->periodRules();
    }
}
