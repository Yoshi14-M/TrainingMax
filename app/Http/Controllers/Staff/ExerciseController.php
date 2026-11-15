<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Exercise\RemoveExercise;
use App\Enums\ExerciseScope;
use App\Http\Requests\Staff\StoreGymExerciseRequest;
use App\Http\Requests\Staff\UpdateGymExerciseRequest;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Queries\ExerciseListQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ジム共通種目の管理（S-15 のスタッフ用。/staff/exercises）。
 * 利用者の個人種目は、記録画面の種目選択と /clients/{client}/exercises（閲覧のみ）で見る。
 */
class ExerciseController extends StaffController
{
    public function index(Request $request, ExerciseListQuery $list): View
    {
        // デフォルト種目と自分のジムの共通種目（非表示にしたジム共通種目も表示する）
        $query = Exercise::query()
            ->visibleToStaff($request->user())
            ->where(fn (Builder $query) => $query->where('is_hidden', false)->orWhere('scope', ExerciseScope::Gym));

        return view('exercises.index', [
            'title' => 'ジム共通種目の管理',
            'groups' => $list->groups($query),
            'parts' => BodyPart::query()->orderBy('sort_order')->get(),
            'categories' => ExerciseCategory::query()->orderBy('sort_order')->get(),
            'canCreate' => $request->user()->can('createGym', Exercise::class),
            'storeUrl' => route('staff.exercises.store'),
            'updateUrl' => fn (Exercise $exercise) => route('staff.exercises.update', $exercise),
            'destroyUrl' => fn (Exercise $exercise) => route('staff.exercises.destroy', $exercise),
            'indexUrl' => route('staff.exercises.index'),
            'editingId' => $request->integer('edit') ?: null,
        ]);
    }

    public function store(StoreGymExerciseRequest $request): RedirectResponse
    {
        $exercise = new Exercise;
        $exercise->fill($request->validated());
        // scope・gym_id は操作者のロール・ジムから決める（リクエストの値は使わない）
        $exercise->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => $request->user()->gym_id])->save();

        return redirect()->route('staff.exercises.index')->with('status', 'ジム共通種目を登録しました。')->with('keep_form_open', true);
    }

    public function update(UpdateGymExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        $exercise->fill($request->validated())->save();

        return redirect(route('staff.exercises.index').'#exercise-'.$exercise->id)->with('status', '種目を更新しました。');
    }

    public function destroy(Exercise $exercise, RemoveExercise $action): RedirectResponse
    {
        $this->authorize('delete', $exercise);

        $deleted = $action->handle($exercise);

        return redirect()->route('staff.exercises.index')->with(
            'status',
            $deleted ? '種目を削除しました。' : '記録で使われているため、削除せず非表示にしました。',
        );
    }
}
