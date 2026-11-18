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

    /**
     * 推奨レップ数の表示（例：6〜8回）。
     */
    public function repsLabel(): string
    {
        return $this->range($this->rep_min, $this->rep_max).'回';
    }

    /**
     * 推奨RIRの表示（例：1〜2、0）。
     */
    public function rirLabel(): string
    {
        return 'RIR '.$this->range($this->rir_min, $this->rir_max);
    }

    /**
     * 推奨休憩時間の表示（例：3分、90秒）。
     */
    public function restLabel(): string
    {
        if ($this->rest_seconds >= 60 && $this->rest_seconds % 60 === 0) {
            return intdiv($this->rest_seconds, 60).'分';
        }

        return $this->rest_seconds.'秒';
    }

    /**
     * 分類名から、解説する用語のキー（lang/ja/glossary.php）を返す。コンパウンド／アイソレートでなければ null。
     */
    public function termKey(): ?string
    {
        return match (true) {
            str_contains($this->name, 'コンパウンド') => 'compound',
            str_contains($this->name, 'アイソレート') => 'isolation',
            default => null,
        };
    }

    private function range(int $min, int $max): string
    {
        return $min === $max ? (string) $min : "{$min}〜{$max}";
    }
}
