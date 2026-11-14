<?php

use App\Http\Controllers\Staff\ClientController;
use App\Http\Controllers\Staff\InitialPasswordMailController;
use App\Http\Controllers\Staff\TrainerController;
use App\Http\Controllers\Staff\TrainerSuspensionController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    // ログイン後のトップ（暫定。ロール別のトップは後続のイシューで実装する）
    Route::view('/', 'home')->name('home');

    // スタッフ専用（認可は基底の StaffController／OwnerController が行う）
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::post('clients/{client}/initial-password-mail', InitialPasswordMailController::class)
            ->name('clients.initial-password-mail');

        Route::get('trainers', [TrainerController::class, 'index'])->name('trainers.index');
        Route::post('trainers', [TrainerController::class, 'store'])->name('trainers.store');
        Route::post('trainers/{trainer}/suspension', [TrainerSuspensionController::class, 'store'])
            ->name('trainers.suspension.store');
        Route::delete('trainers/{trainer}/suspension', [TrainerSuspensionController::class, 'destroy'])
            ->name('trainers.suspension.destroy');
    });
});
