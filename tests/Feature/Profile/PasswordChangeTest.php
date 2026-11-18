<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
            ...$overrides,
        ];
    }

    private function change(User $user, array $data)
    {
        return $this->actingAs($user)->from(route('profile.show'))->put(route('user-password.update'), $data);
    }

    #[Test]
    public function パスワードを変更できる(): void
    {
        $user = User::factory()->personal()->create();

        $this->change($user, $this->input())
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    #[Test]
    public function スタッフもパスワードを変更できる(): void
    {
        $trainer = User::factory()->trainer()->create();

        $this->change($trainer, $this->input())->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-1', $trainer->fresh()->password));
    }

    #[Test]
    public function 現在のパスワードが違うと変更できない(): void
    {
        $user = User::factory()->personal()->create();

        $this->change($user, $this->input(['current_password' => 'wrong-password']))
            ->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    #[Test]
    public function 現在のパスワードが空だと変更できない(): void
    {
        $user = User::factory()->personal()->create();

        $this->change($user, $this->input(['current_password' => '']))
            ->assertSessionHasErrorsIn('updatePassword', ['current_password']);
    }

    #[Test]
    public function 確認用が一致しないと変更できない(): void
    {
        $user = User::factory()->personal()->create();

        $this->change($user, $this->input(['password_confirmation' => 'different-password']))
            ->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function 文字数の境界(): array
    {
        return ['7文字' => [7, false], '8文字' => [8, true], '72文字' => [72, true], '73文字' => [73, false]];
    }

    #[Test]
    #[DataProvider('文字数の境界')]
    public function 新しいパスワードの文字数の境界(int $length, bool $valid): void
    {
        $user = User::factory()->personal()->create();
        $password = str_repeat('a', $length);

        $response = $this->change($user, $this->input(['password' => $password, 'password_confirmation' => $password]));

        $valid
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrorsIn('updatePassword', ['password']);
    }

    #[Test]
    public function エラーは日本語で表示される(): void
    {
        $user = User::factory()->personal()->create();

        $this->actingAs($user)->from(route('profile.show'))->followingRedirects()
            ->put(route('user-password.update'), $this->input(['current_password' => 'wrong-password', 'password' => 'short', 'password_confirmation' => 'short']))
            ->assertSee('現在のパスワードが正しくありません。')
            ->assertSee('パスワードは8文字以上で入力してください。');
    }

    #[Test]
    public function パスワード変更後は他の端末のログインが解除される(): void
    {
        $user = User::factory()->personal()->create();

        // 端末A：ログインしてセッションにパスワードのハッシュを持つ
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->get(route('home'));
        $deviceA = $this->app['session']->all();

        // 端末B（別のセッション）：パスワードを変更する
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->from(route('profile.show'))->put(route('user-password.update'), $this->input())
            ->assertSessionHasNoErrors();

        // 変更した端末Bはログインしたまま
        $this->get(route('profile.show'))->assertOk();

        // 端末A：次のリクエストでログアウトされる
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withSession($deviceA)->get(route('profile.show'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function パスワード変更後はログイン状態保持のCookieも無効になる(): void
    {
        $user = User::factory()->personal()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);
        $recaller = collect($this->app['cookie']->getQueuedCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web'));
        $this->assertNotNull($recaller);

        $this->from(route('profile.show'))->put(route('user-password.update'), $this->input());

        // Cookie（旧パスワードのハッシュを含む）だけで新しい端末からアクセスしても、ログインできない
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->app['cookie']->flushQueuedCookies();
        $this->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function 操作ログにはパスワードを残さない(): void
    {
        $this->fakeAuditLog();
        $user = User::factory()->personal()->create();

        $this->change($user, $this->input());

        $records = $this->auditRecords();
        $this->assertSame([['message' => 'password.changed', 'context' => ['user_id' => $user->id]]], $records);
        $this->assertStringNotContainsString('new-password-1', json_encode($records));
    }

    #[Test]
    public function 未ログインでは変更できない(): void
    {
        $this->put(route('user-password.update'), $this->input())->assertRedirect(route('login'));
    }

    #[Test]
    public function 変更後は新しいパスワードでログインできる(): void
    {
        $user = User::factory()->personal()->create();
        $this->change($user, $this->input());
        Auth::logout();
        $this->flushSession();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-password-1'])
            ->assertRedirect(route('home'));
    }
}
