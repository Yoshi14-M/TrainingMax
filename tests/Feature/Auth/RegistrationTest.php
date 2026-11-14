<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function validInput(array $override = []): array
    {
        return array_merge([
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $override);
    }

    #[Test]
    public function 新規登録画面を表示できる(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('新規登録');
    }

    #[Test]
    public function 新規登録すると個人利用者として作成され認証メールが送信される(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->validInput())->assertRedirect('/');

        $user = User::where('email', 'taro@example.com')->firstOrFail();
        $this->assertSame(UserRole::Client, $user->role);
        $this->assertNull($user->gym_id);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function リクエストにroleやgym_idを含めても個人利用者として作成される(): void
    {
        $this->post(route('register.store'), $this->validInput([
            'role' => 'owner',
            'gym_id' => 1,
            'suspended_at' => now()->toDateTimeString(),
            'email_verified_at' => now()->toDateTimeString(),
        ]))->assertRedirect('/');

        $user = User::where('email', 'taro@example.com')->firstOrFail();
        $this->assertSame(UserRole::Client, $user->role);
        $this->assertNull($user->gym_id);
        $this->assertNull($user->suspended_at);
        $this->assertNull($user->email_verified_at);
    }

    #[Test]
    public function メールアドレスは小文字に変換して保存される(): void
    {
        $this->post(route('register.store'), $this->validInput(['email' => 'Taro@Example.COM']));

        $this->assertDatabaseHas('users', ['email' => 'taro@example.com']);
    }

    #[Test]
    public function 登録済みのメールアドレスでは登録できない(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->from(route('register'))
            ->post(route('register.store'), $this->validInput())
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => 'このメールアドレスは既に登録されています。']);

        $this->assertSame(1, User::count());
    }

    #[Test]
    public function 名前は50文字まで登録できて51文字ではエラーになる(): void
    {
        $this->post(route('register.store'), $this->validInput(['name' => str_repeat('あ', 50)]))
            ->assertSessionHasNoErrors();
        $this->post('/logout');

        $this->post(route('register.store'), $this->validInput([
            'name' => str_repeat('あ', 51),
            'email' => 'other@example.com',
        ]))->assertSessionHasErrors('name');
    }

    #[Test]
    public function パスワードは8文字から72文字まで許可される(): void
    {
        foreach ([7 => false, 8 => true, 72 => true, 73 => false] as $length => $ok) {
            $password = str_repeat('a', $length);
            $response = $this->post(route('register.store'), $this->validInput([
                'email' => "len{$length}@example.com",
                'password' => $password,
                'password_confirmation' => $password,
            ]));

            $ok ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('password');
            auth()->logout();
        }
    }

    #[Test]
    public function パスワード確認が一致しないとエラーになる(): void
    {
        $this->post(route('register.store'), $this->validInput(['password_confirmation' => 'different123']))
            ->assertSessionHasErrors(['password' => 'パスワード（確認）が一致しません。']);
    }

    #[Test]
    public function 必須項目が空だと日本語のエラーが表示される(): void
    {
        $this->post(route('register.store'), [])
            ->assertSessionHasErrors([
                'name' => '名前を入力してください。',
                'email' => 'メールアドレスを入力してください。',
                'password' => 'パスワードを入力してください。',
            ]);
    }

    #[Test]
    public function 新規登録は1分間に7回目から回数制限される(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('register.store'), []);
        }

        $this->post(route('register.store'), [])->assertStatus(429);
    }
}
