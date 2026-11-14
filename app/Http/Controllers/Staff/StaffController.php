<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

/**
 * スタッフ専用（/staff）のコントローラーの基底クラス。
 * 全アクションの実行前（FormRequest の検証より前）に、有効なスタッフであることを UserPolicy で確認する。
 */
abstract class StaffController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                Gate::authorize('accessStaffArea', User::class);

                return $next($request);
            }),
        ];
    }
}
