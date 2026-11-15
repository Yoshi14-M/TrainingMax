<?php

namespace Tests\Feature\Exercises;

use App\Models\Exercise;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExercisePolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ジム共通種目の登録は有効なスタッフだけができる(): void
    {
        $gym = Gym::factory()->create();

        $this->assertTrue(User::factory()->owner($gym)->create()->can('createGym', Exercise::class));
        $this->assertTrue(User::factory()->trainer($gym)->create()->can('createGym', Exercise::class));
        $this->assertFalse(User::factory()->trainer($gym)->suspended()->create()->can('createGym', Exercise::class));
        $this->assertFalse(User::factory()->client($gym)->create()->can('createGym', Exercise::class));
    }

    #[Test]
    public function 個人種目の登録は利用者本人が自分の分だけできる(): void
    {
        $gym = Gym::factory()->create();
        $client = User::factory()->client($gym)->create();

        $this->assertTrue($client->can('createPersonal', [Exercise::class, $client]));
        $this->assertFalse($client->can('createPersonal', [Exercise::class, User::factory()->client($gym)->create()]));
        $this->assertFalse(User::factory()->trainer($gym)->create()->can('createPersonal', [Exercise::class, $client]));
        $this->assertFalse(User::factory()->owner($gym)->create()->can('createPersonal', [Exercise::class, $client]));
    }

    #[Test]
    public function デフォルト種目は誰も編集も削除もできない(): void
    {
        $gym = Gym::factory()->create();
        $default = Exercise::factory()->create();

        foreach ([User::factory()->owner($gym)->create(), User::factory()->trainer($gym)->create(), User::factory()->client($gym)->create()] as $user) {
            $this->assertFalse($user->can('update', $default));
            $this->assertFalse($user->can('delete', $default));
        }
    }

    #[Test]
    public function ジム共通種目はそのジムの有効なスタッフだけが編集と削除ができる(): void
    {
        $gym = Gym::factory()->create();
        $exercise = Exercise::factory()->gym($gym)->create();

        $this->assertTrue(User::factory()->trainer($gym)->create()->can('update', $exercise));
        $this->assertTrue(User::factory()->owner($gym)->create()->can('delete', $exercise));
        $this->assertFalse(User::factory()->trainer()->create()->can('update', $exercise));
        $this->assertFalse(User::factory()->trainer($gym)->suspended()->create()->can('update', $exercise));
        $this->assertFalse(User::factory()->client($gym)->create()->can('update', $exercise));
        $this->assertFalse(User::factory()->client($gym)->create()->can('delete', $exercise));
    }

    #[Test]
    public function 個人種目は持ち主本人だけが編集と削除ができる(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->client($gym)->create();
        $exercise = Exercise::factory()->personal($owner)->create();

        $this->assertTrue($owner->can('update', $exercise));
        $this->assertTrue($owner->can('delete', $exercise));
        $this->assertFalse(User::factory()->client($gym)->create()->can('update', $exercise));
        $this->assertFalse(User::factory()->trainer($gym)->create()->can('update', $exercise));
        $this->assertFalse(User::factory()->owner($gym)->create()->can('delete', $exercise));
    }
}
