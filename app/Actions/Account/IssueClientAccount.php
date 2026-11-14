<?php

namespace App\Actions\Account;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class IssueClientAccount
{
    public function __construct(private readonly SendInitialPasswordMail $sendMail) {}

    /**
     * 利用者アカウントの発行。role は client、gym_id は操作者のジムに固定し、パスワードは未設定にする。
     * メールの送信に失敗した場合は、作成も取り消す。
     *
     * @param  array{name: string, email: string}  $data
     */
    public function handle(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $client = new User;
            $client->fill(['name' => $data['name'], 'email' => $data['email']]);
            $client->forceFill([
                'role' => UserRole::Client,
                'gym_id' => $actor->gym_id,
                'password' => null,
            ])->save();

            $this->sendMail->handle($client);

            Audit::log('client.issued', ['actor_id' => $actor->id, 'client_id' => $client->id]);

            return $client;
        });
    }
}
