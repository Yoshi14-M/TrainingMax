<?php

namespace Tests\Feature\Authorization;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RegistersProbeRoutes;
use Tests\TestCase;

class RecordsAccessTest extends TestCase
{
    use RefreshDatabase;
    use RegistersProbeRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerProbeRoutes();
    }

    #[Test]
    public function 利用者は自分の記録を開ける(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get("/clients/{$client->id}/probe")->assertOk()->assertSee("records:{$client->id}");
    }

    #[Test]
    public function 利用者は他の利用者の記録を開くと403になる(): void
    {
        $gym = Gym::factory()->create();
        $a = User::factory()->client($gym)->create();
        $b = User::factory()->client($gym)->create();

        $this->actingAs($a)->get("/clients/{$b->id}/probe")->assertForbidden();
        $this->actingAs($a)->post("/clients/{$b->id}/probe")->assertForbidden();
    }

    #[Test]
    public function 同じジムのスタッフは記録を開ける(): void
    {
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();
        $client = User::factory()->client($gym)->create();

        $this->actingAs($trainer)->get("/clients/{$client->id}/probe")->assertOk();
        $this->actingAs($trainer)->post("/clients/{$client->id}/probe")->assertOk();
    }

    #[Test]
    public function 他のジムのスタッフは記録を開くと403になる(): void
    {
        $trainer = User::factory()->trainer()->create();
        $client = User::factory()->client()->create();

        $this->actingAs($trainer)->get("/clients/{$client->id}/probe")->assertForbidden();
    }

    #[Test]
    public function スタッフが個人利用者の記録を開くと403になる(): void
    {
        $trainer = User::factory()->trainer()->create();
        $personal = User::factory()->personal()->create();

        $this->actingAs($trainer)->get("/clients/{$personal->id}/probe")->assertForbidden();
    }

    #[Test]
    public function 紐付け解除後はスタッフが元の利用者の記録を開くと403になる(): void
    {
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();
        $client = User::factory()->client($gym)->create();

        $this->actingAs($trainer)->get("/clients/{$client->id}/probe")->assertOk();

        $client->forceFill(['gym_id' => null])->save();

        $this->actingAs($trainer)->get("/clients/{$client->id}/probe")->assertForbidden();
    }

    #[Test]
    public function スタッフのIDを指定すると404になる(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner($gym)->create();
        $trainer = User::factory()->trainer($gym)->create();

        $this->actingAs($owner)->get("/clients/{$trainer->id}/probe")->assertNotFound();
        $this->actingAs($owner)->get("/clients/{$owner->id}/probe")->assertNotFound();
    }

    #[Test]
    public function 存在しない利用者のIDは404になる(): void
    {
        $this->actingAs(User::factory()->trainer()->create())->get('/clients/999999/probe')->assertNotFound();
    }

    #[Test]
    public function 未ログインはログイン画面へ移る(): void
    {
        $client = User::factory()->client()->create();

        $this->get("/clients/{$client->id}/probe")->assertRedirect(route('login'));
    }

    #[Test]
    public function メール未認証の利用者は認証のお願い画面へ移る(): void
    {
        $client = User::factory()->client()->unverified()->create();

        $this->actingAs($client)->get("/clients/{$client->id}/probe")->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function 権限がない場合の画面に説明が表示される(): void
    {
        $a = User::factory()->personal()->create();
        $b = User::factory()->personal()->create();

        $this->actingAs($a)->get("/clients/{$b->id}/probe")
            ->assertForbidden()
            ->assertSee('この画面を表示する権限がありません。');
    }

    #[Test]
    public function 認可は入力値の検証より先に行われる(): void
    {
        $gym = Gym::factory()->create();
        $a = User::factory()->client($gym)->create();
        $b = User::factory()->client($gym)->create();

        // 権限がなければ、入力が不正でも検証エラー（リダイレクト）ではなく403になる
        $this->actingAs($a)->post("/clients/{$b->id}/probe-validated", [])->assertForbidden();

        // 権限があれば、入力が不正なら検証エラーになる
        $this->actingAs($a)->post("/clients/{$a->id}/probe-validated", [])->assertSessionHasErrors('value');
        $this->actingAs($a)->post("/clients/{$a->id}/probe-validated", ['value' => 'x'])->assertOk();
    }
}
