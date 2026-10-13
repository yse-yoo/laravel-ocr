# LightOnOCR Web（Laravel + Inertia + React）

`../next-ocr`（Next.js 版）を Laravel 13 + Inertia v3 + React 19 に移植したもの。

- `/` — PDF / 画像を OCR（grounding 時はページ画像に bbox を重ねて表示）
- `/food` — 食品ラベルを読み取り、原材料・アレルゲン・栄養成分を JSON にして SQLite に保存

## 前提

- PHP 8.3+（gd, exif, pdo_sqlite）/ Composer / Node.js 20+
- PDF はブラウザ側（pdf.js）でページ画像にするので、サーバーに Poppler などは不要
- OpenAI 互換 API で LightOnOCR-3 を動かすサーバー（Ollama または vLLM）
  ```sh
  ollama pull hf.co/prithivMLmods/LightOnOCR-3-0.8B-GGUF:Q8_0
  ollama pull gemma4:e4b   # 食品情報の抽出用
  ```

## 起動

```sh
composer setup   # 初回のみ（composer install, .env, key, migrate, npm install, build）
composer dev     # http://localhost:8000
```

`composer dev`（= `php artisan dev`）で起動する PHP サーバーには `php.d/ocr.ini` が読み込まれる
（アップロード上限 50MB、実行時間 300 秒など）。設定箇所は `app/Providers/AppServiceProvider.php`。

| 環境変数 | 既定値 |
| --- | --- |
| `OCR_BASE_URL` | `http://localhost:8000/v1`（`.env` では Ollama の `http://localhost:11434/v1`） |
| `OCR_MODEL` | `lightonai/LightOnOCR-3-0.8B` |
| `OCR_API_KEY` | `dummy` |
| `FOOD_BASE_URL` / `FOOD_MODEL` | `http://localhost:11434/v1` / `gemma4:e4b` |

## 構成

| ファイル | 役割（Next.js 版の対応先） |
| --- | --- |
| `app/Services/LightOnOcr.php` | 前処理・OCR 呼び出し・grounding パース（`src/lib/ocr.ts`） |
| `app/Services/FoodExtractor.php` | OCR テキスト → 食品情報 JSON（`src/lib/food.ts`） |
| `app/Models/Food.php` | 保存した食品（`src/lib/db.ts`） |
| `app/Http/Controllers/OcrController.php` | `GET /` と `POST /ocr`（画像 1 枚を OCR して JSON で返す） |
| `app/Http/Controllers/FoodController.php` | `GET /food`・`POST /food`・`DELETE /food/{id}` |
| `resources/js/pages/Ocr.tsx` / `Food.tsx` | 画面（`ocr-app.tsx` / `food-app.tsx`） |
| `resources/js/pdf.ts` | PDF をページごとの PNG に描画（pdf.js）。Next.js 版の `pdf-to-img` の代わり |

- OCR 画面: PDF をブラウザでページ画像にし、`useHttp` で 1 ページずつ `POST /ocr` して結果を順に表示する
- 食品画面: PDF なら 1 ページ目だけを画像にして `useForm` で送信。一覧は props で受け取り、保存・削除後に Inertia が取り直す
- pdf.js の cmaps などは `vite.config.ts` が `public/pdfjs/` にコピーする

```sh
php artisan test --compact
```

## 本番（nginx + PHP-FPM）での注意

- `php.d/ocr.ini` と同じ値を PHP-FPM の php.ini に設定し、`request_terminate_timeout` も 300 秒以上にする
- nginx の `client_max_body_size` を上げる
- `npm run build` 済みの `public/build/` があれば、実行時に Node.js は不要
