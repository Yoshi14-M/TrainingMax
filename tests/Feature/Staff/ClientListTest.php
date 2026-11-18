<?php

namespace Tests\Feature\Staff;

use App\Models\Exercise;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\TestCase;

class ClientListTest extends TestCase
{
    use BuildsWorkouts;
    use RefreshDatabase;

    private Gym $gym;

    private User $trainer;

    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->trainer = User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
        $this->exercise = Exercise::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function client(string $name, ?string $lastTrained = null, array $state = []): User
    {
        $client = User::factory()->client($this->gym)->create(['name' => $name, ...$state]);

        if ($lastTrained !== null) {
            $this->addSets($this->makeSession($client, $lastTrained), $this->exercise, [[50, 8, 2, 2]]);
        }

        return $client;
    }

    private function url(array $query = []): string
    {
        return route('staff.clients.index').($query ? '?'.http_build_query($query) : '');
    }

    /**
     * 画面に表示された順の、利用者名。
     *
     * @return array<int, string>
     */
    private function names(array $query = []): array
    {
        $html = $this->actingAs($this->trainer)->get($this->url($query))->assertOk()->getContent();
        preg_match_all('/<p class="font-medium">(.*?)<\/p>/', $html, $matches);

        return array_map('html_entity_decode', $matches[1]);
    }

    #[Test]
    public function 同じジムの利用者だけが表示される(): void
    {
        $this->client('山田太郎');
        User::factory()->client()->create(['name' => '他ジムの利用者']);
        User::factory()->personal()->create(['name' => '個人利用者']);
        User::factory()->trainer($this->gym)->create(['name' => '佐藤トレーナー']);
        User::factory()->owner($this->gym)->create(['name' => 'オーナー']);

        $names = $this->names();

        $this->assertSame(['山田太郎'], $names);
    }

    #[Test]
    public function 初期の並びは最終トレーニング日の新しい順で記録がない利用者は末尾になる(): void
    {
        $this->client('記録なしA');
        $this->client('古い人', '2026-10-01');
        $this->client('新しい人', '2026-11-12');
        $this->client('記録なしB');
        $this->client('中間の人', '2026-11-01');

        $this->assertSame(['新しい人', '中間の人', '古い人', '記録なしA', '記録なしB'], $this->names());
    }

    #[Test]
    public function 最終トレーニング日は複数のセッションのうち最も新しい日である(): void
    {
        $client = $this->client('山田太郎', '2026-10-01');
        $this->addSets($this->makeSession($client, '2026-11-10'), $this->exercise, [[50, 8, 2, 2]]);
        $this->addSets($this->makeSession($client, '2026-10-20'), $this->exercise, [[50, 8, 2, 2]]);
        $this->client('別の人', '2026-11-05');

        $this->assertSame(['山田太郎', '別の人'], $this->names());
        $this->actingAs($this->trainer)->get($this->url())->assertSee('11/10（3日前）');
    }

    #[Test]
    public function 最終トレーニング日が同じなら名前順になる(): void
    {
        $this->client('ちば', '2026-11-10');
        $this->client('あべ', '2026-11-10');

        $names = $this->names();

        $this->assertSame(['あべ', 'ちば'], $names);
    }

    #[Test]
    public function 名前順を選べる(): void
    {
        $this->client('Bさん', '2026-11-12');
        $this->client('Aさん');
        $this->client('Cさん', '2026-11-01');

        $this->assertSame(['Aさん', 'Bさん', 'Cさん'], $this->names(['sort' => 'name']));
    }

    #[Test]
    public function 不正な並び順は初期値になる(): void
    {
        $this->client('記録なし');
        $this->client('記録あり', '2026-11-12');

        foreach (['abc', 'id', 'name; drop table users', '', ['x'], 'NAME'] as $sort) {
            $this->assertSame(['記録あり', '記録なし'], $this->names(['sort' => $sort]), json_encode($sort));
        }
    }

    #[Test]
    public function 最終トレーニング日は経過日数つきで表示され記録がなければ記録なしと表示される(): void
    {
        $this->client('今日の人', '2026-11-13');
        $this->client('昨日の人', '2026-11-12');
        $this->client('3日前の人', '2026-11-10');
        $this->client('先月の人', '2026-10-01');
        $this->client('記録なしの人');

        $this->actingAs($this->trainer)->get($this->url())
            ->assertSee('11/13（今日）')
            ->assertSee('11/12（1日前）')
            ->assertSee('11/10（3日前）')
            ->assertSee('10/1（43日前）')
            ->assertSee('記録なし');
    }

    #[Test]
    public function パスワード未設定の利用者だけにその旨が表示される(): void
    {
        $this->client('設定済み');
        $this->client('未設定', null, ['password' => null]);

        $html = $this->actingAs($this->trainer)->get($this->url())->getContent();

        $this->assertSame(1, substr_count($html, 'パスワード未設定'));
    }

    #[Test]
    public function 行は利用者詳細へのリンクである(): void
    {
        $client = $this->client('山田太郎');

        $this->actingAs($this->trainer)->get($this->url())
            ->assertSee('href="'.route('staff.clients.show', $client).'"', false);
    }

    #[Test]
    public function 上部に利用者アカウント発行と招待コード発行のボタンがある(): void
    {
        $this->actingAs($this->trainer)->get($this->url())
            ->assertSee('利用者アカウント発行')
            ->assertSee(route('staff.clients.create'), false)
            ->assertSee('招待コード発行')
            ->assertSee(route('staff.invitation-codes.index'), false);
    }

    #[Test]
    public function 利用者がいなければ案内が表示される(): void
    {
        $this->actingAs($this->trainer)->get($this->url())->assertSee('利用者がまだいません。');
        $this->get($this->url(['q' => '誰か']))->assertSee('該当する利用者はいません。');
    }

    #[Test]
    public function 名前の部分一致で検索できる(): void
    {
        $this->client('山田太郎');
        $this->client('山田花子');
        $this->client('鈴木一郎');

        $this->assertSame(['山田太郎', '山田花子'], $this->names(['q' => '山田', 'sort' => 'name']));
        $this->assertSame(['山田花子'], $this->names(['q' => '花']));
        $this->assertSame([], $this->names(['q' => '存在しない']));
    }

    #[Test]
    public function 検索語の前後の空白は取り除かれ空白だけなら検索しない(): void
    {
        $this->client('山田太郎');
        $this->client('鈴木一郎');

        $this->assertSame(['山田太郎'], $this->names(['q' => '  山田  ']));
        $this->assertCount(2, $this->names(['q' => '   ']));
        $this->assertCount(2, $this->names(['q' => '']));
    }

    #[Test]
    public function 検索語は50文字以内でそれを超える場合は検索しない(): void
    {
        $this->client(str_repeat('あ', 50));
        $this->client('別の人');

        $this->assertSame([str_repeat('あ', 50)], $this->names(['q' => str_repeat('あ', 50)]));
        // 51文字は不正な値として無視し、全員を表示する
        $this->assertCount(2, $this->names(['q' => str_repeat('あ', 51)]));
    }

    #[Test]
    public function 検索語のパーセントとアンダースコアは文字として検索される(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('LIKE のエスケープは MySQL の仕様に依存します（DB_CONNECTION=mysql を指定してください）。');
        }

        $this->client('山田太郎');
        $this->client('100%達成');
        $this->client('a_b');
        $this->client('axb');

        $this->assertSame(['100%達成'], $this->names(['q' => '%']));
        $this->assertSame(['a_b'], $this->names(['q' => '_']));
        $this->assertSame(['a_b'], $this->names(['q' => 'a_b']));
    }

    #[Test]
    public function 検索語の特殊文字が画面でエスケープされて表示される(): void
    {
        $this->client('山田太郎');

        $this->actingAs($this->trainer)->get($this->url(['q' => '"><script>alert(1)</script>']))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&quot;&gt;&lt;script&gt;', false);
    }

    #[Test]
    public function 一覧は1ページ20件でページ分けされ検索と並び順の条件がページ移動後も保たれる(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->client(sprintf('利用者%02d', $i));
        }

        $first = $this->names(['sort' => 'name']);
        $second = $this->names(['sort' => 'name', 'page' => 2]);

        $this->assertCount(20, $first);
        $this->assertCount(5, $second);
        $this->assertSame('利用者01', $first[0]);
        $this->assertSame('利用者25', $second[4]);

        $html = $this->actingAs($this->trainer)->get($this->url(['q' => '利用者', 'sort' => 'name']))->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*page=2[^"]*"/', $html);
        $this->assertStringContainsString('sort=name', html_entity_decode($html));
        $this->assertStringContainsString('q=%E5%88%A9%E7%94%A8%E8%80%85', html_entity_decode($html));
    }

    #[Test]
    public function ページの指定が不正でも1ページ目が表示される(): void
    {
        $this->client('山田太郎');

        foreach (['0', '-1', 'abc', '999'] as $page) {
            $this->actingAs($this->trainer)->get($this->url(['page' => $page]))->assertOk();
        }
        $this->assertSame(['山田太郎'], $this->names(['page' => 'abc']));
    }

    #[Test]
    public function 利用者や他の人は一覧を表示できない(): void
    {
        $client = $this->client('山田太郎');

        $this->actingAs($client)->get($this->url())->assertForbidden();
        $this->actingAs(User::factory()->personal()->create())->get($this->url())->assertForbidden();
        $this->actingAs(User::factory()->trainer($this->gym)->suspended()->create())->get($this->url())->assertRedirect(route('login'));
        auth()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    #[Test]
    public function オーナーも一覧を表示できる(): void
    {
        $this->client('山田太郎');

        $this->actingAs(User::factory()->owner($this->gym)->create())->get($this->url())->assertOk()->assertSee('山田太郎');
    }

    #[Test]
    public function 解除した利用者は一覧に表示されなくなる(): void
    {
        $client = $this->client('山田太郎');
        $client->forceFill(['gym_id' => null])->save();

        $this->assertSame([], $this->names());
    }

    #[Test]
    public function スタッフのナビは利用者と種目で記録系の項目はない(): void
    {
        $html = $this->actingAs($this->trainer)->get($this->url())->getContent();

        $this->assertStringContainsString(route('staff.clients.index'), $html);
        $this->assertStringContainsString(route('staff.exercises.index'), $html);
        $this->assertStringNotContainsString('>記録</a>', $html);
        $this->assertStringNotContainsString('>グラフ</a>', $html);
    }
}
