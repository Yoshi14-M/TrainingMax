<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private function enableDemo(): void
    {
        config([
            'demo.enabled' => true,
            'trainingmax.seed.owner_email' => 'owner@example.com',
            'demo.accounts.owner.email' => 'owner@example.com',
        ]);
        $this->seed(DatabaseSeeder::class);
    }

    private function demoUser(string $account): User
    {
        return User::where('email', config("demo.accounts.{$account}.email"))->firstOrFail();
    }

    // ---- デモ環境でないとき ----

    #[Test]
    public function デモ環境でなければデモログインは404になりボタンも表示されない(): void
    {
        $user = User::factory()->create(['email' => 'client1@example.com']);

        $this->post(route('demo.login', 'client'))->assertNotFound();
        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('デモ用アカウントでログイン')
            ->assertSee('新規登録（個人利用者）');
    }

    #[Test]
    public function デモ環境でなければパスワード変更などは止めない(): void
    {
        $user = User::factory()->create(['email' => 'client1@example.com']);

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionMissing('error');
    }

    // ---- デモ環境 ----

    #[Test]
    public function ログイン画面にロールごとのボタンが表示され新規登録の案内は表示されない(): void
    {
        $this->enableDemo();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('デモ用アカウントでログイン')
            ->assertSee('オーナーとしてログイン')
            ->assertSee('トレーナーとしてログイン')
            ->assertSee('利用者（ジム所属）としてログイン')
            ->assertSee('利用者（個人）としてログイン')
            ->assertDontSee('新規登録（個人利用者）')
            ->assertDontSee('パスワードをお忘れの方');
    }

    #[Test]
    public function ロールを選ぶとそのデモ用アカウントでログインしホームへ移る(): void
    {
        $this->enableDemo();

        foreach (['owner', 'trainer', 'client', 'personal'] as $account) {
            $this->post(route('demo.login', $account))->assertRedirect(route('home'));
            $this->assertAuthenticatedAs($this->demoUser($account));

            $this->post(route('logout'));
        }
    }

    #[Test]
    public function 定義していないアカウントは404になる(): void
    {
        $this->enableDemo();

        $this->post('/demo-login/unverified')->assertNotFound();
        $this->assertGuest();
    }

    #[Test]
    public function 停止中のアカウントにはログインできない(): void
    {
        $this->enableDemo();
        $this->demoUser('trainer')->forceFill(['suspended_at' => now()])->save();

        $this->post(route('demo.login', 'trainer'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', __('demo.account_unavailable'));
        $this->assertGuest();
    }

    #[Test]
    public function デモ用アカウントのパスワードとプロフィールは変更できない(): void
    {
        $this->enableDemo();
        $client = $this->demoUser('client');
        $passwordHash = $client->password;

        $this->actingAs($client)
            ->from(route('profile.show'))
            ->put(route('user-password.update'), [
                'current_password' => 'x',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('error', __('demo.blocked'));

        $this->actingAs($client)
            ->put(route('user-profile-information.update'), ['name' => '変更', 'email' => 'changed@example.com'])
            ->assertSessionHas('error', __('demo.blocked'));

        $client->refresh();
        $this->assertSame($passwordHash, $client->password);
        $this->assertSame('client1@example.com', $client->email);
    }

    #[Test]
    public function デモ用のトレーナーは停止できないがそれ以外のトレーナーは停止できる(): void
    {
        $this->enableDemo();
        $owner = $this->demoUser('owner');
        $demoTrainer = $this->demoUser('trainer');
        $otherTrainer = User::where('email', 'trainer2@example.com')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('staff.trainers.suspension.store', $demoTrainer))
            ->assertSessionHas('error', __('demo.blocked'));
        $this->assertFalse($demoTrainer->refresh()->isSuspended());

        $this->actingAs($owner)->post(route('staff.trainers.suspension.store', $otherTrainer));
        $this->assertTrue($otherTrainer->refresh()->isSuspended());
    }

    #[Test]
    public function デモ用の利用者はジムとの紐付けを解除できない(): void
    {
        $this->enableDemo();
        $client = $this->demoUser('client');

        $this->actingAs($this->demoUser('trainer'))
            ->delete(route('staff.clients.membership.destroy', $client))
            ->assertSessionHas('error', __('demo.blocked'));

        // 同じテスト内で別のユーザーとして続けて送るため、ログイン状態を一度リセットする
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->actingAs($client)
            ->delete(route('gym-link.destroy'))
            ->assertSessionHas('error', __('demo.blocked'));

        $this->assertNotNull($client->refresh()->gym_id);
    }

    #[Test]
    public function 新規登録とパスワードリセットは止める(): void
    {
        $this->enableDemo();

        $this->post(route('register.store'), [
            'name' => '新規',
            'email' => 'new@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])->assertSessionHas('error', __('demo.blocked'));
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        $this->post(route('password.email'), ['email' => 'client1@example.com'])
            ->assertSessionHas('error', __('demo.blocked'));
    }

    #[Test]
    public function 記録の入力などふだんの操作はデモ環境でもできる(): void
    {
        $this->enableDemo();
        $client = $this->demoUser('client');

        $this->actingAs($client)
            ->get(route('records.home', $client))
            ->assertOk()
            ->assertSee(__('demo.banner'));
    }

    #[Test]
    public function デモデータにはグラフ用の約12週間分の記録とランダムなパスワードが入る(): void
    {
        $this->enableDemo();
        $client = $this->demoUser('client');

        $sessions = WorkoutSession::where('user_id', $client->id);
        $this->assertGreaterThanOrEqual(20, $sessions->count());
        $this->assertTrue($sessions->min('performed_on') <= now()->subWeeks(11)->toDateString());

        // 推定1RMは保存時に計算されている
        $this->assertSame(0, WorkoutSet::whereNull('estimated_1rm')->where('weight', '>', 0)->count());

        $this->assertGreaterThanOrEqual(8, $client->bodyCompositions()->count());
        $this->assertGreaterThan(0, WorkoutSession::where('user_id', $this->demoUser('personal')->id)->count());

        // 開発用の既定パスワード（password）ではログインできない
        foreach (['owner', 'trainer', 'client', 'personal'] as $account) {
            $this->post(route('login.store'), ['email' => config("demo.accounts.{$account}.email"), 'password' => 'password']);
            $this->assertGuest();
        }
    }
}
