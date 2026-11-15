<?php

namespace Database\Seeders;

use App\Actions\Gym\IssueInvitationCode;
use App\Enums\ExerciseScope;
use App\Enums\UserRole;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Gym;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    private const INVITATION_MEMO = '開発用の招待コード';

    /**
     * 動作確認用のテストユーザー（各ロール・各状態）と招待コード。local 環境でのみ実行する。
     * 共通のパスワードは .env の SEED_DEV_USER_PASSWORD から読む。何度実行しても重複しない（メールアドレスで判定）。
     */
    public function run(): void
    {
        $password = config('trainingmax.seed.dev_user_password');

        if (blank($password)) {
            throw new RuntimeException('SEED_DEV_USER_PASSWORD を .env に設定してください。');
        }

        $gym = Gym::query()->firstOrFail();

        // スタッフ
        $trainer = $this->user('trainer1@example.com', '田中トレーナー', UserRole::Trainer, $gym, $password);
        $this->user('trainer2@example.com', '佐藤トレーナー', UserRole::Trainer, $gym, $password);
        $this->user('trainer-suspended@example.com', '停止中トレーナー', UserRole::Trainer, $gym, $password, ['suspended_at' => now()]);

        // ジム所属の利用者
        $client1 = $this->user('client1@example.com', '山田太郎', UserRole::Client, $gym, $password);
        $this->user('client2@example.com', '鈴木花子', UserRole::Client, $gym, $password);
        $this->user('client-pending@example.com', 'パスワード未設定の利用者', UserRole::Client, $gym, null);

        // 個人利用者
        $this->user('personal@example.com', '個人利用者', UserRole::Client, null, $password);
        $this->user('unverified@example.com', 'メール未認証の利用者', UserRole::Client, null, $password, ['email_verified_at' => null]);

        $this->exercises($gym, $client1);
        $this->invitationCode($trainer);
    }

    /**
     * @param  array<string, mixed>  $extra  追加で設定する項目（suspended_at など）
     */
    private function user(string $email, string $name, UserRole $role, ?Gym $gym, ?string $password, array $extra = []): User
    {
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            return $existing;
        }

        $user = new User;
        $user->fill(['name' => $name, 'email' => $email]);
        $user->forceFill([
            'role' => $role,
            'gym_id' => $gym?->id,
            'password' => $password,
            'email_verified_at' => now(),
            ...$extra,
        ])->save();

        return $user;
    }

    /**
     * 個人利用者の紐付けを試すための、未使用の招待コード（作成したときだけコードを表示する）。
     */
    private function invitationCode(User $issuer): void
    {
        $exists = InvitationCode::query()->where('memo', self::INVITATION_MEMO)->usable()->exists();

        if ($exists) {
            return;
        }

        $invitation = app(IssueInvitationCode::class)->handle($issuer, self::INVITATION_MEMO);

        $this->command?->info("開発用の招待コード: {$invitation->code}（有効期限 {$invitation->expires_at->isoFormat('YYYY/MM/DD')}）");
    }

    /**
     * ジム共通種目と、利用者の個人種目（種目画面の確認用）。
     */
    private function exercises(Gym $gym, User $client): void
    {
        $this->exercise('ケーブルクロス', '胸', '標準アイソレート', ['scope' => ExerciseScope::Gym, 'gym_id' => $gym->id]);
        $this->exercise('腹筋ローラー', '腹', '高レップアイソレート', ['scope' => ExerciseScope::Personal, 'owner_user_id' => $client->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes  scope・gym_id・owner_user_id
     */
    private function exercise(string $name, string $part, string $category, array $attributes): void
    {
        $exists = Exercise::query()->where('scope', $attributes['scope'])->where('name', $name)->exists();

        if ($exists) {
            return;
        }

        (new Exercise)->forceFill([
            ...$attributes,
            'body_part_id' => BodyPart::where('name', $part)->value('id'),
            'exercise_category_id' => ExerciseCategory::where('name', $category)->value('id'),
            'name' => $name,
        ])->save();
    }
}
