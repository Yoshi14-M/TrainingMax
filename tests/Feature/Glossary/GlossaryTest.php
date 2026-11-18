<?php

namespace Tests\Feature\Glossary;

use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

/**
 * 用語ツールチップ（機能設計書 F-16）。解説は lang/ja/glossary.php の1か所で管理する。
 */
class GlossaryTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    /** 機能設計書 F-16 の対象用語 */
    private const KEYS = ['rm', 'estimated_1rm', 'rep', 'set', 'rir', 'set_volume', 'total_volume', 'compound', 'isolation', 'interval', 'girth'];

    #[Test]
    public function 用語集に対象の用語がすべて登録されている(): void
    {
        $glossary = trans('glossary');

        $this->assertEqualsCanonicalizing(self::KEYS, array_keys($glossary));

        foreach ($glossary as $key => $entry) {
            $this->assertNotSame('', $entry['term'], $key);
            $this->assertGreaterThan(20, mb_strlen($entry['description']), "{$key} の解説が短すぎます");
        }
    }

    #[Test]
    public function 用語の部品は解説文を用語集から表示する(): void
    {
        $html = Blade::render('<x-term key="rir" />');

        $this->assertStringContainsString(trans('glossary.rir.description'), $html);
        $this->assertStringContainsString('aria-label="RIRの解説"', $html);
        $this->assertStringContainsString('@click.outside', $html);
    }

    #[Test]
    public function 用語の部品は段落の中に置けるようdivとpを使わない(): void
    {
        // <p> の中に <div> があると、ブラウザが <p> を閉じて解説が動かなくなる
        $html = Blade::render('<p>総ボリューム<x-term key="total_volume" /></p>');

        $this->assertStringNotContainsString('<div', $html);
        $this->assertSame(1, substr_count($html, '<p>'));
        $this->assertStringNotContainsString('<p ', $html);
    }

    #[Test]
    public function 用語集にないキーは何も表示しない(): void
    {
        $this->assertSame('', trim(Blade::render('<x-term key="unknown" />')));
    }

    #[Test]
    public function 解説文は画面でエスケープされる(): void
    {
        app('translator')->addLines(['glossary.rir.term' => 'RIR', 'glossary.rir.description' => '<script>alert(1)</script>'], 'ja');

        $html = Blade::render('<x-term key="rir" />');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function 分類と用語(): array
    {
        return [
            '高重量コンパウンド' => ['高重量コンパウンド', 'compound'],
            '中重量コンパウンド' => ['中重量コンパウンド', 'compound'],
            '標準アイソレート' => ['標準アイソレート', 'isolation'],
            '高レップアイソレート' => ['高レップアイソレート', 'isolation'],
        ];
    }

    #[Test]
    #[DataProvider('分類と用語')]
    public function 種目分類から解説する用語が決まる(string $name, string $key): void
    {
        $this->assertSame($key, (new ExerciseCategory(['name' => $name]))->termKey());
    }

    #[Test]
    public function コンパウンドでもアイソレートでもない分類は用語を持たない(): void
    {
        $this->assertNull((new ExerciseCategory(['name' => 'その他']))->termKey());
    }

    #[Test]
    public function セッション画面のセット一覧とセット入力に用語の解説が表示される(): void
    {
        $client = User::factory()->client()->create();
        $session = $this->makeSession($client, now()->toDateString());
        $category = ExerciseCategory::factory()->create(['name' => '高重量コンパウンド']);
        $exercise = Exercise::factory()->personal($client)->create(['exercise_category_id' => $category->id]);
        $this->addSets($session, $exercise, [[60, 10, 2, 2]]);

        $response = $this->actingAs($client)
            ->get(route('records.sessions.show', [$client, $session]).'?exercise='.$exercise->id)
            ->assertOk();

        foreach (['set', 'rep', 'rir', 'estimated_1rm', 'set_volume', 'total_volume', 'compound'] as $key) {
            $response->assertSee(e(trans("glossary.{$key}.description")), false);
        }
    }

    #[Test]
    public function アイソレート種目を選ぶとアイソレートの解説が表示される(): void
    {
        $client = User::factory()->client()->create();
        $session = $this->makeSession($client, now()->toDateString());
        $category = ExerciseCategory::factory()->create(['name' => '標準アイソレート']);
        $exercise = Exercise::factory()->personal($client)->create(['exercise_category_id' => $category->id]);

        $this->actingAs($client)
            ->get(route('records.sessions.show', [$client, $session]).'?exercise='.$exercise->id)
            ->assertSee(e(trans('glossary.isolation.description')), false)
            ->assertDontSee(e(trans('glossary.compound.description')), false);
    }

    #[Test]
    public function 種目の登録フォームにコンパウンドとアイソレートの解説が表示される(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get(route('records.exercises.index', $client))
            ->assertOk()
            ->assertSee(e(trans('glossary.compound.description')), false)
            ->assertSee(e(trans('glossary.isolation.description')), false);
    }

    #[Test]
    public function 体組成の入力フォームに周囲径の解説が表示される(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get(route('records.body.index', $client))
            ->assertOk()
            ->assertSee(e(trans('glossary.girth.description')), false);
    }

    #[Test]
    public function 休憩タイマーにインターバルの解説が表示される(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get(route('records.home', $client))
            ->assertOk()
            ->assertSee(e(trans('glossary.interval.description')), false);
    }

    #[Test]
    public function トレーニングのグラフに指標の用語の解説が表示される(): void
    {
        $client = User::factory()->client()->create();
        $session = $this->makeSession($client, now()->subDay()->toDateString());
        $exercise = Exercise::factory()->personal($client)->create();
        $this->addSets($session, $exercise, [[60, 10, 2, 2]]);

        $this->actingAs($client)->get(route('records.graphs.training', $client).'?exercise='.$exercise->id)
            ->assertOk()
            ->assertSee(e(trans('glossary.estimated_1rm.description')), false)
            ->assertSee(e(trans('glossary.total_volume.description')), false);
    }

    #[Test]
    public function スタッフが利用者の画面を開いても用語の解説が表示される(): void
    {
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();
        $client = User::factory()->client($gym)->create();
        $session = $this->makeSession($client, now()->toDateString());
        $this->addSets($session, Exercise::factory()->default()->create(), [[60, 10, 2, 2]]);

        $this->actingAs($trainer)->get(route('records.sessions.show', [$client, $session]))
            ->assertOk()
            ->assertSee(e(trans('glossary.rep.description')), false);
    }
}
