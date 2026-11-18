<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

/**
 * ログインの成功・失敗・停止中による拒否の操作ログ（セキュリティ設計書8.2）。
 */
class LoginAuditTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeAuditLog();
    }

    #[Test]
    public function ログイン成功はユーザーIDとIPアドレスを記録する(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertSame(
            [['message' => 'login.succeeded', 'context' => ['user_id' => $user->id, 'ip' => '127.0.0.1']]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function パスワードの誤りは入力されたメールアドレスとIPアドレスを記録しパスワードは記録しない(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->post(route('login.store'), ['email' => 'TARO@example.com', 'password' => 'wrong-password-123']);

        $records = $this->auditRecords();
        $this->assertSame([['message' => 'login.failed', 'context' => ['email' => 'taro@example.com', 'ip' => '127.0.0.1']]], $records);
        $this->assertStringNotContainsString('wrong-password-123', json_encode($records));
        $this->assertGuest();
    }

    #[Test]
    public function 存在しないメールアドレスでの失敗も記録する(): void
    {
        $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'password']);

        $this->assertSame(
            [['message' => 'login.failed', 'context' => ['email' => 'nobody@example.com', 'ip' => '127.0.0.1']]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function パスワード未設定のアカウントでの失敗も記録する(): void
    {
        $user = User::factory()->passwordNotSet()->create(['email' => 'pending@example.com']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertSame('login.failed', $this->auditRecords()[0]['message']);
        $this->assertGuest();
    }

    #[Test]
    public function メールアドレス欄が長い場合は100文字で切って記録する(): void
    {
        $this->post(route('login.store'), ['email' => str_repeat('a', 300), 'password' => 'password']);

        $this->assertSame(str_repeat('a', 100), $this->auditRecords()[0]['context']['email']);
    }

    #[Test]
    public function 停止中のトレーナーのログインは拒否として記録される(): void
    {
        $user = User::factory()->trainer()->suspended()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'このアカウントは利用停止中です。']);

        $this->assertSame(
            [['message' => 'login.suspended', 'context' => ['user_id' => $user->id, 'ip' => '127.0.0.1']]],
            $this->auditRecords(),
        );
        $this->assertGuest();
    }

    #[Test]
    public function 停止中でもパスワードが誤っていれば失敗として記録する(): void
    {
        $user = User::factory()->trainer()->suspended()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $this->assertSame('login.failed', $this->auditRecords()[0]['message']);
    }

    #[Test]
    public function ログアウトや画面の表示ではログインの記録を増やさない(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('home'));
        $this->post(route('logout'));

        $this->assertSame([], $this->auditRecords());
    }
}
