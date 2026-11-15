<?php

namespace App\Models;

use Database\Factories\WorkoutSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * セット。推定1RM・セットボリュームの計算と保存は、トレーニング記録のイシュー（#7）で実装する。
 */
class WorkoutSet extends Model
{
    /** @use HasFactory<WorkoutSetFactory> */
    use HasFactory;

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
}
