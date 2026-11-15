<?php

namespace App\Support;

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
}
