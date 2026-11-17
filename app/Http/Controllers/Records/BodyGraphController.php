<?php

namespace App\Http\Controllers\Records;

use App\Http\Requests\Records\BodyGraphRequest;
use App\Models\User;
use App\Queries\BodyGraphQuery;
use App\Support\Graph\GraphPeriod;
use Illuminate\Contracts\View\View;

/**
 * 体組成グラフ（S-14。/clients/{client}/graphs/body）。全項目をひとつのグラフに重ねて表示する。
 */
class BodyGraphController extends RecordsController
{
    public function __invoke(BodyGraphRequest $request, User $client, BodyGraphQuery $query): View
    {
        $period = $request->graphPeriod();
        $series = $query->series($client, $period);

        return view('records.graphs.body', [
            'client' => $client,
            'period' => $period,
            'periodOptions' => GraphPeriod::OPTIONS,
            'chart' => [...$series, 'axes' => ['left' => '体重・筋肉量（kg）', 'right' => '体脂肪率（%）・周囲径（cm）']],
            'hasData' => $series['labels'] !== [],
        ]);
    }
}
