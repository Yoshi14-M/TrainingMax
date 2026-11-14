<?php

namespace Tests\Feature\Account;

use App\Models\Gym;
use App\Models\User;
use App\Notifications\SetInitialPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InitialPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * スタッフが利用者を発行し、メールのトークンを取り出す。
     *
     * @return array{User, string}
     */
    private function issueClient(string $email = 'yamada@example.com'): array
    {
        Notification::fake();

        $this->actingAs(User::factory()->trainer($this->gym)->create())
            ->post(route('staff.clients.store'), ['name' => '山田太郎', 'email' => $email]);
        auth()->logout();

        $client = User::where('email', $email)->firstOrFail();
        $token = null;
        Notification::assertSentTo($client, SetInitialPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        return [$client, $token];
    }

    /**
     * @return array<string, string>
     */
    private function input(string $token, string $email, string $password = 'new-password123'): array
    {
        return ['token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password];
    }

    #[Test]
    public function パスワード設定画面を表示できる(): void
    {
        $this->get(route('initial-password.show', ['token' => 'dummy', 'email' => 'a@example.com']))
            ->assertOk()
            ->assertSee('パスワードの設定')
            ->assertSee('a@example.com');
    }

    #[Test]
    public function メールのリンクからパスワードを設定するとメール認証も完了してログインできる(): void
    {
        [$client, $token] = $this->issueClient();

        $this->post(route('initial-password.store'), $this->input($token, $client->email))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'パスワードを設定しました。ログインしてください。');

        $client->refresh();
        $this->assertTrue(Hash::check('new-password123', $client->password));
        $this->assertNotNull($client->email_verified_at);

        $this->post(route('login.store'), ['email' => $client->email, 'password' => 'new-password123']);
        $this->assertAuthenticatedAs($client);
    }

    #[Test]
    public function 設定前はパスワード未設定のためログインできない(): void
    {
        [$client] = $this->issueClient();

        $this->post(route('login.store'), ['email' => $client->email, 'password' => 'password']);

        $this->assertGuest();
    }

    #[Test]
    public function 一度使ったリンクは無効になる(): void
    {
        [$client, $token] = $this->issueClient();

        $this->post(route('initial-password.store'), $this->input($token, $client->email))->assertRedirect(route('login'));
        $this->post(route('initial-password.store'), $this->input($token, $client->email, 'another-pass123'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('new-password123', $client->fresh()->password));
    }

    #[Test]
    public function 有効期限は72時間で境界を過ぎると無効になる(): void
    {
        [$client, $token] = $this->issueClient('ok@example.com');
        Carbon::setTestNow(now()->addHours(71)->addMinutes(59));
        $this->post(route('initial-password.store'), $this->input($token, $client->email))->assertRedirect(route('login'));

        [$late, $lateToken] = $this->issueClient('late@example.com');
        Carbon::setTestNow(now()->addHours(72)->addMinutes(1));
        $this->post(route('initial-password.store'), $this->input($lateToken, $late->email))->assertSessionHasErrors('email');

        $this->assertNull($late->fresh()->password);
    }

    #[Test]
    public function 通常のパスワードリセットの有効期限は60分のままである(): void
    {
        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->assertSame(4320, config('auth.passwords.initial.expire'));
        $this->assertSame('password_reset_tokens', config('auth.passwords.initial.table'));
        $this->assertSame(config('auth.passwords.users.table'), config('auth.passwords.initial.table'));
    }

    #[Test]
    public function 本人がパスワードリセットを申請すると設定メールのリンクは無効になる(): void
    {
        [$client, $initialToken] = $this->issueClient();

        // 発行から60秒以内の申請は、送信間隔の制限（Laravel 標準）で新しいトークンが作られない
        Carbon::setTestNow(now()->addMinutes(2));
        $this->post(route('password.email'), ['email' => $client->email]);

        $this->post(route('initial-password.store'), $this->input($initialToken, $client->email))
            ->assertSessionHasErrors('email');
        $this->assertNull($client->fresh()->password);
    }

    #[Test]
    public function 無効なトークンや異なるメールアドレスでは設定できない(): void
    {
        [$client, $token] = $this->issueClient();

        $this->post(route('initial-password.store'), $this->input('invalid-token', $client->email))->assertSessionHasErrors('email');
        $this->post(route('initial-password.store'), $this->input($token, 'other@example.com'))->assertSessionHasErrors('email');

        $this->assertNull($client->fresh()->password);
    }

    #[Test]
    public function パスワードは8文字から72文字まで許可される(): void
    {
        [$client, $token] = $this->issueClient();

        $this->post(route('initial-password.store'), $this->input($token, $client->email, 'short'))
            ->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください。']);
        $this->post(route('initial-password.store'), $this->input($token, $client->email, str_repeat('a', 73)))
            ->assertSessionHasErrors('password');
        $this->post(route('initial-password.store'), $this->input($token, $client->email, str_repeat('a', 72)))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function パスワード確認が一致しないとエラーになる(): void
    {
        [$client, $token] = $this->issueClient();

        $input = $this->input($token, $client->email);
        $input['password_confirmation'] = 'different123';

        $this->post(route('initial-password.store'), $input)->assertSessionHasErrors('password');
    }

    #[Test]
    public function メールの本文にパスワードは含まれずリンクと有効期限が書かれている(): void
    {
        [$client, $token] = $this->issueClient();

        $notification = new SetInitialPasswordNotification($token);
        $mail = $notification->toMail($client);
        $rendered = (string) $mail->render();

        $this->assertStringContainsString(route('initial-password.show', ['token' => $token, 'email' => $client->email]), html_entity_decode($rendered));
        $this->assertStringContainsString('72時間', $rendered);
        $this->assertStringNotContainsString('password123', $rendered);
        $this->assertSame('【'.config('app.name').'】パスワードの設定', $mail->subject);
    }

    #[Test]
    public function ログイン済みの人はパスワード設定画面を開けない(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('initial-password.show', ['token' => 'x']))
            ->assertRedirect();
    }
}
