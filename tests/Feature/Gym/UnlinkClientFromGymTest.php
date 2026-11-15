<?php

namespace Tests\Feature\Gym;

use App\Enums\ExerciseScope;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\InvitationCode;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class UnlinkClientFromGymTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    private Gym $gym;

    private User $trainer;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->trainer = User::factory()->trainer($this->gym)->create();
        $this->client = User::factory()->client($this->gym)->create();
        $this->fakeAuditLog();
    }

    private function gymExercise(string $name = 'ケーブルクロス', array $attributes = []): Exercise
    {
        return Exercise::factory()->gym($this->gym)->create(['name' => $name, ...$attributes]);
    }

    private function personalExercise(string $name, array $attributes = []): Exercise
    {
        return Exercise::factory()->personal($this->client)->create(['name' => $name, ...$attributes]);
    }

    /**
     * 利用者の記録（セッション・種目・セット）を作る。
     *
     * @param  array<int, int|float>  $weights  セットの重量（セット番号は1から順に付ける）
     */
    private function record(User $user, Exercise $exercise, string $date, array $weights = [50, 55]): SessionExercise
    {
        $session = WorkoutSession::query()->where('user_id', $user->id)->where('performed_on', $date)->first()
            ?? WorkoutSession::factory()->create(['user_id' => $user->id, 'performed_on' => $date]);

        $sessionExercise = SessionExercise::factory()->create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
        ]);

        foreach ($weights as $index => $weight) {
            WorkoutSet::factory()->create([
                'session_exercise_id' => $sessionExercise->id,
                'set_number' => $index + 1,
                'weight' => $weight,
            ]);
        }

        return $sessionExercise;
    }

    private function unlinkAsStaff(): void
    {
        $this->actingAs($this->trainer)->delete(route('staff.clients.membership.destroy', $this->client))->assertRedirect();
    }

    #[Test]
    public function 同名の個人種目がなければジム共通種目が個人種目としてコピーされ記録の参照先が付け替わる(): void
    {
        $part = BodyPart::factory()->create();
        $category = ExerciseCategory::factory()->create();
        $gymExercise = $this->gymExercise('ケーブルクロス', [
            'body_part_id' => $part->id,
            'exercise_category_id' => $category->id,
            'is_hidden' => true,
        ]);
        $sessionExercise = $this->record($this->client, $gymExercise, '2026-11-01');

        $this->unlinkAsStaff();

        $copy = Exercise::where('scope', ExerciseScope::Personal)->where('owner_user_id', $this->client->id)->sole();
        $this->assertSame('ケーブルクロス', $copy->name);
        $this->assertNull($copy->gym_id);
        $this->assertSame($part->id, $copy->body_part_id);
        $this->assertSame($category->id, $copy->exercise_category_id);
        $this->assertTrue($copy->is_hidden);
        $this->assertSame($copy->id, $sessionExercise->fresh()->exercise_id);
        $this->assertSame(2, $sessionExercise->sets()->count());
        $this->assertNull($this->client->fresh()->gym_id);
        $this->assertTrue(Exercise::whereKey($gymExercise->id)->exists());
    }

    #[Test]
    public function 同名の個人種目があればコピーせずその個人種目にまとめられ分類は個人種目側のまま(): void
    {
        $personalCategory = ExerciseCategory::factory()->create();
        $gymExercise = $this->gymExercise('ベンチプレス');
        $personal = $this->personalExercise('ベンチプレス', ['exercise_category_id' => $personalCategory->id]);
        $sessionExercise = $this->record($this->client, $gymExercise, '2026-11-01');

        $this->unlinkAsStaff();

        $this->assertSame(1, Exercise::where('owner_user_id', $this->client->id)->count());
        $this->assertSame($personal->id, $sessionExercise->fresh()->exercise_id);
        $this->assertSame($personalCategory->id, $personal->fresh()->exercise_category_id);
        $this->assertNull($this->client->fresh()->gym_id);
    }

    #[Test]
    public function 同じセッションに両方の記録がある場合はセットが個人種目側の末尾に移される(): void
    {
        $gymExercise = $this->gymExercise('ベンチプレス');
        $personal = $this->personalExercise('ベンチプレス');
        $gymSide = $this->record($this->client, $gymExercise, '2026-11-01', [70, 75]);
        $personalSide = $this->record($this->client, $personal, '2026-11-01', [50, 55, 60]);

        $this->unlinkAsStaff();

        $this->assertNull(SessionExercise::find($gymSide->id));
        $this->assertSame(
            [1 => '50.00', 2 => '55.00', 3 => '60.00', 4 => '70.00', 5 => '75.00'],
            $personalSide->sets()->orderBy('set_number')->pluck('weight', 'set_number')->all(),
        );
        $this->assertSame(1, SessionExercise::count());
    }

    #[Test]
    public function 複数のセッションの記録も種目ごとに付け替えられる(): void
    {
        $gymExercise = $this->gymExercise('ケーブルクロス');
        $a = $this->record($this->client, $gymExercise, '2026-11-01');
        $b = $this->record($this->client, $gymExercise, '2026-11-05');

        $this->unlinkAsStaff();

        $copy = Exercise::where('owner_user_id', $this->client->id)->sole();
        $this->assertSame($copy->id, $a->fresh()->exercise_id);
        $this->assertSame($copy->id, $b->fresh()->exercise_id);
    }

    #[Test]
    public function コピーとまとめが混在しても件数が操作ログに記録される(): void
    {
        $copied = $this->gymExercise('ケーブルクロス');
        $merged = $this->gymExercise('ベンチプレス');
        $this->personalExercise('ベンチプレス');
        $this->record($this->client, $copied, '2026-11-01');
        $this->record($this->client, $merged, '2026-11-02');

        $this->unlinkAsStaff();

        $this->assertSame(
            [['message' => 'client.unlinked', 'context' => [
                'actor_id' => $this->trainer->id, 'client_id' => $this->client->id, 'copied' => 1, 'merged' => 1,
            ]]],
            $this->auditRecords(),
        );
    }

    #[Test]
    public function 記録で使っていないジム共通種目はコピーされない(): void
    {
        $this->gymExercise('未使用の種目');
        $used = $this->gymExercise('使用した種目');
        $this->record($this->client, $used, '2026-11-01');

        $this->unlinkAsStaff();

        $this->assertSame(['使用した種目'], Exercise::where('owner_user_id', $this->client->id)->pluck('name')->all());
    }

    #[Test]
    public function デフォルト種目と他の利用者の記録や個人種目は影響を受けない(): void
    {
        $gymExercise = $this->gymExercise('ケーブルクロス');
        $default = Exercise::factory()->create(['name' => 'デフォルト種目']);
        $other = User::factory()->client($this->gym)->create();
        $otherRecord = $this->record($other, $gymExercise, '2026-11-01');
        $this->record($this->client, $default, '2026-11-01');
        $this->record($this->client, $gymExercise, '2026-11-02');

        $this->unlinkAsStaff();

        $this->assertSame($gymExercise->id, $otherRecord->fresh()->exercise_id);
        $this->assertSame($this->gym->id, $other->fresh()->gym_id);
        $this->assertSame(0, Exercise::where('owner_user_id', $other->id)->count());
        $this->assertSame(
            $default->id,
            SessionExercise::whereHas('workoutSession', fn ($q) => $q->where('user_id', $this->client->id)->where('performed_on', '2026-11-01'))->value('exercise_id'),
        );
    }

    #[Test]
    public function 解除後はスタッフが記録にアクセスできなくなるが記録は利用者に残る(): void
    {
        $this->record($this->client, $this->gymExercise(), '2026-11-01');
        $this->assertTrue($this->trainer->canAccessRecordsOf($this->client));

        $this->unlinkAsStaff();

        $this->assertFalse($this->trainer->canAccessRecordsOf($this->client->fresh()));
        $this->assertTrue($this->client->fresh()->canAccessRecordsOf($this->client->fresh()));
        $this->assertSame(1, WorkoutSession::where('user_id', $this->client->id)->count());
    }

    #[Test]
    public function 本人が解除できる(): void
    {
        $this->record($this->client, $this->gymExercise(), '2026-11-01');

        $this->actingAs($this->client)->delete(route('gym-link.destroy'))
            ->assertRedirect(route('gym-link.show'))
            ->assertSessionHas('status', 'ジムとの紐付けを解除しました。');

        $this->assertNull($this->client->fresh()->gym_id);
        $this->assertSame(1, Exercise::where('owner_user_id', $this->client->id)->count());
        $this->assertSame(
            ['actor_id' => $this->client->id, 'client_id' => $this->client->id, 'copied' => 1, 'merged' => 0],
            $this->auditRecords()[0]['context'],
        );
    }

    #[Test]
    public function 解除前の確認画面に注意が表示される(): void
    {
        $this->actingAs($this->client)->get(route('gym-link.show'))
            ->assertOk()
            ->assertSee('記録は利用者に残り、個人利用者として使い続けられます', false)
            ->assertSee('ジムのトレーナーは記録を閲覧できなくなります', false);
    }

    #[Test]
    public function 他のジムのスタッフは解除できない(): void
    {
        $other = User::factory()->trainer()->create();

        $this->actingAs($other)->delete(route('staff.clients.membership.destroy', $this->client))->assertForbidden();

        $this->assertSame($this->gym->id, $this->client->fresh()->gym_id);
    }

    #[Test]
    public function 利用者や停止中のスタッフは他の利用者を解除できない(): void
    {
        $member = User::factory()->client($this->gym)->create();
        $suspended = User::factory()->trainer($this->gym)->suspended()->create();

        $this->actingAs($member)->delete(route('staff.clients.membership.destroy', $this->client))->assertForbidden();
        $this->actingAs($suspended)->delete(route('staff.clients.membership.destroy', $this->client))->assertRedirect(route('login'));

        $this->assertSame($this->gym->id, $this->client->fresh()->gym_id);
    }

    #[Test]
    public function ジム未所属の利用者は解除の対象にならない(): void
    {
        $personal = User::factory()->personal()->create();

        $this->actingAs($this->trainer)->delete(route('staff.clients.membership.destroy', $personal))->assertForbidden();
        $this->actingAs($personal)->delete(route('gym-link.destroy'))->assertForbidden();
    }

    #[Test]
    public function スタッフのIDを指定すると404になる(): void
    {
        $this->actingAs($this->trainer)
            ->delete(route('staff.clients.membership.destroy', $this->trainer->id))
            ->assertNotFound();
    }

    #[Test]
    public function 解除済みの利用者を再度解除することはできない(): void
    {
        $this->unlinkAsStaff();

        $this->actingAs($this->trainer)->delete(route('staff.clients.membership.destroy', $this->client))->assertForbidden();
    }

    #[Test]
    public function 解除後に再び招待コードで紐付けられる(): void
    {
        $this->actingAs($this->client)->delete(route('gym-link.destroy'));
        $invitation = InvitationCode::factory()->create(['gym_id' => $this->gym->id, 'issued_by' => $this->trainer->id]);

        $this->actingAs($this->client->fresh())->post(route('gym-link.confirm'), ['code' => $invitation->code]);
        $this->post(route('gym-link.store'), ['consent' => '1']);

        $this->assertSame($this->gym->id, $this->client->fresh()->gym_id);
    }

    #[Test]
    public function 途中で失敗した場合はすべて取り消される(): void
    {
        $gymExercise = $this->gymExercise('ケーブルクロス');
        $sessionExercise = $this->record($this->client, $gymExercise, '2026-11-01');
        Exercise::creating(function () {
            throw new RuntimeException('コピーの途中で失敗');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->trainer)->delete(route('staff.clients.membership.destroy', $this->client));
            $this->fail('例外が発生するはずです。');
        } catch (RuntimeException) {
            // 想定どおり
        }

        $this->assertSame($this->gym->id, $this->client->fresh()->gym_id);
        $this->assertSame($gymExercise->id, $sessionExercise->fresh()->exercise_id);
        $this->assertSame(0, Exercise::where('owner_user_id', $this->client->id)->count());
        $this->assertSame([], $this->auditRecords());
    }

    #[Test]
    public function 英字の大文字小文字が違う同名の個人種目にもまとめられる(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('照合順序に依存するため MySQL 専用です（DB_CONNECTION=mysql を指定してください）。');
        }

        $gymExercise = $this->gymExercise('Bench Press');
        $personal = $this->personalExercise('bench press');
        $sessionExercise = $this->record($this->client, $gymExercise, '2026-11-01');

        $this->unlinkAsStaff();

        $this->assertSame($personal->id, $sessionExercise->fresh()->exercise_id);
        $this->assertSame(1, Exercise::where('owner_user_id', $this->client->id)->count());
    }

    #[Test]
    public function 濁点が違う種目は別の種目として扱われコピーされる(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('照合順序に依存するため MySQL 専用です（DB_CONNECTION=mysql を指定してください）。');
        }

        $gymExercise = $this->gymExercise('バックスクワット');
        $this->personalExercise('ハックスクワット');
        $sessionExercise = $this->record($this->client, $gymExercise, '2026-11-01');

        $this->unlinkAsStaff();

        $this->assertSame(2, Exercise::where('owner_user_id', $this->client->id)->count());
        $this->assertSame(
            'バックスクワット',
            Exercise::find($sessionExercise->fresh()->exercise_id)->name,
        );
    }
}
