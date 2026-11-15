<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 認証まわりの設定（セキュリティ設計書5章）
    |--------------------------------------------------------------------------
    */

    // 「ログイン状態を保持する」の有効期間（分）。既定は30日
    'remember_duration' => (int) env('AUTH_REMEMBER_DURATION', 60 * 24 * 30),

    // パスワードの文字数（bcrypt が72バイトまでしか使わないため上限を設ける）
    'password_min' => (int) env('AUTH_PASSWORD_MIN', 8),
    'password_max' => (int) env('AUTH_PASSWORD_MAX', 72),

    // 回数制限（超えた回数／分）
    'rate_limits' => [
        'login' => (int) env('RATE_LIMIT_LOGIN', 5),
        'register' => (int) env('RATE_LIMIT_REGISTER', 6),
        'password_reset' => (int) env('RATE_LIMIT_PASSWORD_RESET', 6),
        'initial_password_mail' => (int) env('RATE_LIMIT_INITIAL_PASSWORD_MAIL', 1),
        // 招待コードの入力（1ユーザーにつき）
        'invitation_code_per_minute' => (int) env('RATE_LIMIT_INVITATION_CODE_PER_MINUTE', 5),
        'invitation_code_per_hour' => (int) env('RATE_LIMIT_INVITATION_CODE_PER_HOUR', 20),
    ],

    // 招待コードの有効期限（日）
    'invitation_code_expire_days' => (int) env('INVITATION_CODE_EXPIRE_DAYS', 7),

    // 休憩タイマー（秒）。入力できる範囲、「−／＋」の調整幅、推奨休憩時間がないときの既定値、終了時の点滅の長さ
    'timer' => [
        'min_seconds' => (int) env('TIMER_MIN_SECONDS', 10),
        'max_seconds' => (int) env('TIMER_MAX_SECONDS', 600),
        'adjust_seconds' => (int) env('TIMER_ADJUST_SECONDS', 15),
        'default_seconds' => (int) env('TIMER_DEFAULT_SECONDS', 90),
        'blink_seconds' => (int) env('TIMER_BLINK_SECONDS', 10),
    ],

    // 重量の刻み（kg）として選べる値。記録画面（トレーニング記録のイシュー）で、ボタンの増減と推奨重量の切り下げに使う
    'weight_steps' => [0.5, 1, 1.25, 2.5, 5],

    // Strict-Transport-Security の max-age（秒。本番のみ送る）
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | 初期データ（Seeder）の設定
    |--------------------------------------------------------------------------
    | オーナーのメールアドレス・パスワードは .env で指定する（未設定ならシーダーはエラーにする）
    */

    'seed' => [
        'gym_name' => env('SEED_GYM_NAME', 'TrainingMax ジム'),
        'owner_name' => env('SEED_OWNER_NAME', 'オーナー'),
        'owner_email' => env('SEED_OWNER_EMAIL'),
        'owner_password' => env('SEED_OWNER_PASSWORD'),
        // 開発用のテストユーザー（DevelopmentSeeder）の共通パスワード。local 環境でのみ使う
        'dev_user_password' => env('SEED_DEV_USER_PASSWORD'),
    ],

];
