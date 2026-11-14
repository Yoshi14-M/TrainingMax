<?php

namespace Tests\Feature\Database;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 照合順序（utf8mb4_0900_as_ci）に依存するテスト。MySQL の testing DB を指定したときだけ実行する。
 */
class CollationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL 専用のテストです（DB_CONNECTION=mysql を指定してください）。');
        }
    }

    #[Test]
    public function 濁点と半濁点は区別される(): void
    {
        Exercise::factory()->create(['name' => 'バックスクワット']);

        $this->assertSame(1, Exercise::where('name', 'バックスクワット')->count());
        $this->assertSame(0, Exercise::where('name', 'ハックスクワット')->count());
        $this->assertSame(0, Exercise::where('name', 'パックスクワット')->count());
    }

    #[Test]
    public function 英字の大文字小文字は同じとみなされる(): void
    {
        Exercise::factory()->create(['name' => 'Bench']);

        $this->assertSame(1, Exercise::where('name', 'bench')->count());
        $this->assertSame(1, Exercise::where('name', 'BENCH')->count());
    }

    #[Test]
    public function テーブルの照合順序がutf8mb4_0900_as_ciである(): void
    {
        $collation = DB::selectOne(
            'select table_collation as c from information_schema.tables where table_schema = database() and table_name = ?',
            ['exercises'],
        );

        $this->assertSame('utf8mb4_0900_as_ci', $collation->c);
    }
}
