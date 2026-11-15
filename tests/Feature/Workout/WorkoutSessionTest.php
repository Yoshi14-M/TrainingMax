<?php

namespace Tests\Feature\Workout;

use App\Actions\Workout\CreateWorkoutSession;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class WorkoutSessionTest extends TestCase
{
    use BuildsWorkouts;
    use FakesAuditLog;
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->trainer = User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
        $this->fakeAuditLog();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(string $date): string
    {
        return route('records.sessions.show', [$this->client, $date]);
    }

    #[Test]
    public function 今日のセッションがなければ作成の確認が表示される(): void
    {
        $this->actingAs($this->client)->get(route('records.sessions.today', $this->client))
            ->assertOk()
            ->assertSee('2026/11/13（金）')
            ->assertSee('今日のトレーニングを始める');
    }

    #[Test]
    public function 今日のセッションがあればそれを開く(): void
    {
        $this->makeSession($this->client, '2026-11-13');

        $this->actingAs($this->client)->get(route('records.sessions.today', $this->client))
            ->assertRedirect($this->url('2026-11-13'));
    }

    #[Test]
    public function 実施日を省略すると今日のセッションが作られる(): void
    {
        $this->actingAs($this->client)->post(route('records.sessions.store', $this->client))
            ->assertRedirect($this->url('2026-11-13'));

        $session = WorkoutSession::sole();
        $this->assertSame($this->client->id, $session->user_id);
        $this->assertSame('2026-11-13', $session->performed_on->toDateString());
        $this->assertSame($this->client->id, $session->created_by);
    }

    #[Test]
    public function 過去日のセッションを作成できる(): void
    {
        $this->actingAs($this->client)->post(route('records.sessions.store', $this->client), ['performed_on' => '2026-11-01'])
            ->assertRedirect($this->url('2026-11-01'));

        $this->assertDatabaseHas('workout_sessions', ['user_id' => $this->client->id, 'performed_on' => '2026-11-01']);
    }

    #[Test]
    public function 実施日は2000年1月1日から今日までで未来日は作成できない(): void
    {
        $url = route('records.sessions.store', $this->client);
        $this->actingAs($this->client);

        $this->post($url, ['performed_on' => '2000-01-01'])->assertSessionHasNoErrors();
        $this->post($url, ['performed_on' => '2026-11-13'])->assertSessionHasNoErrors();
        $this->post($url, ['performed_on' => '1999-12-31'])
            ->assertSessionHasErrors(['performed_on' => '実施日は2000/01/01以降にしてください。']);
        $this->post($url, ['performed_on' => '2026-11-14'])
            ->assertSessionHasErrors(['performed_on' => '未来の日付のセッションは作成できません。']);
        $this->post($url, ['performed_on' => 'abc'])->assertSessionHasErrors('performed_on');
        $this->post($url, ['performed_on' => '2026/11/01'])->assertSessionHasErrors('performed_on');
        $this->post($url, ['performed_on' => '2026-02-30'])->assertSessionHasErrors('performed_on');
    }

    #[Test]
    public function 同じ日付のセッションは作成できない(): void
    {
        $this->makeSession($this->client, '2026-11-01');

        $this->actingAs($this->client)->post(route('records.sessions.store', $this->client), ['performed_on' => '2026-11-01'])
            ->assertSessionHasErrors(['performed_on' => 'この日のセッションは既にあります。']);

        $this->assertSame(1, WorkoutSession::count());
    }

    #[Test]
    public function 他の利用者の同じ日付があっても作成できる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->makeSession($other, '2026-11-01');

        $this->actingAs($this->client)->post(route('records.sessions.store', $this->client), ['performed_on' => '2026-11-01'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, WorkoutSession::count());
    }

    #[Test]
    public function 同時に2回送信されても既にあるセッションを返す(): void
    {
        $first = app(CreateWorkoutSession::class)->handle($this->client, $this->client, '2026-11-01');
        $second = app(CreateWorkoutSession::class)->handle($this->client, $this->client, '2026-11-01');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, WorkoutSession::count());
    }

    #[Test]
    public function スタッフが利用者のセッションを作ると記録の持ち主は利用者で作成者はスタッフになる(): void
    {
        $this->actingAs($this->trainer)->post(route('records.sessions.store', $this->client), ['performed_on' => '2026-11-01'])
            ->assertRedirect($this->url('2026-11-01'));

        $session = WorkoutSession::sole();
        $this->assertSame($this->client->id, $session->user_id);
        $this->assertSame($this->trainer->id, $session->created_by);
    }

    #[Test]
    public function リクエストのuser_idやcreated_byは使われない(): void
    {
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($this->client)->post(route('records.sessions.store', $this->client), [
            'performed_on' => '2026-11-01', 'user_id' => $other->id, 'created_by' => $other->id,
        ]);

        $session = WorkoutSession::sole();
        $this->assertSame($this->client->id, $session->user_id);
        $this->assertSame($this->client->id, $session->created_by);
    }

    #[Test]
    public function 他の利用者や他ジムのスタッフは作成も閲覧もできない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $outsider = User::factory()->trainer()->create();
        $session = $this->makeSession($this->client, '2026-11-01');

        foreach ([$other, $outsider] as $user) {
            $this->actingAs($user)->post(route('records.sessions.store', $this->client), ['performed_on' => '2026-11-02'])->assertForbidden();
            $this->actingAs($user)->get($this->url('2026-11-01'))->assertForbidden();
            $this->actingAs($user)->get(route('records.sessions.today', $this->client))->assertForbidden();
            $this->actingAs($user)->delete($this->url('2026-11-01'))->assertForbidden();
        }

        $this->assertSame(1, WorkoutSession::count());
        $this->assertNotNull(WorkoutSession::find($session->id));
    }

    #[Test]
    public function 紐付け解除後はスタッフがセッションを開けなくなる(): void
    {
        $this->makeSession($this->client, '2026-11-01');
        $this->client->forceFill(['gym_id' => null])->save();

        $this->actingAs($this->trainer)->get($this->url('2026-11-01'))->assertForbidden();
    }

    #[Test]
    public function メール未認証と未ログインはセッションを開けない(): void
    {
        $this->makeSession($this->client, '2026-11-01');
        $unverified = User::factory()->personal()->unverified()->create();

        $this->get($this->url('2026-11-01'))->assertRedirect(route('login'));
        $this->actingAs($unverified)->get(route('records.sessions.today', $unverified))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function セッションの画面は日付つきで表示される(): void
    {
        $this->makeSession($this->client, '2026-11-01');

        $this->actingAs($this->client)->get($this->url('2026-11-01'))
            ->assertOk()
            ->assertSee('2026/11/01（日）')
            ->assertSee('まだ種目が記録されていません');
    }

    #[Test]
    public function 存在しない日付や形式の違う日付は404になる(): void
    {
        $this->makeSession($this->client, '2026-11-01');
        $this->actingAs($this->client);

        foreach (['2026-11-02', '2026-13-45', '2026-02-30', 'abc', '20261101', '2026-1-1', '2026-11-01-'] as $date) {
            $this->get($this->url($date))->assertNotFound();
        }
    }

    #[Test]
    public function 他の利用者のセッションの日付を指定すると404になる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->makeSession($other, '2026-11-05');

        $this->actingAs($this->client)->get($this->url('2026-11-05'))->assertNotFound();
        $this->actingAs($this->trainer)->get($this->url('2026-11-05'))->assertNotFound();
    }

    #[Test]
    public function 前後のセッションへのリンクが表示される(): void
    {
        $this->makeSession($this->client, '2026-10-20');
        $this->makeSession($this->client, '2026-11-01');
        $this->makeSession($this->client, '2026-11-10');
        $other = User::factory()->client($this->gym)->create();
        $this->makeSession($other, '2026-11-02');

        $this->actingAs($this->client)->get($this->url('2026-11-01'))
            ->assertSee($this->url('2026-10-20'), false)
            ->assertSee($this->url('2026-11-10'), false)
            ->assertDontSee('2026-11-02', false);

        $this->get($this->url('2026-10-20'))->assertSee($this->url('2026-11-01'), false);
    }

    #[Test]
    public function スタッフは利用者のセッションを表示できる(): void
    {
        $this->makeSession($this->client, '2026-11-01');

        $this->actingAs($this->trainer)->get($this->url('2026-11-01'))
            ->assertOk()
            ->assertSee('▶ 山田太郎さんの記録を表示中');
    }

    #[Test]
    public function セッションを削除すると種目とセットも削除され操作ログにセット数が記録される(): void
    {
        $exercise = Exercise::factory()->create();
        $session = $this->makeSession($this->client, '2026-11-01');
        $this->addSets($session, $exercise, [[60, 8, 2, 2], [60, 8, 1, 1], [57.5, 8, 1, 1]]);
        $other = $this->makeSession($this->client, '2026-11-02');
        $this->addSets($other, $exercise, [[60, 8, 2, 2]]);

        $this->actingAs($this->trainer)->delete($this->url('2026-11-01'))
            ->assertRedirect(route('records.sessions.today', $this->client))
            ->assertSessionHas('status', 'セッションを削除しました。');

        $this->assertNull(WorkoutSession::find($session->id));
        $this->assertSame(0, SessionExercise::where('workout_session_id', $session->id)->count());
        $this->assertSame(1, WorkoutSet::count());
        $this->assertNotNull(WorkoutSession::find($other->id));
        $this->assertSame(
            [['message' => 'workout_session.deleted', 'context' => [
                'actor_id' => $this->trainer->id, 'client_id' => $this->client->id, 'session_id' => $session->id, 'sets' => 3,
            ]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function 削除の確認に種目数とセット数が表示される(): void
    {
        $session = $this->makeSession($this->client, '2026-11-01');
        $this->addSets($session, Exercise::factory()->create(), [[60, 8, 2, 2], [60, 8, 1, 1]]);
        $this->addSets($session, Exercise::factory()->create(), [[40, 10, 1, 1]]);

        $this->actingAs($this->client)->get($this->url('2026-11-01'))
            ->assertSee('この日の記録（2種目・3セット）をすべて削除します。', false);
    }
}
