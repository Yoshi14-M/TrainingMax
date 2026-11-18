<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * 画面表示用の数値の整形（機能設計書5.4）。
 */
class Format
{
    /**
     * 重量：必要な桁まで表示する。通常は小数第1位（62.5kg）、小数第2位がある場合は第2位まで（61.25kg）。
     */
    public static function weight(float|string|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $rounded = round((float) $value, 2);
        $decimals = abs($rounded * 10 - round($rounded * 10)) > 0.0001 ? 2 : 1;

        return number_format($rounded, $decimals, '.', '').'kg';
    }

    /**
     * 推定1RM・ボリューム：小数第1位で四捨五入して表示する（計算・保存は丸めずに行う）。
     */
    public static function oneDecimal(float|string|null $value): string
    {
        return $value === null ? '' : number_format(round((float) $value, 1), 1, '.', '');
    }

    /**
     * 最終トレーニング日：経過日数を併記する（例：11/10（3日前））。記録がなければ「記録なし」。
     */
    public static function lastTrained(?string $date): string
    {
        if ($date === null) {
            return '記録なし';
        }

        $day = CarbonImmutable::parse($date)->startOfDay();
        $days = (int) $day->diffInDays(CarbonImmutable::today(), true);

        return $day->format('n/j').'（'.($days === 0 ? '今日' : "{$days}日前").'）';
    }
}
