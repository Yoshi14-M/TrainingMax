<?php

namespace Tests\Feature\Models;

use App\Enums\ExerciseScope;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function visibleNames(User $client): array
    {
        return Exercise::visibleTo($client)->orderBy('name')->pluck('name')->all();
    }

    #[Test]
    public function 個人利用者にはデフォルト種目と本人の個人種目だけが見える(): void
    {
        $client = User::factory()->personal()->create();
        $other = User::factory()->personal()->create();
        $gym = Gym::factory()->create();

        Exercise::factory()->create(['name' => 'デフォルト']);
        Exercise::factory()->personal($client)->create(['name' => '本人の個人']);
        Exercise::factory()->personal($other)->create(['name' => '他人の個人']);
        Exercise::factory()->gym($gym)->create(['name' => 'ジム共通']);

        $this->assertEqualsCanonicalizing(['デフォルト', '本人の個人'], $this->visibleNames($client));
    }

    #[Test]
    public function ジム所属の利用者には自分のジムの共通種目も見える(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $client = User::factory()->client($gym)->create();
        $other = User::factory()->client($gym)->create();

        Exercise::factory()->create(['name' => 'デフォルト']);
        Exercise::factory()->gym($gym)->create(['name' => '自ジム共通']);
        Exercise::factory()->gym($otherGym)->create(['name' => '他ジム共通']);
        Exercise::factory()->personal($client)->create(['name' => '本人の個人']);
        Exercise::factory()->personal($other)->create(['name' => '同ジムの他人の個人']);

        $this->assertEqualsCanonicalizing(['デフォルト', '自ジム共通', '本人の個人'], $this->visibleNames($client));
    }

    #[Test]
    public function 紐付け解除した利用者にはジム共通種目が見えない(): void
    {
        $gym = Gym::factory()->create();
        $client = User::factory()->personal()->create();

        Exercise::factory()->gym($gym)->create(['name' => 'ジム共通']);

        $this->assertSame([], $this->visibleNames($client));
    }

    #[Test]
    public function スタッフにはデフォルト種目と自分のジムの共通種目だけが見える(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();
        $client = User::factory()->client($gym)->create();

        Exercise::factory()->create(['name' => 'デフォルト']);
        Exercise::factory()->gym($gym)->create(['name' => '自ジム共通']);
        Exercise::factory()->gym($otherGym)->create(['name' => '他ジム共通']);
        Exercise::factory()->personal($client)->create(['name' => '利用者の個人']);

        $names = Exercise::visibleToStaff($trainer)->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['デフォルト', '自ジム共通'], $names);
    }

    #[Test]
    public function 非表示の種目は選択対象から外れるが見える範囲には含まれる(): void
    {
        $client = User::factory()->personal()->create();

        Exercise::factory()->create(['name' => '通常']);
        Exercise::factory()->hidden()->create(['name' => '非表示']);

        $this->assertEqualsCanonicalizing(['通常', '非表示'], $this->visibleNames($client));
        $this->assertSame(['通常'], Exercise::visibleTo($client)->selectable()->pluck('name')->all());
    }

    #[Test]
    public function scopeやgym_idやowner_user_idは一括代入できない(): void
    {
        $exercise = (new Exercise)->fill([
            'name' => 'テスト',
            'scope' => 'gym',
            'gym_id' => 1,
            'owner_user_id' => 1,
        ]);

        $this->assertSame('テスト', $exercise->name);
        $this->assertArrayNotHasKey('scope', $exercise->getAttributes());
        $this->assertArrayNotHasKey('gym_id', $exercise->getAttributes());
        $this->assertArrayNotHasKey('owner_user_id', $exercise->getAttributes());
    }

    #[Test]
    public function 層の表示名を返す(): void
    {
        $this->assertSame('デフォルト', ExerciseScope::Default->label());
        $this->assertSame('ジム', ExerciseScope::Gym->label());
        $this->assertSame('個人', ExerciseScope::Personal->label());
    }
}
