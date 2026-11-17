<?php

namespace App\Models;

use Database\Factories\BodyCompositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 体組成の記録（1日1件）。user_id・created_by・updated_by は一括代入できない（処理の中で設定する）。
 */
#[Fillable([
    'measured_on', 'weight_kg', 'body_fat_percent', 'muscle_mass_kg',
    'waist_cm', 'chest_cm', 'upper_arm_cm', 'thigh_cm', 'hip_cm', 'calf_cm', 'neck_cm',
])]
class BodyComposition extends Model
{
    /** @use HasFactory<BodyCompositionFactory> */
    use HasFactory;

    /**
     * 画面に表示する項目（フォームの名前 => 表示名・単位）。周囲径は7項目で、左右は分けない。
     *
     * @var array<string, array{label: string, unit: string}>
     */
    public const MAIN_FIELDS = [
        'weight_kg' => ['label' => '体重', 'unit' => 'kg'],
        'body_fat_percent' => ['label' => '体脂肪率', 'unit' => '%'],
        'muscle_mass_kg' => ['label' => '筋肉量', 'unit' => 'kg'],
    ];

    /**
     * @var array<string, array{label: string, unit: string}>
     */
    public const GIRTH_FIELDS = [
        'waist_cm' => ['label' => 'ウエスト', 'unit' => 'cm'],
        'chest_cm' => ['label' => '胸囲', 'unit' => 'cm'],
        'upper_arm_cm' => ['label' => '腕囲（上腕）', 'unit' => 'cm'],
        'thigh_cm' => ['label' => '太もも囲', 'unit' => 'cm'],
        'hip_cm' => ['label' => 'ヒップ', 'unit' => 'cm'],
        'calf_cm' => ['label' => 'ふくらはぎ', 'unit' => 'cm'],
        'neck_cm' => ['label' => '首', 'unit' => 'cm'],
    ];

    /**
     * 項目ごとの入力範囲（セキュリティ設計書6.7）。いずれも小数第1位まで。
     *
     * @var array<string, array{0: float, 1: float}>
     */
    public const RANGES = [
        'weight_kg' => [20.0, 300.0],
        'body_fat_percent' => [1.0, 70.0],
        'muscle_mass_kg' => [10.0, 150.0],
        'waist_cm' => [10.0, 250.0],
        'chest_cm' => [10.0, 250.0],
        'upper_arm_cm' => [10.0, 250.0],
        'thigh_cm' => [10.0, 250.0],
        'hip_cm' => [10.0, 250.0],
        'calf_cm' => [10.0, 250.0],
        'neck_cm' => [10.0, 250.0],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // date の既定のキャストは SQLite で時刻付きの文字列を保存してしまうため、日付の形式を指定する
            'measured_on' => 'date:Y-m-d',
            'weight_kg' => 'decimal:1',
            'body_fat_percent' => 'decimal:1',
            'muscle_mass_kg' => 'decimal:1',
            'waist_cm' => 'decimal:1',
            'chest_cm' => 'decimal:1',
            'upper_arm_cm' => 'decimal:1',
            'thigh_cm' => 'decimal:1',
            'hip_cm' => 'decimal:1',
            'calf_cm' => 'decimal:1',
            'neck_cm' => 'decimal:1',
        ];
    }

    /**
     * 記録の持ち主（利用者）。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 入力者。
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 最終更新者。
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
