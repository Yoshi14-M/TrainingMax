<?php

namespace Tests\Feature\Account;

use App\Support\Audit;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use FakesAuditLog;

    #[Test]
    public function 操作ログはauditチャンネルに記録される(): void
    {
        $this->fakeAuditLog();

        Audit::log('client.unlinked', ['actor_id' => 3, 'client_id' => 15]);

        $this->assertSame(
            [['message' => 'client.unlinked', 'context' => ['actor_id' => 3, 'client_id' => 15]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function auditチャンネルは日ごとのファイルで90日保存される(): void
    {
        $channel = config('logging.channels.audit');

        $this->assertSame('daily', $channel['driver']);
        $this->assertSame(storage_path('logs/audit.log'), $channel['path']);
        $this->assertSame(90, $channel['max_files']);
        $this->assertSame('info', $channel['level']);
    }
}
