<?php

namespace Tests\Unit;

use App\Enums\Gender;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnumLabelTest extends TestCase
{
    #[Test]
    public function ロールの表示名を返す(): void
    {
        $this->assertSame('オーナー', UserRole::Owner->label());
        $this->assertSame('トレーナー', UserRole::Trainer->label());
        $this->assertSame('利用者', UserRole::Client->label());
    }

    #[Test]
    public function 性別の表示名を返す(): void
    {
        $this->assertSame('男性', Gender::Male->label());
        $this->assertSame('女性', Gender::Female->label());
        $this->assertSame('その他', Gender::Other->label());
        $this->assertSame('回答しない', Gender::NoAnswer->label());
    }
}
