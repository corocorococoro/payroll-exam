# きゅーよ！

給与計算実務能力検定2級の2026年11月試験に向けた学習Webアプリです。毎回7問の短いレッスン、間隔反復、デイリー目標、本番形式の模擬試験を一つの学習ループにまとめています。

## 主な機能

- 労働法・給与・税・社会保険・実務計算のスキルツリー
- 1回7問のサーバー発行レッスンとサーバー側採点
- Leitner方式の復習キュー
- 40問・120分・100点満点の模擬試験3セット
- XP、ストリーク、デイリークエスト、リーグ、バッジ
- 2026年度の料率・税額表をまとめた資料集
- Filamentによる問題・レッスン・資料集の管理画面
- PWA対応、Google OAuth対応（認証情報設定時のみ表示）

教材はオリジナル問題です。自動検証の範囲と一次資料は [docs/content-sources.md](docs/content-sources.md) を参照してください。本アプリは合格を保証するものではなく、受験時は当年度の公式テキストと主催者の案内を優先してください。

## ローカル起動

Docker Desktopが必要です。

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

- アプリ: http://localhost:8000
- Mailpit: http://localhost:8025
- PostgreSQL: `localhost:5433`

ローカル環境ではログイン画面の「開発用ログイン」から動作確認できます。

## 品質チェック

```bash
docker compose exec -T app composer test
npm run lint:check
npm run format:check
npm run types:check
npm run build
```

PHP側の品質ゲートにはPint、PHPStan、Pestが含まれます。計算問題は構造化した `calc_params` から正答を再計算し、シード値と一致することをテストしています。

## Railwayへのデプロイ

`railway.toml` とマルチステージDockerfileを同梱しています。アプリサービスにPostgreSQLを接続し、次の環境変数を設定してください。

公開環境: https://payroll-exam-production.up.railway.app

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://your-domain.example
DB_CONNECTION=pgsql
DATABASE_URL=${{Postgres.DATABASE_URL}}
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

Googleログインには `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`、`GOOGLE_REDIRECT_URI` を設定します。未設定の場合はログイン画面に表示されません。

本番メール配送はバックログです。配送プロバイダーと送信ドメイン認証を整備するまでは `MAIL_MAILER=log` を維持し、外部へメールを送信しません。詳細は [plan.md](plan.md#バックログ) を参照してください。

## 技術構成

- Laravel 13 / PHP 8.4 / FrankenPHP
- Inertia 3 / Vue 3 / TypeScript / Tailwind CSS 4
- PostgreSQL / Redis
- Filament 5
- Pest / PHPStan / Pint / ESLint / Prettier / vue-tsc
