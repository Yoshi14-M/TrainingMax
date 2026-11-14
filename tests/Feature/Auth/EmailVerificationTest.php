<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function メール未認証の利用者がホームを開くと認証のお願い画面へ移る(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function 認証のお願い画面に再送ボタンが表示される(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('認証メールを再送');
    }

    #[Test]
    public function 認証メールのリンクを開くと認証が完了する(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function 署名が正しくないリンクでは認証されない(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function 他のユーザーの認証リンクでは認証されない(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $other->id,
            'hash' => sha1($other->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($other->fresh()->email_verified_at);
    }

    #[Test]
    public function 認証メールを再送できる(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function 認証メールの再送は1分間に7回目から回数制限される(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post(route('verification.send'));
        }

        $this->actingAs($user)->post(route('verification.send'))->assertStatus(429);
    }

    #[Test]
    public function 認証済みならホームを開ける(): void
    {
        $this->actingAs(User::factory()->create())->get(route('home'))->assertOk();
    }
}
