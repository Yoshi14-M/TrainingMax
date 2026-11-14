<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = '入力したメールアドレスが登録されていれば、再設定用のメールを送信しました。';

    #[Test]
    public function 登録済みのメールアドレスで申請すると再設定メールが送信される(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', self::MESSAGE);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function 未登録のメールアドレスでも同じメッセージが表示されメールは送信されない(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', self::MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    #[Test]
    public function 送信間隔の制限中でも同じメッセージが表示される(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', self::MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    #[Test]
    public function 申請は1分間に7回目から回数制限されるが同じメッセージを返す(): void
    {
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('password.email'), ['email' => "user{$i}@example.com"]);
        }

        $this->post(route('password.email'), ['email' => 'user7@example.com'])
            ->assertSessionHas('status', self::MESSAGE);
        Notification::assertNothingSent();
    }

    #[Test]
    public function 再設定画面を表示できる(): void
    {
        $this->get(route('password.reset', ['token' => 'dummy', 'email' => 'a@example.com']))
            ->assertOk()
            ->assertSee('パスワードの再設定');
    }

    #[Test]
    public function 有効なトークンでパスワードを再設定できる(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    #[Test]
    public function パスワード未設定のアカウントも再設定でパスワードを設定できる(): void
    {
        $user = User::factory()->passwordNotSet()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->password);
    }

    #[Test]
    public function 無効なトークンでは再設定できない(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('new-password123', $user->fresh()->password));
    }

    #[Test]
    public function 再設定のパスワードは8文字未満だとエラーになる(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください。']);
    }
}
