<?php

namespace Tests\Feature\Authorization;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RegistersProbeRoutes;
use Tests\TestCase;

class SuspendedUserTest extends TestCase
{
    use RefreshDatabase;
    use RegistersProbeRoutes;

    #[Test]
    public function 停止中のトレーナーはパスワードが正しくてもログインできない(): void
    {
        $trainer = User::factory()->trainer()->suspended()->create();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $trainer->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'このアカウントは利用停止中です。']);

        $this->assertGuest();
    }

    #[Test]
    public function 停止中でもパスワードが違えば通常のエラーになり停止状態は分からない(): void
    {
        $trainer = User::factory()->trainer()->suspended()->create();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $trainer->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
    }

    #[Test]
    public function 停止を再開するとログインできる(): void
    {
        $trainer = User::factory()->trainer()->suspended()->create();
        $trainer->forceFill(['suspended_at' => null])->save();

        $this->post(route('login.store'), ['email' => $trainer->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($trainer);
    }

    #[Test]
    public function ログイン中に停止されると次の操作でログアウトされログイン画面へ移る(): void
    {
        $this->registerProbeRoutes();
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();

        $this->actingAs($trainer)->get('/staff/probe')->assertOk();

        $trainer->forceFill(['suspended_at' => now()])->save();

        $this->get('/staff/probe')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'このアカウントは利用停止中です。']);

        $this->assertGuest();
    }

    #[Test]
    public function 停止後はスタッフ専用画面を開けない(): void
    {
        $this->registerProbeRoutes();
        $trainer = User::factory()->trainer()->suspended()->create();

        $this->actingAs($trainer)->get('/staff/probe')->assertRedirect(route('login'));
    }

    #[Test]
    public function JSONのリクエストでは401が返る(): void
    {
        $this->registerProbeRoutes();
        $trainer = User::factory()->trainer()->suspended()->create();

        $this->actingAs($trainer)->getJson('/staff/probe')->assertUnauthorized();
    }

    #[Test]
    public function 停止中のユーザーにはパスワードリセットのメールを送らず同じメッセージを表示する(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer()->suspended()->create();

        $this->post(route('password.email'), ['email' => $trainer->email])
            ->assertSessionHas('status', '入力したメールアドレスが登録されていれば、再設定用のメールを送信しました。');

        Notification::assertNothingSent();
    }

    #[Test]
    public function 停止していないユーザーにはパスワードリセットのメールが送られる(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer()->create();

        $this->post(route('password.email'), ['email' => $trainer->email]);

        Notification::assertSentTo($trainer, ResetPassword::class);
    }
}
