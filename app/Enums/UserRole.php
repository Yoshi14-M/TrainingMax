<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Trainer = 'trainer';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'オーナー',
            self::Trainer => 'トレーナー',
            self::Client => '利用者',
        };
    }
}
