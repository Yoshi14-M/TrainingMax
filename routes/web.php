<?php

use App\Http\Controllers\Account\GymLinkController;
use App\Http\Controllers\Records\ExerciseController as RecordsExerciseController;
use App\Http\Controllers\Staff\ClientController;
use App\Http\Controllers\Staff\ExerciseController as StaffExerciseController;
use App\Http\Controllers\Staff\InitialPasswordMailController;
use App\Http\Controllers\Staff\InvitationCodeController;
use App\Http\Controllers\Staff\MembershipController;
use App\Http\Controllers\Staff\TrainerController;
use App\Http\Controllers\Staff\TrainerSuspensionController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    // ログイン後のトップ（暫定。ロール別のトップは後続のイシューで実装する）
    Route::view('/', 'home')->name('home');

    // ジム連携（個人利用者：招待コードで紐付け、ジム所属の利用者：解除）
    Route::get('gym-link', [GymLinkController::class, 'show'])->name('gym-link.show');
    Route::post('gym-link/confirm', [GymLinkController::class, 'confirm'])
        ->middleware('throttle:invitation-code')->name('gym-link.confirm');
    Route::get('gym-link/confirm', [GymLinkController::class, 'showConfirm'])->name('gym-link.confirm.show');
    Route::post('gym-link', [GymLinkController::class, 'store'])->name('gym-link.store');
    Route::delete('gym-link', [GymLinkController::class, 'destroy'])->name('gym-link.destroy');

    // 利用者の記録（認可は基底の RecordsController が行う。名前は records.*）
    Route::prefix('clients/{client}')->name('records.')->group(function () {
        Route::get('exercises', [RecordsExerciseController::class, 'index'])->name('exercises.index');
        Route::post('exercises', [RecordsExerciseController::class, 'store'])->name('exercises.store');
        Route::put('exercises/{personalExercise}', [RecordsExerciseController::class, 'update'])->name('exercises.update');
        Route::delete('exercises/{personalExercise}', [RecordsExerciseController::class, 'destroy'])->name('exercises.destroy');
    });

    // スタッフ専用（認可は基底の StaffController／OwnerController が行う）
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::post('clients/{client}/initial-password-mail', InitialPasswordMailController::class)
            ->name('clients.initial-password-mail');

        Route::delete('clients/{client}/membership', [MembershipController::class, 'destroy'])
            ->name('clients.membership.destroy');

        Route::get('invitation-codes', [InvitationCodeController::class, 'index'])->name('invitation-codes.index');
        Route::post('invitation-codes', [InvitationCodeController::class, 'store'])->name('invitation-codes.store');
        Route::delete('invitation-codes/{invitationCode}', [InvitationCodeController::class, 'destroy'])
            ->name('invitation-codes.destroy');

        Route::get('exercises', [StaffExerciseController::class, 'index'])->name('exercises.index');
        Route::post('exercises', [StaffExerciseController::class, 'store'])->name('exercises.store');
        Route::put('exercises/{exercise}', [StaffExerciseController::class, 'update'])->name('exercises.update');
        Route::delete('exercises/{exercise}', [StaffExerciseController::class, 'destroy'])->name('exercises.destroy');

        Route::get('trainers', [TrainerController::class, 'index'])->name('trainers.index');
        Route::post('trainers', [TrainerController::class, 'store'])->name('trainers.store');
        Route::post('trainers/{trainer}/suspension', [TrainerSuspensionController::class, 'store'])
            ->name('trainers.suspension.store');
        Route::delete('trainers/{trainer}/suspension', [TrainerSuspensionController::class, 'destroy'])
            ->name('trainers.suspension.destroy');
    });
});
