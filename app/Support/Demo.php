<?php

namespace App\Support;

use App\Models\User;

/**
 * デモ環境（config/demo.php）の判定をまとめる。
 */
class Demo
{
    public static function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    /**
     * ボタンでログインできるデモ用アカウントのメールアドレス（小文字）。
     *
     * @return array<int, string>
     */
    public static function emails(): array
    {
        return collect(config('demo.accounts'))
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->values()
            ->all();
    }

    public static function isDemoAccount(?User $user): bool
    {
        return $user !== null && in_array(mb_strtolower($user->email), self::emails(), true);
    }
}
