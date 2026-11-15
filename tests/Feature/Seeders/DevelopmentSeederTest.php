<?php

namespace Tests\Feature\Seeders;

use App\Enums\ExerciseScope;
use App\Enums\UserRole;
use App\Models\Exercise;
use App\Models\InvitationCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'trainingmax.seed.owner_email' => 'owner@example.com',
            'trainingmax.seed.owner_password' => 'owner-password',
            'trainingmax.seed.dev_user_password' => 'dev-password',
        ]);
    }

    private function seedAll(): void
    {
        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function 各ロール各状態のテストユーザーが作成される(): void
    {
        $this->seedAll();

        $gymId = User::where('email', 'owner@example.com')->value('gym_id');

        $trainer = User::where('email', 'trainer1@example.com')->firstOrFail();
        $this->assertSame(UserRole::Trainer, $trainer->role);
        $this->assertSame($gymId, $trainer->gym_id);
        $this->assertTrue($trainer->isActiveStaff());
        $this->assertNotNull($trainer->email_verified_at);

        $this->assertTrue(User::where('email', 'trainer2@example.com')->firstOrFail()->isActiveStaff());
        $this->assertTrue(User::where('email', 'trainer-suspended@example.com')->firstOrFail()->isSuspended());

        foreach (['client1@example.com', 'client2@example.com'] as $email) {
            $client = User::where('email', $email)->firstOrFail();
            $this->assertSame(UserRole::Client, $client->role);
            $this->assertSame($gymId, $client->gym_id);
        }

        $pending = User::where('email', 'client-pending@example.com')->firstOrFail();
        $this->assertNull($pending->password);
        $this->assertSame($gymId, $pending->gym_id);

        $personal = User::where('email', 'personal@example.com')->firstOrFail();
        $this->assertSame(UserRole::Client, $personal->role);
        $this->assertNull($personal->gym_id);
        $this->assertNotNull($personal->email_verified_at);

        $this->assertNull(User::where('email', 'unverified@example.com')->firstOrFail()->email_verified_at);
    }

    #[Test]
    public function 共通のパスワードで作成される(): void
    {
        $this->seedAll();

        $this->assertTrue(Hash::check('dev-password', User::where('email', 'client1@example.com')->value('password')));
        $this->assertTrue(Hash::check('dev-password', User::where('email', 'trainer1@example.com')->value('password')));
    }

    #[Test]
    public function 未使用の招待コードが1件作成される(): void
    {
        $this->seedAll();

        $invitation = InvitationCode::sole();
        $this->assertTrue($invitation->isUsable());
        $this->assertSame(User::where('email', 'trainer1@example.com')->value('id'), $invitation->issued_by);
    }

    #[Test]
    public function ジム共通種目と個人種目が作成される(): void
    {
        $this->seedAll();

        $gymExercise = Exercise::where('name', 'ケーブルクロス')->firstOrFail();
        $this->assertSame(ExerciseScope::Gym, $gymExercise->scope);
        $this->assertSame(User::where('email', 'owner@example.com')->value('gym_id'), $gymExercise->gym_id);

        $personal = Exercise::where('name', '腹筋ローラー')->firstOrFail();
        $this->assertSame(ExerciseScope::Personal, $personal->scope);
        $this->assertSame(User::where('email', 'client1@example.com')->value('id'), $personal->owner_user_id);
    }

    #[Test]
    public function 何度実行しても重複しない(): void
    {
        $this->seedAll();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(9, User::count());
        $this->assertSame(1, InvitationCode::count());
        $this->assertSame(1, Exercise::where('name', 'ケーブルクロス')->count());
        $this->assertSame(1, Exercise::where('name', '腹筋ローラー')->count());
    }

    #[Test]
    public function local環境以外では作成されない(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame(0, InvitationCode::count());

        // 本番では db:seed が確認を求めるため、シーダーを直接実行する
        $this->app['env'] = 'production';
        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertSame(1, User::count());
    }

    #[Test]
    public function パスワードの設定がないとエラーになる(): void
    {
        config(['trainingmax.seed.dev_user_password' => null]);
        $this->seed(DatabaseSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_DEV_USER_PASSWORD');

        $this->seed(DevelopmentSeeder::class);
    }
}
