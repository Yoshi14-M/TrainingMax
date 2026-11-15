<?php

namespace App\Enums;

enum InvitationCodeStatus: string
{
    case Unused = 'unused';
    case Used = 'used';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Unused => '未使用',
            self::Used => '使用済み',
            self::Revoked => '取消',
            self::Expired => '期限切れ',
        };
    }
}
