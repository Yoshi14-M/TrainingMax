<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * role・gym_id・suspended_at・email_verified_at・password は一括代入できない。
 * 処理の中で forceFill() などにより明示的に設定する（セキュリティ設計書4.6）。
 */
#[Fillable(['name', 'email', 'height_cm', 'birth_date', 'gender'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'gender' => Gender::class,
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
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
     * @return HasMany<WorkoutSession, $this>
     */
    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    /**
     * @return HasMany<BodyComposition, $this>
     */
    public function bodyCompositions(): HasMany
    {
        return $this->hasMany(BodyComposition::class);
    }

    /**
     * 本人の個人種目。
     *
     * @return HasMany<Exercise, $this>
     */
    public function personalExercises(): HasMany
    {
        return $this->hasMany(Exercise::class, 'owner_user_id');
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [UserRole::Owner, UserRole::Trainer], true);
    }

    public function isTrainer(): bool
    {
        return $this->role === UserRole::Trainer;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * 有効なスタッフ（owner / trainer で、停止されていない）。
     */
    public function isActiveStaff(): bool
    {
        return $this->isStaff() && ! $this->isSuspended();
    }

    /**
     * 対象の利用者の記録にアクセスできるか（セキュリティ設計書3.2。すべての認可の土台）。
     *
     * ① 本人、または ② 同じジムに現在所属している有効なスタッフ。
     * 紐付けを解除した利用者は gym_id が NULL になるため、スタッフはアクセスできなくなる。
     */
    public function canAccessRecordsOf(User $client): bool
    {
        if (! $client->isClient()) {
            return false;
        }

        if ($this->is($client)) {
            return true;
        }

        return $this->isActiveStaff()
            && $this->gym_id !== null
            && $this->gym_id === $client->gym_id;
    }

    /**
     * 停止中のユーザーにはパスワードリセットのメールを送らない（セキュリティ設計書5.3）。
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->isSuspended()) {
            return;
        }

        parent::sendPasswordResetNotification($token);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeClients(Builder $query): void
    {
        $query->where('role', UserRole::Client);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeTrainers(Builder $query): void
    {
        $query->where('role', UserRole::Trainer);
    }
}
