<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 招待コードが使えない（存在しない・期限切れ・使用済み・取消）。理由は利用者に出し分けない。
 */
class InvalidInvitationCodeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.invitation_code_invalid'));
    }
}
