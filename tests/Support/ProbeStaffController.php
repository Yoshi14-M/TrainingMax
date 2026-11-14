<?php

namespace Tests\Support;

use App\Http\Controllers\Staff\StaffController;

/**
 * テスト用：StaffController を継承した最小のコントローラー。
 */
class ProbeStaffController extends StaffController
{
    public function show()
    {
        return response('staff');
    }
}
