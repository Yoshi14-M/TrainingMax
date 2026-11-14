<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ログイン中のユーザーが停止されていたら、次の操作でログアウトさせる（セキュリティ設計書5.3）。
 * web グループ全体にかけるため、ルートへの付け忘れが起きない。
 */
class EnsureUserIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuspended()) {
            return $next($request);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('auth.suspended')], 401);
        }

        return redirect()->route('login')->withErrors(['email' => __('auth.suspended')]);
    }
}
