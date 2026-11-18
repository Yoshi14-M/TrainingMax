<?php

namespace App\Listeners;

use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ログイン失敗を操作ログに残す（セキュリティ設計書8.2）。失敗時は入力されたメールアドレスとIPアドレス。
 * パスワードは残さない。メールアドレス欄にパスワードを入れてしまった場合に備え、長さを制限する。
 */
class LogFailedLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Failed $event): void
    {
        Audit::log('login.failed', [
            'email' => Str::limit(mb_strtolower((string) ($event->credentials['email'] ?? '')), 100, ''),
            'ip' => $this->request->ip(),
        ]);
    }
}
