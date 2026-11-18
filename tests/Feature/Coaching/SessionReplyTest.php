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

class SessionReplyTest extends TestCase
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

    private function comment(string $body, User $by, ?SessionComment $parent = null, ?WorkoutSession $session = null): SessionComment
    {
        $comment = new SessionComment(['body' => $body]);
        $comment->forceFill([
            'workout_session_id' => ($session ?? $this->session)->id,
            'parent_id' => $parent?->id,
            'created_by' => $by->id,
        ])->save();

        return $comment;
    }

    private function replyUrl(SessionComment $comment, ?WorkoutSession $session = null): string
    {
        return route('records.comments.reply', [$this->client, $session ?? $this->session, $comment]);
    }

    private function show(): string
    {
        return route('records.sessions.show', [$this->client, $this->session]);
    }

    // ---- 返信の登録 ----

    #[Test]
    public function トレーナーが利用者のメモに返信できる(): void
    {
        $memo = $this->comment('右肩が重い', $this->client);

        $this->actingAs($this->trainer)->post($this->replyUrl($memo), ['body' => '無理せず、可動域を小さくしましょう'])
            ->assertRedirect($this->show().'#comment-'.SessionComment::max('id'))
            ->assertSessionHas('status', '返信を書き込みました。');

        $reply = SessionComment::whereNotNull('parent_id')->firstOrFail();
        $this->assertSame($memo->id, $reply->parent_id);
        $this->assertSame($this->session->id, $reply->workout_session_id);
        $this->assertSame($this->trainer->id, $reply->created_by);
    }

    #[Test]
    public function 利用者がトレーナーのコメントに返信できる(): void
    {
        $comment = $this->comment('フォームが良くなりました', $this->trainer);

        $this->actingAs($this->client)->post($this->replyUrl($comment), ['body' => 'ありがとうございます！'])
            ->assertSessionHasNoErrors();

        $reply = SessionComment::whereNotNull('parent_id')->firstOrFail();
        $this->assertSame($comment->id, $reply->parent_id);
        $this->assertSame($this->client->id, $reply->created_by);
    }

    #[Test]
    public function 他のトレーナーやオーナーも返信できる(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $other = User::factory()->trainer($this->gym)->create();

        $this->actingAs($other)->post($this->replyUrl($comment), ['body' => '返信1'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->replyUrl($comment), ['body' => '返信2'])->assertSessionHasNoErrors();

        $this->assertSame(2, $comment->replies()->count());
    }

    #[Test]
    public function 返信への返信はできない(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $reply = $this->comment('返信', $this->client, $comment);

        $this->actingAs($this->trainer)->post($this->replyUrl($reply), ['body' => '孫の返信'])->assertForbidden();
        $this->assertSame(1, SessionComment::whereNotNull('parent_id')->count());
    }

    #[Test]
    public function リクエストに含めた返信先や書いた人は無視される(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $other = $this->comment('別のコメント', $this->trainer);

        $this->actingAs($this->client)->post($this->replyUrl($comment), ['body' => '返信', 'parent_id' => $other->id, 'created_by' => $this->trainer->id]);

        $reply = SessionComment::whereNotNull('parent_id')->firstOrFail();
        $this->assertSame($comment->id, $reply->parent_id);
        $this->assertSame($this->client->id, $reply->created_by);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function 本文の境界(): array
    {
        return ['空' => ['', false], '1000文字' => [str_repeat('あ', 1000), true], '1001文字' => [str_repeat('あ', 1001), false]];
    }

    #[Test]
    #[DataProvider('本文の境界')]
    public function 返信の文字数の境界(string $body, bool $valid): void
    {
        $comment = $this->comment('コメント', $this->trainer);

        $response = $this->actingAs($this->client)->from($this->show())->post($this->replyUrl($comment), ['body' => $body]);

        $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrorsIn('reply', ['body']);
        $this->assertSame($valid ? 1 : 0, SessionComment::whereNotNull('parent_id')->count());
    }

    #[Test]
    public function 返信の入力エラーは返信欄に表示され入力値が保持される(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $other = $this->comment('別のコメント', $this->trainer);

        $this->actingAs($this->client)->from($this->show())
            ->post($this->replyUrl($comment), ['body' => str_repeat('あ', 1001)])
            ->assertRedirect($this->show().'?reply-to='.$comment->id.'#comment-'.$comment->id);

        // 入力エラーで戻った後の表示（同じセッションのまま、リダイレクト先を開く）
        $html = $this->followingRedirects()->actingAs($this->client)->from($this->show())
            ->post($this->replyUrl($comment), ['body' => str_repeat('あ', 1001)])
            ->getContent();

        // 返信しようとしたコメントの欄だけが開き、エラーが1か所だけ表示される
        $this->assertSame(1, substr_count($html, 'コメントは1000文字以内で入力してください。'));
        $this->assertSame(1, substr_count($html, '<details class="mt-3" open>'));
        $this->assertNotNull($other);
    }

    // ---- 認可 ----

    #[Test]
    public function 他のジムのスタッフは返信できない(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $outsider = User::factory()->trainer()->create();

        $this->actingAs($outsider)->post($this->replyUrl($comment), ['body' => 'x'])->assertForbidden();
        $this->assertSame(0, SessionComment::whereNotNull('parent_id')->count());
    }

    #[Test]
    public function 他の利用者は返信できない(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($other)->post($this->replyUrl($comment), ['body' => 'x'])->assertForbidden();
    }

    #[Test]
    public function 紐付け解除後はスタッフは返信できないが利用者本人は返信できる(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->client->forceFill(['gym_id' => null])->save();

        $this->actingAs($this->trainer)->post($this->replyUrl($comment), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->client)->post($this->replyUrl($comment), ['body' => '解除後の返信'])->assertSessionHasNoErrors();

        $this->assertSame(1, SessionComment::whereNotNull('parent_id')->count());
    }

    #[Test]
    public function 別のセッションのコメントへの返信は404(): void
    {
        $other = $this->makeSession($this->client, '2026-11-10');
        $comment = $this->comment('別の日', $this->trainer, null, $other);

        $this->actingAs($this->trainer)->post($this->replyUrl($comment), ['body' => 'x'])->assertNotFound();
    }

    #[Test]
    public function 未ログインでは返信できない(): void
    {
        $comment = $this->comment('コメント', $this->trainer);

        $this->post($this->replyUrl($comment), ['body' => 'x'])->assertRedirect(route('login'));
    }

    // ---- 編集・削除 ----

    #[Test]
    public function 返信は書いた本人だけが編集できる(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $reply = $this->comment('返信', $this->client, $comment);
        $url = route('records.comments.update', [$this->client, $this->session, $reply]);

        $this->actingAs($this->trainer)->put($url, ['body' => '書き換え'])->assertForbidden();
        $this->actingAs($this->client)->put($url, ['body' => '更新'])->assertSessionHasNoErrors();

        $this->assertSame('更新', $reply->fresh()->body);
    }

    #[Test]
    public function オーナーはスタッフの返信を削除できるが利用者の返信は削除できない(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $staffReply = $this->comment('スタッフの返信', $this->trainer, $comment);
        $clientReply = $this->comment('利用者の返信', $this->client, $comment);

        $this->actingAs($this->owner)->delete(route('records.comments.destroy', [$this->client, $this->session, $clientReply]))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('records.comments.destroy', [$this->client, $this->session, $staffReply]))->assertSessionHasNoErrors();

        $this->assertSame([$clientReply->id], SessionComment::whereNotNull('parent_id')->pluck('id')->all());
    }

    #[Test]
    public function 最初のコメントを削除すると返信も削除される(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->comment('返信1', $this->client, $comment);
        $this->comment('返信2', $this->trainer, $comment);

        $this->actingAs($this->trainer)->delete(route('records.comments.destroy', [$this->client, $this->session, $comment]));

        $this->assertSame(0, SessionComment::count());
    }

    #[Test]
    public function セッションを削除すると返信も削除される(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->comment('返信', $this->client, $comment);

        $this->actingAs($this->client)->delete(route('records.sessions.destroy', [$this->client, $this->session]));

        $this->assertSame(0, SessionComment::count());
    }

    // ---- 表示 ----

    #[Test]
    public function 返信は元のコメントの下に古い順で表示される(): void
    {
        $first = $this->comment('一つ目のコメント', $this->trainer);
        $first->forceFill(['created_at' => '2026-11-12 18:00:00'])->saveQuietly();
        $second = $this->comment('二つ目のコメント', $this->client);
        $second->forceFill(['created_at' => '2026-11-12 19:00:00'])->saveQuietly();
        $this->comment('遅い返信', $this->client, $first)->forceFill(['created_at' => '2026-11-12 21:00:00'])->saveQuietly();
        $this->comment('早い返信', $this->trainer, $first)->forceFill(['created_at' => '2026-11-12 20:00:00'])->saveQuietly();

        $this->actingAs($this->client)->get($this->show())
            ->assertSeeInOrder(['一つ目のコメント', '早い返信', '遅い返信', '二つ目のコメント']);
    }

    #[Test]
    public function 返信には返信する欄を出さず元のコメントにだけ出す(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->comment('返信', $this->client, $comment);

        $response = $this->actingAs($this->client)->get($this->show());

        $this->assertSame(1, substr_count($response->getContent(), 'aria-label="返信"'));
    }

    #[Test]
    public function 書いた人が表示され利用者本人は本人と表示される(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->comment('返信', $this->client, $comment);

        $this->actingAs($this->trainer)->get($this->show())
            ->assertSee('↳ 本人・', false)
            ->assertSee('田中トレーナー・');
    }

    #[Test]
    public function 返信がある場合の削除の確認に件数が表示される(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->comment('返信1', $this->client, $comment);
        $this->comment('返信2', $this->client, $comment);

        $this->actingAs($this->trainer)->get($this->show())->assertSee('このコメントと、返信2件を削除します。');
    }

    #[Test]
    public function 返信の本文はエスケープされる(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->comment('<script>alert(1)</script>', $this->client, $comment);

        $this->actingAs($this->trainer)->get($this->show())
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    #[Test]
    public function 返信だけがある日にもカレンダーにマークが付く(): void
    {
        $comment = $this->comment('コメント', $this->trainer);
        $this->comment('返信', $this->client, $comment);

        $html = $this->actingAs($this->client)->get(route('records.home', $this->client).'?month=2026-11')->getContent();

        $this->assertSame(1, substr_count($html, 'title="コメントあり"'));
    }
}
