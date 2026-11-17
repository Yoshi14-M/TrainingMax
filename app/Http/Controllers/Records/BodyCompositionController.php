<?php

namespace App\Http\Controllers\Records;

use App\Actions\Body\SaveBodyComposition;
use App\Http\Requests\Records\SaveBodyCompositionRequest;
use App\Models\BodyComposition;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 体組成の記録・履歴（S-12。/clients/{client}/body）。
 */
class BodyCompositionController extends RecordsController
{
    public function index(Request $request, User $client): View
    {
        $date = $this->selectedDate($request);

        return view('records.body.index', [
            'client' => $client,
            'date' => $date,
            // 既に記録がある日付を選ぶと、その記録の編集になる
            'editing' => $client->bodyCompositions()->where('measured_on', $date)->first(),
            'history' => $client->bodyCompositions()
                ->with(['creator', 'updater'])
                ->orderByDesc('measured_on')
                ->paginate(20),
        ]);
    }

    public function store(SaveBodyCompositionRequest $request, User $client, SaveBodyComposition $action): RedirectResponse
    {
        $composition = $action->handle($request->user(), $client, $request->validated());

        return redirect(route('records.body.index', $client).'?date='.$composition->measured_on->toDateString())
            ->with('status', $composition->wasRecentlyCreated ? '体組成を記録しました。' : '体組成を更新しました。');
    }

    public function destroy(User $client, BodyComposition $bodyComposition): RedirectResponse
    {
        $bodyComposition->delete();

        return redirect()->route('records.body.index', $client)->with('status', '体組成の記録を削除しました。');
    }

    /**
     * 表示する測定日（URLの ?date=）。不正な値や範囲外（2000-01-01〜今日の外）は、操作を止めず今日にする。
     */
    private function selectedDate(Request $request): string
    {
        $value = $request->query('date');
        $today = now()->toDateString();

        $valid = is_string($value)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            && $value >= '2000-01-01'
            && $value <= $today;

        return $valid ? $value : $today;
    }
}
