<?php

namespace App\Models;

use App\Support\Training\OneRepMax;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * セット。重量・レップ数・目標RIR・実績RIR だけが一括代入できる。
 * session_exercise_id・set_number・created_by・updated_by は処理の中で設定し、
 * 推定1RM・セットボリュームは保存時に計算する（Action・Seeder・Factory のどこから保存してもずれない）。
 */
#[Fillable(['weight', 'reps', 'target_rir', 'actual_rir'])]
class WorkoutSet extends Model
{
    /** @use HasFactory<WorkoutSetFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (WorkoutSet $set) {
            $weight = (float) $set->weight;

            $set->estimated_1rm = OneRepMax::estimate($weight, (int) $set->reps, (int) $set->actual_rir);
            $set->set_volume = OneRepMax::volume($weight, (int) $set->reps);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'set_volume' => 'decimal:2',
            'estimated_1rm' => 'float',
        ];
    }

    /**
     * @return BelongsTo<SessionExercise, $this>
     */
    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(SessionExercise::class);
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
