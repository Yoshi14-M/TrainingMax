<?php

namespace App\Enums;

enum CoachingNoteCategory: string
{
    case Caution = 'caution';
    case Injury = 'injury';
    case Goal = 'goal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Caution => '注意点',
            self::Injury => '怪我・既往歴',
            self::Goal => '目標',
            self::Other => 'その他',
        };
    }

    /**
     * セッション画面（S-11）の上部に、トレーニング中に確認できるよう表示する分類。
     */
    public function showsOnSession(): bool
    {
        return in_array($this, [self::Caution, self::Injury], true);
    }
}
