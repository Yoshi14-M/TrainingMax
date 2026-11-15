<?php

namespace App\Actions\Gym;

use App\Enums\ExerciseScope;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UnlinkClientFromGym
{
    /**
     * ジムとの紐付けを解除する（データ設計書5.3）。記録は利用者に残る。
     *
     * 記録に使ったジム共通種目は、本人の個人種目にして記録の参照先を付け替える（推移を途切れさせないため）。
     * - 同じ名前の個人種目を本人が持っている場合：コピーせず、その個人種目にまとめる（分類・刻みは個人種目側）
     * - 持っていない場合：ジム共通種目を個人種目としてコピーする
     * 同じセッションにジム共通種目と同名の個人種目の両方の記録がある場合は、セットを個人種目側の末尾に移す。
     */
    public function handle(User $actor, User $client): void
    {
        DB::transaction(function () use ($actor, $client) {
            $client = User::query()->lockForUpdate()->findOrFail($client->id);

            $copied = 0;
            $merged = 0;

            foreach ($this->usedGymExercises($client) as $gymExercise) {
                $personal = $client->personalExercises()->where('name', $gymExercise->name)->first();

                if ($personal === null) {
                    $personal = $this->copyToPersonal($client, $gymExercise);
                    $copied++;
                } else {
                    $merged++;
                }

                $this->repoint($client, $gymExercise, $personal);
            }

            $client->forceFill(['gym_id' => null])->save();

            Audit::log('client.unlinked', [
                'actor_id' => $actor->id,
                'client_id' => $client->id,
                'copied' => $copied,
                'merged' => $merged,
            ]);
        });
    }

    /**
     * 利用者の記録で使われているジム共通種目。
     *
     * @return Collection<int, Exercise>
     */
    private function usedGymExercises(User $client)
    {
        return Exercise::query()
            ->where('scope', ExerciseScope::Gym)
            ->whereHas('sessionExercises.workoutSession', fn ($query) => $query->where('user_id', $client->id))
            ->get();
    }

    private function copyToPersonal(User $client, Exercise $gymExercise): Exercise
    {
        $personal = new Exercise;
        $personal->fill([
            'body_part_id' => $gymExercise->body_part_id,
            'exercise_category_id' => $gymExercise->exercise_category_id,
            'name' => $gymExercise->name,
            'weight_step' => $gymExercise->weight_step,
            'is_hidden' => $gymExercise->is_hidden,
        ]);
        $personal->forceFill([
            'scope' => ExerciseScope::Personal,
            'gym_id' => null,
            'owner_user_id' => $client->id,
        ])->save();

        return $personal;
    }

    /**
     * 利用者の記録の参照先を、ジム共通種目から個人種目に付け替える。
     */
    private function repoint(User $client, Exercise $gymExercise, Exercise $personal): void
    {
        $sessionExercises = SessionExercise::query()
            ->where('exercise_id', $gymExercise->id)
            ->whereHas('workoutSession', fn ($query) => $query->where('user_id', $client->id))
            ->lockForUpdate()
            ->get();

        foreach ($sessionExercises as $sessionExercise) {
            $existing = SessionExercise::query()
                ->where('workout_session_id', $sessionExercise->workout_session_id)
                ->where('exercise_id', $personal->id)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                $sessionExercise->forceFill(['exercise_id' => $personal->id])->save();

                continue;
            }

            // 同じセッションに両方ある：セットを個人種目側の末尾に移し、空になった側を削除する
            $nextNumber = (int) $existing->sets()->max('set_number');

            foreach ($sessionExercise->sets()->orderBy('set_number')->get() as $set) {
                $set->forceFill([
                    'session_exercise_id' => $existing->id,
                    'set_number' => ++$nextNumber,
                ])->save();
            }

            $sessionExercise->delete();
        }
    }
}
