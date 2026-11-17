<?php

namespace App\Http\Requests\Records;

use App\Models\BodyPart;
use App\Models\Exercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * トレーニンググラフ（S-13）のURLパラメータ：part（部位）・exercise（種目）・期間。
 * 認可は基底の RecordsController で済んでいるが、見えない種目のIDは 403 とする（セキュリティ設計書6.9）。
 */
class TrainingGraphRequest extends FormRequest
{
    use GraphPeriodInput;

    public function authorize(): bool
    {
        $exercise = $this->query('exercise');

        // 整数でない値は、初期値に置き換えるため認可の対象外（rules で無視される）
        if (! is_string($exercise) || ! ctype_digit($exercise)) {
            return true;
        }

        // 見えない種目（他人の個人種目・他ジムの種目・存在しない種目）は403。非表示の種目は、過去の記録を見るため可
        return Exercise::query()->visibleTo($this->route('client'))->whereKey((int) $exercise)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'part' => ['nullable', 'integer', Rule::exists(BodyPart::class, 'id')],
            'exercise' => ['nullable', 'integer'],
            ...$this->periodRules(),
        ];
    }

    public function partId(): ?int
    {
        $value = $this->validInput('part');

        return $value === null ? null : (int) $value;
    }

    public function exerciseId(): ?int
    {
        $value = $this->validInput('exercise');

        return $value === null ? null : (int) $value;
    }
}
