#!/usr/bin/env sh
# ------------------------------------------------------------
# コンテナ起動時の処理（Render の無料プランは pre-deploy コマンドが使えないため、ここで行う）
#   1. SQLite のファイルを用意（DB_CONNECTION=sqlite のとき）
#   2. マイグレーション。デモ環境（DEMO_MODE=true）では毎回作り直し＝起動のたびにデモデータを初期化
#   3. 設定・ルート・ビューをキャッシュ（Render の環境変数を読み込んだ後に行う必要があるため、ビルド時ではなくここで）
#   4. FrankenPHP で public/ を配信
# ------------------------------------------------------------
set -e

cd /app

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/app/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
fi

if [ "${DEMO_MODE:-false}" = "true" ]; then
    echo "[start] デモ環境：データベースを初期化してデモデータを投入します"
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force
fi

php artisan optimize

echo "[start] :${PORT} で起動します"
exec frankenphp php-server --root public/ --listen ":${PORT}"
