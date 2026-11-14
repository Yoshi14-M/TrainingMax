<?php

namespace App\Actions\Account;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class AddTrainer
{
    public function __construct(private readonly SendInitialPasswordMail $sendMail) {}

    /**
     * トレーナーの追加。role は trainer、gym_id は操作者（オーナー）のジムに固定し、パスワードは未設定にする。
     *
     * @param  array{name: string, email: string}  $data
     */
    public function handle(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $trainer = new User;
            $trainer->fill(['name' => $data['name'], 'email' => $data['email']]);
            $trainer->forceFill([
                'role' => UserRole::Trainer,
                'gym_id' => $actor->gym_id,
                'password' => null,
            ])->save();

            $this->sendMail->handle($trainer);

            Audit::log('trainer.added', ['actor_id' => $actor->id, 'target_id' => $trainer->id]);

            return $trainer;
        });
    }
}
