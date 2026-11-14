<?php

namespace App\Models;

use Database\Factories\BodyPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 部位マスタ（画面からの追加・編集はしない）。
 */
#[Fillable(['name', 'sort_order'])]
class BodyPart extends Model
{
    /** @use HasFactory<BodyPartFactory> */
    use HasFactory;

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
