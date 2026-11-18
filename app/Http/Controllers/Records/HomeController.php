<?php

namespace App\Http\Controllers\Records;

use App\Http\Requests\Records\HomeRequest;
use App\Models\User;
use App\Queries\CalendarQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/**
 * ホーム（月カレンダー。S-10。/clients/{client}?month=YYYY-MM）。
 */
class HomeController extends RecordsController
{
    public function __invoke(HomeRequest $request, User $client, CalendarQuery $calendar): View
    {
        $month = $request->month();
        $data = $calendar->handle($client, $month);

        return view('records.home', [
            'client' => $client,
            'month' => $month,
            'monthLabel' => CarbonImmutable::createFromFormat('!Y-m', $month)->isoFormat('YYYY年M月'),
            'weeks' => $data['weeks'],
            'previousMonth' => $data['previous'],
            'nextMonth' => $data['next'],
            'todaySession' => $client->workoutSessions()->where('performed_on', now()->toDateString())->first(),
        ]);
    }
}
