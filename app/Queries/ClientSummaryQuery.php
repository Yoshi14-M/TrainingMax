<?php

namespace App\Queries;

use App\Models\BodyComposition;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * 利用者詳細（S-21）の、最終トレーニング日と最新の体重・体脂肪率。
 */
class ClientSummaryQuery
{
    /**
     * @return array{last_trained_on: CarbonImmutable|null, weight: BodyComposition|null, body_fat: BodyComposition|null}
     */
    public function handle(User $client): array
    {
        $lastTrained = $client->workoutSessions()->max('performed_on');

        return [
            'last_trained_on' => $lastTrained === null ? null : CarbonImmutable::parse($lastTrained),
            // 値が入っている最新の記録（体重だけ・体脂肪率だけの記録があるため、項目ごとに探す）
            'weight' => $this->latest($client, 'weight_kg'),
            'body_fat' => $this->latest($client, 'body_fat_percent'),
        ];
    }

    private function latest(User $client, string $column): ?BodyComposition
    {
        return $client->bodyCompositions()->whereNotNull($column)->orderByDesc('measured_on')->first();
    }
}
