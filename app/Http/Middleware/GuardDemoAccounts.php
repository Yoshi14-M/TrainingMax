<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * デモ環境で、完了できない操作（メール送信が必要なもの）と、デモ用アカウントを使えなくする操作を止める。
 * web グループ全体にかけ、対象は config/demo.php のルート名で管理する（ルートへの付け忘れを防ぐ）。
 */
class GuardDemoAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Demo::enabled() || $request->isMethodSafe()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return $next($request);
        }

        if (in_array($routeName, config('demo.blocked_routes'), true) || $this->targetsDemoAccount($request, $routeName)) {
            return $this->reject($request);
        }

        return $next($request);
    }

    private function targetsDemoAccount(Request $request, string $routeName): bool
    {
        $protected = config('demo.protected_routes');

        if (! array_key_exists($routeName, $protected)) {
            return false;
        }

        $parameter = $protected[$routeName];

        $target = $parameter === null
            ? $request->user()
            : $request->route($parameter);

        // ルートバインディングの前に呼ばれた場合（ID のまま）にも対応する
        if (! $target instanceof User && $target !== null) {
            $target = User::query()->find($target);
        }

        return Demo::isDemoAccount($target);
    }

    private function reject(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('demo.blocked')], 403);
        }

        return redirect()->back(fallback: url('/'))->with('error', __('demo.blocked'));
    }
}
