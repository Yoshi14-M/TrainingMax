<?php

namespace Tests\Feature\Authorization;

use App\Models\BodyComposition;
use App\Models\CoachingNote;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\InvitationCode;
use App\Models\SessionComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

/**
 * `/clients/{client}` と `/staff` 配下の「すべての」ルートを実際に呼び出し、権限のない人が拒否されることを確認する
 * （セキュリティ設計書10章。ルートを追加しても、このテストが自動でそのルートを対象にする）。
 *
 * 対象のデータは、利用者「山田」（ジムA）が持つ記録・メモ・種目などを実在のIDで用意する。
 * 拒否は 403（または存在を知らせない 404）で、成功・リダイレクト・サーバーエラーは失敗とする。
 */
class AllRoutesAuthorizationTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $victim;

    /** @var array<string, string|int> */
    private array $parameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $owner = User::factory()->owner($this->gym)->create();
        $trainer = User::factory()->trainer($this->gym)->create();
        $this->victim = User::factory()->client($this->gym)->create();

        $session = $this->makeSession($this->victim, '2026-11-12');
        $personalExercise = Exercise::factory()->personal($this->victim)->create();
        $sessionExercise = $this->addSets($session, $personalExercise, [[60, 10, 2, 2]]);

        $comment = new SessionComment(['body' => '本文']);
        $comment->forceFill(['workout_session_id' => $session->id, 'created_by' => $trainer->id])->save();

        $note = new CoachingNote(['category' => 'caution', 'body' => '本文']);
        $note->forceFill([
            'gym_id' => $this->gym->id,
            'client_user_id' => $this->victim->id,
            'created_by' => $trainer->id,
            'updated_by' => $trainer->id,
        ])->save();

        $this->parameters = [
            'client' => $this->victim->id,
            'workoutSession' => '2026-11-12',
            'workoutSet' => $sessionExercise->sets()->firstOrFail()->id,
            'sessionExercise' => $sessionExercise->id,
            'sessionComment' => $comment->id,
            'bodyComposition' => BodyComposition::factory()->create(['user_id' => $this->victim->id])->id,
            'personalExercise' => $personalExercise->id,
            'coachingNote' => $note->id,
            'exercise' => Exercise::factory()->gym($this->gym)->create()->id,
            'invitationCode' => InvitationCode::factory()->create(['gym_id' => $this->gym->id, 'issued_by' => $owner->id])->id,
            'trainer' => $trainer->id,
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function routes(string $prefix): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), $prefix)) {
                continue;
            }

            $uri = (string) preg_replace_callback('/\{(\w+)\??}/', function (array $m): string {
                $this->assertArrayHasKey($m[1], $this->parameters, "テスト用のデータがありません：{$m[1]}");

                return (string) $this->parameters[$m[1]];
            }, $route->uri());

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes[] = [$method, '/'.$uri];
            }
        }

        $this->assertNotEmpty($routes);

        return $routes;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $routes
     */
    private function assertAllRejected(User $attacker, array $routes): void
    {
        foreach ($routes as [$method, $uri]) {
            $status = $this->actingAs($attacker)->call($method, $uri)->getStatusCode();

            $this->assertContains($status, [403, 404], "{$method} {$uri} が {$status} を返しました（403 または 404 が必要）");
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function 記録を見る権限のない人(): array
    {
        return [
            '同じジムの別の利用者' => ['sameGymClient'],
            '個人利用者' => ['personal'],
            '他のジムのトレーナー' => ['otherGymTrainer'],
            '他のジムのオーナー' => ['otherGymOwner'],
            '他のジムの利用者' => ['otherGymClient'],
        ];
    }

    private function attacker(string $kind): User
    {
        $other = Gym::factory()->create();

        return match ($kind) {
            'sameGymClient' => User::factory()->client($this->gym)->create(),
            'personal' => User::factory()->personal()->create(),
            'otherGymTrainer' => User::factory()->trainer($other)->create(),
            'otherGymOwner' => User::factory()->owner($other)->create(),
            'otherGymClient' => User::factory()->client($other)->create(),
        };
    }

    #[Test]
    #[DataProvider('記録を見る権限のない人')]
    public function 権限のない人は利用者の記録のすべてのルートで拒否される(string $kind): void
    {
        $routes = $this->routes('clients/{client}');
        $this->assertGreaterThan(15, count($routes));

        $this->assertAllRejected($this->attacker($kind), $routes);
    }

    #[Test]
    #[DataProvider('記録を見る権限のない人')]
    public function 権限のない人はスタッフ専用のすべてのルートで拒否される(string $kind): void
    {
        // 利用者・個人利用者は全ルート。他のジムのスタッフは、他のジムのデータを扱うルートで拒否される
        $routes = $this->routes('staff');
        $this->assertGreaterThan(15, count($routes));

        $attacker = $this->attacker($kind);

        foreach ($routes as [$method, $uri]) {
            $status = $this->actingAs($attacker)->call($method, $uri)->getStatusCode();

            if ($attacker->isStaff() && $this->isOwnGymOnly($method, $uri)) {
                // 一覧・作成画面・自分のジムへの登録は、他のジムのスタッフでも自分のジムの範囲で動く（対象データが他のジムのものでないルート）
                continue;
            }

            $this->assertContains($status, [403, 404], "{$method} {$uri} が {$status} を返しました（403 または 404 が必要）");
        }
    }

    /**
     * 他のジムのスタッフが開いても、自分のジムの範囲で動くルート（URLに他のジムのデータを含まない）。
     */
    private function isOwnGymOnly(string $method, string $uri): bool
    {
        return in_array($uri, ['/staff/clients', '/staff/clients/create', '/staff/exercises', '/staff/invitation-codes', '/staff/trainers'], true)
            || ($method === 'POST' && in_array($uri, ['/staff/clients', '/staff/exercises', '/staff/invitation-codes', '/staff/trainers'], true));
    }

    #[Test]
    public function トレーナーはトレーナー管理のすべてのルートで拒否される(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();
        $routes = $this->routes('staff/trainers');

        $this->assertGreaterThanOrEqual(4, count($routes));
        $this->assertAllRejected($trainer, $routes);
    }

    #[Test]
    public function 未ログインはすべてのルートでログイン画面へ移る(): void
    {
        foreach ([...$this->routes('clients/{client}'), ...$this->routes('staff')] as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect(route('login'));
        }
    }

    #[Test]
    public function 停止中のトレーナーはすべてのルートでログイン画面へ戻される(): void
    {
        $suspended = User::factory()->trainer($this->gym)->suspended()->create();

        foreach ([...$this->routes('clients/{client}'), ...$this->routes('staff')] as [$method, $uri]) {
            $this->actingAs($suspended)->call($method, $uri)->assertRedirect(route('login'));
        }
    }

    #[Test]
    public function 紐付け解除後は元のジムのスタッフが利用者の記録のすべてのルートで拒否される(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();
        $this->victim->forceFill(['gym_id' => null])->save();

        $routes = $this->routes('clients/{client}');
        $this->assertAllRejected($trainer, $routes);
    }

    #[Test]
    public function 他のジムの利用者の指導メモと個人利用者のデータはスタッフ専用ルートでも扱えない(): void
    {
        $other = User::factory()->trainer(Gym::factory()->create())->create();
        $client = $this->victim;

        $this->actingAs($other)->get("/staff/clients/{$client->id}")->assertForbidden();
        $this->actingAs($other)->post("/staff/clients/{$client->id}/notes", ['category' => 'caution', 'body' => 'x'])->assertForbidden();
        $this->assertSame(1, CoachingNote::query()->count());
    }

    #[Test]
    public function 利用者のIDにスタッフのIDを指定するとすべてのルートで404になる(): void
    {
        $viewer = User::factory()->owner($this->gym)->create();
        $this->parameters['client'] = $viewer->id;

        foreach ([...$this->routes('clients/{client}'), ...$this->routes('staff/clients/{client}')] as [$method, $uri]) {
            $this->actingAs($viewer)->call($method, $uri)->assertNotFound();
        }
    }
}
