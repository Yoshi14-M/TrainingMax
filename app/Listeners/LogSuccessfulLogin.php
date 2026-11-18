<?php

namespace App\Listeners;

use App\Support\Audit;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * ログイン成功を操作ログに残す（セキュリティ設計書8.2）。ユーザーIDとIPアドレスのみ。
 */
class LogSuccessfulLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        Audit::log('login.succeeded', ['user_id' => $event->user->getAuthIdentifier(), 'ip' => $this->request->ip()]);
    }
}
