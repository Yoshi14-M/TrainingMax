<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Models\UserSession;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuspendTrainer
{
    /**
     * トレーナーの停止。ログイン保持のトークンの作り直しと、ログイン中のセッションの削除もあわせて行う
     * （ミドルウェアによる次の操作でのログアウトと二重の対策。セキュリティ設計書5.3）。
     */
    public function handle(User $actor, User $trainer): void
    {
        DB::transaction(function () use ($actor, $trainer) {
            $trainer->forceFill([
                'suspended_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            UserSession::query()->where('user_id', $trainer->id)->delete();

            Audit::log('trainer.suspended', ['actor_id' => $actor->id, 'target_id' => $trainer->id]);
        });
    }
}
