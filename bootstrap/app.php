<?php

use App\Http\Middleware\EnsureUserIsNotSuspended;
use App\Http\Middleware\GuardDemoAccounts;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 停止確認は web グループ全体にかける（ルートへの付け忘れを防ぐ）
        $middleware->web(append: [
            EnsureUserIsNotSuspended::class,
            // デモ環境（DEMO_MODE=true）だけ、デモ用アカウントを壊す操作などを止める
            GuardDemoAccounts::class,
        ]);

        // Render などの PaaS は、ロードバランサーで HTTPS を終端して HTTP で転送する。
        // X-Forwarded-Proto を信頼し、URL・Cookie を https として扱う
        $middleware->trustProxies(at: '*');

        // セキュリティヘッダーは全レスポンス（404 などルートに一致しないものも含む）に付ける
        $middleware->append(SetSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // セッション切れの送信（419）。Laravel ではセッション切れの送信は認証より先に CSRF の確認で 419 になる。
        // ログインしている（または未ログイン用の画面の）場合は、入力値を残して元の画面へ戻し再送信を案内する。
        // ログインが切れている場合はログイン画面へ移し、ログイン後に元の画面へ戻す（アーキテクチャ設計書5.7）。
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $isGuestPage = in_array('guest', $request->route()?->gatherMiddleware() ?? [], true);

            if (Auth::check() || $isGuestPage) {
                return redirect()->back(fallback: url('/'))
                    ->withInput(Arr::except($request->input(), ['_token', 'password', 'password_confirmation', 'current_password']))
                    ->with('error', __('auth.session_expired'));
            }

            return redirect()->guest(route('login'))->with('error', __('auth.session_expired'));
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
