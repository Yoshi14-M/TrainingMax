<?php

namespace App\Http\Controllers\Records;

use App\Http\Requests\Records\TrainingGraphRequest;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\User;
use App\Queries\TrainingGraphQuery;
use App\Support\Graph\GraphPeriod;
use Illuminate\Contracts\View\View;

/**
 * トレーニンググラフ（S-13。/clients/{client}/graphs/training）。
 * 部位だけを選んだ場合はその部位の合計（セット数・種目数・総ボリューム）、種目まで選んだ場合は種目の指標を、
 * ひとつのグラフに重ねて表示する。
 */
class TrainingGraphController extends RecordsController
{
    public function __invoke(TrainingGraphRequest $request, User $client, TrainingGraphQuery $query): View
    {
        $period = $request->graphPeriod();
        $parts = $query->partsWithRecords($client);

        $exercise = null;
        $part = null;

        if ($request->exerciseId() !== null) {
            // 種目を選んだとき（認可は TrainingGraphRequest で確認済み）：種目の部位が優先される
            $exercise = Exercise::query()->visibleTo($client)->with('bodyPart')->find($request->exerciseId());
            $part = $exercise?->bodyPart;
        }

        if ($part === null && $request->partId() !== null) {
            $part = BodyPart::query()->find($request->partId());
        }

        // 初期値：記録のある最初の部位
        $part ??= $parts->first();

        if ($part !== null && ! $parts->contains('id', $part->id)) {
            $parts = $parts->push($part)->sortBy('sort_order')->values();
        }

        if ($exercise !== null) {
            $series = $query->exerciseSeries($client, $exercise, $period);
            $axes = ['left' => '推定1RM・最大重量（kg）', 'right' => 'ボリューム'];
        } elseif ($part !== null) {
            $series = $query->partSeries($client, $part, $period);
            $axes = ['left' => 'セット数・種目数', 'right' => '総ボリューム'];
        } else {
            $series = ['labels' => [], 'datasets' => []];
            $axes = ['left' => '', 'right' => ''];
        }

        return view('records.graphs.training', [
            'client' => $client,
            'parts' => $parts,
            'part' => $part,
            'exercises' => $part === null ? collect() : $query->exercisesWithRecords($client, $part),
            'exercise' => $exercise,
            'period' => $period,
            'periodOptions' => GraphPeriod::OPTIONS,
            'chart' => [...$series, 'axes' => $axes],
            'hasData' => $series['labels'] !== [],
            'subject' => $exercise?->name ?? ($part !== null ? $part->name.'（部位全体）' : null),
        ]);
    }
}
