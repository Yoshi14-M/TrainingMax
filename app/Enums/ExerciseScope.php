<?php

namespace App\Enums;

enum ExerciseScope: string
{
    case Default = 'default';
    case Gym = 'gym';
    case Personal = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'デフォルト',
            self::Gym => 'ジム',
            self::Personal => '個人',
        };
    }
}
