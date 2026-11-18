<?php

namespace Tests\Feature\Account;

use App\Models\Gym;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsWorkouts;
use Tests\Support\FakesAuditLog;
use Tests\TestCase;

/**
 * 操作ログに値（パスワード・招待コード・メモ・コメント・プロフィール・体組成の値）が出ないこと
 * （セキュリティ設計書8.3）。いくつもの操作を行い、ログ全体を一度に調べる。
 */
class AuditLogContentTest extends TestCase
{
    use BuildsWorkouts;
    use FakesAuditLog;
    use RefreshDatabase;

    #[Test]
    public function 操作ログにパスワード・招待コード・メモ・コメント・プロフィールの値が含まれない(): void
    {
        $this->fakeAuditLog();

        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create(['email' => 'trainer-secret@example.com']);
        $client = User::factory()->client($gym)->create(['email' => 'client-secret@example.com']);

        // パスワード変更・プロフィール更新（メールアドレスも変更）
        $this->actingAs($client)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Brand-new-pass-777',
            'password_confirmation' => 'Brand-new-pass-777',
        ])->assertSessionHasNoErrors();
        $this->actingAs($client)->put(route('user-profile-information.update'), [
            'name' => '秘密の氏名',
            'email' => 'changed-secret@example.com',
            'current_password' => 'Brand-new-pass-777',
            'height_cm' => '171.5',
        ])->assertSessionHasNoErrors();

        // 別の人の操作へ移る（セッションに残った利用者のパスワードの印で、トレーナーがログアウトされないように）
        $this->flushSession();

        // 招待コードの発行・指導メモ・コメント・体組成
        $this->actingAs($trainer)->post(route('staff.invitation-codes.store'), ['memo' => '秘密の招待メモ'])->assertSessionHasNoErrors()->assertRedirect(route('staff.invitation-codes.index'));
        $code = InvitationCode::sole()->code;
        $this->actingAs($trainer)->post(route('staff.clients.notes.store', $client), ['category' => 'injury', 'body' => '秘密の指導メモ本文'])
            ->assertSessionHasNoErrors();
        $session = $this->makeSession($client, '2026-11-12');
        $this->actingAs($trainer)->post(route('records.comments.store', [$client, $session]), ['body' => '秘密のコメント本文'])
            ->assertSessionHasNoErrors();
        $this->flushSession();
        $this->actingAs($client)->post(route('records.body.store', $client), ['measured_on' => '2026-11-12', 'weight_kg' => '73.456'])
            ->assertSessionHasNoErrors();

        // ログイン失敗（パスワードは入力値として渡すだけ）
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'client-secret@example.com', 'password' => 'typed-wrong-pass-123']);

        $log = json_encode($this->auditRecords(), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('password.changed', $log);
        $this->assertStringContainsString('profile.updated', $log);
        $this->assertStringContainsString('invitation_code.issued', $log);

        foreach (['Brand-new-pass-777', 'typed-wrong-pass-123', $code, '秘密の氏名', 'changed-secret@example.com', '秘密の招待メモ',
            '秘密の指導メモ本文', '秘密のコメント本文', '171.5', '73.456', 'trainer-secret@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $log, "操作ログに「{$secret}」が含まれています");
        }
    }

    #[Test]
    public function 指導メモとコメントの操作では操作ログを作らない(): void
    {
        $this->fakeAuditLog();
        $gym = Gym::factory()->create();
        $trainer = User::factory()->trainer($gym)->create();
        $client = User::factory()->client($gym)->create();
        $session = $this->makeSession($client, '2026-11-12');

        $this->actingAs($trainer)->post(route('staff.clients.notes.store', $client), ['category' => 'caution', 'body' => 'メモ']);
        $this->actingAs($trainer)->post(route('records.comments.store', [$client, $session]), ['body' => 'コメント']);

        $this->assertSame([], $this->auditRecords());
    }
}
