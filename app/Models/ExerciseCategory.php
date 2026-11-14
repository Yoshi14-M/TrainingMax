<?php

namespace App\Models;

use Database\Factories\ExerciseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 種目分類マスタ（推奨レップ数・RIR・休憩時間。画面からの変更はしない）。
 */
#[Fillable(['name', 'rep_min', 'rep_max', 'rir_min', 'rir_max', 'rest_seconds', 'sort_order'])]
class ExerciseCategory extends Model
{
    /** @use HasFactory<ExerciseCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
