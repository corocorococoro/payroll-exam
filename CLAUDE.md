# きゅーよ！ — 給与計算実務能力検定2級 合格伴走アプリ

Duolingo型ゲーミフィケーションで毎日コツコツ学ぶ検定対策Webアプリ。
目標: 2026年11月22日の給与計算実務能力検定2級に合格する。

設計の一次資料: `docs/deep-research-report.md`（模試48問・出題範囲・分野配点・学習タイムライン）

## スタック

- Laravel 13 + Inertia 3 + Vue 3 + TypeScript + Tailwind CSS 4（vue-starter-kit dev-main ベース）
- PostgreSQL 17 / Redis（session・cache・queue）
- 認証: Fortify（メール+パスワード）+ Socialite Google OAuth。local限定 `/auth/dev-login` あり
- Wayfinder（ルートのTS型生成）、Fortify、Pest、Pint、Larastan
- タイムゾーンは Asia/Tokyo 固定。ストリーク等の日付判定はすべてJST

## 開発環境（ホストにPHPなし。すべてDocker経由）

```bash
docker compose up -d          # app(8000) postgres(5433) redis mailpit(8025)
npm run dev                   # Vite はホストで実行（5173）
docker compose exec app php artisan migrate --seed
docker compose exec app composer test        # pint + phpstan + テスト
docker compose exec app php artisan wayfinder:generate --with-form
```

- composer 単発実行: `docker run --rm -v $PWD:/opt -w /opt laravelsail/php84-composer:latest composer ...`
- queue/scheduler はcomposeサービスとして常駐（queue: Redis、scheduler: JST 0時のストリーク判定等）

## 規約・注意

- 解答判定は必ずサーバー側。問題配信レスポンスに正解・解説を含めない（リーグのチート防止）
- 計算問題はシードJSONに計算パラメータを構造化して持ち、Pestで正答を再計算検証する
- 料率・税額は `fiscal_year`（2026年度=法令基準日2026-09-01）でバージョニング。ハードコード禁止
- 2級の試験仕様: 知識35問×2点 + 計算5問×6点 = 100点、70点合格、120分
- UIは日本語・モバイルファースト。かわいさ重視（パステル、丸ゴシック、マスコット「きゅーちゃん」）
- User モデルは PHP Attribute スタイル（`#[Fillable]`）— Laravel 13 の新規約に合わせる
