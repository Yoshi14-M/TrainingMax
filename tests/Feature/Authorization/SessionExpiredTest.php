<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * セッション切れの送信（419）の処理。テストでは CSRF の確認が無効なため、
 * TokenMismatchException を投げるルートで 419 を再現する。
 */
class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = '一定時間操作がなかったため、もう一度送信してください。';

    protected function setUp(): void
    {
        parent::setUp();

        $expired = fn () => throw new TokenMismatchException;

        Route::middleware(['web', 'auth'])->post('/_expired-auth', $expired);
        Route::middleware(['web', 'guest'])->post('/_expired-guest-page', $expired);
        Route::middleware(['web'])->post('/_expired-open', $expired);
    }

    #[Test]
    public function ログインしている場合は入力値を残して元の画面へ戻り再送信を案内する(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/previous')
            ->post('/_expired-auth', ['memo' => 'メモ', 'password' => 'secret', 'current_password' => 'secret'])
            ->assertRedirect('/previous')
            ->assertSessionHas('error', self::MESSAGE)
            ->assertSessionHasInput('memo', 'メモ')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.current_password');
    }

    #[Test]
    public function ログインが切れている場合はログイン画面へ移り元の画面を覚えておく(): void
    {
        $this->from('/previous')
            ->post('/_expired-open', ['memo' => 'メモ'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::MESSAGE)
            ->assertSessionHas('url.intended', url('/previous'));
    }

    #[Test]
    public function ログイン前の画面の送信は入力値を残して元の画面へ戻る(): void
    {
        $this->from('/login')
            ->post('/_expired-guest-page', ['email' => 'taro@example.com', 'password' => 'secret'])
            ->assertRedirect('/login')
            ->assertSessionHas('error', self::MESSAGE)
            ->assertSessionHasInput('email', 'taro@example.com')
            ->assertSessionMissing('_old_input.password');
    }

    #[Test]
    public function JSONのリクエストでは419のまま返す(): void
    {
        $this->postJson('/_expired-open')->assertStatus(419);
    }

    #[Test]
    public function 案内のメッセージが画面に表示される(): void
    {
        $this->withSession(['error' => self::MESSAGE])->get('/login')->assertSee(self::MESSAGE);
    }
}
