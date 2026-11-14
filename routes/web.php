<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

// ログイン後のトップ（暫定。ロール別のトップは後続のイシューで実装する）
Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::view('/', 'home')->name('home');
});
