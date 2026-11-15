<?php

namespace Tests\Feature\Seeders;

use App\Enums\ExerciseScope;
use App\Enums\UserRole;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class MasterSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'trainingmax.seed.gym_name' => 'テストジム',
            'trainingmax.seed.owner_name' => 'テストオーナー',
            'trainingmax.seed.owner_email' => 'Owner@Example.com',
            'trainingmax.seed.owner_password' => 'owner-password',
        ]);
    }

    #[Test]
    public function 部位が胸から腹まで6件作成される(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['胸', '背中', '肩', '腕', '脚', '腹'],
            BodyPart::orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5, 6], BodyPart::orderBy('sort_order')->pluck('sort_order')->all());
    }

    #[Test]
    public function 種目分類の推奨値がデータ設計書どおりに作成される(): void
    {
        $this->seed(DatabaseSeeder::class);

        $expected = [
            '高重量コンパウンド' => [6, 8, 1, 2, 180],
            '中重量コンパウンド' => [8, 10, 1, 2, 120],
            '標準アイソレート' => [10, 12, 0, 1, 90],
            '高レップアイソレート' => [12, 15, 0, 0, 60],
        ];

        $this->assertSame(4, ExerciseCategory::count());

        foreach ($expected as $name => $values) {
            $category = ExerciseCategory::where('name', $name)->firstOrFail();
            $this->assertSame(
                $values,
                [$category->rep_min, $category->rep_max, $category->rir_min, $category->rir_max, $category->rest_seconds],
                $name,
            );
        }

        $this->assertSame(
            ['高重量コンパウンド', '中重量コンパウンド', '標準アイソレート', '高レップアイソレート'],
            ExerciseCategory::orderBy('sort_order')->pluck('name')->all(),
        );
    }

    #[Test]
    public function デフォルト種目が部位と分類つきで作成される(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(13, Exercise::count());
        $this->assertSame(13, Exercise::where('scope', ExerciseScope::Default)->count());
        $this->assertSame(0, Exercise::whereNotNull('gym_id')->orWhereNotNull('owner_user_id')->count());

        $bench = Exercise::with(['bodyPart', 'category'])->where('name', 'ベンチプレス')->firstOrFail();
        $this->assertSame('胸', $bench->bodyPart->name);
        $this->assertSame('高重量コンパウンド', $bench->category->name);
        $this->assertFalse($bench->is_hidden);

        $sideRaise = Exercise::with(['bodyPart', 'category'])->where('name', 'サイドレイズ')->firstOrFail();
        $this->assertSame('肩', $sideRaise->bodyPart->name);
        $this->assertSame('高レップアイソレート', $sideRaise->category->name);
    }

    #[Test]
    public function ジムが1件だけ作成される(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Gym::count());
        $this->assertSame('テストジム', Gym::first()->name);
    }

    #[Test]
    public function オーナーが認証済みでジムに所属して作成される(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('role', UserRole::Owner)->sole();
        $this->assertSame('owner@example.com', $owner->email);
        $this->assertSame('テストオーナー', $owner->name);
        $this->assertSame(Gym::first()->id, $owner->gym_id);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertNull($owner->suspended_at);
        $this->assertTrue(Hash::check('owner-password', $owner->password));
    }

    #[Test]
    public function 何度実行しても重複しない(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, BodyPart::count());
        $this->assertSame(4, ExerciseCategory::count());
        $this->assertSame(13, Exercise::count());
        $this->assertSame(1, Gym::count());
        $this->assertSame(1, User::count());
    }

    #[Test]
    public function 既にジムがあれば新しく作らない(): void
    {
        Gym::factory()->create(['name' => '既存ジム']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Gym::count());
        $this->assertSame('既存ジム', Gym::first()->name);
        $this->assertSame(Gym::first()->id, User::where('role', UserRole::Owner)->sole()->gym_id);
    }

    #[Test]
    public function オーナーの設定がないとエラーになる(): void
    {
        config(['trainingmax.seed.owner_email' => null, 'trainingmax.seed.owner_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_OWNER_EMAIL');

        $this->seed(DatabaseSeeder::class);
    }
}
