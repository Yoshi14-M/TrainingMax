<?php

namespace Tests\Feature\Home;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->trainer = User::factory()->trainer($this->gym)->create();
    }

    /**
     * ナビ（メインメニュー）の項目名とリンク先。
     *
     * @return array<string, string>
     */
    private function navItems(string $html): array
    {
        preg_match('/<nav[^>]*aria-label="メインメニュー".*?<\/nav>/s', $html, $nav);
        preg_match_all('/<a href="([^"]+)"[^>]*>([^<]+)<\/a>/', $nav[0], $matches, PREG_SET_ORDER);

        $items = [];
        foreach ($matches as [, $href, $label]) {
            $items[trim($label)] = html_entity_decode($href);
        }

        return $items;
    }

    #[Test]
    public function 利用者のナビはホームから種目まで並ぶ(): void
    {
        $html = $this->actingAs($this->client)->get(route('records.home', $this->client))->getContent();
        $items = $this->navItems($html);

        $this->assertSame(['ホーム', '記録', '体組成', 'グラフ', '種目'], array_slice(array_keys($items), 0, 5));
        $this->assertSame(route('records.home', $this->client), $items['ホーム']);
        $this->assertSame(route('records.sessions.today', $this->client), $items['記録']);
        $this->assertArrayNotHasKey('利用者', $items);
    }

    #[Test]
    public function 利用者のナビの今の画面が強調される(): void
    {
        $html = $this->actingAs($this->client)->get(route('records.home', $this->client))->getContent();

        $this->assertMatchesRegularExpression('/border-b-2 border-blue-700 font-semibold text-blue-700"[^>]*>ホーム/', $html);
    }

    #[Test]
    public function スタッフのナビは利用者と種目である(): void
    {
        $items = $this->navItems($this->actingAs($this->trainer)->get(route('staff.clients.index'))->getContent());

        $this->assertSame(['利用者', '種目'], array_keys($items));
        $this->assertSame(route('staff.clients.index'), $items['利用者']);
        $this->assertSame(route('staff.exercises.index'), $items['種目']);
    }

    #[Test]
    public function スタッフが利用者の記録を表示しているときはホームから種目と利用者一覧へ戻る項目が並ぶ(): void
    {
        $items = $this->navItems($this->actingAs($this->trainer)->get(route('records.home', $this->client))->getContent());

        $this->assertSame(['ホーム', '記録', '体組成', 'グラフ', '種目', '利用者'], array_keys($items));
        $this->assertSame(route('records.home', $this->client), $items['ホーム']);
        $this->assertSame(route('staff.clients.index'), $items['利用者']);
    }

    #[Test]
    public function 帯の戻るリンクは利用者一覧へ向かう(): void
    {
        $this->actingAs($this->trainer)->get(route('records.home', $this->client))
            ->assertSee('▶ 山田太郎さんの記録を表示中')
            ->assertSee('利用者一覧へ戻る')
            ->assertSee('href="'.route('staff.clients.index').'"', false);
    }

    #[Test]
    public function 利用者自身の画面には帯が表示されない(): void
    {
        $this->actingAs($this->client)->get(route('records.home', $this->client))->assertDontSee('の記録を表示中');
    }

    #[Test]
    public function メニューにスタッフ用の項目は利用者に表示されずジム連携はスタッフに表示されない(): void
    {
        $client = $this->actingAs($this->client)->get(route('records.home', $this->client))->getContent();
        $staff = $this->actingAs($this->trainer)->get(route('staff.clients.index'))->getContent();

        $this->assertStringContainsString('ジム連携', $client);
        $this->assertStringNotContainsString('利用者アカウント発行', $client);
        $this->assertStringContainsString('利用者アカウント発行', $staff);
        $this->assertStringNotContainsString('ジム連携', $staff);
    }
}
