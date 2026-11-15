<?php

namespace App\Actions\Gym;

use App\Models\InvitationCode;
use App\Models\User;
use App\Support\Audit;

class RevokeInvitationCode
{
    /**
     * 未使用の招待コードを取り消す。
     */
    public function handle(User $actor, InvitationCode $invitation): void
    {
        $invitation->forceFill(['revoked_at' => now()])->save();

        Audit::log('invitation_code.revoked', ['actor_id' => $actor->id, 'invitation_code_id' => $invitation->id]);
    }
}
