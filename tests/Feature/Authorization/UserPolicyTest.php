<?php

namespace Tests\Feature\Authorization;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function スタッフ専用エリアは有効なスタッフだけが使える(): void
    {
        $gym = Gym::factory()->create();

        $this->assertTrue(Gate::forUser(User::factory()->owner($gym)->create())->allows('accessStaffArea', User::class));
        $this->assertTrue(Gate::forUser(User::factory()->trainer($gym)->create())->allows('accessStaffArea', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->trainer($gym)->suspended()->create())->allows('accessStaffArea', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->client($gym)->create())->allows('accessStaffArea', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->personal()->create())->allows('accessStaffArea', User::class));
    }

    #[Test]
    public function トレーナー管理は有効なオーナーだけが使える(): void
    {
        $gym = Gym::factory()->create();

        $this->assertTrue(Gate::forUser(User::factory()->owner($gym)->create())->allows('manageTrainers', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->owner($gym)->suspended()->create())->allows('manageTrainers', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->trainer($gym)->create())->allows('manageTrainers', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->client($gym)->create())->allows('manageTrainers', User::class));
    }

    #[Test]
    public function 記録へのアクセスはcanAccessRecordsOfと同じ結果になる(): void
    {
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();
        $client = User::factory()->client($gym)->create();
        $other = User::factory()->personal()->create();

        $this->assertTrue(Gate::forUser($trainer)->allows('accessRecords', $client));
        $this->assertFalse(Gate::forUser($trainer)->allows('accessRecords', $other));
    }

    #[Test]
    public function 利用者アカウントの発行は有効なスタッフだけができる(): void
    {
        $gym = Gym::factory()->create();

        $this->assertTrue(Gate::forUser(User::factory()->owner($gym)->create())->allows('issueClient', User::class));
        $this->assertTrue(Gate::forUser(User::factory()->trainer($gym)->create())->allows('issueClient', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->trainer($gym)->suspended()->create())->allows('issueClient', User::class));
        $this->assertFalse(Gate::forUser(User::factory()->client($gym)->create())->allows('issueClient', User::class));
    }

    #[Test]
    public function パスワード設定メールの再送は同じジムのパスワード未設定の利用者に対してだけできる(): void
    {
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();

        $this->assertTrue($trainer->can('sendInitialPasswordMail', User::factory()->client($gym)->passwordNotSet()->create()));
        $this->assertFalse($trainer->can('sendInitialPasswordMail', User::factory()->client($gym)->create()));
        $this->assertFalse($trainer->can('sendInitialPasswordMail', User::factory()->client()->passwordNotSet()->create()));
        $this->assertFalse($trainer->can('sendInitialPasswordMail', User::factory()->personal()->passwordNotSet()->create()));
        $this->assertFalse($trainer->can('sendInitialPasswordMail', User::factory()->trainer($gym)->passwordNotSet()->create()));
        $this->assertFalse(User::factory()->trainer($gym)->suspended()->create()->can('sendInitialPasswordMail', User::factory()->client($gym)->passwordNotSet()->create()));
    }

    #[Test]
    public function トレーナーの停止と再開は同じジムのトレーナーに対するオーナーだけができる(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner($gym)->create();
        $trainer = User::factory()->trainer($gym)->create();

        $this->assertTrue($owner->can('suspend', $trainer));
        $this->assertTrue($owner->can('resume', $trainer));
        $this->assertFalse($owner->can('suspend', User::factory()->trainer()->create()));
        $this->assertFalse($trainer->can('suspend', User::factory()->trainer($gym)->create()));
        $this->assertFalse(User::factory()->owner($gym)->suspended()->create()->can('suspend', $trainer));
    }
}
