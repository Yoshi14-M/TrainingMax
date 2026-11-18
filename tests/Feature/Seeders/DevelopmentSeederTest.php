<?php

namespace Tests\Feature\Seeders;

use App\Enums\ExerciseScope;
use App\Enums\UserRole;
use App\Models\BodyComposition;
use App\Models\Exercise;
use App\Models\InvitationCode;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
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
    public function 山田太郎のトレーニング記録のサンプルが作成される(): void
    {
        $this->seedAll();

        $client = User::where('email', 'client1@example.com')->firstOrFail();
        $trainer = User::where('email', 'trainer1@example.com')->firstOrFail();

        $this->assertSame(9, WorkoutSession::where('user_id', $client->id)->count());
        $this->assertSame(36, WorkoutSet::count());
        // 鈴木花子の記録が1件ある（利用者一覧の並びの確認用）
        $second = User::where('email', 'client2@example.com')->firstOrFail();
        $this->assertSame(1, WorkoutSession::where('user_id', $second->id)->count());
        $this->assertSame(1, BodyComposition::where('user_id', $second->id)->count());
        // トレーナーが入力した記録と、本人が入力した記録がある
        $this->assertTrue(WorkoutSet::where('created_by', $trainer->id)->exists());
        $this->assertTrue(WorkoutSet::where('created_by', $client->id)->exists());
        // 推定1RMとボリュームは保存時に計算されている
        $this->assertSame(0, WorkoutSet::where('estimated_1rm', '<=', 0)->count());
        $this->assertSame(0, WorkoutSet::where('set_volume', '<=', 0)->count());
    }

    #[Test]
    public function 山田太郎の体組成のサンプルが作成される(): void
    {
        $this->seedAll();

        $client = User::where('email', 'client1@example.com')->firstOrFail();
        $trainer = User::where('email', 'trainer1@example.com')->firstOrFail();

        $this->assertSame(4, BodyComposition::where('user_id', $client->id)->count());
        $this->assertTrue(BodyComposition::where('created_by', $trainer->id)->exists());
        $this->assertSame(0, BodyComposition::whereNull('weight_kg')->count());
        // 周囲径まで入力した記録がある
        $this->assertTrue(BodyComposition::whereNotNull('waist_cm')->exists());
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
        $this->assertSame(10, WorkoutSession::count());
        $this->assertSame(36, WorkoutSet::count());
        $this->assertSame(5, BodyComposition::count());
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
