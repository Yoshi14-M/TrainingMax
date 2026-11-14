<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Route;

/**
 * 認可のテスト用ルートを登録する（実際の画面は後続のイシューで作る）。
 */
trait RegistersProbeRoutes
{
    protected function registerProbeRoutes(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function () {
            Route::get('/clients/{client}/probe', [ProbeRecordsController::class, 'show']);
            Route::post('/clients/{client}/probe', [ProbeRecordsController::class, 'store']);
            Route::post('/clients/{client}/probe-validated', [ProbeRecordsController::class, 'validated']);
            Route::get('/staff/probe', [ProbeStaffController::class, 'show']);
            Route::get('/staff/trainers/{trainer}/probe', [ProbeOwnerController::class, 'show']);
        });
    }
}
