<?php

namespace App\Actions\Fortify;

use App\Http\Requests\Auth\PasswordRules;

/**
 * パスワードのルールは FormRequest 側（PasswordRules）に集約している。
 */
trait PasswordValidationRules
{
    use PasswordRules;
}
