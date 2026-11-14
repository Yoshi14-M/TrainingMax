<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 全レスポンスにセキュリティヘッダーを付ける（セキュリティ設計書7.5）。
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.config('trainingmax.hsts_max_age'));
        }

        return $response;
    }
}
