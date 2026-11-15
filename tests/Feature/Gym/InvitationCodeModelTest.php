<?php

namespace Tests\Feature\Gym;

use App\Enums\InvitationCodeStatus;
use App\Models\InvitationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvitationCodeModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 状態は日時から判定される(): void
    {
        $this->assertSame(InvitationCodeStatus::Unused, InvitationCode::factory()->create()->status());
        $this->assertSame(InvitationCodeStatus::Used, InvitationCode::factory()->used()->create()->status());
        $this->assertSame(InvitationCodeStatus::Revoked, InvitationCode::factory()->revoked()->create()->status());
        $this->assertSame(InvitationCodeStatus::Expired, InvitationCode::factory()->expired()->create()->status());
    }

    #[Test]
    public function 状態は使用済み_取消_期限切れの順に判定される(): void
    {
        $this->assertSame(InvitationCodeStatus::Used, InvitationCode::factory()->used()->revoked()->expired()->create()->status());
        $this->assertSame(InvitationCodeStatus::Revoked, InvitationCode::factory()->revoked()->expired()->create()->status());
    }

    #[Test]
    public function 使えるコードは未使用_未取消_期限内のものだけである(): void
    {
        $usable = InvitationCode::factory()->create();
        InvitationCode::factory()->used()->create();
        InvitationCode::factory()->revoked()->create();
        InvitationCode::factory()->expired()->create();

        $this->assertSame([$usable->id], InvitationCode::usable()->pluck('id')->all());
    }

    #[Test]
    public function 状態の表示名を返す(): void
    {
        $this->assertSame('未使用', InvitationCodeStatus::Unused->label());
        $this->assertSame('使用済み', InvitationCodeStatus::Used->label());
        $this->assertSame('取消', InvitationCodeStatus::Revoked->label());
        $this->assertSame('期限切れ', InvitationCodeStatus::Expired->label());
    }

    #[Test]
    public function 招待コードの文字は32種類でIとOと0と1を含まない(): void
    {
        $this->assertSame(32, strlen(InvitationCode::ALPHABET));
        $this->assertSame(32, count(array_unique(str_split(InvitationCode::ALPHABET))));
        $this->assertSame(0, preg_match('/[IO01]/', InvitationCode::ALPHABET));
    }
}
