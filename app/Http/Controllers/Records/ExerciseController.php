<?php

namespace App\Http\Controllers\Records;

use App\Actions\Exercise\RemoveExercise;
use App\Enums\ExerciseScope;
use App\Http\Requests\Records\StorePersonalExerciseRequest;
use App\Http\Requests\Records\UpdatePersonalExerciseRequest;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\User;
use App\Queries\ExerciseListQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 種目一覧・個人種目の登録・編集・削除（S-15。/clients/{client}/exercises）。
 * スタッフは閲覧のみ（登録・編集・削除は本人だけ）。
 */
class ExerciseController extends RecordsController
{
    public function index(Request $request, User $client, ExerciseListQuery $list): View
    {
        $own = $request->user()->is($client);

        $query = Exercise::query()->visibleTo($client);

        if ($own) {
            // 本人には、非表示にした個人種目も表示する（再表示できるように）
            $query->where(fn (Builder $query) => $query->where('is_hidden', false)->orWhere('scope', ExerciseScope::Personal));
        } else {
            $query->selectable();
        }

        return view('exercises.index', [
            'title' => $own ? '種目' : "{$client->name}さんの種目",
            'groups' => $list->groups($query),
            'parts' => BodyPart::query()->orderBy('sort_order')->get(),
            'categories' => ExerciseCategory::query()->orderBy('sort_order')->get(),
            'canCreate' => $request->user()->can('createPersonal', [Exercise::class, $client]),
            'storeUrl' => route('records.exercises.store', $client),
            'updateUrl' => fn (Exercise $exercise) => route('records.exercises.update', [$client, $exercise]),
            'destroyUrl' => fn (Exercise $exercise) => route('records.exercises.destroy', [$client, $exercise]),
            'indexUrl' => route('records.exercises.index', $client),
            'editingId' => $request->integer('edit') ?: null,
        ]);
    }

    public function store(StorePersonalExerciseRequest $request, User $client): RedirectResponse
    {
        $exercise = new Exercise;
        $exercise->fill($request->validated());
        // scope・owner_user_id は操作者のロールから決める（リクエストの値は使わない）
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $client->id])->save();

        return redirect()->route('records.exercises.index', $client)->with('status', '種目を登録しました。')->with('keep_form_open', true);
    }

    public function update(UpdatePersonalExerciseRequest $request, User $client, Exercise $personalExercise): RedirectResponse
    {
        $personalExercise->fill($request->validated())->save();

        return redirect(route('records.exercises.index', $client).'#exercise-'.$personalExercise->id)->with('status', '種目を更新しました。');
    }

    public function destroy(Request $request, User $client, Exercise $personalExercise, RemoveExercise $action): RedirectResponse
    {
        $this->authorize('delete', $personalExercise);

        $deleted = $action->handle($personalExercise);

        return redirect()->route('records.exercises.index', $client)->with(
            'status',
            $deleted ? '種目を削除しました。' : '記録で使われているため、削除せず非表示にしました。',
        );
    }
}
