<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

/**
 * 操作ログ（audit チャンネル）をメモリに記録して、テストから内容を確認できるようにする。
 */
trait FakesAuditLog
{
    protected function fakeAuditLog(): void
    {
        config(['logging.channels.audit' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
        Log::forgetChannel('audit');
    }

    /**
     * @return array<int, array{message: string, context: array<string, mixed>}>
     */
    protected function auditRecords(): array
    {
        /** @var TestHandler $handler */
        $handler = Log::channel('audit')->getLogger()->getHandlers()[0];

        return array_map(
            fn ($record) => ['message' => $record->message, 'context' => $record->context],
            $handler->getRecords(),
        );
    }
}
