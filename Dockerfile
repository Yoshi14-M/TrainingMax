# ------------------------------------------------------------
# 公開（Render など）用のイメージ。開発は従来どおり Laravel Sail（compose.yaml）を使う。
#   PHP 実行・Web サーバー：FrankenPHP（Caddy 内蔵。Nginx・PHP-FPM の設定が不要）
#   ビルド：docker build -t trainingmax .
#   ローカル確認：docker run --rm -p 10000:10000 --env-file .env.render.local trainingmax
# ------------------------------------------------------------

# 1. PHP の依存（--no-dev：Pest・Sail などは入れない）。最終イメージと次のアセットのビルドで使う
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs

# 2. フロントエンド（Tailwind CSS・Alpine.js・Chart.js）を Vite でビルド
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
# app.css の @source が参照する、Laravel のページネーションのビュー（クラス名を Tailwind に読ませる）
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# 3. アプリ本体
FROM dunglas/frankenphp:1-php8.5-bookworm

# pdo_sqlite は標準で入っている。MySQL・PostgreSQL に切り替えても動くよう両方入れておく
RUN install-php-extensions intl zip opcache pcntl pdo_mysql pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 公式イメージの frankenphp には「1024 番未満のポートを使う権限（cap_net_bind_service）」が付いている。
# Render のようにこの権限を許可しない実行環境では、起動時に「Operation not permitted」で落ちる。
# 本アプリは 10000 番などの高いポートで待ち受けるため、権限を外す
RUN setcap -r /usr/local/bin/frankenphp

# 本番向けの PHP 設定（エラーを画面に出さない・OPcache 有効）
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/render/php.ini "$PHP_INI_DIR/conf.d/zz-trainingmax.ini"

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs database \
    && chmod +x docker/render/start.sh

# Render は PORT（既定 10000）で待ち受けることを期待する
ENV PORT=10000
EXPOSE 10000

CMD ["docker/render/start.sh"]
