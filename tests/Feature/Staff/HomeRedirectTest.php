<?php

namespace Tests\Feature\Staff;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 利用者は自分のホームへ移る(): void
    {
        $client = User::factory()->client(Gym::factory()->create())->create();
        $personal = User::factory()->personal()->create();

        $this->actingAs($client)->get('/')->assertRedirect(route('records.home', $client));
        $this->actingAs($personal)->get('/')->assertRedirect(route('records.home', $personal));
    }

    #[Test]
    public function トレーナーとオーナーは利用者一覧へ移る(): void
    {
        $gym = Gym::factory()->create();

        $this->actingAs(User::factory()->trainer($gym)->create())->get('/')->assertRedirect(route('staff.clients.index'));
        $this->actingAs(User::factory()->owner($gym)->create())->get('/')->assertRedirect(route('staff.clients.index'));
    }

    #[Test]
    public function 未ログインはログイン画面へ移る(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    #[Test]
    public function メール未認証の利用者は認証のお願い画面へ移る(): void
    {
        $this->actingAs(User::factory()->personal()->unverified()->create())->get('/')->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function 停止中のトレーナーはログイン画面へ移る(): void
    {
        $this->actingAs(User::factory()->trainer(Gym::factory()->create())->suspended()->create())->get('/')->assertRedirect(route('login'));
    }

    #[Test]
    public function ログインすると利用者はホームへスタッフは利用者一覧へ着く(): void
    {
        $gym = Gym::factory()->create();
        $client = User::factory()->client($gym)->create();
        $trainer = User::factory()->trainer($gym)->create();

        $this->post(route('login.store'), ['email' => $client->email, 'password' => 'password'])->assertRedirect(route('home'));
        $this->get(route('home'))->assertRedirect(route('records.home', $client));
        $this->get(route('records.home', $client))->assertOk();
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $trainer->email, 'password' => 'password'])->assertRedirect(route('home'));
        $this->get(route('home'))->assertRedirect(route('staff.clients.index'));
        $this->get(route('staff.clients.index'))->assertOk();
    }

    #[Test]
    public function ログイン前に開こうとした画面があればログイン後にそこへ戻る(): void
    {
        $gym = Gym::factory()->create();
        $client = User::factory()->client($gym)->create();

        $this->get(route('records.body.index', $client))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $client->email, 'password' => 'password'])
            ->assertRedirect(route('records.body.index', $client));
    }
}
