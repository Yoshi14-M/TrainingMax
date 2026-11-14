<?php

namespace Tests\Feature\Authorization;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanAccessRecordsOfTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 利用者は自分の記録にアクセスできる(): void
    {
        $client = User::factory()->client()->create();
        $personal = User::factory()->personal()->create();

        $this->assertTrue($client->canAccessRecordsOf($client));
        $this->assertTrue($personal->canAccessRecordsOf($personal));
    }

    #[Test]
    public function 利用者は他の利用者の記録にアクセスできない(): void
    {
        $gym = Gym::factory()->create();
        $a = User::factory()->client($gym)->create();
        $b = User::factory()->client($gym)->create();

        $this->assertFalse($a->canAccessRecordsOf($b));
    }

    #[Test]
    public function 同じジムの有効なスタッフは利用者の記録にアクセスできる(): void
    {
        $gym = Gym::factory()->create();
        $client = User::factory()->client($gym)->create();

        $this->assertTrue(User::factory()->trainer($gym)->create()->canAccessRecordsOf($client));
        $this->assertTrue(User::factory()->owner($gym)->create()->canAccessRecordsOf($client));
    }

    #[Test]
    public function 他のジムのスタッフはアクセスできない(): void
    {
        $client = User::factory()->client()->create();
        $trainer = User::factory()->trainer()->create();

        $this->assertFalse($trainer->canAccessRecordsOf($client));
    }

    #[Test]
    public function スタッフは個人利用者の記録にアクセスできない(): void
    {
        $trainer = User::factory()->trainer()->create();
        $personal = User::factory()->personal()->create();

        $this->assertFalse($trainer->canAccessRecordsOf($personal));
    }

    #[Test]
    public function 紐付けを解除した利用者の記録にはスタッフがアクセスできなくなる(): void
    {
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();
        $client = User::factory()->client($gym)->create();

        $this->assertTrue($trainer->canAccessRecordsOf($client));

        $client->forceFill(['gym_id' => null])->save();

        $this->assertFalse($trainer->canAccessRecordsOf($client->fresh()));
    }

    #[Test]
    public function 停止中のスタッフはアクセスできない(): void
    {
        $gym = Gym::factory()->create();
        $client = User::factory()->client($gym)->create();

        $this->assertFalse(User::factory()->trainer($gym)->suspended()->create()->canAccessRecordsOf($client));
    }

    #[Test]
    public function 対象が利用者でなければアクセスできない(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner($gym)->create();
        $trainer = User::factory()->trainer($gym)->create();

        $this->assertFalse($owner->canAccessRecordsOf($trainer));
        $this->assertFalse($owner->canAccessRecordsOf($owner));
    }
}
