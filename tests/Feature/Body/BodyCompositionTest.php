<?php

namespace Tests\Feature\Body;

use App\Actions\Body\SaveBodyComposition;
use App\Models\BodyComposition;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BodyCompositionTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $client;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->gym = Gym::factory()->create();
        $this->client = User::factory()->client($this->gym)->create(['name' => '山田太郎']);
        $this->trainer = User::factory()->trainer($this->gym)->create(['name' => '田中トレーナー']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function url(): string
    {
        return route('records.body.index', $this->client);
    }

    private function storeUrl(): string
    {
        return route('records.body.store', $this->client);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function input(array $override = []): array
    {
        return array_merge(['measured_on' => '2026-11-13', 'weight_kg' => '70.5'], $override);
    }

    private function record(string $date, array $values = [], ?User $user = null, ?User $by = null): BodyComposition
    {
        $user ??= $this->client;
        $record = new BodyComposition;
        $record->fill(['measured_on' => $date, ...$values]);
        $record->forceFill(['user_id' => $user->id, 'created_by' => ($by ?? $user)->id, 'updated_by' => ($by ?? $user)->id])->save();

        return $record;
    }

    #[Test]
    public function 記録がなければ空の履歴と今日の日付のフォームが表示される(): void
    {
        $html = $this->actingAs($this->client)->get($this->url())->assertOk()
            ->assertSee('体組成の記録はまだありません。')
            ->assertSee('周囲径を入力する')
            ->assertSee('記録する')
            ->assertDontSee('この日の記録があります')
            ->getContent();

        $this->assertStringContainsString('value="2026-11-13"', $html);
        $this->assertStringContainsString('max="2026-11-13"', $html);
    }

    #[Test]
    public function 履歴は新しい順で日付と体重と体脂肪率と筋肉量と入力者が表示される(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.0, 'body_fat_percent' => 18.5, 'muscle_mass_kg' => 55.2]);
        $this->record('2026-11-10', ['weight_kg' => 69.8], null, $this->trainer);
        $this->record('2026-10-20', ['waist_cm' => 80]);

        $html = $this->actingAs($this->client)->get($this->url())->assertOk()->getContent();
        // 表示上の空白（改行・インデント）をそろえて確認する
        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertStringContainsString('体重 70.0kg／ 体脂肪率 18.5%／ 筋肉量 55.2kg', $text);
        $this->assertStringContainsString('体重 69.8kg／ 体脂肪率 －／ 筋肉量 －', $text);
        $this->assertStringContainsString('入力：田中トレーナー／更新：田中トレーナー', $text);
        $this->assertStringContainsString('入力：本人／更新：本人', $text);
        $this->assertTextInOrder($text, ['2026/11/10（火）', '2026/11/01（日）', '2026/10/20（火）']);
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function assertTextInOrder(string $text, array $needles): void
    {
        $position = 0;

        foreach ($needles as $needle) {
            $found = strpos($text, $needle, $position);
            $this->assertNotFalse($found, $needle);
            $position = $found + strlen($needle);
        }
    }

    #[Test]
    public function 他の利用者の記録は履歴に表示されない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->record('2026-11-01', ['weight_kg' => 99.9], $other);

        $this->actingAs($this->client)->get($this->url())->assertDontSee('99.9');
    }

    #[Test]
    public function 履歴は20件ごとにページ分けされる(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->record(Carbon::parse('2026-11-12')->subDays($i)->toDateString(), ['weight_kg' => 70]);
        }
        $this->actingAs($this->client);

        $first = $this->get($this->url())->assertOk()->getContent();
        $second = $this->get($this->url().'?page=2')->assertOk()->getContent();

        $this->assertSame(20, substr_count($first, '>編集</a>'));
        $this->assertSame(5, substr_count($second, '>編集</a>'));
    }

    #[Test]
    public function 既に記録がある日付を選ぶとその記録の編集になる(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.2, 'waist_cm' => 82.5]);

        $html = $this->actingAs($this->client)->get($this->url().'?date=2026-11-01')->assertOk()
            ->assertSee('この日の記録があります。保存すると更新されます。')
            ->assertSee('更新する')
            ->getContent();

        $this->assertStringContainsString('value="2026-11-01"', $html);
        $this->assertStringContainsString('value="70.2"', $html);
        // 周囲径に値があれば折りたたみは開いた状態になる
        $this->assertMatchesRegularExpression('/<details class="mb-4"\s+open/', $html);
        $this->assertStringContainsString('value="82.5"', $html);
    }

    #[Test]
    public function 記録のない日付を選ぶと空のフォームになる(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70.2]);

        $html = $this->actingAs($this->client)->get($this->url().'?date=2026-11-05')->assertOk()->getContent();

        $this->assertStringContainsString('value="2026-11-05"', $html);
        $this->assertStringNotContainsString('value="70.2"', $html);
        $this->assertStringNotContainsString('更新する', $html);
    }

    #[Test]
    public function 不正な日付や範囲外の日付は今日として表示される(): void
    {
        $this->actingAs($this->client);

        foreach (['abc', '2026-13-45', '2026-02-30', '1999-12-31', '2026-11-14', '20261101', '', ['x']] as $date) {
            $this->get($this->url().'?'.http_build_query(['date' => $date]))
                ->assertOk()
                ->assertSee('value="2026-11-13"', false);
        }
    }

    #[Test]
    public function 体重だけでも記録できる(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), $this->input())
            ->assertRedirect($this->url().'?date=2026-11-13')
            ->assertSessionHas('status', '体組成を記録しました。');

        $record = BodyComposition::sole();
        $this->assertSame($this->client->id, $record->user_id);
        $this->assertSame('2026-11-13', $record->measured_on->toDateString());
        $this->assertSame('70.5', $record->weight_kg);
        $this->assertNull($record->body_fat_percent);
        $this->assertNull($record->waist_cm);
        $this->assertSame($this->client->id, $record->created_by);
        $this->assertSame($this->client->id, $record->updated_by);
    }

    #[Test]
    public function 全項目を記録できる(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), $this->input([
            'weight_kg' => '70.5', 'body_fat_percent' => '18.2', 'muscle_mass_kg' => '55.0',
            'waist_cm' => '80.5', 'chest_cm' => '95', 'upper_arm_cm' => '32.1', 'thigh_cm' => '55.5',
            'hip_cm' => '92', 'calf_cm' => '38.2', 'neck_cm' => '37',
        ]))->assertSessionHasNoErrors();

        $record = BodyComposition::sole();
        $this->assertSame(
            ['70.5', '18.2', '55.0', '80.5', '95.0', '32.1', '55.5', '92.0', '38.2', '37.0'],
            [$record->weight_kg, $record->body_fat_percent, $record->muscle_mass_kg, $record->waist_cm, $record->chest_cm,
                $record->upper_arm_cm, $record->thigh_cm, $record->hip_cm, $record->calf_cm, $record->neck_cm],
        );
    }

    #[Test]
    public function 周囲径だけでも記録できる(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), ['measured_on' => '2026-11-13', 'neck_cm' => '37'])
            ->assertSessionHasNoErrors();

        $this->assertSame('37.0', BodyComposition::sole()->neck_cm);
    }

    #[Test]
    public function すべての項目が空だとエラーになる(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), ['measured_on' => '2026-11-13'])
            ->assertSessionHasErrors(['measurements' => '体重・体脂肪率・筋肉量・周囲径のうち、1項目以上を入力してください。']);
        $this->post($this->storeUrl(), ['measured_on' => '2026-11-13', 'weight_kg' => '', 'waist_cm' => ''])
            ->assertSessionHasErrors('measurements');

        $this->assertSame(0, BodyComposition::count());
    }

    #[Test]
    public function 各項目は範囲の境界の内側は許可され外側は拒否される(): void
    {
        $this->actingAs($this->client);

        foreach (BodyComposition::RANGES as $field => [$min, $max]) {
            foreach ([$min, $max, ($min + $max) / 2] as $inside) {
                $this->post($this->storeUrl(), ['measured_on' => '2026-11-13', $field => number_format($inside, 1, '.', '')])
                    ->assertSessionHasNoErrors();
            }
            foreach ([$min - 0.1, $max + 0.1, 0, -1] as $outside) {
                $this->post($this->storeUrl(), ['measured_on' => '2026-11-13', $field => (string) $outside])
                    ->assertSessionHasErrors($field);
            }
        }
    }

    #[Test]
    public function 小数第2位以上や数値でない値は拒否される(): void
    {
        $this->actingAs($this->client);

        foreach (['70.55', '70.123', 'abc', '70kg', '7e1'] as $value) {
            $this->post($this->storeUrl(), $this->input(['weight_kg' => $value]))->assertSessionHasErrors('weight_kg');
        }
        $this->post($this->storeUrl(), $this->input(['body_fat_percent' => '18.25']))->assertSessionHasErrors('body_fat_percent');
    }

    #[Test]
    public function エラーメッセージは項目名と範囲が日本語で表示される(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), $this->input(['weight_kg' => '19.9', 'body_fat_percent' => '71', 'waist_cm' => '9']))
            ->assertSessionHasErrors([
                'weight_kg' => '体重は20〜300（kg）の範囲で入力してください。',
                'body_fat_percent' => '体脂肪率は1〜70（%）の範囲で入力してください。',
                'waist_cm' => 'ウエストは10〜250（cm）の範囲で入力してください。',
            ]);
    }

    #[Test]
    public function 測定日は2000年1月1日から今日までで未来日は記録できない(): void
    {
        $this->actingAs($this->client);

        $this->post($this->storeUrl(), $this->input(['measured_on' => '2000-01-01']))->assertSessionHasNoErrors();
        $this->post($this->storeUrl(), $this->input(['measured_on' => '2026-11-13']))->assertSessionHasNoErrors();
        $this->post($this->storeUrl(), $this->input(['measured_on' => '1999-12-31']))
            ->assertSessionHasErrors(['measured_on' => '測定日は2000/01/01以降にしてください。']);
        $this->post($this->storeUrl(), $this->input(['measured_on' => '2026-11-14']))
            ->assertSessionHasErrors(['measured_on' => '未来の日付は記録できません。']);
        $this->post($this->storeUrl(), $this->input(['measured_on' => '']))->assertSessionHasErrors(['measured_on' => '測定日を入力してください。']);
        $this->post($this->storeUrl(), $this->input(['measured_on' => '2026-02-30']))->assertSessionHasErrors('measured_on');
        $this->post($this->storeUrl(), $this->input(['measured_on' => '2026/11/01']))->assertSessionHasErrors('measured_on');
    }

    #[Test]
    public function 同じ日付で保存すると新規作成ではなく更新になる(): void
    {
        $this->record('2026-11-13', ['weight_kg' => 70.0, 'body_fat_percent' => 18.0]);

        $this->actingAs($this->trainer)->post($this->storeUrl(), $this->input(['weight_kg' => '69.5']))
            ->assertRedirect($this->url().'?date=2026-11-13')
            ->assertSessionHas('status', '体組成を更新しました。');

        $record = BodyComposition::sole();
        $this->assertSame('69.5', $record->weight_kg);
        // フォームの内容どおりに置き換えるため、空にした項目は NULL になる
        $this->assertNull($record->body_fat_percent);
        $this->assertSame($this->client->id, $record->created_by);
        $this->assertSame($this->trainer->id, $record->updated_by);
    }

    #[Test]
    public function 他の日付や他の利用者の同じ日付があっても新規に作成できる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $this->record('2026-11-12', ['weight_kg' => 70], $this->client);
        $this->record('2026-11-13', ['weight_kg' => 80], $other);

        $this->actingAs($this->client)->post($this->storeUrl(), $this->input())->assertSessionHasNoErrors();

        $this->assertSame(3, BodyComposition::count());
        $this->assertSame('80.0', BodyComposition::where('user_id', $other->id)->sole()->weight_kg);
    }

    #[Test]
    public function スタッフが入力すると入力者はスタッフで記録の持ち主は利用者になる(): void
    {
        $this->actingAs($this->trainer)->post($this->storeUrl(), $this->input())->assertSessionHasNoErrors();

        $record = BodyComposition::sole();
        $this->assertSame($this->client->id, $record->user_id);
        $this->assertSame($this->trainer->id, $record->created_by);
        $this->assertSame($this->trainer->id, $record->updated_by);
    }

    #[Test]
    public function リクエストのuser_idやcreated_byは使われない(): void
    {
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($this->client)->post($this->storeUrl(), $this->input(['user_id' => $other->id, 'created_by' => $other->id, 'updated_by' => $other->id]));

        $record = BodyComposition::sole();
        $this->assertSame($this->client->id, $record->user_id);
        $this->assertSame($this->client->id, $record->created_by);
        $this->assertSame($this->client->id, $record->updated_by);
    }

    #[Test]
    public function 入力エラーのとき入力値が保持され周囲径の折りたたみが開く(): void
    {
        $this->actingAs($this->client)->from($this->url())
            ->post($this->storeUrl(), $this->input(['waist_cm' => '9', 'weight_kg' => '71.5']));

        $html = $this->get($this->url())->getContent();

        $this->assertStringContainsString('value="71.5"', $html);
        $this->assertStringContainsString('value="9"', $html);
        $this->assertMatchesRegularExpression('/<details class="mb-4"\s+open/', $html);
        $this->assertStringContainsString('ウエストは10〜250（cm）の範囲で入力してください。', $html);
    }

    #[Test]
    public function 他の利用者や他ジムのスタッフは閲覧も保存も削除もできない(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $outsider = User::factory()->trainer()->create();
        $record = $this->record('2026-11-01', ['weight_kg' => 70]);

        foreach ([$other, $outsider] as $user) {
            $this->actingAs($user)->get($this->url())->assertForbidden();
            $this->actingAs($user)->post($this->storeUrl(), $this->input())->assertForbidden();
            $this->actingAs($user)->delete(route('records.body.destroy', [$this->client, $record]))->assertForbidden();
        }

        $this->assertSame(1, BodyComposition::count());
    }

    #[Test]
    public function 認可は入力の検証より先に行われる(): void
    {
        $other = User::factory()->client($this->gym)->create();

        $this->actingAs($other)->post($this->storeUrl(), [])->assertForbidden();
    }

    #[Test]
    public function 未ログインとメール未認証は使えない(): void
    {
        $unverified = User::factory()->personal()->unverified()->create();

        $this->get($this->url())->assertRedirect(route('login'));
        $this->actingAs($unverified)->get(route('records.body.index', $unverified))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function 紐付け解除後はスタッフが体組成を閲覧できなくなる(): void
    {
        $this->client->forceFill(['gym_id' => null])->save();

        $this->actingAs($this->trainer)->get($this->url())->assertForbidden();
    }

    #[Test]
    public function 利用者もスタッフも記録を削除できる(): void
    {
        $mine = $this->record('2026-11-01', ['weight_kg' => 70]);
        $other = $this->record('2026-11-02', ['weight_kg' => 71]);

        $this->actingAs($this->client)->delete(route('records.body.destroy', [$this->client, $mine]))
            ->assertRedirect($this->url())
            ->assertSessionHas('status', '体組成の記録を削除しました。');
        $this->actingAs($this->trainer)->delete(route('records.body.destroy', [$this->client, $other]));

        $this->assertSame(0, BodyComposition::count());
    }

    #[Test]
    public function 他の利用者の記録のIDを指定すると404になる(): void
    {
        $other = User::factory()->client($this->gym)->create();
        $others = $this->record('2026-11-01', ['weight_kg' => 99], $other);

        $this->actingAs($this->client)->delete(route('records.body.destroy', [$this->client, $others->id]))->assertNotFound();
        $this->actingAs($this->trainer)->delete(route('records.body.destroy', [$this->client, $others->id]))->assertNotFound();
        $this->actingAs($this->client)->delete(route('records.body.destroy', [$this->client, 999999]))->assertNotFound();

        $this->assertSame(1, BodyComposition::count());
    }

    #[Test]
    public function 履歴の編集と削除は右端に並び削除は画面内のモーダルで確認する(): void
    {
        $record = $this->record('2026-11-01', ['weight_kg' => 70]);

        $this->actingAs($this->client)->get($this->url())
            ->assertSee($this->url().'?date=2026-11-01#body-form', false)
            ->assertSee(route('records.body.destroy', [$this->client, $record]), false)
            ->assertSee('2026/11/01の体組成の記録を削除します。', false)
            ->assertSee('role="dialog"', false);
    }

    #[Test]
    public function ナビに体組成の項目が表示される(): void
    {
        $this->actingAs($this->client)->get($this->url())
            ->assertSee('体組成')
            ->assertSee(route('records.body.index', $this->client), false);
    }

    #[Test]
    public function スタッフが見るとき帯と体組成のナビが利用者のものになる(): void
    {
        $this->actingAs($this->trainer)->get($this->url())
            ->assertSee('▶ 山田太郎さんの記録を表示中')
            ->assertSee(route('records.body.index', $this->client), false);
    }

    #[Test]
    public function 画面にBladeの記述が文字として表示されない(): void
    {
        $this->record('2026-11-01', ['weight_kg' => 70, 'waist_cm' => 80]);
        $this->actingAs($this->client);

        foreach ([$this->url(), $this->url().'?date=2026-11-01'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsString('@if', $html);
            $this->assertStringNotContainsString('$errors', $html);
            $this->assertStringNotContainsString('@php', $html);
        }
    }

    #[Test]
    public function 測定日は日付だけで保存され時刻が付かない(): void
    {
        $this->actingAs($this->client)->post($this->storeUrl(), $this->input(['measured_on' => '2026-11-05']));

        $this->assertSame('2026-11-05', BodyComposition::sole()->getRawOriginal('measured_on'));
        $this->assertDatabaseHas('body_compositions', ['user_id' => $this->client->id, 'measured_on' => '2026-11-05']);
    }

    #[Test]
    public function 同時に保存されて一意制約に当たっても既にある記録を更新する(): void
    {
        $action = app(SaveBodyComposition::class);
        $action->handle($this->client, $this->client, ['measured_on' => '2026-11-13', 'weight_kg' => '70.0']);
        $action->handle($this->trainer, $this->client, ['measured_on' => '2026-11-13', 'weight_kg' => '71.0']);

        $this->assertSame(1, BodyComposition::count());
        $this->assertSame('71.0', BodyComposition::sole()->weight_kg);
    }
}
