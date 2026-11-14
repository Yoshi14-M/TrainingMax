<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class ResumeTrainer
{
    /**
     * トレーナーの停止の解除。再び、ログインできるようになる。
     */
    public function handle(User $actor, User $trainer): void
    {
        DB::transaction(function () use ($actor, $trainer) {
            $trainer->forceFill(['suspended_at' => null])->save();

            Audit::log('trainer.resumed', ['actor_id' => $actor->id, 'target_id' => $trainer->id]);
        });
    }
}
