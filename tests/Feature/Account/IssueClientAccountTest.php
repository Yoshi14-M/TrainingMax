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

class IssueClientAccountTest extends TestCase
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

    #[Test]
    public function 発行画面を表示できる(): void
    {
        $this->actingAs(User::factory()->trainer($this->gym)->create())
            ->get(route('staff.clients.create'))
            ->assertOk()
            ->assertSee('利用者アカウント発行');
    }

    #[Test]
    public function トレーナーが利用者を発行するとジム所属でパスワード未設定の利用者が作られメールが送信される(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($trainer)
            ->post(route('staff.clients.store'), ['name' => '山田太郎', 'email' => 'Yamada@Example.com'])
            ->assertRedirect(route('staff.clients.create'))
            ->assertSessionHas('status');

        $client = User::where('email', 'yamada@example.com')->firstOrFail();
        $this->assertSame(UserRole::Client, $client->role);
        $this->assertSame($this->gym->id, $client->gym_id);
        $this->assertNull($client->password);
        $this->assertNull($client->email_verified_at);
        Notification::assertSentTo($client, SetInitialPasswordNotification::class);
    }

    #[Test]
    public function オーナーも利用者を発行できる(): void
    {
        Notification::fake();
        $owner = User::factory()->owner($this->gym)->create();

        $this->actingAs($owner)
            ->post(route('staff.clients.store'), ['name' => '山田太郎', 'email' => 'yamada@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'yamada@example.com', 'gym_id' => $this->gym->id]);
    }

    #[Test]
    public function リクエストにroleやgym_idを含めても操作者のジムの利用者として作られる(): void
    {
        Notification::fake();
        $otherGym = Gym::factory()->create();
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($trainer)->post(route('staff.clients.store'), [
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
            'role' => 'owner',
            'gym_id' => $otherGym->id,
            'password' => 'password123',
            'email_verified_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $client = User::where('email', 'yamada@example.com')->firstOrFail();
        $this->assertSame(UserRole::Client, $client->role);
        $this->assertSame($this->gym->id, $client->gym_id);
        $this->assertNull($client->password);
        $this->assertNull($client->email_verified_at);
    }

    #[Test]
    public function 登録済みのメールアドレスではスタッフ向けの案内が表示される(): void
    {
        Notification::fake();
        User::factory()->personal()->create(['email' => 'yamada@example.com']);

        $this->actingAs(User::factory()->trainer($this->gym)->create())
            ->from(route('staff.clients.create'))
            ->post(route('staff.clients.store'), ['name' => '山田太郎', 'email' => 'yamada@example.com'])
            ->assertRedirect(route('staff.clients.create'))
            ->assertSessionHasErrors(['email' => 'このメールアドレスは既に登録されています。個人利用者の場合は招待コードで紐付けてください。']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function 名前とメールアドレスの文字数の境界を確認する(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();
        $longEmail = str_repeat('a', 243).'@example.com'; // 255文字

        $this->actingAs($trainer)->post(route('staff.clients.store'), [
            'name' => str_repeat('あ', 50),
            'email' => $longEmail,
        ])->assertSessionHasNoErrors();

        $this->actingAs($trainer)->post(route('staff.clients.store'), [
            'name' => str_repeat('あ', 51),
            'email' => 'b@example.com',
        ])->assertSessionHasErrors('name');

        $this->actingAs($trainer)->post(route('staff.clients.store'), [
            'name' => '山田',
            'email' => 'c'.$longEmail,
        ])->assertSessionHasErrors('email');
    }

    #[Test]
    public function 必須項目が空だとエラーになる(): void
    {
        $this->actingAs(User::factory()->trainer($this->gym)->create())
            ->post(route('staff.clients.store'), [])
            ->assertSessionHasErrors(['name' => '名前を入力してください。', 'email' => 'メールアドレスを入力してください。']);
    }

    #[Test]
    public function 利用者は発行できない(): void
    {
        $client = User::factory()->client($this->gym)->create();

        $this->actingAs($client)->get(route('staff.clients.create'))->assertForbidden();
        $this->actingAs($client)
            ->post(route('staff.clients.store'), ['name' => '山田', 'email' => 'x@example.com'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    #[Test]
    public function 停止中のトレーナーは発行できない(): void
    {
        $trainer = User::factory()->trainer($this->gym)->suspended()->create();

        $this->actingAs($trainer)
            ->post(route('staff.clients.store'), ['name' => '山田', 'email' => 'x@example.com'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    #[Test]
    public function 未ログインではログイン画面へ移る(): void
    {
        $this->post(route('staff.clients.store'), ['name' => '山田', 'email' => 'x@example.com'])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function 発行の操作ログにはIDだけが記録される(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($trainer)->post(route('staff.clients.store'), ['name' => '山田太郎', 'email' => 'yamada@example.com']);

        $client = User::where('email', 'yamada@example.com')->firstOrFail();
        $this->assertSame(
            [['message' => 'client.issued', 'context' => ['actor_id' => $trainer->id, 'client_id' => $client->id]]],
            $this->auditRecords(),
        );
    }
}
