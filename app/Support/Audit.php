<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * 操作ログの書き出し（専用チャンネル `audit`。セキュリティ設計書8章）。
 *
 * 記録するのは日時（ログが自動で付ける）、操作者・対象のID、件数などだけ。
 * パスワード・トークン・招待コード・指導メモ・コメント・体組成・プロフィールの値は渡さない。
 */
class Audit
{
    /**
     * @param  string  $event  例：client.issued
     * @param  array<string, mixed>  $context  IDや件数など（値そのものは入れない）
     */
    public static function log(string $event, array $context = []): void
    {
        Log::channel('audit')->info($event, $context);
    }
}
