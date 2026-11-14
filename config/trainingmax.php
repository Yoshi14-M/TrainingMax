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
    ],

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
    ],

];
