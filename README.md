# TrainingMax

パーソナルトレーナーが利用者のトレーニング記録を入力・共有し、指導を効率的に行うための Web アプリです。

- トレーナー個人に依存しない指導：記録・推奨重量・指導メモをジム内で共有します。
- 利用者もログインして、自分の記録を入力・確認できます。トレーナーのいない個人利用にも対応しています。
- スマホでの利用を主に想定したレスポンシブ対応です。画面・メッセージは日本語のみです（kg・cm、タイムゾーンは Asia/Tokyo）。

## 主な機能

| 分類                 | 内容                                                                                                                           |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| トレーニング記録     | セッション単位で、種目・セット（重量・レップ数・目標／実績 RIR）を記録・編集・削除。重量の刻み・自重種目に対応                 |
| 推定 1RM・推奨重量   | 実績（重量・レップ数・RIR）から推定 1RM（Epley 式）を算出し、次のセット・別日の推奨重量を提示                                  |
| 種目の分類           | 種目を 4 分類に分け、分類ごとの推奨レップ数・RIR・休憩時間を提示。種目はデフォルト／ジム共通／個人の 3 層                      |
| 休憩タイマー         | 推奨の休憩時間でカウントダウン。画面を移動しても継続                                                                           |
| グラフ               | 推定 1RM・最大重量・セットボリューム・総ボリューム、体組成（体重・体脂肪率・筋肉量・周囲径）の推移（Chart.js）                 |
| ホーム               | 月カレンダーに、実施日・部位・体組成の記録・コメントの有無を表示                                                               |
| 指導メモ・コメント   | スタッフ専用の指導メモ（注意点・怪我・既往歴・目標）。セッションごとのコメントは、スタッフと利用者本人のメモを兼ね、返信も可能 |
| 用語ツールチップ     | RIR・推定 1RM などの用語を「？」で解説（解説は 1 か所で管理）                                                                  |
| アカウント・ジム管理 | オーナーによるトレーナーの追加・停止、利用者アカウントの発行（パスワード設定メール）、招待コードによるジムとの紐付け・解除     |
| 認証                 | 登録・メール認証・ログイン・パスワードリセット・プロフィール変更（Laravel Fortify ＋ 自作の Blade 画面）                       |

### ロール

| ロール             | できること                                                                             |
| ------------------ | -------------------------------------------------------------------------------------- |
| オーナー           | トレーナーの管理、およびトレーナーの権限すべて                                         |
| トレーナー         | ジム内の全利用者の記録の閲覧・入力・編集、指導メモ、招待コード・利用者アカウントの発行 |
| 利用者（ジム所属） | 自分の記録の入力・編集（トレーナーが入力した記録も編集可）、コメント・メモ             |
| 利用者（個人）     | ジムに所属せず、自分の記録のみを扱う。後から招待コードでジムに紐付けられる             |

## 画面イメージ

スマホ幅での表示です。ロールごとに使える画面が変わります。

### 共通（全ロール）

<table>
<tr><td align="center" valign="top" width="25%"><img src="docs/images/0_login.png" alt="ログイン"><br><sub>ログイン</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/12_profile1.png" alt="プロフィール（スタッフ）"><br><sub>プロフィール（スタッフ）</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_profile1.png" alt="プロフィール（利用者）"><br><sub>プロフィール（利用者）</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/123_profile2.png" alt="パスワード変更"><br><sub>パスワード変更</sub></td></tr>
</table>

### オーナー

<table>
<tr><td align="center" valign="top" width="25%"><img src="docs/images/1_menu.png" alt="メニュー"><br><sub>メニュー</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/1_staff_trainers.png" alt="トレーナー管理（追加・停止・再開）"><br><sub>トレーナー管理（追加・停止・再開）</sub></td></tr>
</table>

### トレーナー（オーナーも利用可）

<table>
<tr><td align="center" valign="top" width="25%"><img src="docs/images/2_menu.png" alt="メニュー"><br><sub>メニュー</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/2_staff_clients.png" alt="利用者一覧（検索・並び替え）"><br><sub>利用者一覧（検索・並び替え）</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/2_staff_client1.png" alt="利用者詳細"><br><sub>利用者詳細</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/2_staff_client2.png" alt="指導メモ（利用者には非表示）"><br><sub>指導メモ（利用者には非表示）</sub></td></tr>
<tr><td align="center" valign="top" width="25%"><img src="docs/images/2_staff_client3.png" alt="指導メモの追加・紐付け解除"><br><sub>指導メモの追加</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/2_staff_client_create.png" alt="利用者アカウントの発行"><br><sub>利用者アカウントの発行</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/2_staff_invitation.png" alt="招待コードの発行・取消"><br><sub>招待コードの発行・取消</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/2_staff_exercises.png" alt="ジム共通種目の管理"><br><sub>ジム共通種目の管理</sub></td></tr>
</table>

### 利用者（トレーナーの代理入力・確認時も同じ画面）

<table>
<tr><td align="center" valign="top" width="25%"><img src="docs/images/3_menu.png" alt="メニュー"><br><sub>メニュー</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_client_home.png" alt="ホーム（月カレンダー）"><br><sub>ホーム（月カレンダー）</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_client_sessions.png" alt="トレーニング記録（セット一覧・用語の「？」）"><br><sub>トレーニング記録</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_client_sessions-2.png" alt="コメント・メモと返信"><br><sub>コメント・メモと返信</sub></td></tr>
<tr><td align="center" valign="top" width="25%"><img src="docs/images/3_client_body.png" alt="体組成の記録"><br><sub>体組成の記録</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_client_graphs_training.png" alt="トレーニングのグラフ"><br><sub>トレーニングのグラフ</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_client_graphs_body.png" alt="体組成のグラフ"><br><sub>体組成のグラフ</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_client_exercises.png" alt="個人種目"><br><sub>個人種目</sub></td></tr>
<tr><td align="center" valign="top" width="25%"><img src="docs/images/3_client_gym-link.png" alt="ジム連携（招待コードの入力）"><br><sub>ジム連携（招待コードの入力）</sub></td><td align="center" valign="top" width="25%"><img src="docs/images/3_client_gym-link2.png" alt="ジム連携（所属中・解除）"><br><sub>ジム連携（所属中・解除）</sub></td></tr>
</table>

## 技術スタック

- **バックエンド**：PHP 8.5 / Laravel 13、Laravel Fortify
- **フロントエンド**：Blade、Tailwind CSS 4、Alpine.js、Chart.js、Vite（Livewire・Inertia・スターターキットは不使用）
- **データベース**：MySQL 8.4
- **開発環境**：Docker / Laravel Sail（Mailpit、phpMyAdmin 付き）
- **テスト・品質**：Pest 4（クラス形式）、Laravel Pint、Node 標準のテストランナー（JavaScript の純粋なロジック）

## 設計上の工夫

### 認可を 1 か所に集約

- 利用者の記録にアクセスできるかの判定を `User::canAccessRecordsOf()` に集約し、Policy から呼び出します（本人、同じジムの有効なスタッフのみ。個人利用者・他ジム・停止中のスタッフは不可）。
- `/clients/{client}/...` と `/staff/...` のコントローラーは、それぞれ共通の基底クラス（`Records\RecordsController`、`Staff\StaffController`）を継承します。基底クラスのミドルウェアがリクエストごとに認可するため、個別のコントローラーでの付け忘れが起きません。基底クラスを使わないルートは、テストで検出します。
- ルートバインディングで URL のスコープを検証します（他の利用者のセッション・セットは 404）。
- `role`・`gym_id`・`created_by` などは一括代入を許可せず、リクエストからは設定できません。

### 利用者のデータを守る

- 指導メモは利用者本人の画面・データ（JSON を含む）に含めません。本人の画面ではメモを読み込む問い合わせ自体をしません。
- ジムとの紐付けを解除すると、スタッフは元の利用者の記録・指導メモを見られなくなります。記録は利用者本人のものとして残ります。
- 利用者が入力した値は常にエスケープして表示します（`{!! !!}` を使わない）。
- 入力は FormRequest で検証し、メッセージは日本語です。DB 操作は Eloquent のみで、生 SQL は使いません。

### 認証・セッション

- 停止したトレーナーは、次のリクエストでログアウトされます。パスワード変更後は、他の端末のログインを解除します。
- ログイン・登録・パスワードリセット・招待コードの入力に回数制限を設け、パスワードリセットは登録の有無を知られない応答にします。
- セキュリティヘッダー（`X-Frame-Options`、`X-Content-Type-Options`、`Referrer-Policy`、本番のみ HSTS）を付与します。

### 操作ログ

- 専用チャンネル `audit`（日ごとのファイル、90 日保存）に、重要な操作を ID と件数だけで記録します。パスワード・招待コード・メモ・コメントなどの値は記録しません（ログ全体を調べるテストで確認）。

### コードの構成

| 層                     | 役割                                                                        |
| ---------------------- | --------------------------------------------------------------------------- |
| `app/Http/Controllers` | 薄く保つ                                                                    |
| `app/Http/Requests`    | 入力検証（FormRequest。日本語の `messages()`）                              |
| `app/Policies`         | 認可                                                                        |
| `app/Actions`          | 複数テーブルの更新・トランザクション・操作ログを伴う処理（1 処理 1 クラス） |
| `app/Queries`          | 複雑な読み取り・集計（カレンダー、グラフ、推奨重量など）                    |
| `app/Support/Training` | 推定 1RM などの純粋な計算（DB を使わない）                                  |
| `lang/ja`              | 日本語メッセージ、用語の解説（`glossary.php`）                              |

## データベース設計

主なテーブルの ER 図です（`created_by`・`updated_by` など、記録者を示す `users` への参照は図から省略しています）。

```mermaid
erDiagram
    gyms ||--o{ users : "所属"
    gyms ||--o{ invitation_codes : "発行"
    gyms ||--o{ exercises : "ジム共通種目"
    gyms ||--o{ coaching_notes : "指導メモ"
    users ||--o{ workout_sessions : "記録"
    users ||--o{ body_compositions : "記録"
    users ||--o{ exercises : "個人種目"
    users ||--o{ coaching_notes : "対象の利用者"
    users |o--o{ invitation_codes : "使用"
    body_parts ||--o{ exercises : "部位"
    exercise_categories ||--o{ exercises : "分類"
    workout_sessions ||--o{ session_exercises : "実施した種目"
    exercises ||--o{ session_exercises : ""
    session_exercises ||--o{ workout_sets : "セット"
    workout_sessions ||--o{ session_comments : "コメント"
    session_comments |o--o{ session_comments : "返信"

    gyms {
        bigint id PK
        string name
    }
    users {
        bigint id PK
        bigint gym_id FK "NULL=個人利用者・スタッフ以外"
        string role "owner / trainer / client"
        string email UK
        timestamp suspended_at "停止中"
        decimal height_cm
        date birth_date
    }
    invitation_codes {
        bigint id PK
        bigint gym_id FK
        char code UK "8文字"
        timestamp expires_at
        timestamp used_at
        timestamp revoked_at
    }
    body_parts {
        bigint id PK
        string name UK
    }
    exercise_categories {
        bigint id PK
        string name UK "4分類"
        tinyint rep_min_max "推奨レップ数"
        tinyint rir_min_max "推奨RIR"
        smallint rest_seconds "推奨休憩時間"
    }
    exercises {
        bigint id PK
        string scope "default / gym / personal"
        bigint gym_id FK
        bigint owner_user_id FK
        bigint body_part_id FK
        bigint exercise_category_id FK
        string name
        boolean is_hidden
    }
    workout_sessions {
        bigint id PK
        bigint user_id FK
        date performed_on "UK(user_id, performed_on)"
    }
    session_exercises {
        bigint id PK
        bigint workout_session_id FK
        bigint exercise_id FK
        smallint sort_order
    }
    workout_sets {
        bigint id PK
        bigint session_exercise_id FK
        tinyint set_number
        decimal weight
        tinyint reps
        tinyint target_rir
        tinyint actual_rir
        double estimated_1rm
        decimal set_volume
    }
    body_compositions {
        bigint id PK
        bigint user_id FK
        date measured_on "UK(user_id, measured_on)"
        decimal weight_kg "体重・体脂肪率・筋肉量・周囲径"
    }
    coaching_notes {
        bigint id PK
        bigint gym_id FK
        bigint client_user_id FK
        string category
        text body
    }
    session_comments {
        bigint id PK
        bigint workout_session_id FK
        bigint parent_id FK "返信先（1段階のみ）"
        text body
    }
```

- **ジムとの紐付け**は `users.gym_id` で表します。紐付けを解除しても、利用者の記録（`workout_sessions` など）は利用者のものとして残り、指導メモ（`coaching_notes`）はジム側に残ります。
- **種目の 3 層**（デフォルト／ジム共通／個人）は `exercises.scope` と、`gym_id`・`owner_user_id` で表します。
- 推定 1RM とセットボリュームは `workout_sets` に保存し、グラフと推奨重量の集計を軽くしています。
- 外部キーは原則 `RESTRICT`（親を消しても子が消えない）で、記録の誤削除を防ぎます。セッション配下の種目・セット・コメントだけは `CASCADE` です。
- 同じ利用者の同じ日付のセッション・体組成は 1 件までです（複合ユニーク制約）。
- 種目名の重複判定には、照合順序 `utf8mb4_0900_as_ci`（大文字小文字は区別せず、濁点・半濁点は区別）を使います。

## テスト

認可は「拒否されるケース」を中心にテストしています。

- PHP のテスト 789 件（インメモリ SQLite で実行。照合順序・行ロックなど MySQL 依存のテストは MySQL で実行）。カバレッジは約 98%。
- `/clients/{client}` と `/staff` 配下の**すべてのルート**を、権限のない人（同じジムの別の利用者、個人利用者、他ジムのスタッフなど）で呼び出して拒否を確認するテストがあり、ルートを追加すると自動で対象になります。
- 日本語のテスト名（例：`紐付け解除後はスタッフが元の利用者の記録を開くと403になる`）で、検証内容が読めるようにしています。

```bash
sail artisan test                                                                  # インメモリ SQLite
sail exec -e DB_CONNECTION=mysql -e DB_DATABASE=testing laravel.test php artisan test   # MySQL
SAIL_XDEBUG_MODE=coverage sail artisan test --coverage                             # カバレッジ
sail npm test                                                                      # JavaScript のロジック
sail bin pint --test                                                               # コードスタイル
```

## セットアップ

Docker が使える環境が必要です（以下、`sail` は `./vendor/bin/sail` です）。

```bash
git clone <このリポジトリ>
cd TrainingMax
cp .env.example .env

# 初回のみ：Composer の依存を Docker で入れる
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php85-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

- アプリ：http://localhost:8000（`APP_URL`）。`APP_PORT` を変えている場合はそのポート
- Mailpit（送信メールの確認）：http://localhost:8025
- メールの送信先を Mailpit にするには、`.env` の `MAIL_MAILER=smtp`・`MAIL_HOST=mailpit`・`MAIL_PORT=1025` を設定します。

### 開発用テストユーザー

`local` 環境の `migrate:fresh --seed` で作成されます。共通パスワードは `.env` の `SEED_DEV_USER_PASSWORD`、オーナーのパスワードは `SEED_OWNER_PASSWORD` です。**本番では開発用ユーザーは作成されません**（`local` 環境のみ）。

| メールアドレス                                 | 内容                                                               |
| ---------------------------------------------- | ------------------------------------------------------------------ |
| `owner@example.com`                            | オーナー                                                           |
| `trainer1@example.com`・`trainer2@example.com` | トレーナー                                                         |
| `trainer-suspended@example.com`                | 停止中のトレーナー                                                 |
| `client1@example.com`・`client2@example.com`   | ジム所属の利用者（サンプルの記録・体組成・指導メモ・コメント入り） |
| `client-pending@example.com`                   | パスワード未設定の利用者                                           |
| `personal@example.com`                         | 個人利用者                                                         |
| `unverified@example.com`                       | メール未認証の個人利用者                                           |

## ライセンス

MIT License
