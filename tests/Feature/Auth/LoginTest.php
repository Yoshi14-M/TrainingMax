<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ログイン画面に共用端末の注意が表示される(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ログイン状態を保持する')
            ->assertSee('共用端末ではチェックしないでください。');
    }

    #[Test]
    public function ログインに成功するとホームへ遷移する(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function メールアドレスは大文字小文字を区別せずログインできる(): void
    {
        $user = User::factory()->create(['email' => 'taro@example.com']);

        $this->post(route('login.store'), ['email' => 'TARO@Example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function 元の画面がある場合はログイン後にそこへ戻る(): void
    {
        $user = User::factory()->create();

        $this->get(route('home'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
    }

    #[Test]
    public function パスワードが違うとどちらが違うか分からないエラーになる(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);

        $this->assertGuest();
    }

    #[Test]
    public function 存在しないメールアドレスでも同じエラーになる(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
    }

    #[Test]
    public function パスワード未設定のアカウントではログインできない(): void
    {
        $user = User::factory()->passwordNotSet()->create();

        foreach (['', 'password', 'null'] as $password) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => $password]);
            $this->assertGuest();
        }
    }

    #[Test]
    public function ログインは1分間に6回目から回数制限される(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    #[Test]
    public function ログイン状態を保持するCookieの有効期間は30日になる(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $cookie->getExpiresTime(), 60);
    }

    #[Test]
    public function ログイン状態を保持しない場合はCookieが発行されない(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertEmpty(collect($response->headers->getCookies())
            ->filter(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
    }

    #[Test]
    public function ログアウトするとログイン画面へ戻る(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
    }

    #[Test]
    public function ログイン済みの人はログイン画面を開くとホームへ移る(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect();
    }

    #[Test]
    public function セッションの有効期間は30分である(): void
    {
        $this->assertSame(30, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }
}
