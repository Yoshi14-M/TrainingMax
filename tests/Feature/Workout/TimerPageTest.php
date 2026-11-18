<?php

namespace Tests\Feature\Workout;

use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

/**
 * 休憩タイマー（F-10）の画面への組み込み。カウントダウンなどの動作は JavaScript（resources/js/timer.js）で、
 * 純粋なロジックは `npm test`（tests/js）で確認する。
 */
class TimerPageTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private User $client;

    private WorkoutSession $session;

    private Exercise $heavy;

    private Exercise $light;

    protected function setUp(): void
    {
        parent::setUp();

        $gym = Gym::factory()->create();
        $this->client = User::factory()->client($gym)->create();
        $part = BodyPart::factory()->create();
        $this->heavy = Exercise::factory()->create([
            'body_part_id' => $part->id,
            'exercise_category_id' => ExerciseCategory::factory()->create(['rest_seconds' => 180])->id,
        ]);
        $this->light = Exercise::factory()->create([
            'body_part_id' => $part->id,
            'exercise_category_id' => ExerciseCategory::factory()->create(['rest_seconds' => 60])->id,
        ]);
        $this->session = $this->makeSession($this->client, '2026-11-01');
    }

    /**
     * @return array<string, mixed>
     */
    private function timerConfig(string $html): array
    {
        preg_match("/x-data=\"workoutTimer\\(JSON\\.parse\\('(.*?)'\\)\\)\"/s", $html, $match);

        $json = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', fn ($m) => mb_chr(hexdec($m[1])), $match[1] ?? '');

        // HTML に埋め込まれた JavaScript の文字列リテラルのエスケープ（バックスラッシュの重ね書き）を戻してから JSON として読む
        $json = str_replace(['\\\\\\/', '\\\\'], ['/', '\\'], $json);

        return json_decode($json, true) ?? [];
    }

    private function html(array $query = [], ?User $as = null): string
    {
        return $this->actingAs($as ?? $this->client)
            ->get(route('records.sessions.show', [$this->client, $this->session]).($query ? '?'.http_build_query($query) : ''))
            ->assertOk()
            ->getContent();
    }

    #[Test]
    public function 種目を選ぶとタイマーに分類の推奨休憩時間がセットされる(): void
    {
        $this->assertSame(180, $this->timerConfig($this->html(['exercise' => $this->heavy->id]))['restSeconds']);
        $this->assertSame(60, $this->timerConfig($this->html(['exercise' => $this->light->id]))['restSeconds']);
    }

    #[Test]
    public function 種目を選んでいなければ推奨休憩時間はなく作動中でなければ表示されない(): void
    {
        $config = $this->timerConfig($this->html());

        $this->assertNull($config['restSeconds']);
        // 表示するかどうかは JavaScript が決める（推奨休憩時間がなく作動中でもない場合は隠す）
        $this->assertSame(90, $config['defaultSeconds']);
    }

    #[Test]
    public function セットを記録した後の画面でタイマーに推奨休憩時間がセットされる(): void
    {
        $this->actingAs($this->client)->post(route('records.sets.store', [$this->client, $this->session]), [
            'exercise_id' => $this->heavy->id, 'weight' => '60', 'reps' => '8', 'target_rir' => '2', 'actual_rir' => '2',
        ])->assertRedirect();

        $html = $this->get(route('records.sessions.show', [$this->client, $this->session]).'?exercise='.$this->heavy->id)->getContent();

        $this->assertSame(180, $this->timerConfig($html)['restSeconds']);
    }

    #[Test]
    public function 記録画面には他の画面から戻るためのURLが渡される(): void
    {
        $this->assertSame(
            route('records.sessions.show', [$this->client, $this->session]),
            $this->timerConfig($this->html())['returnUrl'],
        );

        $other = $this->makeSession($this->client, '2026-11-02');
        $html = $this->actingAs($this->client)->get(route('records.sessions.show', [$this->client, $other]))->getContent();

        $this->assertSame(route('records.sessions.show', [$this->client, $other]), $this->timerConfig($html)['returnUrl']);
    }

    #[Test]
    public function 入力できる秒数の範囲と調整幅は設定から渡される(): void
    {
        $config = $this->timerConfig($this->html());

        $this->assertSame(10, $config['min']);
        $this->assertSame(600, $config['max']);
        $this->assertSame(15, $config['adjustStep']);
        $this->assertSame(10, $config['blinkSeconds']);

        config(['trainingmax.timer' => [
            'min_seconds' => 20, 'max_seconds' => 300, 'adjust_seconds' => 5, 'default_seconds' => 45, 'blink_seconds' => 3,
        ]]);
        $changed = $this->timerConfig($this->html());

        $this->assertSame([20, 300, 5, 45, 3], [$changed['min'], $changed['max'], $changed['adjustStep'], $changed['defaultSeconds'], $changed['blinkSeconds']]);
        $this->assertSame(20, $this->inputAttribute($this->html(), 'min'));
        $this->assertSame(300, $this->inputAttribute($this->html(), 'max'));
    }

    private function inputAttribute(string $html, string $name): int
    {
        preg_match('/<input type="number"[^>]*'.$name.'="(\d+)"[^>]*aria-label="休憩時間（秒）"/s', $html, $match);

        return (int) ($match[1] ?? 0);
    }

    #[Test]
    public function タイマーの操作ボタンが表示される(): void
    {
        $html = $this->html(['exercise' => $this->heavy->id]);

        foreach (['休憩タイマー', '開始', '停止', 'リセット', '−15秒', '＋15秒', '休憩時間（秒）', '終了しました'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    #[Test]
    public function スタッフが利用者の記録を開いてもタイマーが表示される(): void
    {
        $trainer = User::factory()->trainer($this->client->gym)->create();

        $this->assertSame(180, $this->timerConfig($this->html(['exercise' => $this->heavy->id], $trainer))['restSeconds']);
    }

    #[Test]
    public function 表示している間は入力フォームに重ならないようページの下に余白を作る(): void
    {
        $js = file_get_contents(resource_path('js/timer.js'));

        $this->assertStringContainsString("document.body.classList.toggle('pb-44'", $js);
        $this->assertStringContainsString("this.\$watch('visible'", $js);
    }

    #[Test]
    public function 初期化前の要素を隠すCSSがある(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('[x-cloak]', $css);
        $this->assertStringContainsString('display: none !important', $css);
    }

    #[Test]
    public function タイマーは種目など他の画面にも組み込まれ作動中は他の画面でも表示できる(): void
    {
        // 作動中かどうかはブラウザ（sessionStorage）が持つため、どの画面にもタイマーを組み込み、
        // 作動中・終了後だけ JavaScript が表示する。他の画面では推奨休憩時間（restSeconds）も戻り先のURLもない
        foreach ([route('records.exercises.index', $this->client), route('records.home', $this->client), route('gym-link.show')] as $url) {
            $html = $this->actingAs($this->client)->get($url)->getContent();

            $this->assertStringContainsString('休憩タイマー', $html, $url);
        }

        $config = $this->timerConfig($this->actingAs($this->client)->get(route('records.exercises.index', $this->client))->getContent());

        $this->assertNull($config['restSeconds']);
        $this->assertNull($config['returnUrl']);
    }

    #[Test]
    public function スタッフが利用者の種目の画面を見ていてもタイマーが組み込まれる(): void
    {
        $trainer = User::factory()->trainer($this->client->gym)->create();

        $this->actingAs($trainer)->get(route('records.exercises.index', $this->client))->assertSee('休憩タイマー');
        $this->actingAs($trainer)->get(route('staff.exercises.index'))->assertSee('休憩タイマー');
        $this->actingAs($trainer)->get(route('staff.trainers.index'))->assertDontSee('休憩タイマー');
    }

    #[Test]
    public function ログイン前の画面にはタイマーを組み込まない(): void
    {
        auth()->logout();

        $this->get(route('login'))->assertDontSee('休憩タイマー');
    }

    #[Test]
    public function タイマーは記録画面へ戻るリンクを持つ(): void
    {
        $html = $this->html(['exercise' => $this->heavy->id]);

        $this->assertStringContainsString('記録画面へ', $html);
        $this->assertStringContainsString('showReturn', $html);
    }
}
