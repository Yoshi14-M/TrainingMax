<?php

namespace App\Models;

use App\Enums\ExerciseScope;
use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 種目（デフォルト／ジム共通／個人）。
 * scope・gym_id・owner_user_id は一括代入できない。操作者のロールから処理の中で設定する（セキュリティ設計書4.3）。
 */
#[Fillable(['body_part_id', 'exercise_category_id', 'name', 'weight_step', 'is_hidden'])]
class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ExerciseScope::class,
            'weight_step' => 'decimal:2',
            'is_hidden' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BodyPart, $this>
     */
    public function bodyPart(): BelongsTo
    {
        return $this->belongsTo(BodyPart::class);
    }

    /**
     * @return BelongsTo<ExerciseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExerciseCategory::class, 'exercise_category_id');
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * 対象の利用者に見える種目（デフォルト、所属ジムの共通種目、本人の個人種目）。
     *
     * @param  Builder<Exercise>  $query
     */
    public function scopeVisibleTo(Builder $query, User $client): void
    {
        $query->where(function (Builder $query) use ($client) {
            $query->where('scope', ExerciseScope::Default)
                ->orWhere(function (Builder $query) use ($client) {
                    $query->where('scope', ExerciseScope::Personal)
                        ->where('owner_user_id', $client->id);
                });

            if ($client->gym_id !== null) {
                $query->orWhere(function (Builder $query) use ($client) {
                    $query->where('scope', ExerciseScope::Gym)
                        ->where('gym_id', $client->gym_id);
                });
            }
        });
    }

    /**
     * スタッフに見える種目（デフォルト、自分のジムの共通種目）。個人種目は含まない。
     *
     * @param  Builder<Exercise>  $query
     */
    public function scopeVisibleToStaff(Builder $query, User $staff): void
    {
        $query->where(function (Builder $query) use ($staff) {
            $query->where('scope', ExerciseScope::Default);

            if ($staff->gym_id !== null) {
                $query->orWhere(function (Builder $query) use ($staff) {
                    $query->where('scope', ExerciseScope::Gym)
                        ->where('gym_id', $staff->gym_id);
                });
            }
        });
    }

    /**
     * 種目選択に出せる種目（非表示を除く）。
     *
     * @param  Builder<Exercise>  $query
     */
    public function scopeSelectable(Builder $query): void
    {
        $query->where('is_hidden', false);
    }
}
