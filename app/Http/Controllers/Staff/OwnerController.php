<?php

namespace App\Http\Controllers\Staff;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

/**
 * オーナーだけが使えるスタッフ専用画面（トレーナー管理）の基底クラス。
 */
abstract class OwnerController extends StaffController
{
    /**
     * @return array<int, Middleware>
     */
    final public static function middleware(): array
    {
        return [
            ...parent::middleware(),
            new Middleware(function (Request $request, Closure $next) {
                Gate::authorize('manageTrainers', User::class);

                return $next($request);
            }),
        ];
    }
}
