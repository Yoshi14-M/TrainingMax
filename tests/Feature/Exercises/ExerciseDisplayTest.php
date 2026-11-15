<?php

namespace Tests\Feature\Exercises;

use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseDisplayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 推奨値の表示を返す(): void
    {
        $heavy = new ExerciseCategory;
        $heavy->forceFill(['rep_min' => 6, 'rep_max' => 8, 'rir_min' => 1, 'rir_max' => 2, 'rest_seconds' => 180]);
        $light = new ExerciseCategory;
        $light->forceFill(['rep_min' => 12, 'rep_max' => 15, 'rir_min' => 0, 'rir_max' => 0, 'rest_seconds' => 90]);

        $this->assertSame('6〜8回', $heavy->repsLabel());
        $this->assertSame('RIR 1〜2', $heavy->rirLabel());
        $this->assertSame('3分', $heavy->restLabel());
        $this->assertSame('12〜15回', $light->repsLabel());
        $this->assertSame('RIR 0', $light->rirLabel());
        $this->assertSame('90秒', $light->restLabel());
    }

    #[Test]
    public function YouTubeの検索リンクは種目名をURLエンコードして新しいタブで開く(): void
    {
        $html = (string) $this->blade('<x-youtube-link name="A&B ベンチ" />');

        $this->assertStringContainsString('https://www.youtube.com/results?search_query='.urlencode('A&B ベンチ やり方'), $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    #[Test]
    public function 種目名のHTMLはエスケープされて表示される(): void
    {
        $client = User::factory()->client(Gym::factory()->create())->create();
        Exercise::factory()->personal($client)->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($client)->get(route('records.exercises.index', $client))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    #[Test]
    public function 利用者の画面のナビに種目とメニューが表示される(): void
    {
        $client = User::factory()->personal()->create();

        $this->actingAs($client)->get(route('records.exercises.index', $client))
            ->assertSee('ホーム')
            ->assertSee('種目')
            ->assertSee('メニュー')
            ->assertSee(route('records.exercises.index', $client), false);
    }

    #[Test]
    public function スタッフのナビにはジム共通種目と管理メニューが表示される(): void
    {
        $gym = Gym::factory()->create();

        $this->actingAs(User::factory()->owner($gym)->create())->get(route('staff.exercises.index'))
            ->assertSee(route('staff.exercises.index'), false)
            ->assertSee('利用者アカウント発行')
            ->assertSee('招待コード')
            ->assertSee('トレーナー管理');

        $this->actingAs(User::factory()->trainer($gym)->create())->get(route('staff.exercises.index'))
            ->assertSee('招待コード')
            ->assertDontSee('トレーナー管理');
    }

    #[Test]
    public function スタッフが利用者の記録を表示している間は帯が表示される(): void
    {
        $gym = Gym::factory()->create();
        $client = User::factory()->client($gym)->create(['name' => '山田太郎']);
        $trainer = User::factory()->trainer($gym)->create();

        $this->actingAs($trainer)->get(route('records.exercises.index', $client))->assertSee('▶ 山田太郎さんの記録を表示中');
        $this->actingAs($client)->get(route('records.exercises.index', $client))->assertDontSee('の記録を表示中');
    }

    #[Test]
    public function ナビは画面上部のヘッダーに表示され下部の固定ナビはない(): void
    {
        $client = User::factory()->personal()->create();

        $html = $this->actingAs($client)->get(route('records.exercises.index', $client))->getContent();

        // ナビ自体は画面下部に固定しない（下部に固定するのは休憩タイマーだけ）
        preg_match('/<nav[^>]*aria-label="メインメニュー"[^>]*>/', $html, $nav);
        $this->assertNotEmpty($nav);
        $this->assertStringNotContainsString('fixed', $nav[0]);
        $this->assertLessThan(strpos($html, '<main'), strpos($html, 'aria-label="メインメニュー"'));
    }
}
