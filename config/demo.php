<?php

return [

    /*
    |--------------------------------------------------------------------------
    | デモ環境（ポートフォリオ公開用）
    |--------------------------------------------------------------------------
    | DEMO_MODE=true のときだけ有効。ログイン画面に「ロールを選んでログイン」ボタンを出し、
    | デモ用アカウントを壊す操作（パスワード変更・停止・紐付け解除など）と、メールが届かない操作
    | （新規登録・パスワードリセット）を止める。本番運用では設定しない（既定は false）。
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    // ボタンでログインできるアカウント（キー => [メールアドレス, ボタンの表示, 説明]）。
    // メールアドレスは OwnerSeeder・DevelopmentSeeder で作るユーザーに合わせる。
    'accounts' => [
        'owner' => [
            'email' => env('SEED_OWNER_EMAIL', 'owner@example.com'),
            'label' => 'オーナー',
            'description' => 'トレーナーの追加・停止、トレーナーの全機能',
        ],
        'trainer' => [
            'email' => 'trainer1@example.com',
            'label' => 'トレーナー',
            'description' => '利用者の記録の入力・閲覧、指導メモ、招待コード',
        ],
        'client' => [
            'email' => 'client1@example.com',
            'label' => '利用者（ジム所属）',
            'description' => '記録・グラフ・トレーナーとのコメント',
        ],
        'personal' => [
            'email' => 'personal@example.com',
            'label' => '利用者（個人）',
            'description' => 'ジムに所属せず自分の記録だけを扱う',
        ],
    ],

    // デモ環境で常に止めるルート（メールが届かないため完了できない操作）
    'blocked_routes' => [
        'register.store',
        'password.email',
        'password.update',
    ],

    // デモ用アカウントが対象のときに止めるルート（他の閲覧者がログインできなくなるのを防ぐ）
    //   ルート名 => 対象のアカウントを表すルートパラメーター（null はログイン中の本人）
    'protected_routes' => [
        'user-profile-information.update' => null,
        'user-password.update' => null,
        'gym-link.destroy' => null,
        'staff.trainers.suspension.store' => 'trainer',
        'staff.clients.membership.destroy' => 'client',
    ],

];
