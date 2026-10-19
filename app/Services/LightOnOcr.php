<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;

/**
 * LightOnOCR-3 を呼び出すサーバー側ロジック（next-ocr の src/lib/ocr.ts を移植）
 * PDF はブラウザ側（resources/js/pdf.ts）でページごとの画像にしてから送られてくる
 */
class LightOnOcr
{
    private ImageManager $images;

    public function __construct()
    {
        // デコード時に EXIF Orientation で自動回転される（スマホ写真対策）
        $this->images = new ImageManager(Driver::class);
    }

    /**
     * 推奨前処理: 長辺 2048px に縮小（比率維持・拡大はしない）して PNG にする
     */
    public function toPng(string $binary): string
    {
        return (string) $this->images->decodeBinary($binary)
            ->scaleDown(2048, 2048)
            ->encode(new PngEncoder);
    }

    public function ocr(string $png, bool $grounding): string
    {
        $content = [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode($png)]],
        ];
        if ($grounding) {
            $content[] = ['type' => 'text', 'text' => 'grounding'];
        }

        $message = Http::baseUrl(config('services.ocr.base_url'))
            ->withToken(config('services.ocr.api_key'))
            ->timeout(300)
            ->post('chat/completions', [
                'model' => config('services.ocr.model'),
                'messages' => [['role' => 'user', 'content' => $content]],
                'max_tokens' => 4096,
                'temperature' => 0.2,
                'top_p' => 0.9,
            ])
            ->throw()
            ->json('choices.0.message');

        // Ollama は qwen3.5 系テンプレートを思考モデル扱いし、本文を reasoning に入れて返す
        return ($message['content'] ?? '') ?: ($message['reasoning'] ?? '');
    }

    /**
     * grounding 出力をブロック配列に変換
     * 例: "![title](80,70,920,140)\n# 見出し" → ['label', 'bbox', 'text']
     * Ollama(GGUF) 版はマーカーと本文が同じ行に来る: "![title](80,70,920,140) # 見出し"
     *
     * @return list<array{label: string, bbox: array{int, int, int, int}, text: string}>
     */
    public static function parseGrounding(string $md): array
    {
        preg_match_all('/^!\[([a-z_]+\+?)\]\((\d+),(\d+),(\d+),(\d+)\)[ \t]*/m', $md, $marks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $blocks = [];
        foreach ($marks as $i => $m) {
            $start = $m[0][1] + strlen($m[0][0]);
            $end = $marks[$i + 1][0][1] ?? strlen($md);
            $blocks[] = [
                'label' => $m[1][0],
                'bbox' => array_map(fn ($g) => (int) $g[0], array_slice($m, 2, 4)),
                'text' => trim(substr($md, $start, $end - $start)),
            ];
        }

        return $blocks;
    }

    /**
     * 文字が読み取れたか。写真全体を図とみなすと "![image](image_1.png)" だけが返ってくる
     */
    public static function hasText(string $md): bool
    {
        return trim(preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $md)) !== '';
    }

    /**
     * プレビュー表示用の軽量 JPEG（data URL）
     */
    public function preview(string $png): string
    {
        return (string) $this->images->decodeBinary($png)
            ->scaleDown(1200, 1200)
            ->encode(new JpegEncoder(quality: 80))
            ->toDataUri();
    }
}
