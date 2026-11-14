<?php

namespace Tests\Support;

use App\Http\Controllers\Records\RecordsController;
use App\Models\User;

/**
 * テスト用：RecordsController を継承した最小のコントローラー。
 */
class ProbeRecordsController extends RecordsController
{
    public function show(User $client)
    {
        return response('records:'.$client->id);
    }

    public function store(User $client)
    {
        return response('stored:'.$client->id);
    }

    public function validated(User $client, ProbeRequest $request)
    {
        return response('validated:'.$client->id);
    }
}
