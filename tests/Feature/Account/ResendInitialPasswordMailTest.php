<?php

namespace Tests\Feature\Account;

use App\Models\Gym;
use App\Models\User;
use App\Notifications\SetInitialPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class ResendInitialPasswordMailTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->fakeAuditLog();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(User $client): string
    {
        return route('staff.clients.initial-password-mail', $client);
    }

    #[Test]
    public function スタッフはパスワード未設定の利用者にメールを再送できる(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();
        $client = User::factory()->client($this->gym)->passwordNotSet()->create();

        $this->actingAs($trainer)->post($this->url($client))
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($client, SetInitialPasswordNotification::class);
        $this->assertSame(
            [['message' => 'initial_password_mail.resent', 'context' => ['actor_id' => $trainer->id, 'target_id' => $client->id]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function パスワード設定済みの利用者には再送できない(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();
        $client = User::factory()->client($this->gym)->create();

        $this->actingAs($trainer)->post($this->url($client))->assertForbidden();

        Notification::assertNothingSent();
    }

    #[Test]
    public function 他のジムの利用者には再送できない(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();
        $client = User::factory()->client()->passwordNotSet()->create();

        $this->actingAs($trainer)->post($this->url($client))->assertForbidden();

        Notification::assertNothingSent();
    }

    #[Test]
    public function 個人利用者には再送できない(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();
        $personal = User::factory()->personal()->passwordNotSet()->create();

        $this->actingAs($trainer)->post($this->url($personal))->assertForbidden();
    }

    #[Test]
    public function 利用者は再送できない(): void
    {
        Notification::fake();
        $client = User::factory()->client($this->gym)->create();
        $other = User::factory()->client($this->gym)->passwordNotSet()->create();

        $this->actingAs($client)->post($this->url($other))->assertForbidden();
    }

    #[Test]
    public function スタッフのIDを指定すると404になる(): void
    {
        $owner = User::factory()->owner($this->gym)->create();
        $trainer = User::factory()->trainer($this->gym)->passwordNotSet()->create();

        $this->actingAs($owner)->post(route('staff.clients.initial-password-mail', $trainer->id))->assertNotFound();
    }

    #[Test]
    public function 同じ利用者への再送は1分に1回までで制限中は案内が表示される(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();
        $client = User::factory()->client($this->gym)->passwordNotSet()->create();

        $this->actingAs($trainer)->post($this->url($client))->assertSessionHas('status');
        $this->actingAs($trainer)->post($this->url($client))
            ->assertRedirect()
            ->assertSessionHas('error', 'しばらくしてから再送してください。');

        Notification::assertSentToTimes($client, SetInitialPasswordNotification::class, 1);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->actingAs($trainer)->post($this->url($client))->assertSessionHas('status');
        Notification::assertSentToTimes($client, SetInitialPasswordNotification::class, 2);
    }

    #[Test]
    public function 別の利用者への再送は制限されない(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();
        $a = User::factory()->client($this->gym)->passwordNotSet()->create();
        $b = User::factory()->client($this->gym)->passwordNotSet()->create();

        $this->actingAs($trainer)->post($this->url($a))->assertSessionHas('status');
        $this->actingAs($trainer)->post($this->url($b))->assertSessionHas('status');
    }

    #[Test]
    public function 権限のない人の操作では制限の回数を消費しない(): void
    {
        Notification::fake();
        $client = User::factory()->client($this->gym)->passwordNotSet()->create();
        $other = User::factory()->client($this->gym)->create();
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($other)->post($this->url($client))->assertForbidden();
        $this->actingAs($trainer)->post($this->url($client))->assertSessionHas('status');
    }
}
