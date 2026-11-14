<?php

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\SetInitialPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class AddTrainerTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->owner($this->gym)->create();
        $this->fakeAuditLog();
    }

    #[Test]
    public function オーナーはトレーナー管理画面で一覧を見られる(): void
    {
        User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
        User::factory()->trainer($this->gym)->suspended()->create(['name' => '停止トレーナー']);
        User::factory()->trainer($this->gym)->passwordNotSet()->create(['name' => '未設定トレーナー']);
        User::factory()->trainer()->create(['name' => '他ジムトレーナー']);

        $this->actingAs($this->owner)->get(route('staff.trainers.index'))
            ->assertOk()
            ->assertSee('田中トレーナー')
            ->assertSee('停止中')
            ->assertSee('パスワード未設定')
            ->assertSee('有効')
            ->assertDontSee('他ジムトレーナー');
    }

    #[Test]
    public function オーナーがトレーナーを追加するとトレーナーが作られメールが送信される(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->post(route('staff.trainers.store'), ['name' => '佐藤トレーナー', 'email' => 'Sato@Example.com'])
            ->assertRedirect(route('staff.trainers.index'))
            ->assertSessionHas('status');

        $trainer = User::where('email', 'sato@example.com')->firstOrFail();
        $this->assertSame(UserRole::Trainer, $trainer->role);
        $this->assertSame($this->gym->id, $trainer->gym_id);
        $this->assertNull($trainer->password);
        $this->assertNull($trainer->email_verified_at);
        Notification::assertSentTo($trainer, SetInitialPasswordNotification::class);
        $this->assertSame(
            [['message' => 'trainer.added', 'context' => ['actor_id' => $this->owner->id, 'target_id' => $trainer->id]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function リクエストにroleを含めてもトレーナーとして作られる(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->post(route('staff.trainers.store'), [
            'name' => '佐藤', 'email' => 'sato@example.com', 'role' => 'owner', 'gym_id' => 999,
        ])->assertRedirect();

        $trainer = User::where('email', 'sato@example.com')->firstOrFail();
        $this->assertSame(UserRole::Trainer, $trainer->role);
        $this->assertSame($this->gym->id, $trainer->gym_id);
    }

    #[Test]
    public function 登録済みのメールアドレスではエラーになる(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'sato@example.com']);

        $this->actingAs($this->owner)
            ->post(route('staff.trainers.store'), ['name' => '佐藤', 'email' => 'sato@example.com'])
            ->assertSessionHasErrors(['email' => 'このメールアドレスは既に登録されています。']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function トレーナーはトレーナー管理を開けず追加もできない(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($trainer)->get(route('staff.trainers.index'))->assertForbidden();
        $this->actingAs($trainer)
            ->post(route('staff.trainers.store'), ['name' => '佐藤', 'email' => 'sato@example.com'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sato@example.com']);
    }

    #[Test]
    public function 利用者はトレーナー管理を開けない(): void
    {
        $this->actingAs(User::factory()->client($this->gym)->create())
            ->get(route('staff.trainers.index'))
            ->assertForbidden();
    }

    #[Test]
    public function 認可は入力の検証より先に行われる(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($trainer)->post(route('staff.trainers.store'), [])->assertForbidden();
    }
}
