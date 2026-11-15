<?php

namespace App\Actions\Gym;

use App\Exceptions\InvalidInvitationCodeException;
use App\Models\InvitationCode;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class LinkClientToGym
{
    /**
     * 招待コードで個人利用者をジムに紐付ける（データ設計書5.2）。
     * コードを行ロックして再確認し、使用済みにして users.gym_id を設定する。
     * 個人利用時代の記録は user_id で利用者に紐付いているため、gym_id の設定だけでスタッフから見えるようになる。
     *
     * @throws InvalidInvitationCodeException コードが使えない、または利用者が既にジム所属の場合
     */
    public function handle(User $client, int $invitationCodeId): void
    {
        DB::transaction(function () use ($client, $invitationCodeId) {
            $invitation = InvitationCode::query()->lockForUpdate()->find($invitationCodeId);
            $client = User::query()->lockForUpdate()->findOrFail($client->id);

            if ($invitation === null || ! $invitation->isUsable() || $client->gym_id !== null) {
                throw new InvalidInvitationCodeException;
            }

            $invitation->forceFill(['used_by' => $client->id, 'used_at' => now()])->save();
            $client->forceFill(['gym_id' => $invitation->gym_id])->save();

            Audit::log('client.linked', ['client_id' => $client->id, 'invitation_code_id' => $invitation->id]);
        });
    }
}
