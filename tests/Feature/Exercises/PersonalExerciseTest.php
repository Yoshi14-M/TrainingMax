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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonalExerciseTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private BodyPart $part;

    private ExerciseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create();
        $this->part = BodyPart::factory()->create(['name' => '胸', 'sort_order' => 1]);
        $this->category = ExerciseCategory::factory()->create([
            'name' => '高重量コンパウンド', 'rep_min' => 6, 'rep_max' => 8, 'rir_min' => 1, 'rir_max' => 2, 'rest_seconds' => 180,
        ]);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function input(array $override = []): array
    {
        return array_merge([
            'body_part_id' => $this->part->id,
            'name' => 'マイ種目',
            'exercise_category_id' => $this->category->id,
        ], $override);
    }

    private function exercise(array $attributes = []): Exercise
    {
        return Exercise::factory()->create(['body_part_id' => $this->part->id, 'exercise_category_id' => $this->category->id, ...$attributes]);
    }

    private function useInRecord(Exercise $exercise, User $user): void
    {
        $session = WorkoutSession::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-11-01']);
        SessionExercise::factory()->create(['workout_session_id' => $session->id, 'exercise_id' => $exercise->id]);
    }

    private function index(User $client): string
    {
        return route('records.exercises.index', $client);
    }

    #[Test]
    public function 個人利用者にはデフォルト種目と本人の個人種目だけが見える(): void
    {
        $personalClient = User::factory()->personal()->create();
        $this->exercise(['name' => 'デフォルト種目']);
        $this->exercise(['name' => '本人の個人種目'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $personalClient->id])->save();
        $this->exercise(['name' => '他人の個人種目'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $this->exercise(['name' => 'ジム共通種目'])->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => $this->gym->id])->save();

        $this->actingAs($personalClient)->get($this->index($personalClient))
            ->assertOk()
            ->assertSee('デフォルト種目')
            ->assertSee('本人の個人種目')
            ->assertDontSee('他人の個人種目')
            ->assertDontSee('ジム共通種目');
    }

    #[Test]
    public function ジム所属の利用者にはジム共通種目も見える(): void
    {
        $this->exercise(['name' => 'ジム共通種目'])->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => $this->gym->id])->save();
        $this->exercise(['name' => '他ジム共通種目'])->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => Gym::factory()->create()->id])->save();

        $this->actingAs($this->client)->get($this->index($this->client))
            ->assertSee('ジム共通種目')
            ->assertDontSee('他ジム共通種目');
    }

    #[Test]
    public function 一覧には部位_層のラベル_分類の推奨値_YouTubeリンクが表示される(): void
    {
        $this->exercise(['name' => 'ベンチ プレス']);

        $this->actingAs($this->client)->get($this->index($this->client))
            ->assertSee('胸')
            ->assertSee('デフォルト')
            ->assertSee('高重量コンパウンド')
            ->assertSee('6〜8回')
            ->assertSee('RIR 1〜2')
            ->assertSee('休憩3分')
            ->assertSee('search_query='.urlencode('ベンチ プレス やり方'), false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    #[Test]
    public function 部位は表示順に並び種目は層と名前の順に並ぶ(): void
    {
        $back = BodyPart::factory()->create(['name' => '背中', 'sort_order' => 2]);
        $this->exercise(['name' => 'ZZ個人'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $this->exercise(['name' => 'BBデフォルト']);
        $this->exercise(['name' => 'AAデフォルト']);
        $this->exercise(['name' => '背中の種目', 'body_part_id' => $back->id]);

        $this->actingAs($this->client)->get($this->index($this->client))
            ->assertSeeInOrder(['胸', 'AAデフォルト', 'BBデフォルト', 'ZZ個人', '背中', '背中の種目']);
    }

    #[Test]
    public function 本人には非表示の個人種目が非表示つきで表示されるが非表示のジム共通種目は表示されない(): void
    {
        $this->exercise(['name' => '非表示の個人', 'is_hidden' => true])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $this->exercise(['name' => '非表示のジム共通', 'is_hidden' => true])->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => $this->gym->id])->save();

        $this->actingAs($this->client)->get($this->index($this->client))
            ->assertSee('非表示の個人')
            ->assertSee('非表示')
            ->assertDontSee('非表示のジム共通');
    }

    #[Test]
    public function スタッフは利用者の種目を閲覧できるが登録フォームは表示されず非表示の種目も出ない(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();
        $this->exercise(['name' => '利用者の個人種目'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $this->exercise(['name' => '非表示の個人', 'is_hidden' => true])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();

        $this->actingAs($trainer)->get($this->index($this->client))
            ->assertOk()
            ->assertSee('利用者の個人種目')
            ->assertDontSee('非表示の個人')
            ->assertDontSee('種目を登録する')
            ->assertDontSee('編集')
            ->assertSee("{$this->client->name}さんの記録を表示中");
    }

    #[Test]
    public function 他の利用者の種目一覧は403になる(): void
    {
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($other)->get($this->index($this->client))->assertForbidden();
    }

    #[Test]
    public function 個人種目を登録できる(): void
    {
        $this->actingAs($this->client)->post(route('records.exercises.store', $this->client), $this->input())
            ->assertRedirect($this->index($this->client))
            ->assertSessionHas('status', '種目を登録しました。');

        $exercise = Exercise::where('name', 'マイ種目')->sole();
        $this->assertSame(ExerciseScope::Personal, $exercise->scope);
        $this->assertSame($this->client->id, $exercise->owner_user_id);
        $this->assertNull($exercise->gym_id);
        $this->assertSame($this->part->id, $exercise->body_part_id);
        $this->assertSame($this->category->id, $exercise->exercise_category_id);
        $this->assertFalse($exercise->is_hidden);
    }

    #[Test]
    public function リクエストにscopeやgym_idやowner_user_idを含めても無視される(): void
    {
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($this->client)->post(route('records.exercises.store', $this->client), $this->input([
            'scope' => 'default', 'gym_id' => $this->gym->id, 'owner_user_id' => $other->id,
        ]))->assertRedirect();

        $exercise = Exercise::where('name', 'マイ種目')->sole();
        $this->assertSame(ExerciseScope::Personal, $exercise->scope);
        $this->assertNull($exercise->gym_id);
        $this->assertSame($this->client->id, $exercise->owner_user_id);
    }

    #[Test]
    public function 種目名は50文字まで登録できて51文字と空はエラーになる(): void
    {
        $this->actingAs($this->client);
        $url = route('records.exercises.store', $this->client);

        $this->post($url, $this->input(['name' => str_repeat('あ', 50)]))->assertSessionHasNoErrors();
        $this->post($url, $this->input(['name' => str_repeat('あ', 51)]))
            ->assertSessionHasErrors(['name' => '種目名は50文字以内で入力してください。']);
        $this->post($url, $this->input(['name' => '']))->assertSessionHasErrors(['name' => '種目名を入力してください。']);
    }

    #[Test]
    public function 重量の刻みは種目の項目ではなく送信されても無視される(): void
    {
        $this->assertFalse(Schema::hasColumn('exercises', 'weight_step'));

        $this->actingAs($this->client)->post(route('records.exercises.store', $this->client), $this->input(['weight_step' => '3']))
            ->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('weight_step', Exercise::where('name', 'マイ種目')->sole()->getAttributes());
    }

    #[Test]
    public function 部位と分類は存在するものを選ぶ必要がある(): void
    {
        $url = route('records.exercises.store', $this->client);

        $this->actingAs($this->client)->post($url, $this->input(['body_part_id' => '']))
            ->assertSessionHasErrors(['body_part_id' => '部位を選択してください。']);
        $this->post($url, $this->input(['body_part_id' => 99999]))->assertSessionHasErrors('body_part_id');
        $this->post($url, $this->input(['exercise_category_id' => '']))
            ->assertSessionHasErrors(['exercise_category_id' => '分類を選択してください。']);
        $this->post($url, $this->input(['exercise_category_id' => 99999]))->assertSessionHasErrors('exercise_category_id');
    }

    #[Test]
    public function 見える範囲に同じ名前の種目があると登録できない(): void
    {
        $this->exercise(['name' => 'デフォルト種目']);
        $this->exercise(['name' => 'ジム共通種目'])->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => $this->gym->id])->save();
        $this->exercise(['name' => '自分の個人種目'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $this->exercise(['name' => '自分の非表示種目', 'is_hidden' => true])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $url = route('records.exercises.store', $this->client);

        foreach (['デフォルト種目', 'ジム共通種目', '自分の個人種目', '自分の非表示種目'] as $name) {
            $this->actingAs($this->client)->post($url, $this->input(['name' => $name]))
                ->assertSessionHasErrors(['name' => '同じ名前の種目が既にあります。']);
        }
    }

    #[Test]
    public function 見えない種目と同じ名前は登録できる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->exercise(['name' => '他人の個人種目'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $other->id])->save();
        $this->exercise(['name' => '他ジム共通種目'])->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => Gym::factory()->create()->id])->save();
        $personal = User::factory()->personal()->create();
        $this->exercise(['name' => 'ジム共通種目'])->forceFill(['scope' => ExerciseScope::Gym, 'gym_id' => $this->gym->id])->save();

        $this->actingAs($this->client)->post(route('records.exercises.store', $this->client), $this->input(['name' => '他人の個人種目']))
            ->assertSessionHasNoErrors();
        $this->post(route('records.exercises.store', $this->client), $this->input(['name' => '他ジム共通種目']))->assertSessionHasNoErrors();
        // ジム未所属の個人利用者には、ジム共通種目は見えない
        $this->actingAs($personal)->post(route('records.exercises.store', $personal), $this->input(['name' => 'ジム共通種目']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function 英字の大文字小文字は同じ名前とみなされ濁点は区別される(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('照合順序に依存するため MySQL 専用です（DB_CONNECTION=mysql を指定してください）。');
        }

        $this->exercise(['name' => 'Bench'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $this->exercise(['name' => 'バックスクワット'])->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $url = route('records.exercises.store', $this->client);

        $this->actingAs($this->client)->post($url, $this->input(['name' => 'bench']))->assertSessionHasErrors('name');
        $this->post($url, $this->input(['name' => 'ハックスクワット']))->assertSessionHasNoErrors();
    }

    #[Test]
    public function スタッフや他の利用者は個人種目を登録できない(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();
        $other = User::factory()->client($this->gym)->create();
        $url = route('records.exercises.store', $this->client);

        $this->actingAs($trainer)->post($url, $this->input())->assertForbidden();
        $this->actingAs($other)->post($url, $this->input())->assertForbidden();

        $this->assertSame(0, Exercise::where('name', 'マイ種目')->count());
    }

    #[Test]
    public function 認可は入力の検証より先に行われる(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();

        $this->actingAs($trainer)->post(route('records.exercises.store', $this->client), [])->assertForbidden();
    }

    #[Test]
    public function 個人種目を編集できる(): void
    {
        $exercise = $this->exercise(['name' => '旧名']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $newCategory = ExerciseCategory::factory()->create();

        $this->actingAs($this->client)->put(route('records.exercises.update', [$this->client, $exercise]), $this->input([
            'name' => '新名', 'exercise_category_id' => $newCategory->id, 'is_hidden' => '1',
        ]))->assertRedirect($this->index($this->client).'#exercise-'.$exercise->id)->assertSessionHas('status', '種目を更新しました。');

        $exercise->refresh();
        $this->assertSame('新名', $exercise->name);
        $this->assertSame($newCategory->id, $exercise->exercise_category_id);
        $this->assertTrue($exercise->is_hidden);
        $this->assertSame(ExerciseScope::Personal, $exercise->scope);
        $this->assertSame($this->client->id, $exercise->owner_user_id);
    }

    #[Test]
    public function 同じ名前のまま他の項目だけを更新できる(): void
    {
        $exercise = $this->exercise(['name' => 'そのまま']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $newCategory = ExerciseCategory::factory()->create();

        $this->actingAs($this->client)->put(route('records.exercises.update', [$this->client, $exercise]), $this->input(['name' => 'そのまま', 'exercise_category_id' => $newCategory->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($newCategory->id, $exercise->fresh()->exercise_category_id);
    }

    #[Test]
    public function 他の種目と同じ名前には変更できない(): void
    {
        $this->exercise(['name' => 'デフォルト種目']);
        $exercise = $this->exercise(['name' => '自分の種目']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();

        $this->actingAs($this->client)->put(route('records.exercises.update', [$this->client, $exercise]), $this->input(['name' => 'デフォルト種目']))
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function 非表示の個人種目を再表示できる(): void
    {
        $exercise = $this->exercise(['name' => '非表示の種目', 'is_hidden' => true]);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();

        $this->actingAs($this->client)->put(route('records.exercises.update', [$this->client, $exercise]), $this->input(['name' => '非表示の種目']));

        $this->assertFalse($exercise->fresh()->is_hidden);
    }

    #[Test]
    public function 他人の種目やデフォルト種目は編集も削除もできず404になる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $others = $this->exercise(['name' => '他人の種目']);
        $others->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $other->id])->save();
        $default = $this->exercise(['name' => 'デフォルト種目']);

        foreach ([$others, $default] as $target) {
            $this->actingAs($this->client)->put(route('records.exercises.update', [$this->client, $target->id]), $this->input())->assertNotFound();
            $this->actingAs($this->client)->delete(route('records.exercises.destroy', [$this->client, $target->id]))->assertNotFound();
        }

        $this->assertSame('他人の種目', $others->fresh()->name);
        $this->assertTrue(Exercise::whereKey($default->id)->exists());
    }

    #[Test]
    public function スタッフは利用者の個人種目を編集も削除もできない(): void
    {
        $trainer = User::factory()->trainer($this->gym)->create();
        $exercise = $this->exercise(['name' => '利用者の種目']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();

        $this->actingAs($trainer)->put(route('records.exercises.update', [$this->client, $exercise]), $this->input(['name' => '変更']))->assertForbidden();
        $this->actingAs($trainer)->delete(route('records.exercises.destroy', [$this->client, $exercise]))->assertForbidden();

        $this->assertSame('利用者の種目', $exercise->fresh()->name);
    }

    #[Test]
    public function 記録で使われていない個人種目は削除される(): void
    {
        $exercise = $this->exercise(['name' => '未使用']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();

        $this->actingAs($this->client)->delete(route('records.exercises.destroy', [$this->client, $exercise]))
            ->assertRedirect($this->index($this->client))
            ->assertSessionHas('status', '種目を削除しました。');

        $this->assertNull(Exercise::find($exercise->id));
    }

    #[Test]
    public function 記録で使われている個人種目は削除されず非表示になる(): void
    {
        $exercise = $this->exercise(['name' => '使用中']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $this->useInRecord($exercise, $this->client);

        $this->actingAs($this->client)->delete(route('records.exercises.destroy', [$this->client, $exercise]))
            ->assertSessionHas('status', '記録で使われているため、削除せず非表示にしました。');

        $this->assertTrue($exercise->fresh()->is_hidden);
        $this->assertSame(1, SessionExercise::where('exercise_id', $exercise->id)->count());
    }

    #[Test]
    public function 編集の指定があると該当の種目の編集フォームが表示される(): void
    {
        $exercise = $this->exercise(['name' => '編集する種目']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();

        $this->actingAs($this->client)->get($this->index($this->client).'?edit='.$exercise->id)
            ->assertOk()
            ->assertSee('value="編集する種目"', false)
            ->assertSee('更新');
        $this->get($this->index($this->client))->assertDontSee('value="編集する種目"', false);
    }

    #[Test]
    public function 編集の指定がデフォルト種目の場合はフォームを表示しない(): void
    {
        $default = $this->exercise(['name' => 'デフォルト種目']);

        $this->actingAs($this->client)->get($this->index($this->client).'?edit='.$default->id)
            ->assertOk()
            ->assertDontSee('value="デフォルト種目"', false);
    }

    private const FORM_OPEN = '/<details class="mb-6"\s+open/';

    #[Test]
    public function 登録フォームは通常は閉じている(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            self::FORM_OPEN,
            $this->actingAs($this->client)->get($this->index($this->client))->getContent(),
        );
    }

    #[Test]
    public function 登録の直後は登録フォームが開いた状態で完了メッセージが表示される(): void
    {
        $this->actingAs($this->client)->post(route('records.exercises.store', $this->client), $this->input())
            ->assertSessionHas('keep_form_open', true);

        $html = $this->get($this->index($this->client))->getContent();

        $this->assertMatchesRegularExpression(self::FORM_OPEN, $html);
        $this->assertStringContainsString('種目を登録しました。', $html);
        // 表示した後は、次の表示で閉じる
        $this->assertDoesNotMatchRegularExpression(self::FORM_OPEN, $this->get($this->index($this->client))->getContent());
    }

    #[Test]
    public function 登録の入力エラーのときは登録フォームが開いた状態でエラーが表示される(): void
    {
        $this->actingAs($this->client)->from($this->index($this->client))
            ->post(route('records.exercises.store', $this->client), $this->input(['name' => '']));

        // 注意：assertSessionHasErrors() を先に呼ぶとフラッシュデータが消えるため、画面の内容で確認する
        $html = $this->get($this->index($this->client))->getContent();

        $this->assertMatchesRegularExpression(self::FORM_OPEN, $html);
        $this->assertStringContainsString('種目名を入力してください。', $html);
    }

    #[Test]
    public function 重複エラーのときも登録フォームが開いた状態で表示される(): void
    {
        $this->exercise(['name' => 'デフォルト種目']);

        $this->actingAs($this->client)->from($this->index($this->client))
            ->post(route('records.exercises.store', $this->client), $this->input(['name' => 'デフォルト種目']));

        $html = $this->get($this->index($this->client))->getContent();

        $this->assertMatchesRegularExpression(self::FORM_OPEN, $html);
        $this->assertStringContainsString('同じ名前の種目が既にあります。', $html);
    }

    #[Test]
    public function 画面にBladeの記述が文字として表示されない(): void
    {
        $html = $this->actingAs($this->client)->get($this->index($this->client))->getContent();

        $this->assertStringNotContainsString('$errors', $html);
        $this->assertStringNotContainsString('$editingId', $html);
        $this->assertStringNotContainsString('@if', $html);
        $this->assertStringNotContainsString('@open', $html);
    }

    #[Test]
    public function 編集のリンクと更新後の戻り先は種目の位置を指しページ上部に移動しない(): void
    {
        $exercise = $this->exercise(['name' => '編集する種目']);
        $exercise->forceFill(['scope' => ExerciseScope::Personal, 'owner_user_id' => $this->client->id])->save();
        $anchor = '#exercise-'.$exercise->id;

        $this->actingAs($this->client)->get($this->index($this->client))
            ->assertSee('id="exercise-'.$exercise->id.'"', false)
            ->assertSee('?edit='.$exercise->id.$anchor, false);

        $this->from($this->index($this->client).'?edit='.$exercise->id)
            ->put(route('records.exercises.update', [$this->client, $exercise]), $this->input(['name' => '']))
            ->assertRedirect($this->index($this->client).'?edit='.$exercise->id.$anchor)
            ->assertSessionHasErrors('name');
    }
}
