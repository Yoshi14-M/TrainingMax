<?php

namespace App\Support\Training;

/**
 * 推定1RM・推奨重量・ボリュームの計算（DBを使わない純粋な計算。Unitテストの対象）。
 *
 * 推定1RM（Epley式・実績RIR使用） ＝ 重量 ×（1 ＋（実績レップ数 ＋ 実績RIR）÷ 30）
 * 推奨重量 ＝ 基準の推定1RM ÷（1 ＋（推奨レップ数の最小値 ＋ 目標RIR）÷ 30） を、重量の刻みで切り下げる
 */
class OneRepMax
{
    /**
     * 目標RIRの範囲（推奨重量の表を作る範囲）。
     */
    public const RIR_MIN = 0;

    public const RIR_MAX = 10;

    /**
     * 推定1RM（丸めずに返す）。
     */
    public static function estimate(float $weight, int $reps, int $actualRir): float
    {
        return $weight * (1 + ($reps + $actualRir) / 30);
    }

    /**
     * セットボリューム（重量 × レップ数）。
     */
    public static function volume(float $weight, int $reps): float
    {
        return $weight * $reps;
    }

    /**
     * 推奨重量（基準の推定1RMからの逆算を、刻みで切り下げた値）。
     */
    public static function recommend(float $baseEstimate, int $minReps, int $targetRir, float $step): float
    {
        $raw = $baseEstimate / (1 + ($minReps + $targetRir) / 30);

        return self::floorToStep($raw, $step);
    }

    /**
     * 刻みごと（config の weight_steps）の、目標RIR 0〜10 の推奨重量の一覧。
     * 刻みは種目ではなく記録画面で切り替えるため、全刻み分をサーバーで計算して画面に渡す（計算式をPHPとJavaScriptの2か所に書かない）。
     *
     * @return array<string, array<int, float>> 刻み（文字列。例：'2.5'）→ 目標RIR → 推奨重量
     */
    public static function recommendTables(float $baseEstimate, int $minReps): array
    {
        $tables = [];

        foreach (config('trainingmax.weight_steps') as $step) {
            for ($rir = self::RIR_MIN; $rir <= self::RIR_MAX; $rir++) {
                $tables[(string) $step][$rir] = self::recommend($baseEstimate, $minReps, $rir, (float) $step);
            }
        }

        return $tables;
    }

    /**
     * 刻みで切り下げる。浮動小数点の誤差（例：62.5 が 62.4999… になる）を避けるため、
     * 0.01kg単位の整数に直してから計算する。
     */
    public static function floorToStep(float $value, float $step): float
    {
        $stepCents = (int) round($step * 100);

        if ($stepCents <= 0) {
            return $value;
        }

        // 誤差を除いてから0.01kg単位に切り下げる
        $cents = (int) floor(round($value * 100, 6));

        return ($cents - $cents % $stepCents) / 100;
    }
}
