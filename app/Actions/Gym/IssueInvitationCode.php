<?php

namespace App\Actions\Gym;

use App\Models\InvitationCode;
use App\Models\User;
use App\Support\Audit;

class IssueInvitationCode
{
    /**
     * 招待コードの発行。安全な乱数（random_int）で8文字を作り、既存と重複したら作り直す。
     * gym_id・issued_by は操作者から決める（リクエストの値は使わない）。
     */
    public function handle(User $actor, ?string $memo = null): InvitationCode
    {
        do {
            $code = $this->generateCode();
        } while (InvitationCode::query()->where('code', $code)->exists());

        $invitation = new InvitationCode;
        $invitation->fill(['memo' => $memo]);
        $invitation->forceFill([
            'gym_id' => $actor->gym_id,
            'code' => $code,
            'issued_by' => $actor->id,
            'expires_at' => now()->addDays(config('trainingmax.invitation_code_expire_days')),
        ])->save();

        // コードの文字列は操作ログに出さない
        Audit::log('invitation_code.issued', ['actor_id' => $actor->id, 'invitation_code_id' => $invitation->id]);

        return $invitation;
    }

    private function generateCode(): string
    {
        $max = strlen(InvitationCode::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < InvitationCode::LENGTH; $i++) {
            $code .= InvitationCode::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
