<?php

namespace Tests\Feature\Coaching;

use App\Enums\CoachingNoteCategory;
use App\Models\CoachingNote;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class CoachingNoteTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $trainer;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->trainer = User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function note(string $body = 'メモの本文', CoachingNoteCategory $category = CoachingNoteCategory::Caution, ?User $by = null): CoachingNote
    {
        $by ??= $this->trainer;
        $note = new CoachingNote(['category' => $category, 'body' => $body]);
        $note->forceFill([
            'gym_id' => $this->client->gym_id,
            'client_user_id' => $this->client->id,
            'created_by' => $by->id,
            'updated_by' => $by->id,
        ])->save();

        return $note;
    }

    // ---- 表示（S-21） ----

    #[Test]
    public function 利用者詳細に指導メモが分類ごとに表示される(): void
    {
        $this->note('目標のメモ', CoachingNoteCategory::Goal);
        $this->note('注意点のメモ', CoachingNoteCategory::Caution);
        $this->note('怪我のメモ', CoachingNoteCategory::Injury);

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))
            ->assertOk()
            ->assertSeeInOrder(['注意点', '注意点のメモ', '怪我・既往歴', '怪我のメモ', '目標', '目標のメモ'])
            ->assertSee('スタッフだけが見られます。');
    }

    #[Test]
    public function 同じ分類の中は更新日時の新しい順に表示される(): void
    {
        $old = $this->note('古いメモ');
        $old->forceFill(['updated_at' => '2026-11-01 10:00:00'])->saveQuietly();
        $new = $this->note('新しいメモ');
        $new->forceFill(['updated_at' => '2026-11-10 10:00:00'])->saveQuietly();

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))
            ->assertSeeInOrder(['新しいメモ', '古いメモ']);
    }

    #[Test]
    public function 入力者と最終更新者が表示される(): void
    {
        $other = User::factory()->trainer($this->gym)->create(['name' => '佐藤トレーナー']);
        $note = $this->note();
        $note->forceFill(['updated_by' => $other->id])->saveQuietly();

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))
            ->assertSee('入力：田中トレーナー／更新：佐藤トレーナー');
    }

    #[Test]
    public function 停止中のスタッフの名前には停止中と付く(): void
    {
        $suspended = User::factory()->trainer($this->gym)->suspended()->create(['name' => '停止太郎']);
        $this->note('x', CoachingNoteCategory::Other, $suspended);

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))
            ->assertSee('入力：停止太郎（停止中）');
    }

    #[Test]
    public function メモがなければ案内が表示される(): void
    {
        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))
            ->assertSee('指導メモはまだありません。');
    }

    #[Test]
    public function 本文はエスケープされ改行は保たれる(): void
    {
        $this->note("<script>alert('x')</script>\n2行目");

        $response = $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('&lt;script&gt;', false);

        $this->assertStringContainsString('whitespace-pre-wrap', $response->getContent());
    }

    #[Test]
    public function 他の利用者のメモは表示されない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $note = new CoachingNote(['category' => CoachingNoteCategory::Caution, 'body' => '他の人のメモ']);
        $note->forceFill(['gym_id' => $this->gym->id, 'client_user_id' => $other->id, 'created_by' => $this->trainer->id, 'updated_by' => $this->trainer->id])->save();

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))
            ->assertDontSee('他の人のメモ');
    }

    #[Test]
    public function 編集の画面が表示される(): void
    {
        $note = $this->note('編集前の本文');

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client).'?edit-note='.$note->id)
            ->assertSee('<textarea name="body"', false)
            ->assertSee('編集前の本文');
    }

    // ---- 登録 ----

    #[Test]
    public function スタッフが指導メモを登録できる(): void
    {
        $this->actingAs($this->trainer)
            ->post(route('staff.clients.notes.store', $this->client), ['category' => 'injury', 'body' => '右肩に注意'])
            ->assertRedirectContains(route('staff.clients.show', $this->client))
            ->assertSessionHas('status', '指導メモを追加しました。');

        $note = CoachingNote::firstOrFail();
        $this->assertSame(CoachingNoteCategory::Injury, $note->category);
        $this->assertSame('右肩に注意', $note->body);
        $this->assertSame($this->gym->id, $note->gym_id);
        $this->assertSame($this->client->id, $note->client_user_id);
        $this->assertSame($this->trainer->id, $note->created_by);
        $this->assertSame($this->trainer->id, $note->updated_by);
    }

    #[Test]
    public function オーナーも指導メモを登録できる(): void
    {
        $owner = User::factory()->owner($this->gym)->create();

        $this->actingAs($owner)
            ->post(route('staff.clients.notes.store', $this->client), ['category' => 'goal', 'body' => '目標'])
            ->assertSessionHasNoErrors();

        $this->assertSame($owner->id, CoachingNote::firstOrFail()->created_by);
    }

    #[Test]
    public function リクエストに含めたジムや入力者は無視される(): void
    {
        $otherGym = Gym::factory()->create();
        $other = User::factory()->trainer($this->gym)->create();

        $this->actingAs($this->trainer)->post(route('staff.clients.notes.store', $this->client), [
            'category' => 'other', 'body' => '本文',
            'gym_id' => $otherGym->id, 'client_user_id' => $other->id, 'created_by' => $other->id, 'updated_by' => $other->id,
        ]);

        $note = CoachingNote::firstOrFail();
        $this->assertSame($this->gym->id, $note->gym_id);
        $this->assertSame($this->client->id, $note->client_user_id);
        $this->assertSame($this->trainer->id, $note->created_by);
        $this->assertSame($this->trainer->id, $note->updated_by);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function 不正な入力(): array
    {
        return [
            '分類なし' => [['body' => '本文'], 'category'],
            '不明な分類' => [['category' => 'xxx', 'body' => '本文'], 'category'],
            '本文なし' => [['category' => 'goal'], 'body'],
            '本文が空' => [['category' => 'goal', 'body' => ''], 'body'],
            '本文が1001文字' => [['category' => 'goal', 'body' => 'あ1001'], 'body'],
        ];
    }

    #[Test]
    #[DataProvider('不正な入力')]
    public function 不正な入力は登録できない(array $input, string $field): void
    {
        if (($input['body'] ?? null) === 'あ1001') {
            $input['body'] = str_repeat('あ', 1001);
        }

        $this->actingAs($this->trainer)->from(route('staff.clients.show', $this->client))
            ->post(route('staff.clients.notes.store', $this->client), $input)
            ->assertSessionHasErrors($field);

        $this->assertSame(0, CoachingNote::count());
    }

    #[Test]
    public function 本文は1000文字まで登録できる(): void
    {
        $this->actingAs($this->trainer)
            ->post(route('staff.clients.notes.store', $this->client), ['category' => 'goal', 'body' => str_repeat('あ', 1000)])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CoachingNote::count());
    }

    #[Test]
    public function 入力エラーは日本語で表示され入力値が保持される(): void
    {
        $this->actingAs($this->trainer)->from(route('staff.clients.show', $this->client))->followingRedirects()
            ->post(route('staff.clients.notes.store', $this->client), ['category' => 'xxx', 'body' => ''])
            ->assertSee('分類の選択が正しくありません。')
            ->assertSee('本文を入力してください。');
    }

    // ---- 編集・削除（同じジムのスタッフなら誰でも） ----

    #[Test]
    public function 他のトレーナーも指導メモを編集できる(): void
    {
        $note = $this->note('元の本文');
        $other = User::factory()->trainer($this->gym)->create();

        $this->actingAs($other)
            ->put(route('staff.clients.notes.update', [$this->client, $note]), ['category' => 'goal', 'body' => '新しい本文'])
            ->assertSessionHas('status', '指導メモを更新しました。');

        $note->refresh();
        $this->assertSame('新しい本文', $note->body);
        $this->assertSame(CoachingNoteCategory::Goal, $note->category);
        $this->assertSame($this->trainer->id, $note->created_by);
        $this->assertSame($other->id, $note->updated_by);
    }

    #[Test]
    public function 編集の入力エラーでは編集中のメモの位置に戻る(): void
    {
        $note = $this->note();

        $this->actingAs($this->trainer)->from(route('staff.clients.show', $this->client))
            ->put(route('staff.clients.notes.update', [$this->client, $note]), ['category' => 'goal', 'body' => ''])
            ->assertRedirect(route('staff.clients.show', $this->client).'?edit-note='.$note->id.'#note-'.$note->id);
    }

    #[Test]
    public function 他のトレーナーも指導メモを削除できる(): void
    {
        $note = $this->note();
        $other = User::factory()->trainer($this->gym)->create();

        $this->actingAs($other)->delete(route('staff.clients.notes.destroy', [$this->client, $note]))
            ->assertSessionHas('status', '指導メモを削除しました。');

        $this->assertSame(0, CoachingNote::count());
    }

    #[Test]
    public function 他の利用者のメモのIDを指定すると404(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $note = $this->note();

        $this->actingAs($this->trainer)->put(route('staff.clients.notes.update', [$other, $note]), ['category' => 'goal', 'body' => 'x'])->assertNotFound();
        $this->actingAs($this->trainer)->delete(route('staff.clients.notes.destroy', [$other, $note]))->assertNotFound();
        $this->assertSame('メモの本文', $note->fresh()->body);
    }

    // ---- 認可（利用者本人・他ジム・解除後） ----

    #[Test]
    public function 利用者本人は指導メモを操作できず403になる(): void
    {
        $note = $this->note();

        $this->actingAs($this->client)->get(route('staff.clients.show', $this->client))->assertForbidden();
        $this->actingAs($this->client)->post(route('staff.clients.notes.store', $this->client), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->actingAs($this->client)->put(route('staff.clients.notes.update', [$this->client, $note]), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->actingAs($this->client)->delete(route('staff.clients.notes.destroy', [$this->client, $note]))->assertForbidden();

        $this->assertSame(1, CoachingNote::count());
        $this->assertSame('メモの本文', $note->fresh()->body);
    }

    #[Test]
    public function 他のジムのスタッフは指導メモを操作できない(): void
    {
        $note = $this->note();
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($outsider)->get(route('staff.clients.show', $this->client))->assertForbidden();
        $this->actingAs($outsider)->post(route('staff.clients.notes.store', $this->client), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->actingAs($outsider)->put(route('staff.clients.notes.update', [$this->client, $note]), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->actingAs($outsider)->delete(route('staff.clients.notes.destroy', [$this->client, $note]))->assertForbidden();
        $this->assertSame(1, CoachingNote::count());
    }

    #[Test]
    public function 停止中のスタッフはログアウトされ指導メモを操作できない(): void
    {
        $suspended = User::factory()->trainer($this->gym)->suspended()->create();

        $this->actingAs($suspended)->post(route('staff.clients.notes.store', $this->client), ['category' => 'goal', 'body' => 'x'])->assertRedirect(route('login'));
        $this->assertSame(0, CoachingNote::count());
    }

    #[Test]
    public function 個人利用者には指導メモを登録できない(): void
    {
        $personal = User::factory()->personal()->create();

        $this->actingAs($this->trainer)->post(route('staff.clients.notes.store', $personal), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->assertSame(0, CoachingNote::count());
    }

    #[Test]
    public function 紐付け解除後はメモが見えず操作もできない(): void
    {
        $note = $this->note();
        $this->client->forceFill(['gym_id' => null])->save();

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))->assertForbidden();
        $this->actingAs($this->trainer)->post(route('staff.clients.notes.store', $this->client), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->actingAs($this->trainer)->put(route('staff.clients.notes.update', [$this->client, $note]), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->actingAs($this->trainer)->delete(route('staff.clients.notes.destroy', [$this->client, $note]))->assertForbidden();
        $this->assertSame(1, CoachingNote::count());
    }

    #[Test]
    public function 解除後も指導メモはジム側に残り再度紐付けると再び表示される(): void
    {
        $this->note('残るメモ');
        $this->client->forceFill(['gym_id' => null])->save();
        $this->assertSame(1, CoachingNote::count());

        $this->client->forceFill(['gym_id' => $this->gym->id])->save();

        $this->actingAs($this->trainer)->get(route('staff.clients.show', $this->client))->assertSee('残るメモ');
    }

    #[Test]
    public function 別のジムに紐付いた利用者の以前のジムのメモは新しいジムに見えない(): void
    {
        $note = $this->note('前のジムのメモ');
        $newGym = Gym::factory()->create();
        $newTrainer = User::factory()->trainer($newGym)->create();
        $this->client->forceFill(['gym_id' => $newGym->id])->save();

        $this->actingAs($newTrainer)->get(route('staff.clients.show', $this->client))
            ->assertOk()
            ->assertDontSee('前のジムのメモ');
        $this->actingAs($newTrainer)->put(route('staff.clients.notes.update', [$this->client, $note]), ['category' => 'goal', 'body' => 'x'])->assertForbidden();
        $this->actingAs($newTrainer)->delete(route('staff.clients.notes.destroy', [$this->client, $note]))->assertForbidden();
        $this->assertSame('前のジムのメモ', $note->fresh()->body);
    }

    #[Test]
    public function 未ログインでは操作できない(): void
    {
        $this->post(route('staff.clients.notes.store', $this->client), ['category' => 'goal', 'body' => 'x'])->assertRedirect(route('login'));
    }

    // ---- 利用者本人の画面・データに含めない ----

    #[Test]
    public function 利用者本人のどの画面にも指導メモの本文が含まれない(): void
    {
        $this->note('秘密の注意点');
        $this->note('秘密の怪我', CoachingNoteCategory::Injury);
        $session = $this->makeSession($this->client, '2026-11-12');

        foreach ([
            route('records.home', $this->client),
            route('records.sessions.show', [$this->client, $session]),
            route('records.sessions.today', $this->client),
            route('records.body.index', $this->client),
            route('records.graphs.training', $this->client),
            route('records.graphs.body', $this->client),
            route('records.exercises.index', $this->client),
            route('profile.show'),
            route('gym-link.show'),
        ] as $url) {
            $content = $this->actingAs($this->client)->get($url)->getContent();

            $this->assertStringNotContainsString('秘密の注意点', $content, $url);
            $this->assertStringNotContainsString('秘密の怪我', $content, $url);
            $this->assertStringNotContainsString('指導メモ', $content, $url);
        }
    }

    #[Test]
    public function 利用者本人の記録画面では指導メモを読み込む問い合わせ自体をしない(): void
    {
        $this->note();
        $session = $this->makeSession($this->client, '2026-11-12');
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($this->client)->get(route('records.sessions.show', [$this->client, $session]))->assertOk();

        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'coaching_notes'))));
    }

    // ---- 記録画面（S-11）の要約 ----

    #[Test]
    public function スタッフの記録画面に注意点と怪我の既往歴だけが折りたたみで表示される(): void
    {
        $this->note('注意のメモ', CoachingNoteCategory::Caution);
        $this->note('怪我のメモ', CoachingNoteCategory::Injury);
        $this->note('目標のメモ', CoachingNoteCategory::Goal);
        $this->note('その他のメモ', CoachingNoteCategory::Other);
        $session = $this->makeSession($this->client, '2026-11-12');

        $response = $this->actingAs($this->trainer)->get(route('records.sessions.show', [$this->client, $session]))
            ->assertOk()
            ->assertSee('指導メモ（注意点・怪我・既往歴：2件）')
            ->assertSee('注意のメモ')
            ->assertSee('怪我のメモ')
            ->assertDontSee('目標のメモ')
            ->assertDontSee('その他のメモ');

        $this->assertStringContainsString('<details', $response->getContent());
    }

    #[Test]
    public function 注意点も怪我もなければ要約を表示しない(): void
    {
        $this->note('目標のメモ', CoachingNoteCategory::Goal);
        $session = $this->makeSession($this->client, '2026-11-12');

        $this->actingAs($this->trainer)->get(route('records.sessions.show', [$this->client, $session]))
            ->assertDontSee('指導メモ（');
    }

    #[Test]
    public function 他のジムのメモは記録画面の要約に出ない(): void
    {
        $other = Gym::factory()->create();
        $note = new CoachingNote(['category' => CoachingNoteCategory::Caution, 'body' => '前のジムの注意点']);
        $note->forceFill(['gym_id' => $other->id, 'client_user_id' => $this->client->id, 'created_by' => $this->trainer->id, 'updated_by' => $this->trainer->id])->save();
        $session = $this->makeSession($this->client, '2026-11-12');

        $this->actingAs($this->trainer)->get(route('records.sessions.show', [$this->client, $session]))
            ->assertDontSee('前のジムの注意点');
    }

    #[Test]
    public function カテゴリの定義(): void
    {
        $this->assertSame(['注意点', '怪我・既往歴', '目標', 'その他'], array_map(fn ($c) => $c->label(), CoachingNoteCategory::cases()));
    }
}
