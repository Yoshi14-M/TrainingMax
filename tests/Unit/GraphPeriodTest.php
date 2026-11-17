<?php

namespace Tests\Unit;

use App\Support\Graph\GraphPeriod;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GraphPeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function 全期間は開始日も終了日も指定しない(): void
    {
        $period = GraphPeriod::make('all', null, null);

        $this->assertSame('all', $period->key);
        $this->assertNull($period->from);
        $this->assertNull($period->to);
    }

    #[Test]
    public function 直近30日と直近90日は今日までの期間になる(): void
    {
        $thirty = GraphPeriod::make('30', null, null);
        $ninety = GraphPeriod::make('90', null, null);

        $this->assertSame('2026-10-14', $thirty->from->toDateString());
        $this->assertSame('2026-11-13', $thirty->to->toDateString());
        $this->assertSame('2026-08-15', $ninety->from->toDateString());
        $this->assertSame('2026-11-13', $ninety->to->toDateString());
    }

    #[Test]
    public function 期間指定は開始日と終了日を使う(): void
    {
        $period = GraphPeriod::make('custom', '2026-10-01', '2026-10-31');

        $this->assertSame('custom', $period->key);
        $this->assertSame('2026-10-01', $period->from->toDateString());
        $this->assertSame('2026-10-31', $period->to->toDateString());
    }

    #[Test]
    public function 期間指定で日付が足りない場合や不明な種類は直近90日になる(): void
    {
        foreach ([
            GraphPeriod::make('custom', null, '2026-10-31'),
            GraphPeriod::make('custom', '2026-10-01', null),
            GraphPeriod::make('custom', null, null),
            GraphPeriod::make('abc', null, null),
            GraphPeriod::make('', null, null),
        ] as $period) {
            $this->assertSame('90', $period->key);
            $this->assertSame('2026-08-15', $period->from->toDateString());
        }
    }

    #[Test]
    public function 初期値は直近90日である(): void
    {
        $this->assertSame('90', GraphPeriod::DEFAULT);
        $this->assertSame(['all', '30', '90', 'custom'], array_map('strval', array_keys(GraphPeriod::OPTIONS)));
    }
}
