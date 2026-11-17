<?php

namespace App\Providers;

use App\Models\Exercise;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // {personalExercise} は {client} の個人種目だけを取得する（他人の個人種目は 404）。
        Route::bind('personalExercise', fn (string $value, \Illuminate\Routing\Route $route): Exercise => $route->parameter('client')->personalExercises()->findOrFail($value));
        // {workoutSession} は {client} のセッションを実施日（YYYY-MM-DD）で取得する。
        // 日付の形式が違う・実在しない日付・存在しないセッション・他の利用者のセッションは 404。
        Route::bind('workoutSession', function (string $value, \Illuminate\Routing\Route $route) {
            $isDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
                && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);

            abort_unless($isDate, 404);

            return $route->parameter('client')->workoutSessions()->where('performed_on', $value)->firstOrFail();
        });
        // {workoutSet} は {workoutSession} のセットだけを取得する（親子が違えば 404）。
        Route::bind('workoutSet', fn (string $value, \Illuminate\Routing\Route $route) => $route->parameter('workoutSession')->workoutSets()->findOrFail($value));
        // {sessionExercise} は {workoutSession} の種目だけを取得する（親子が違えば 404）。
        Route::bind('sessionExercise', fn (string $value, \Illuminate\Routing\Route $route) => $route->parameter('workoutSession')->sessionExercises()->findOrFail($value));
        // {bodyComposition} は {client} の体組成の記録だけを取得する（他の利用者の記録は 404）。
        Route::bind('bodyComposition', fn (string $value, \Illuminate\Routing\Route $route) => $route->parameter('client')->bodyCompositions()->findOrFail($value));
        Route::bind('trainer', fn (string $value): User => User::query()->trainers()->findOrFail($value));

        // 招待コードの入力：1ユーザーにつき 5回／分 かつ 20回／時。成功・失敗・形式不正を問わず数える。
        // 同じユーザーに2つの制限をかけるため、キーを分ける（同じキーだと回数が混ざる）。
        RateLimiter::for('invitation-code', function (Request $request) {
            $response = function (Request $request, array $headers) {
                Audit::log('invitation_code.rate_limited', ['user_id' => $request->user()?->id, 'ip' => $request->ip()]);

                return back()->with('error', __('auth.invitation_code_throttle'));
            };
            $id = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute(config('trainingmax.rate_limits.invitation_code_per_minute'))->by('minute:'.$id)->response($response),
                Limit::perHour(config('trainingmax.rate_limits.invitation_code_per_hour'))->by('hour:'.$id)->response($response),
            ];
        });
    }
}
