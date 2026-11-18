<?php

namespace Tests\Feature\Coaching;

use App\Models\Gym;
use App\Models\SessionComment;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class SessionCommentTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $trainer;

    private User $client;

    private WorkoutSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->owner($this->gym)->create(['name' => '山本オーナー']);
        $this->trainer = User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->session = $this->makeSession($this->client, '2026-11-12');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function comment(string $body = 'コメント本文', ?User $by = null, ?WorkoutSession $session = null): SessionComment
    {
        $comment = new SessionComment(['body' => $body]);
        $comment->forceFill([
            'workout_session_id' => ($session ?? $this->session)->id,
            'created_by' => ($by ?? $this->trainer)->id,
        ])->save();

        return $comment;
    }

    private function url(string $name, ?SessionComment $comment = null, ?WorkoutSession $session = null): string
    {
        return $comment === null
            ? route("records.comments.$name", [$this->client, $session ?? $this->session])
            : route("records.comments.$name", [$this->client, $session ?? $this->session, $comment]);
    }

    private function show(): string
    {
        return route('records.sessions.show', [$this->client, $this->session]);
    }

    private function unlink(): void
    {
        $this->client->forceFill(['gym_id' => null])->save();
    }

    // ---- 表示 ----

    #[Test]
    public function スタッフの記録画面にコメントが古い順に書いた人と日時つきで表示される(): void
    {
        $this->comment('二番目のコメント', $this->owner)->forceFill(['created_at' => '2026-11-12 20:00:00'])->saveQuietly();
        $this->comment('一番目のコメント')->forceFill(['created_at' => '2026-11-12 18:30:00'])->saveQuietly();

        $this->actingAs($this->trainer)->get($this->show())
            ->assertOk()
            ->assertSeeInOrder(['田中トレーナー・2026/11/12 18:30', '一番目のコメント', '山本オーナー・2026/11/12 20:00', '二番目のコメント'])
            ->assertSee('コメント・メモを書く');
    }

    #[Test]
    public function 利用者本人もコメントを閲覧しトレーニングメモを書き込める(): void
    {
        $this->comment('利用者へのコメント');

        $this->actingAs($this->client)->get($this->show())
            ->assertOk()
            ->assertSee('利用者へのコメント')
            ->assertSee('田中トレーナー')
            ->assertSee('コメント・メモを書く')
            ->assertSee('書き込む')
            // スタッフのコメントは編集・削除できない（返信はできる）
            ->assertDontSee('?edit-comment=', false)
            ->assertDontSee('このコメントを削除します。')
            ->assertSee('返信する');
    }

    #[Test]
    public function 個人利用者もメモの欄が表示される(): void
    {
        $personal = User::factory()->personal()->create();
        $session = $this->makeSession($personal, '2026-11-12');

        $this->actingAs($personal)->get(route('records.sessions.show', [$personal, $session]))
            ->assertOk()
            ->assertSee('コメント・メモを書く');
    }

    #[Test]
    public function 紐付け解除後も利用者本人はコメントを閲覧できる(): void
    {
        $this->comment('解除前に書かれたコメント');
        $this->unlink();

        $this->actingAs($this->client)->get($this->show())
            ->assertOk()
            ->assertSee('解除前に書かれたコメント')
            ->assertSee('書き込む');
    }

    #[Test]
    public function 本文はエスケープされ改行は保たれる(): void
    {
        $this->comment("<b>太字</b>\n2行目");

        $response = $this->actingAs($this->trainer)->get($this->show())
            ->assertDontSee('<b>太字</b>', false)
            ->assertSee('&lt;b&gt;太字&lt;/b&gt;', false);

        $this->assertStringContainsString('whitespace-pre-wrap', $response->getContent());
    }

    #[Test]
    public function 停止中のスタッフが書いたコメントには停止中と付く(): void
    {
        $suspended = User::factory()->trainer($this->gym)->suspended()->create(['name' => '停止太郎']);
        $this->comment('x', $suspended);

        $this->actingAs($this->client)->get($this->show())->assertSee('停止太郎（停止中）');
    }

    #[Test]
    public function 他のセッションのコメントは表示されない(): void
    {
        $other = $this->makeSession($this->client, '2026-11-10');
        $this->comment('別の日のコメント', null, $other);

        $this->actingAs($this->trainer)->get($this->show())->assertDontSee('別の日のコメント');
    }

    #[Test]
    public function 本人の記録画面でも編集と削除のボタンは書いた本人にだけ出る(): void
    {
        $this->comment('田中のコメント');
        $other = User::factory()->trainer($this->gym)->create();

        $this->actingAs($this->trainer)->get($this->show())->assertSee('?edit-comment=', false)->assertSee('このコメントを削除します。');
        $this->actingAs($other)->get($this->show())->assertDontSee('?edit-comment=', false)->assertDontSee('このコメントを削除します。');
        // オーナーは削除のみ
        $this->actingAs($this->owner)->get($this->show())->assertDontSee('?edit-comment=', false)->assertSee('このコメントを削除します。');
    }

    #[Test]
    public function 利用者本人のメモは本人にだけ編集と削除のボタンが出る(): void
    {
        $this->comment('利用者のメモ', $this->client);

        $this->actingAs($this->client)->get($this->show())->assertSee('?edit-comment=', false)->assertSee('このコメントを削除します。')->assertSee('本人・');
        $this->actingAs($this->trainer)->get($this->show())->assertDontSee('?edit-comment=', false)->assertDontSee('このコメントを削除します。')->assertSee('本人・');
        // オーナーも、利用者本人のメモは削除できない
        $this->actingAs($this->owner)->get($this->show())->assertDontSee('このコメントを削除します。');
    }

    #[Test]
    public function 編集の画面が表示される(): void
    {
        $comment = $this->comment('編集前');

        $this->actingAs($this->trainer)->get($this->show().'?edit-comment='.$comment->id)
            ->assertSee('<textarea name="body"', false)
            ->assertSee('編集前');
    }

    #[Test]
    public function 書いた本人以外の編集画面はフォームを出さない(): void
    {
        $comment = $this->comment('編集前');

        $this->actingAs($this->owner)->get($this->show().'?edit-comment='.$comment->id)
            ->assertDontSee('aria-label="コメント"', false)
            ->assertSee('編集前');
    }

    // ---- 登録 ----

    #[Test]
    public function トレーナーがコメントを登録できる(): void
    {
        $this->actingAs($this->trainer)->post($this->url('store'), ['body' => 'よく頑張りました'])
            ->assertSessionHas('status', 'コメントを書き込みました。');

        $comment = SessionComment::firstOrFail();
        $this->assertSame('よく頑張りました', $comment->body);
        $this->assertSame($this->session->id, $comment->workout_session_id);
        $this->assertSame($this->trainer->id, $comment->created_by);
    }

    #[Test]
    public function オーナーもコメントを登録できる(): void
    {
        $this->actingAs($this->owner)->post($this->url('store'), ['body' => 'コメント'])->assertSessionHasNoErrors();

        $this->assertSame($this->owner->id, SessionComment::firstOrFail()->created_by);
    }

    #[Test]
    public function リクエストに含めたセッションや書いた人は無視される(): void
    {
        $other = $this->makeSession($this->client, '2026-11-10');

        $this->actingAs($this->trainer)->post($this->url('store'), [
            'body' => '本文', 'workout_session_id' => $other->id, 'created_by' => $this->owner->id,
        ]);

        $comment = SessionComment::firstOrFail();
        $this->assertSame($this->session->id, $comment->workout_session_id);
        $this->assertSame($this->trainer->id, $comment->created_by);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function 本文の境界(): array
    {
        return ['空' => ['', false], '1文字' => ['あ', true], '1000文字' => [str_repeat('あ', 1000), true], '1001文字' => [str_repeat('あ', 1001), false]];
    }

    #[Test]
    #[DataProvider('本文の境界')]
    public function 本文の文字数の境界(string $body, bool $valid): void
    {
        $response = $this->actingAs($this->trainer)->from($this->show())->post($this->url('store'), ['body' => $body]);

        $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('body');
        $this->assertSame($valid ? 1 : 0, SessionComment::count());
    }

    #[Test]
    public function 入力エラーは日本語で表示されコメント欄の位置に戻る(): void
    {
        $this->actingAs($this->trainer)->from($this->show())
            ->post($this->url('store'), ['body' => ''])
            ->assertRedirect($this->show().'#comments');

        $this->actingAs($this->trainer)->from($this->show())->followingRedirects()
            ->post($this->url('store'), ['body' => ''])
            ->assertSee('コメントを入力してください。');
    }

    #[Test]
    public function 利用者本人はトレーニングメモとして登録できる(): void
    {
        $this->actingAs($this->client)->post($this->url('store'), ['body' => '今日は調子が良かった'])
            ->assertSessionHas('status', 'コメントを書き込みました。');

        $comment = SessionComment::firstOrFail();
        $this->assertSame($this->client->id, $comment->created_by);
        $this->assertSame($this->session->id, $comment->workout_session_id);
        $this->assertNull($comment->parent_id);
    }

    #[Test]
    public function 個人利用者も自分のセッションにメモを登録できる(): void
    {
        $personal = User::factory()->personal()->create();
        $session = $this->makeSession($personal, '2026-11-12');

        $this->actingAs($personal)->post(route('records.comments.store', [$personal, $session]), ['body' => 'メモ'])
            ->assertSessionHasNoErrors();

        $this->assertSame($personal->id, SessionComment::firstOrFail()->created_by);
    }

    #[Test]
    public function 利用者は他の利用者のセッションに登録できない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $otherSession = $this->makeSession($other, '2026-11-12');

        $this->actingAs($this->client)->post(route('records.comments.store', [$other, $otherSession]), ['body' => 'x'])->assertForbidden();
        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function 個人利用者のセッションにはコメントを登録できない(): void
    {
        $personal = User::factory()->personal()->create();
        $session = $this->makeSession($personal, '2026-11-12');

        $this->actingAs($this->trainer)->post(route('records.comments.store', [$personal, $session]), ['body' => 'x'])->assertForbidden();
        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function 他のジムのスタッフはコメントを登録できない(): void
    {
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($outsider)->post($this->url('store'), ['body' => 'x'])->assertForbidden();
        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function 紐付け解除後はスタッフもコメントを登録できない(): void
    {
        $this->unlink();

        $this->actingAs($this->trainer)->post($this->url('store'), ['body' => 'x'])->assertForbidden();
        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function 認可は入力チェックより先に行われる(): void
    {
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($outsider)->post($this->url('store'), ['body' => ''])->assertForbidden();
    }

    #[Test]
    public function 未ログインでは登録できない(): void
    {
        $this->post($this->url('store'), ['body' => 'x'])->assertRedirect(route('login'));
    }

    // ---- 編集（書いた本人のみ） ----

    #[Test]
    public function 書いた本人はコメントを編集できる(): void
    {
        $comment = $this->comment('元の本文');

        $this->actingAs($this->trainer)->put($this->url('update', $comment), ['body' => '新しい本文'])
            ->assertSessionHas('status', 'コメントを更新しました。');

        $this->assertSame('新しい本文', $comment->fresh()->body);
        $this->assertSame($this->trainer->id, $comment->fresh()->created_by);
    }

    #[Test]
    public function 他のトレーナーはコメントを編集できず403になる(): void
    {
        $comment = $this->comment('元の本文');
        $other = User::factory()->trainer($this->gym)->create();

        $this->actingAs($other)->put($this->url('update', $comment), ['body' => '書き換え'])->assertForbidden();
        $this->assertSame('元の本文', $comment->fresh()->body);
    }

    #[Test]
    public function オーナーも他の人のコメントは編集できない(): void
    {
        $comment = $this->comment('元の本文');

        $this->actingAs($this->owner)->put($this->url('update', $comment), ['body' => '書き換え'])->assertForbidden();
        $this->assertSame('元の本文', $comment->fresh()->body);
    }

    #[Test]
    public function オーナーは自分が書いたコメントを編集できる(): void
    {
        $comment = $this->comment('元の本文', $this->owner);

        $this->actingAs($this->owner)->put($this->url('update', $comment), ['body' => '更新'])->assertSessionHasNoErrors();
        $this->assertSame('更新', $comment->fresh()->body);
    }

    #[Test]
    public function 利用者本人はスタッフのコメントを編集できない(): void
    {
        $comment = $this->comment('元の本文');

        $this->actingAs($this->client)->put($this->url('update', $comment), ['body' => '書き換え'])->assertForbidden();
        $this->assertSame('元の本文', $comment->fresh()->body);
    }

    #[Test]
    public function 利用者本人は自分のメモを編集できる(): void
    {
        $comment = $this->comment('元のメモ', $this->client);

        $this->actingAs($this->client)->put($this->url('update', $comment), ['body' => '更新したメモ'])->assertSessionHasNoErrors();
        $this->assertSame('更新したメモ', $comment->fresh()->body);
    }

    #[Test]
    public function トレーナーもオーナーも利用者本人のメモを編集できない(): void
    {
        $comment = $this->comment('本人のメモ', $this->client);

        $this->actingAs($this->trainer)->put($this->url('update', $comment), ['body' => '書き換え'])->assertForbidden();
        $this->actingAs($this->owner)->put($this->url('update', $comment), ['body' => '書き換え'])->assertForbidden();
        $this->assertSame('本人のメモ', $comment->fresh()->body);
    }

    #[Test]
    public function 紐付け解除後も利用者本人は自分のメモを編集と削除ができる(): void
    {
        $comment = $this->comment('本人のメモ', $this->client);
        $this->unlink();

        $this->actingAs($this->client)->put($this->url('update', $comment), ['body' => '解除後に更新'])->assertSessionHasNoErrors();
        $this->assertSame('解除後に更新', $comment->fresh()->body);

        $this->actingAs($this->client)->delete($this->url('destroy', $comment))->assertSessionHasNoErrors();
        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function 紐付け解除後にスタッフは利用者のメモを編集と削除ができない(): void
    {
        $comment = $this->comment('本人のメモ', $this->client);
        $this->unlink();

        $this->actingAs($this->trainer)->put($this->url('update', $comment), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->owner)->delete($this->url('destroy', $comment))->assertForbidden();
    }

    #[Test]
    public function 紐付け解除後は書いた本人でも編集できない(): void
    {
        $comment = $this->comment('元の本文');
        $this->unlink();

        $this->actingAs($this->trainer)->put($this->url('update', $comment), ['body' => '書き換え'])->assertForbidden();
        $this->assertSame('元の本文', $comment->fresh()->body);
    }

    #[Test]
    public function 編集の入力エラーでは編集中のコメントの位置に戻る(): void
    {
        $comment = $this->comment();

        $this->actingAs($this->trainer)->from($this->show())->put($this->url('update', $comment), ['body' => ''])
            ->assertRedirect($this->show().'?edit-comment='.$comment->id.'#comment-'.$comment->id);
    }

    // ---- 削除（書いた本人とオーナー） ----

    #[Test]
    public function 書いた本人はコメントを削除できる(): void
    {
        $comment = $this->comment();

        $this->actingAs($this->trainer)->delete($this->url('destroy', $comment))
            ->assertSessionHas('status', 'コメントを削除しました。');

        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function オーナーは他の人のコメントを削除できる(): void
    {
        $comment = $this->comment();

        $this->actingAs($this->owner)->delete($this->url('destroy', $comment))->assertSessionHasNoErrors();
        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function 他のトレーナーはコメントを削除できず403になる(): void
    {
        $comment = $this->comment();
        $other = User::factory()->trainer($this->gym)->create();

        $this->actingAs($other)->delete($this->url('destroy', $comment))->assertForbidden();
        $this->assertSame(1, SessionComment::count());
    }

    #[Test]
    public function 利用者本人はスタッフのコメントを削除できない(): void
    {
        $comment = $this->comment();

        $this->actingAs($this->client)->delete($this->url('destroy', $comment))->assertForbidden();
        $this->assertSame(1, SessionComment::count());
    }

    #[Test]
    public function 紐付け解除後は書いた本人もオーナーも削除できない(): void
    {
        $comment = $this->comment();
        $this->unlink();

        $this->actingAs($this->trainer)->delete($this->url('destroy', $comment))->assertForbidden();
        $this->actingAs($this->owner)->delete($this->url('destroy', $comment))->assertForbidden();
        $this->assertSame(1, SessionComment::count());
    }

    #[Test]
    public function 他のジムのオーナーは削除できない(): void
    {
        $comment = $this->comment();
        $outsider = User::factory()->owner()->create();

        $this->actingAs($outsider)->delete($this->url('destroy', $comment))->assertForbidden();
        $this->assertSame(1, SessionComment::count());
    }

    #[Test]
    public function 別のセッションのコメントのIDを指定すると404(): void
    {
        $other = $this->makeSession($this->client, '2026-11-10');
        $comment = $this->comment('別の日', null, $other);

        $this->actingAs($this->trainer)->put($this->url('update', $comment), ['body' => 'x'])->assertNotFound();
        $this->actingAs($this->trainer)->delete($this->url('destroy', $comment))->assertNotFound();
        $this->assertSame(1, SessionComment::count());
    }

    #[Test]
    public function 他の利用者のセッションのコメントは操作できない(): void
    {
        $otherClient = User::factory()->client($this->gym)->create();
        $otherSession = $this->makeSession($otherClient, '2026-11-12');
        $comment = $this->comment('他の利用者', null, $otherSession);

        // 自分のURLの {client} に他の利用者のセッションは当てはまらない（404）
        $this->actingAs($this->client)->delete(route('records.comments.destroy', [$this->client, $otherSession, $comment]))->assertNotFound();
        $this->actingAs($this->client)->post(route('records.comments.store', [$otherClient, $otherSession]), ['body' => 'x'])->assertForbidden();
    }

    #[Test]
    public function セッションを削除するとコメントも削除される(): void
    {
        $this->comment();
        $this->comment('2件目');

        $this->actingAs($this->trainer)->delete(route('records.sessions.destroy', [$this->client, $this->session]))->assertRedirect();

        $this->assertSame(0, SessionComment::count());
    }

    // ---- ホームのカレンダー ----

    #[Test]
    public function コメントがある日のカレンダーにマークが付く(): void
    {
        $this->comment();
        $this->makeSession($this->client, '2026-11-10');

        $response = $this->actingAs($this->client)->get(route('records.home', $this->client).'?month=2026-11')->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'title="コメントあり"'));
        $response->assertSee('はコメントがある日です。', false);
    }

    #[Test]
    public function コメントがなければマークは付かない(): void
    {
        $this->actingAs($this->client)->get(route('records.home', $this->client).'?month=2026-11')
            ->assertDontSee('title="コメントあり"', false);
    }
}
