<?php

namespace Tests\Feature\Exercises;

use App\Enums\ExerciseScope;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GymExerciseTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $trainer;

    private BodyPart $part;

    private ExerciseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->trainer = User::factory()->trainer($this->gym)->create();
        $this->part = BodyPart::factory()->create(['name' => '胸']);
        $this->category = ExerciseCategory::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function input(array $override = []): array
    {
        return array_merge([
            'body_part_id' => $this->part->id,
            'name' => 'ジムの種目',
            'exercise_category_id' => $this->category->id,
        ], $override);
    }

    private function gymExercise(string $name, array $attributes = []): Exercise
    {
        $exercise = Exercise::factory()->create(['body_part_id' => $this->part->id, 'exercise_category_id' => $this->category->id, 'name' => $name, ...$attributes]);
        $exercise->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => $this->gym->id])->save();

        return $exercise;
    }

    #[Test]
    public function スタッフにはデフォルト種目とジム共通種目が見え利用者の個人種目は見えない(): void
    {
        $client = User::factory()->client($this->gym)->create();
        Exercise::factory()->create(['name' => 'デフォルト種目', 'body_part_id' => $this->part->id]);
        $this->gymExercise('ジム共通種目');
        Exercise::factory()->personal($client)->create(['name' => '利用者の個人種目', 'body_part_id' => $this->part->id]);
        Exercise::factory()->gym()->create(['name' => '他ジム共通種目', 'body_part_id' => $this->part->id]);

        $this->actingAs($this->trainer)->get(route('staff.exercises.index'))
            ->assertOk()
            ->assertSee('ジム共通種目の管理')
            ->assertSee('デフォルト種目')
            ->assertSee('ジム共通種目')
            ->assertSee('種目を登録する')
            ->assertDontSee('利用者の個人種目')
            ->assertDontSee('他ジム共通種目');
    }

    #[Test]
    public function 非表示のジム共通種目は管理画面に非表示つきで表示される(): void
    {
        $this->gymExercise('非表示のジム種目', ['is_hidden' => true]);
        Exercise::factory()->hidden()->create(['name' => '非表示のデフォルト']);

        $this->actingAs($this->trainer)->get(route('staff.exercises.index'))
            ->assertSee('非表示のジム種目')
            ->assertSee('非表示')
            ->assertDontSee('非表示のデフォルト');
    }

    #[Test]
    public function トレーナーとオーナーがジム共通種目を登録できる(): void
    {
        $owner = User::factory()->owner($this->gym)->create();

        $this->actingAs($this->trainer)->post(route('staff.exercises.store'), $this->input(['name' => 'トレーナー登録']))
            ->assertRedirect(route('staff.exercises.index'))
            ->assertSessionHas('status', 'ジム共通種目を登録しました。');
        $this->actingAs($owner)->post(route('staff.exercises.store'), $this->input(['name' => 'オーナー登録']))->assertRedirect();

        foreach (['トレーナー登録', 'オーナー登録'] as $name) {
            $exercise = Exercise::where('name', $name)->sole();
            $this->assertSame(ExerciseScope::Gym, $exercise->scope);
            $this->assertSame($this->gym->id, $exercise->gym_id);
            $this->assertNull($exercise->owner_user_id);
        }
    }

    #[Test]
    public function リクエストにscopeやgym_idやowner_user_idを含めても無視される(): void
    {
        $otherGym = Gym::factory()->create();

        $this->actingAs($this->trainer)->post(route('staff.exercises.store'), $this->input([
            'scope' => 'default', 'gym_id' => $otherGym->id, 'owner_user_id' => $this->trainer->id,
        ]));

        $exercise = Exercise::where('name', 'ジムの種目')->sole();
        $this->assertSame(ExerciseScope::Gym, $exercise->scope);
        $this->assertSame($this->gym->id, $exercise->gym_id);
        $this->assertNull($exercise->owner_user_id);
    }

    #[Test]
    public function 入力の境界とエラーは個人種目と同じ(): void
    {
        $this->actingAs($this->trainer);
        $url = route('staff.exercises.store');

        $this->post($url, $this->input(['name' => str_repeat('あ', 50)]))->assertSessionHasNoErrors();
        $this->post($url, $this->input(['name' => str_repeat('あ', 51)]))->assertSessionHasErrors('name');
        $this->post($url, $this->input(['name' => '部位なし', 'body_part_id' => 99999]))->assertSessionHasErrors('body_part_id');
        $this->post($url, $this->input(['name' => '分類なし', 'exercise_category_id' => 99999]))->assertSessionHasErrors('exercise_category_id');
    }

    #[Test]
    public function デフォルト種目やジム共通種目と同じ名前は登録できない(): void
    {
        Exercise::factory()->create(['name' => 'デフォルト種目']);
        $this->gymExercise('ジム共通種目');

        foreach (['デフォルト種目', 'ジム共通種目'] as $name) {
            $this->actingAs($this->trainer)->post(route('staff.exercises.store'), $this->input(['name' => $name]))
                ->assertSessionHasErrors(['name' => '同じ名前の種目が既にあります。']);
        }
    }

    #[Test]
    public function 利用者の個人種目と同じ名前のジム共通種目は登録できる(): void
    {
        $client = User::factory()->client($this->gym)->create();
        Exercise::factory()->personal($client)->create(['name' => '同じ名前']);

        $this->actingAs($this->trainer)->post(route('staff.exercises.store'), $this->input(['name' => '同じ名前']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function 利用者は管理画面を開けず登録も編集も削除もできない(): void
    {
        $client = User::factory()->client($this->gym)->create();
        $exercise = $this->gymExercise('ジム共通種目');

        $this->actingAs($client)->get(route('staff.exercises.index'))->assertForbidden();
        $this->actingAs($client)->post(route('staff.exercises.store'), $this->input())->assertForbidden();
        $this->actingAs($client)->put(route('staff.exercises.update', $exercise), $this->input())->assertForbidden();
        $this->actingAs($client)->delete(route('staff.exercises.destroy', $exercise))->assertForbidden();

        $this->assertSame('ジム共通種目', $exercise->fresh()->name);
    }

    #[Test]
    public function 停止中のスタッフは登録できない(): void
    {
        $suspended = User::factory()->trainer($this->gym)->suspended()->create();

        $this->actingAs($suspended)->post(route('staff.exercises.store'), $this->input())->assertRedirect(route('login'));

        $this->assertSame(0, Exercise::where('name', 'ジムの種目')->count());
    }

    #[Test]
    public function ジム共通種目を編集できる(): void
    {
        $exercise = $this->gymExercise('旧名');

        $this->actingAs($this->trainer)->put(route('staff.exercises.update', $exercise), $this->input(['name' => '新名']))
            ->assertRedirect(route('staff.exercises.index').'#exercise-'.$exercise->id)
            ->assertSessionHas('status', '種目を更新しました。');

        $exercise->refresh();
        $this->assertSame('新名', $exercise->name);
        $this->assertSame(ExerciseScope::Gym, $exercise->scope);
        $this->assertSame($this->gym->id, $exercise->gym_id);
    }

    #[Test]
    public function 分類の変更は記録で使われている種目でもできる(): void
    {
        $exercise = $this->gymExercise('使用中の種目');
        $session = WorkoutSession::factory()->create(['performed_on' => '2026-11-01']);
        SessionExercise::factory()->create(['workout_session_id' => $session->id, 'exercise_id' => $exercise->id]);
        $newCategory = ExerciseCategory::factory()->create();

        $this->actingAs($this->trainer)->put(route('staff.exercises.update', $exercise), $this->input([
            'name' => '使用中の種目', 'exercise_category_id' => $newCategory->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($newCategory->id, $exercise->fresh()->exercise_category_id);
    }

    #[Test]
    public function デフォルト種目と利用者の個人種目は編集も削除もできない(): void
    {
        $client = User::factory()->client($this->gym)->create();
        $default = Exercise::factory()->create(['name' => 'デフォルト種目']);
        $personal = Exercise::factory()->personal($client)->create(['name' => '利用者の種目']);

        foreach ([$default, $personal] as $exercise) {
            $this->actingAs($this->trainer)->put(route('staff.exercises.update', $exercise), $this->input(['name' => '変更']))->assertForbidden();
            $this->actingAs($this->trainer)->delete(route('staff.exercises.destroy', $exercise))->assertForbidden();
        }

        $this->assertSame('デフォルト種目', $default->fresh()->name);
        $this->assertSame('利用者の種目', $personal->fresh()->name);
    }

    #[Test]
    public function 他のジムのジム共通種目は編集も削除もできない(): void
    {
        $other = Exercise::factory()->gym()->create(['name' => '他ジム種目']);

        $this->actingAs($this->trainer)->put(route('staff.exercises.update', $other), $this->input(['name' => '変更']))->assertForbidden();
        $this->actingAs($this->trainer)->delete(route('staff.exercises.destroy', $other))->assertForbidden();
    }

    #[Test]
    public function 記録で使われていないジム共通種目は削除され使われていれば非表示になる(): void
    {
        $unused = $this->gymExercise('未使用');
        $used = $this->gymExercise('使用中');
        $session = WorkoutSession::factory()->create(['performed_on' => '2026-11-01']);
        SessionExercise::factory()->create(['workout_session_id' => $session->id, 'exercise_id' => $used->id]);

        $this->actingAs($this->trainer)->delete(route('staff.exercises.destroy', $unused))
            ->assertSessionHas('status', '種目を削除しました。');
        $this->actingAs($this->trainer)->delete(route('staff.exercises.destroy', $used))
            ->assertSessionHas('status', '記録で使われているため、削除せず非表示にしました。');

        $this->assertNull(Exercise::find($unused->id));
        $this->assertTrue($used->fresh()->is_hidden);
    }

    #[Test]
    public function 非表示にしたジム共通種目は利用者の一覧から消え過去の記録には残る(): void
    {
        $client = User::factory()->client($this->gym)->create();
        $exercise = $this->gymExercise('消える種目');
        $session = WorkoutSession::factory()->create(['user_id' => $client->id, 'performed_on' => '2026-11-01']);
        $sessionExercise = SessionExercise::factory()->create(['workout_session_id' => $session->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($client)->get(route('records.exercises.index', $client))->assertSee('消える種目');

        $this->actingAs($this->trainer)->delete(route('staff.exercises.destroy', $exercise));

        $this->actingAs($client)->get(route('records.exercises.index', $client))->assertDontSee('消える種目');
        $this->assertSame($exercise->id, $sessionExercise->fresh()->exercise_id);
    }

    #[Test]
    public function 非表示のジム共通種目を再表示できる(): void
    {
        $exercise = $this->gymExercise('非表示の種目', ['is_hidden' => true]);

        $this->actingAs($this->trainer)->put(route('staff.exercises.update', $exercise), $this->input(['name' => '非表示の種目']));

        $this->assertFalse($exercise->fresh()->is_hidden);
    }

    #[Test]
    public function ジム共通種目の管理画面ではスタッフが編集と削除を行える(): void
    {
        $exercise = $this->gymExercise('操作する種目');
        Exercise::factory()->create(['name' => 'デフォルト種目', 'body_part_id' => $this->part->id]);

        $response = $this->actingAs($this->trainer)->get(route('staff.exercises.index').'?edit='.$exercise->id);

        $response->assertSee('value="操作する種目"', false)->assertSee('更新');
        $this->assertSame(1, substr_count($response->getContent(), 'name="_method" value="DELETE"'));
    }

    #[Test]
    public function 登録の直後は登録フォームが開いた状態で完了メッセージが表示される(): void
    {
        $this->actingAs($this->trainer)->post(route('staff.exercises.store'), $this->input())
            ->assertRedirect(route('staff.exercises.index'))
            ->assertSessionHas('keep_form_open', true);

        $html = $this->get(route('staff.exercises.index'))->getContent();

        $this->assertMatchesRegularExpression('/<details class="mb-6"\s+open/', $html);
        $this->assertStringContainsString('ジム共通種目を登録しました。', $html);
    }

    #[Test]
    public function 登録の入力エラーのときは登録フォームが開いた状態でエラーが表示される(): void
    {
        $this->actingAs($this->trainer)->from(route('staff.exercises.index'))
            ->post(route('staff.exercises.store'), $this->input(['name' => '']));

        $html = $this->get(route('staff.exercises.index'))->getContent();

        $this->assertMatchesRegularExpression('/<details class="mb-6"\s+open/', $html);
        $this->assertStringContainsString('種目名を入力してください。', $html);
    }

    #[Test]
    public function 利用者の画面のメニューにスタッフ専用の項目は表示されない(): void
    {
        $client = User::factory()->client($this->gym)->create();

        $this->actingAs($client)->get(route('records.exercises.index', $client))
            ->assertOk()
            ->assertDontSee('利用者アカウント発行')
            ->assertDontSee('トレーナー管理')
            ->assertSee('ジム連携');
    }
}
