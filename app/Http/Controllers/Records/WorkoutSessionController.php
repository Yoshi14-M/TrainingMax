<?php

namespace App\Http\Controllers\Records;

use App\Actions\Workout\CreateWorkoutSession;
use App\Actions\Workout\DeleteWorkoutSession;
use App\Http\Requests\Records\NewWorkoutSessionRequest;
use App\Http\Requests\Records\StoreWorkoutSessionRequest;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Queries\LatestBodyWeightQuery;
use App\Queries\RecommendationQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * トレーニング記録のセッション（S-11。/clients/{client}/sessions）。
 */
class WorkoutSessionController extends RecordsController
{
    /**
     * 今日のセッションを開く。なければ作成の確認を表示する。
     */
    public function today(User $client): View|RedirectResponse
    {
        $session = $client->workoutSessions()->where('performed_on', now()->toDateString())->first();

        if ($session !== null) {
            return redirect()->route('records.sessions.show', [$client, $session]);
        }

        return view('records.sessions.today', ['client' => $client]);
    }

    /**
     * 「この日の記録を作成」の確認（カレンダーの日付から。今日・過去日のみ）。既にセッションがあればそれを開く。
     */
    public function create(NewWorkoutSessionRequest $request, User $client): View|RedirectResponse
    {
        $date = $request->validated('date');
        $session = $client->workoutSessions()->where('performed_on', $date)->first();

        if ($session !== null) {
            return redirect()->route('records.sessions.show', [$client, $session]);
        }

        return view('records.sessions.create', ['client' => $client, 'date' => CarbonImmutable::parse($date)]);
    }

    public function store(StoreWorkoutSessionRequest $request, User $client, CreateWorkoutSession $action): RedirectResponse
    {
        $session = $action->handle($request->user(), $client, $request->validated('performed_on'));

        return redirect()->route('records.sessions.show', [$client, $session]);
    }

    public function show(Request $request, User $client, WorkoutSession $workoutSession, RecommendationQuery $recommendations, LatestBodyWeightQuery $bodyWeights): View
    {
        $workoutSession->load([
            'sessionExercises' => fn ($query) => $query->orderBy('sort_order'),
            'sessionExercises.exercise.category',
            'sessionExercises.sets' => fn ($query) => $query->orderBy('set_number'),
            'sessionExercises.sets.creator',
            'sessionExercises.sets.updater',
        ]);

        $date = $workoutSession->performed_on->toDateString();

        // 選べる種目：対象の利用者に見える種目（非表示を除く）。スタッフが入力する場合も、利用者に見える種目で判定する
        $exercises = Exercise::query()->visibleTo($client)->selectable()->with(['bodyPart', 'category'])->get();

        $selected = null;
        $selectedSessionExercise = null;
        $candidates = [];

        if ($request->filled('exercise')) {
            $selected = $exercises->firstWhere('id', $request->integer('exercise'));

            // 見えない種目（他人の個人種目・他ジムの種目・非表示の種目）のIDは403
            abort_if($selected === null, 403);

            $selectedSessionExercise = $workoutSession->sessionExercises->firstWhere('exercise_id', $selected->id);
            $candidates = $recommendations->candidates($client, $selected, $workoutSession, $selectedSessionExercise);
        }

        $editingSet = null;

        if ($request->filled('edit')) {
            $editingSet = $workoutSession->workoutSets()->find($request->integer('edit'));
        }

        return view('records.sessions.show', [
            'client' => $client,
            'session' => $workoutSession,
            'sessionExercises' => $workoutSession->sessionExercises,
            'previousDate' => $client->workoutSessions()->where('performed_on', '<', $date)->max('performed_on'),
            'nextDate' => $client->workoutSessions()->where('performed_on', '>', $date)->min('performed_on'),
            'exercises' => $exercises,
            'selected' => $selected,
            'selectedSessionExercise' => $selectedSessionExercise,
            'candidates' => $candidates,
            'editingSet' => $editingSet,
            'bodyWeight' => $bodyWeights->handle($client, $workoutSession->performed_on),
        ]);
    }

    public function destroy(Request $request, User $client, WorkoutSession $workoutSession, DeleteWorkoutSession $action): RedirectResponse
    {
        $action->handle($request->user(), $workoutSession);

        return redirect()->route('records.sessions.today', $client)->with('status', 'セッションを削除しました。');
    }
}
