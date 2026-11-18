<?php

namespace Tests\Feature\Profile;

use App\Enums\Gender;
use App\Models\BodyComposition;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use FakesAuditLog;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(User $user, array $overrides = []): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            ...$overrides,
        ];
    }

    private function update(User $user, array $data)
    {
        return $this->actingAs($user)->from(route('profile.show'))->put(route('user-profile-information.update'), $data);
    }

    #[Test]
    public function 利用者のプロフィール画面に全項目が表示される(): void
    {
        $user = User::factory()->personal()->create(['height_cm' => 172.5, 'birth_date' => '1990-04-05', 'gender' => Gender::Female]);
        BodyComposition::factory()->for($user)->create(['measured_on' => '2026-11-01', 'weight_kg' => 61.5]);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('value="172.5"', false)
            ->assertSee('value="1990-04-05"', false)
            ->assertSee('<option value="female" selected>女性</option>', false)
            ->assertSee('61.5kg')
            ->assertSee('体重は体組成の記録で入力します。');
    }

    #[Test]
    public function スタッフのプロフィール画面には身長などと体重を表示しない(): void
    {
        $this->actingAs(User::factory()->trainer()->create())->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('name="height_cm"', false)
            ->assertDontSee('name="birth_date"', false)
            ->assertDontSee('name="gender"', false)
            ->assertDontSee('体重：');
    }

    #[Test]
    public function 未ログインではプロフィール画面を開けない(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    #[Test]
    public function メール未認証ではプロフィール画面を開けない(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get(route('profile.show'))
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function メニューからプロフィールへ移れる(): void
    {
        foreach ([User::factory()->personal()->create(), User::factory()->trainer()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get(route('profile.show'))->assertSee(route('profile.show'), false);
        }
    }

    #[Test]
    public function 利用者は全項目を更新できる(): void
    {
        $user = User::factory()->personal()->create();

        $this->update($user, $this->input($user, [
            'name' => '新しい名前', 'height_cm' => '180.5', 'birth_date' => '1985-12-31', 'gender' => 'male',
        ]))->assertRedirect(route('profile.show'))->assertSessionHas('status', 'プロフィールを更新しました。');

        $user->refresh();
        $this->assertSame('新しい名前', $user->name);
        $this->assertEquals(180.5, $user->height_cm);
        $this->assertSame('1985-12-31', $user->birth_date->format('Y-m-d'));
        $this->assertSame(Gender::Male, $user->gender);
    }

    #[Test]
    public function 身長などを空にするとNULLになる(): void
    {
        $user = User::factory()->personal()->create(['height_cm' => 170, 'birth_date' => '1990-01-01', 'gender' => Gender::Male]);

        $this->update($user, $this->input($user, ['height_cm' => '', 'birth_date' => '', 'gender' => '']))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->height_cm);
        $this->assertNull($user->birth_date);
        $this->assertNull($user->gender);
    }

    #[Test]
    public function スタッフが身長などを送っても無視される(): void
    {
        $trainer = User::factory()->trainer()->create(['height_cm' => null, 'birth_date' => null, 'gender' => null]);

        $this->update($trainer, $this->input($trainer, [
            'name' => '変更後', 'height_cm' => '170', 'birth_date' => '1990-01-01', 'gender' => 'male',
        ]))->assertSessionHasNoErrors();

        $trainer->refresh();
        $this->assertSame('変更後', $trainer->name);
        $this->assertNull($trainer->height_cm);
        $this->assertNull($trainer->birth_date);
        $this->assertNull($trainer->gender);
    }

    #[Test]
    public function スタッフが身長に不正な値を送ってもエラーにならない(): void
    {
        $trainer = User::factory()->trainer()->create();

        $this->update($trainer, $this->input($trainer, ['height_cm' => 'abc', 'gender' => 'xxx']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function ロールやジムは更新できない(): void
    {
        $user = User::factory()->personal()->create();

        $this->update($user, $this->input($user, ['role' => 'owner', 'gym_id' => 1, 'password' => 'hacked-password']));

        $user->refresh();
        $this->assertTrue($user->isClient());
        $this->assertNull($user->gym_id);
        $this->assertTrue(\Hash::check('password', $user->password));
    }

    #[Test]
    public function 他の利用者のプロフィールは更新できない(): void
    {
        $user = User::factory()->personal()->create();
        $other = User::factory()->personal()->create(['name' => '他人']);

        $this->update($user, $this->input($user, ['id' => $other->id, 'user_id' => $other->id, 'name' => '乗っ取り']));

        $this->assertSame('他人', $other->fresh()->name);
        $this->assertSame('乗っ取り', $user->fresh()->name);
    }

    #[Test]
    public function メールアドレスを変更するには現在のパスワードが必要(): void
    {
        $user = User::factory()->personal()->create();

        $this->update($user, $this->input($user, ['email' => 'new@example.com']))
            ->assertSessionHasErrorsIn('updateProfileInformation', ['current_password']);

        $this->assertNotSame('new@example.com', $user->fresh()->email);
    }

    #[Test]
    public function 現在のパスワードが違うとメールアドレスを変更できない(): void
    {
        $user = User::factory()->personal()->create();

        $this->update($user, $this->input($user, ['email' => 'new@example.com', 'current_password' => 'wrong-password']))
            ->assertSessionHasErrorsIn('updateProfileInformation', ['current_password']);

        $this->assertNotSame('new@example.com', $user->fresh()->email);
    }

    #[Test]
    public function メールアドレスを変更すると未認証になり認証メールが送られる(): void
    {
        Notification::fake();
        $user = User::factory()->personal()->create();

        $this->update($user, $this->input($user, ['email' => '  NEW@Example.com ', 'current_password' => 'password']))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('profile.show'))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function メールアドレスが同じなら現在のパスワードは不要で認証も維持される(): void
    {
        Notification::fake();
        $user = User::factory()->personal()->create();

        $this->update($user, $this->input($user, ['email' => strtoupper($user->email), 'name' => '名前だけ変更']))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    #[Test]
    public function 他の利用者が使っているメールアドレスには変更できない(): void
    {
        $user = User::factory()->personal()->create();
        $other = User::factory()->personal()->create();

        $this->update($user, $this->input($user, ['email' => $other->email, 'current_password' => 'password']))
            ->assertSessionHasErrorsIn('updateProfileInformation', ['email']);
    }

    #[Test]
    public function スタッフもメールアドレスを変更できる(): void
    {
        Notification::fake();
        $trainer = User::factory()->trainer()->create();

        $this->update($trainer, $this->input($trainer, ['email' => 'staff-new@example.com', 'current_password' => 'password']))
            ->assertSessionHasNoErrors();

        $this->assertSame('staff-new@example.com', $trainer->fresh()->email);
    }

    #[Test]
    public function 入力エラーは日本語で表示され入力値が保持される(): void
    {
        $user = User::factory()->personal()->create();

        $this->actingAs($user)->from(route('profile.show'))
            ->followingRedirects()
            ->put(route('user-profile-information.update'), $this->input($user, ['name' => '', 'height_cm' => '99', 'gender' => 'xxx']))
            ->assertSee('名前を入力してください。')
            ->assertSee('身長は100〜250cmの範囲で入力してください。')
            ->assertSee('性別の選択が正しくありません。');
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function 名前の境界(): array
    {
        return [
            '50文字' => [str_repeat('あ', 50), 'name', true],
            '51文字' => [str_repeat('あ', 51), 'name', false],
            '空' => ['', 'name', false],
        ];
    }

    #[Test]
    #[DataProvider('名前の境界')]
    public function 名前の文字数の境界(string $value, string $field, bool $valid): void
    {
        $user = User::factory()->personal()->create();

        $response = $this->update($user, $this->input($user, [$field => $value]));

        $valid
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrorsIn('updateProfileInformation', [$field]);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function 身長の境界(): array
    {
        return [
            '100.0' => ['100.0', true],
            '250.0' => ['250.0', true],
            '99.9' => ['99.9', false],
            '250.1' => ['250.1', false],
            '小数第2位' => ['170.55', false],
            '整数' => ['170', true],
            '数値でない' => ['abc', false],
        ];
    }

    #[Test]
    #[DataProvider('身長の境界')]
    public function 身長の範囲の境界(string $value, bool $valid): void
    {
        $user = User::factory()->personal()->create();

        $response = $this->update($user, $this->input($user, ['height_cm' => $value]));

        $valid
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrorsIn('updateProfileInformation', ['height_cm']);
    }

    #[Test]
    public function 生年月日の境界(): void
    {
        $user = User::factory()->personal()->create();
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        foreach (['1900-01-01' => true, $today => true, '1899-12-31' => false, $tomorrow => false, '1990/01/01' => false, '2026-02-30' => false] as $value => $valid) {
            $response = $this->update($user, $this->input($user, ['birth_date' => (string) $value]));

            $valid
                ? $response->assertSessionHasNoErrors()
                : $response->assertSessionHasErrorsIn('updateProfileInformation', ['birth_date']);
        }
    }

    #[Test]
    public function 性別は定義された値のみ受け付ける(): void
    {
        $user = User::factory()->personal()->create();

        foreach (Gender::cases() as $gender) {
            $this->update($user, $this->input($user, ['gender' => $gender->value]))->assertSessionHasNoErrors();
        }

        $this->update($user, $this->input($user, ['gender' => 'unknown']))
            ->assertSessionHasErrorsIn('updateProfileInformation', ['gender']);
    }

    #[Test]
    public function 操作ログには値を残さない(): void
    {
        $this->fakeAuditLog();
        Notification::fake();
        $user = User::factory()->personal()->create();

        $this->update($user, $this->input($user, ['name' => '秘密の名前', 'email' => 'secret@example.com', 'current_password' => 'password']));

        $records = $this->auditRecords();
        $this->assertSame([['message' => 'profile.updated', 'context' => ['user_id' => $user->id, 'email_changed' => true]]], $records);
        $this->assertStringNotContainsString('secret', json_encode($records));
    }

    #[Test]
    public function 未ログインでは更新できない(): void
    {
        $this->put(route('user-profile-information.update'), ['name' => 'x', 'email' => 'x@example.com'])
            ->assertRedirect(route('login'));
    }
}
