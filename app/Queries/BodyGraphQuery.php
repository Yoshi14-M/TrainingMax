<?php

namespace App\Queries;

use App\Models\BodyComposition;
use App\Models\User;
use App\Support\Graph\GraphPeriod;

/**
 * 体組成グラフ（S-14）のデータ。体重・体脂肪率・筋肉量・周囲径7項目を、ひとつのグラフに重ねる（機能設計書F-13）。
 * その項目が入力された日だけを点にする（入力のない日は null）。
 *
 * 返す形：['labels' => 測定日（YYYY-MM-DD）の昇順, 'datasets' => 項目ごとの値]
 */
class BodyGraphQuery
{
    /**
     * 色（項目の並び順）。
     *
     * @var array<string, string>
     */
    private const COLORS = [
        'weight_kg' => '#2563eb',
        'body_fat_percent' => '#dc2626',
        'muscle_mass_kg' => '#16a34a',
        'waist_cm' => '#f59e0b',
        'chest_cm' => '#8b5cf6',
        'upper_arm_cm' => '#0891b2',
        'thigh_cm' => '#db2777',
        'hip_cm' => '#65a30d',
        'calf_cm' => '#ea580c',
        'neck_cm' => '#475569',
    ];

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function series(User $client, GraphPeriod $period): array
    {
        $records = $client->bodyCompositions()
            ->when($period->from !== null, fn ($query) => $query->where('measured_on', '>=', $period->from->toDateString()))
            ->when($period->to !== null, fn ($query) => $query->where('measured_on', '<=', $period->to->toDateString()))
            ->orderBy('measured_on')
            ->get();

        $datasets = [];

        foreach ([...BodyComposition::MAIN_FIELDS, ...BodyComposition::GIRTH_FIELDS] as $field => $definition) {
            $datasets[] = [
                'key' => $field,
                'label' => $definition['label'],
                'unit' => $definition['unit'],
                // 左の縦軸＝kg（体重・筋肉量）、右の縦軸＝%・cm（体脂肪率・周囲径）
                'axis' => $definition['unit'] === 'kg' ? 'left' : 'right',
                'color' => self::COLORS[$field],
                // 初期表示は体重・体脂肪率・筋肉量。周囲径は凡例で表示する
                'hidden' => array_key_exists($field, BodyComposition::GIRTH_FIELDS),
                'data' => $records->map(fn (BodyComposition $record) => $record->{$field} === null ? null : round((float) $record->{$field}, 1))->all(),
            ];
        }

        return [
            'labels' => $records->map(fn (BodyComposition $record) => $record->measured_on->toDateString())->all(),
            'datasets' => $datasets,
        ];
    }
}
