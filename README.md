# きゅーよ！

給与計算実務能力検定2級の2026年11月試験に向けた学習Webアプリです。短いレッスン、間隔反復、デイリー目標、公式公開仕様に合わせた診断模試を一つの学習ループにまとめています。

## 主な機能

- 労働法・給与・税・社会保険・実務計算のスキルツリー
- 1回最大10問のサーバー発行レッスンとサーバー側採点
- 初回正解も忘却前に再出題するLeitner方式の復習キュー
- 2級範囲を横断する831問（通常学習711問＋初見模試用120問）
- 合格コア169問を優先する1日最大10問の新規学習目標
- 40問・120分・100点満点、問題重複なし・全5単元を含む模擬試験3セット
- 未見の問題で受けた初回120分の別模試2回を使う合格圏判定（各70点、平均80点、計算・単元別下限あり）
- XP、ストリーク、デイリークエスト、リーグ、バッジ
- 2026年度の料率・税額表をまとめた資料集
- Filamentによる問題・レッスン・資料集の管理画面
- PWA対応、Google OAuth対応（認証情報設定時のみ表示）

全831問を一つの正本で管理します。初見性を守るため模試採用120問は通常レッスンへ事前配信せず、その模試の採点後に復習・弱点レッスンへ解放します。各問の解答後には論点別の関連する公式資料を表示します。自動検証の範囲と一次資料は [docs/content-sources.md](docs/content-sources.md)、定期改訂の手順は [docs/content-review-policy.md](docs/content-review-policy.md) を参照してください。

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

本番起動時の `content:sync` は問題データのリリースハッシュが変わった時だけ正本を同期します。同じリリースのコンテナ再起動では、管理画面で更新したレビュー期限・メモを保持します。問題内容の版が上がった場合、その問題の旧版で得た習熟判定は無効となり即日復習へ戻ります。開始済み・採点済みの模試は開始時スナップショットを使うため、改訂後も表示と得点が変わりません。問題本文・正答の恒久変更は `database/seeders/data` の正本にも反映してください。

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
