<?php

namespace App\Http\Requests\Records;

use App\Actions\Workout\RecordWorkoutSet;
use App\Models\SessionExercise;
use App\Rules\VisibleExercise;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * セットの登録。認可は基底の RecordsController で済んでいる。
 * 種目は、対象の利用者（URL）に見える種目で、非表示でないこと。
 */
class StoreWorkoutSetRequest extends FormRequest
{
    use WorkoutSetInputRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exercise_id' => ['required', 'integer', new VisibleExercise($this->route('client'))],
            ...$this->setRules(),
        ];
    }

    /**
     * 件数の上限（1セッション30種目・1種目30セット）。Action でも、同時操作に備えて再確認する。
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('exercise_id')) {
                    return;
                }

                $session = $this->route('workoutSession');
                $sessionExercise = SessionExercise::query()
                    ->where('workout_session_id', $session->id)
                    ->where('exercise_id', $this->input('exercise_id'))
                    ->first();

                if ($sessionExercise === null) {
                    if ($session->sessionExercises()->count() >= RecordWorkoutSet::MAX_EXERCISES) {
                        $validator->errors()->add('exercise_id', '1セッションに追加できる種目は'.RecordWorkoutSet::MAX_EXERCISES.'種目までです。');
                    }
                } elseif ($sessionExercise->sets()->count() >= RecordWorkoutSet::MAX_SETS) {
                    $validator->errors()->add('exercise_id', '1種目に記録できるセットは'.RecordWorkoutSet::MAX_SETS.'セットまでです。');
                }
            },
        ];
    }
}
