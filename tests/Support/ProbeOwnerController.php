<?php

namespace Tests\Support;

use App\Http\Controllers\Staff\OwnerController;
use App\Models\User;

/**
 * テスト用：OwnerController を継承した最小のコントローラー。
 */
class ProbeOwnerController extends OwnerController
{
    public function show(User $trainer)
    {
        return response('owner:'.$trainer->id);
    }
}
