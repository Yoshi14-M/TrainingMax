<?php

namespace App\Queries;

use App\Models\BodyComposition;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * 「自重」ボタンに入れる、直近の体重（セッション日以前で、体重が記録されている最新の体組成。データ設計書4.10）。
 */
class LatestBodyWeightQuery
{
    /**
     * @return array{weight: float, measured_on: Carbon}|null 体重の記録がなければ null
     */
    public function handle(User $client, Carbon $sessionDate): ?array
    {
        $record = BodyComposition::query()
            ->where('user_id', $client->id)
            ->where('measured_on', '<=', $sessionDate->toDateString())
            ->whereNotNull('weight_kg')
            ->orderByDesc('measured_on')
            ->first();

        return $record === null ? null : ['weight' => (float) $record->weight_kg, 'measured_on' => $record->measured_on];
    }
}
