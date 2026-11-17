<?php

namespace App\Actions\Body;

use App\Models\BodyComposition;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class SaveBodyComposition
{
    /**
     * 体組成を保存する。同じ日付の記録があれば更新し、なければ作成する（1日1件）。
     * user_id は対象の利用者（URL）から、入力者・最終更新者は操作者から決める（リクエストの値は使わない）。
     * 更新では、送信された値で全項目を置き換える（空にした項目は NULL になる）。
     * 同時に2回送信されて一意制約（user_id, measured_on）に引っかかった場合は、既にある記録を更新する。
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, User $client, array $data): BodyComposition
    {
        $existing = $client->bodyCompositions()->where('measured_on', $data['measured_on'])->first();

        if ($existing !== null) {
            return $this->update($actor, $existing, $data);
        }

        try {
            $composition = new BodyComposition;
            $composition->fill($data);
            $composition->forceFill([
                'user_id' => $client->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            return $composition;
        } catch (UniqueConstraintViolationException) {
            return $this->update($actor, $client->bodyCompositions()->where('measured_on', $data['measured_on'])->firstOrFail(), $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function update(User $actor, BodyComposition $composition, array $data): BodyComposition
    {
        // 送信されなかった項目も NULL にして、フォームの内容どおりに置き換える
        $values = array_fill_keys(array_keys(BodyComposition::RANGES), null);

        $composition->fill([...$values, ...$data]);
        $composition->forceFill(['updated_by' => $actor->id])->save();

        return $composition;
    }
}
