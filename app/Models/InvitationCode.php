<?php

namespace App\Models;

use App\Enums\InvitationCodeStatus;
use Database\Factories\InvitationCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 招待コード。状態は列に持たず、日時から判定する（データ設計書4.3）。
 * gym_id・code・issued_by・used_by などは一括代入できない（Action の中で設定する）。
 */
#[Fillable(['memo'])]
class InvitationCode extends Model
{
    /** @use HasFactory<InvitationCodeFactory> */
    use HasFactory;

    /**
     * 招待コードに使う文字（英大文字のI・Oを除く24文字 ＋ 数字2〜9の8文字 ＝ 32文字）。
     */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
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
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * 状態（上から順に判定：使用済み → 取消 → 期限切れ → 未使用）。
     */
    public function status(): InvitationCodeStatus
    {
        return match (true) {
            $this->used_at !== null => InvitationCodeStatus::Used,
            $this->revoked_at !== null => InvitationCodeStatus::Revoked,
            $this->expires_at->isPast() => InvitationCodeStatus::Expired,
            default => InvitationCodeStatus::Unused,
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === InvitationCodeStatus::Unused;
    }

    /**
     * 使える招待コード（未使用・未取消・期限内）。
     *
     * @param  Builder<InvitationCode>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }
}
