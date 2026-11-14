<?php

namespace Tests\Feature\Account;

use App\Models\Gym;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class TrainerSuspensionTest extends TestCase
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

    private function createSession(User $user): void
    {
        $session = new UserSession;
        $session->forceFill([
            'id' => 'session-'.$user->id,
            'user_id' => $user->id,
            'payload' => 'x',
            'last_activity' => time(),
        ])->save();
    }

    #[Test]
    public function オーナーがトレーナーを停止するとログインの保持トークンとセッションが無効になる(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create(['remember_token' => 'old-token']);
        $other = User::factory()->trainer($this->gym)->create();
        $this->createSession($trainer);
        $this->createSession($other);

        $this->actingAs($this->owner)->post(route('staff.trainers.suspension.store', $trainer))
            ->assertRedirect(route('staff.trainers.index'))
            ->assertSessionHas('status');

        $trainer->refresh();
        $this->assertNotNull($trainer->suspended_at);
        $this->assertNotSame('old-token', $trainer->remember_token);
        $this->assertSame(0, UserSession::where('user_id', $trainer->id)->count());
        $this->assertSame(1, UserSession::where('user_id', $other->id)->count());
        $this->assertSame(
            [['message' => 'trainer.suspended', 'context' => ['actor_id' => $this->owner->id, 'target_id' => $trainer->id]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function 停止したトレーナーはログインできない(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($this->owner)->post(route('staff.trainers.suspension.store', $trainer));
        auth()->logout();

        $this->post(route('login.store'), ['email' => $trainer->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'このアカウントは利用停止中です。']);
    }

    #[Test]
    public function 停止を解除するとログインできる(): void
    {
        $trainer = User::factory()->trainer($this->gym)->suspended()->create();

        $this->actingAs($this->owner)->delete(route('staff.trainers.suspension.destroy', $trainer))
            ->assertRedirect(route('staff.trainers.index'))
            ->assertSessionHas('status');

        $this->assertNull($trainer->fresh()->suspended_at);
        $this->assertSame(
            [['message' => 'trainer.resumed', 'context' => ['actor_id' => $this->owner->id, 'target_id' => $trainer->id]]],
            $this->auditRecords(),
        );

        auth()->logout();
        $this->post(route('login.store'), ['email' => $trainer->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($trainer);
    }

    #[Test]
    public function トレーナーは他のトレーナーを停止できない(): void
    {
        $a = User::factory()->trainer($this->gym)->create();
        $b = User::factory()->trainer($this->gym)->create();

        $this->actingAs($a)->post(route('staff.trainers.suspension.store', $b))->assertForbidden();
        $this->actingAs($a)->delete(route('staff.trainers.suspension.destroy', $b))->assertForbidden();

        $this->assertNull($b->fresh()->suspended_at);
    }

    #[Test]
    public function 他のジムのトレーナーは停止できない(): void
    {
        $other = User::factory()->trainer()->create();

        $this->actingAs($this->owner)->post(route('staff.trainers.suspension.store', $other))->assertForbidden();

        $this->assertNull($other->fresh()->suspended_at);
    }

    #[Test]
    public function オーナー自身や他のオーナーは停止の対象にできない(): void
    {
        $anotherOwner = User::factory()->owner($this->gym)->create();

        $this->assertFalse($this->owner->can('suspend', $this->owner));
        $this->assertFalse($this->owner->can('suspend', $anotherOwner));
        $this->assertFalse($this->owner->can('resume', $anotherOwner));

        $this->actingAs($this->owner)->post(route('staff.trainers.suspension.store', $this->owner->id))->assertNotFound();
        $this->assertNull($this->owner->fresh()->suspended_at);
    }

    #[Test]
    public function 利用者のIDを指定すると404になる(): void
    {
        $client = User::factory()->client($this->gym)->create();

        $this->actingAs($this->owner)->post(route('staff.trainers.suspension.store', $client->id))->assertNotFound();
    }

    #[Test]
    public function 停止したトレーナーが入力した内容は残る(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($this->owner)->post(route('staff.trainers.suspension.store', $trainer));

        $this->assertDatabaseHas('users', ['id' => $trainer->id, 'name' => $trainer->name]);
    }
}
