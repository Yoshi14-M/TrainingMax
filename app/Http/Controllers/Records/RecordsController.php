<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * 利用者の記録（/clients/{client}）のコントローラーの基底クラス。
 *
 * 全アクションの実行前（ルートバインディングの後・FormRequest の検証より前）に、
 * UserPolicy::accessRecords で認可する。個別のアクションに書かないため付け忘れが起きない。
 * サブクラスは middleware() を上書きできない（final）。
 */
abstract class RecordsController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    final public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                $client = $request->route('client');

                if (! $client instanceof User) {
                    throw new LogicException('RecordsController のルートには {client} パラメータが必要です。');
                }

                Gate::authorize('accessRecords', $client);

                return $next($request);
            }),
        ];
    }
}
