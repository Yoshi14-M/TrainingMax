<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // {client} は利用者（role = client）だけ、{trainer} はトレーナー（role = trainer）だけを取得する。
        // スタッフのIDなどを指定した場合は 404 になる（アーキテクチャ設計書4.3）。
        Route::bind('client', fn (string $value): User => User::query()->clients()->findOrFail($value));
        Route::bind('trainer', fn (string $value): User => User::query()->trainers()->findOrFail($value));
    }
}
