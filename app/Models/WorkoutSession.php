<?php

namespace App\Models;

use Database\Factories\WorkoutSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * トレーニングのセッション（1日分。1日1件）。user_id・created_by は一括代入できない。
 */
class WorkoutSession extends Model
{
    /** @use HasFactory<WorkoutSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date:Y-m-d',
        ];
    }

    /**
     * URLでは、IDではなく実施日（YYYY-MM-DD）でセッションを表す（/clients/15/sessions/2026-11-13）。
     */
    public function getRouteKeyName(): string
    {
        return 'performed_on';
    }

    public function getRouteKey(): string
    {
        return $this->performed_on->toDateString();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SessionExercise, $this>
     */
    public function sessionExercises(): HasMany
    {
        return $this->hasMany(SessionExercise::class);
    }

    /**
     * セッションの全セット（session_exercises 経由）。
     *
     * @return HasManyThrough<WorkoutSet, SessionExercise, $this>
     */
    public function workoutSets(): HasManyThrough
    {
        return $this->hasManyThrough(WorkoutSet::class, SessionExercise::class);
    }
}
