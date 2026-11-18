<?php

namespace App\Models;

use Database\Factories\SessionCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * セッションコメント。スタッフが書くコメントと、利用者本人のトレーニングメモを兼ね、どちらにも返信（1段階）できる。
 * workout_session_id・parent_id・created_by は一括代入できない（処理の中で設定する）。
 */
#[Fillable(['body'])]
class SessionComment extends Model
{
    /** @use HasFactory<SessionCommentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WorkoutSession, $this>
     */
    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    /**
     * 返信先のコメント（返信のとき）。
     *
     * @return BelongsTo<SessionComment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SessionComment::class, 'parent_id');
    }

    /**
     * このコメントへの返信。
     *
     * @return HasMany<SessionComment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(SessionComment::class, 'parent_id');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * 書いた人（スタッフ、または利用者本人）。
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
