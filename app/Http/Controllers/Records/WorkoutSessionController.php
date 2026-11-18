<?php

namespace App\Http\Controllers\Records;

use App\Actions\Workout\CreateWorkoutSession;
use App\Actions\Workout\DeleteWorkoutSession;
use App\Enums\CoachingNoteCategory;
use App\Http\Requests\Records\NewWorkoutSessionRequest;
use App\Http\Requests\Records\StoreWorkoutSessionRequest;
use App\Models\CoachingNote;
use App\Models\Exercise;
use App\Models\SessionComment;
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

        $user = $request->user();

        // 指導メモの要約はスタッフのときだけ読み込む（利用者本人には、読み込み自体をしない。セキュリティ設計書4.4）
        $notes = $user->can('viewAny', [CoachingNote::class, $client])
            ? $client->coachingNotes()
                ->where('gym_id', $user->gym_id)
                ->whereIn('category', collect(CoachingNoteCategory::cases())->filter->showsOnSession()->map->value->all())
                ->with(['creator', 'updater'])
                ->orderBy('category')->latest('updated_at')->latest('id')
                ->get()
            : null;

        // コメント（スレッド）：最初のコメントを古い順に、それぞれの返信を古い順に表示する
        $comments = $workoutSession->comments()
            ->whereNull('parent_id')
            ->with(['author', 'replies' => fn ($query) => $query->with('author')->orderBy('created_at')->orderBy('id')])
            ->orderBy('created_at')->orderBy('id')
            ->get();

        // 認可の判定で、コメントごとにセッションと利用者を読み直さないよう、読み込み済みのものを渡す
        $workoutSession->setRelation('user', $client);
        $all = $comments->flatMap(fn (SessionComment $comment) => collect([$comment])->concat($comment->replies));
        $all->each(fn (SessionComment $comment) => $comment->setRelation('workoutSession', $workoutSession));

        // 編集できるのは書いた本人だけ（他の人のコメントの編集画面は出さない）
        $editingComment = $request->filled('edit-comment') ? $all->firstWhere('id', $request->integer('edit-comment')) : null;
        $editingComment = $editingComment !== null && $user->can('update', $editingComment) ? $editingComment : null;
        // 返信中のコメント（返信の入力エラーで戻ったとき、その欄を開いて入力値を保つ）
        $replyingTo = $request->filled('reply-to') ? $comments->firstWhere('id', $request->integer('reply-to')) : null;

        return view('records.sessions.show', [
            'client' => $client,
            'notes' => $notes,
            'comments' => $comments,
            'editingComment' => $editingComment,
            'replyingTo' => $replyingTo,
            'canComment' => $user->can('create', [SessionComment::class, $workoutSession]),
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
