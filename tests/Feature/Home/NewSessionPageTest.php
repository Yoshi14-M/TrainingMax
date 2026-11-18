<?php

namespace Tests\Feature\Home;

use App\Models\Gym;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class NewSessionPageTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private User $client;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $gym = Gym::factory()->create();
        $this->client = User::factory()->client($gym)->create();
        $this->trainer = User::factory()->trainer($gym)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(string $date): string
    {
        return route('records.sessions.create', $this->client).'?date='.$date;
    }

    #[Test]
    public function 記録のない過去日に作成の確認が表示される(): void
    {
        $this->actingAs($this->client)->get($this->url('2026-11-05'))->assertOk()
            ->assertSee('2026/11/05（木）')
            ->assertSee('この日の記録を作成')
            ->assertSee('name="performed_on" value="2026-11-05"', false)
            ->assertSee(route('records.home', $this->client).'?month=2026-11', false);
    }

    #[Test]
    public function 今日も作成できる(): void
    {
        $this->actingAs($this->client)->get($this->url('2026-11-13'))->assertOk()->assertSee('この日の記録を作成');
    }

    #[Test]
    public function 既にセッションがあればそれを開く(): void
    {
        $this->makeSession($this->client, '2026-11-05');

        $this->actingAs($this->client)->get($this->url('2026-11-05'))
            ->assertRedirect(route('records.sessions.show', [$this->client, '2026-11-05']));
    }

    #[Test]
    public function 確認から作成するとセッションが作られて開く(): void
    {
        $this->actingAs($this->client)->post(route('records.sessions.store', $this->client), ['performed_on' => '2026-11-05'])
            ->assertRedirect(route('records.sessions.show', [$this->client, '2026-11-05']));

        $this->assertSame(1, WorkoutSession::count());
    }

    #[Test]
    public function 未来日や範囲外や不正な日付は作成の確認を表示しない(): void
    {
        $this->actingAs($this->client)->from(route('records.home', $this->client));

        foreach (['2026-11-14', '1999-12-31', 'abc', '2026-02-30', '2026/11/05', ''] as $date) {
            $this->get($this->url($date))->assertRedirect()->assertSessionHasErrors('date');
        }

        $this->assertSame(0, WorkoutSession::count());
    }

    #[Test]
    public function スタッフも利用者の日付に対して作成の確認を開ける(): void
    {
        $this->actingAs($this->trainer)->get($this->url('2026-11-05'))->assertOk()->assertSee('この日の記録を作成');
    }

    #[Test]
    public function 他の利用者や他ジムのスタッフは開けない(): void
    {
        $other = User::factory()->client($this->client->gym)->create();
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($other)->get($this->url('2026-11-05'))->assertForbidden();
        $this->actingAs($outsider)->get($this->url('2026-11-05'))->assertForbidden();
    }
}
