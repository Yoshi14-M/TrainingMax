<?php

namespace App\Rules;

use App\Models\Exercise;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * セットに使う種目は、対象の利用者に見える種目で、かつ非表示でないこと（セキュリティ設計書4.1・6.5）。
 * スタッフが入力する場合も「スタッフに見える種目」ではなく「対象の利用者に見える種目」で判定する。
 */
class VisibleExercise implements ValidationRule
{
    public function __construct(private readonly User $client) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $visible = Exercise::query()
            ->visibleTo($this->client)
            ->selectable()
            ->whereKey($value)
            ->exists();

        if (! $visible) {
            $fail('この種目は選択できません。');
        }
    }
}
