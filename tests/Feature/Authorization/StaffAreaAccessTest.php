<?php

namespace Tests\Feature\Authorization;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RegistersProbeRoutes;
use Tests\TestCase;

class StaffAreaAccessTest extends TestCase
{
    use RefreshDatabase;
    use RegistersProbeRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerProbeRoutes();
    }

    #[Test]
    public function トレーナーとオーナーはスタッフ専用画面を開ける(): void
    {
        $gym = Gym::factory()->create();

        $this->actingAs(User::factory()->trainer($gym)->create())->get('/staff/probe')->assertOk();
        $this->actingAs(User::factory()->owner($gym)->create())->get('/staff/probe')->assertOk();
    }

    #[Test]
    public function 利用者はスタッフ専用画面を開くと403になる(): void
    {
        $this->actingAs(User::factory()->client()->create())->get('/staff/probe')->assertForbidden();
        $this->actingAs(User::factory()->personal()->create())->get('/staff/probe')->assertForbidden();
    }

    #[Test]
    public function トレーナー管理はオーナーだけが開ける(): void
    {
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();

        $this->actingAs(User::factory()->owner($gym)->create())->get("/staff/trainers/{$trainer->id}/probe")->assertOk();
        $this->actingAs($trainer)->get("/staff/trainers/{$trainer->id}/probe")->assertForbidden();
    }

    #[Test]
    public function トレーナー以外のIDをtrainerに指定すると404になる(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner($gym)->create();
        $client = User::factory()->client($gym)->create();

        $this->actingAs($owner)->get("/staff/trainers/{$client->id}/probe")->assertNotFound();
        $this->actingAs($owner)->get("/staff/trainers/{$owner->id}/probe")->assertNotFound();
    }
}
