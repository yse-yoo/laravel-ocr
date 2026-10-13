<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * OCR テキスト → 食品情報（原材料・アレルゲン・栄養成分）の変換（next-ocr の src/lib/food.ts を移植）
 */
class FoodExtractor
{
    private const PROMPT = <<<'TXT'
        食品ラベルの OCR テキストから情報を抽出して JSON で返す。
        - 書かれていない値は null または空配列。推測で値を作らない。
        - ingredientsText は「原材料名：」に続く文字列を一字一句そのまま写す（括弧や「／」も残す）。
        - allergens は「アレルギー物質」等の表示と、原材料中の「（〇〇由来）」「（一部に〇〇を含む）」から拾う。
        - nutrition.per は「1袋（30g）当たり」のように表示どおりに写す。
        - 栄養成分は数値のみ（単位は付けない）。kJ のみの場合は energyKcal を null にする。
        TXT;

    // LLM に出させるのはここまで。ingredients / additives はコードで分割する
    private static function schema(): array
    {
        $num = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['productName', 'ingredientsText', 'allergens', 'nutrition'],
            'properties' => [
                'productName' => ['type' => ['string', 'null']],
                'ingredientsText' => ['type' => ['string', 'null']],
                'allergens' => ['type' => 'array', 'items' => ['type' => 'string']],
                'nutrition' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['per', 'energyKcal', 'proteinG', 'fatG', 'carbohydrateG', 'sugarG', 'fiberG', 'saltG'],
                    'properties' => [
                        'per' => ['type' => ['string', 'null']],
                        'energyKcal' => $num,
                        'proteinG' => $num,
                        'fatG' => $num,
                        'carbohydrateG' => $num,
                        'sugarG' => $num,
                        'fiberG' => $num,
                        'saltG' => $num,
                    ],
                ],
            ],
        ];
    }

    public function extract(string $ocrText): array
    {
        $content = Http::baseUrl(config('services.food.base_url'))
            ->withToken(config('services.food.api_key'))
            ->timeout(300)
            ->post('chat/completions', [
                'model' => config('services.food.model'),
                'temperature' => 0,
                // gemma4 系は思考モードだと 1 件 60 秒以上かかるため、思考なしで呼ぶ
                'reasoning_effort' => 'none',
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'food', 'schema' => self::schema(), 'strict' => true],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => self::PROMPT],
                    ['role' => 'user', 'content' => $ocrText],
                ],
            ])
            ->throw()
            ->json('choices.0.message.content');

        $info = json_decode($content ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        // モデルが「原材料名：」まで含めて返した場合に備えて除く
        $text = isset($info['ingredientsText'])
            ? preg_replace('/^原材料名?\s*[:：]\s*/u', '', $info['ingredientsText'])
            : null;

        return [
            'productName' => $info['productName'] ?? null,
            'ingredientsText' => $text,
            ...self::splitIngredients($text),
            'allergens' => $info['allergens'] ?? [],
            'nutrition' => $info['nutrition'] ?? [],
        ];
    }

    /**
     * 「原材料／添加物」の表示を分ける。「／」が無ければ全て原材料として扱う
     *
     * @return array{ingredients: list<string>, additives: list<string>}
     */
    public static function splitIngredients(?string $text): array
    {
        if (! $text) {
            return ['ingredients' => [], 'additives' => []];
        }
        $parts = self::splitTopLevel($text, '／/');

        return [
            'ingredients' => self::splitTopLevel($parts[0] ?? '', '、,，'),
            'additives' => self::splitTopLevel(implode('、', array_slice($parts, 1)), '、,，'),
        ];
    }

    /**
     * 括弧の外にある区切り文字だけで分割する（「乳化剤（大豆由来）」などを壊さない）
     *
     * @return list<string>
     */
    private static function splitTopLevel(string $text, string $seps): array
    {
        $out = [];
        $depth = 0;
        $cur = '';
        foreach (mb_str_split($text) as $ch) {
            if (str_contains('（(「【', $ch)) {
                $depth++;
            } elseif (str_contains('）)」】', $ch)) {
                $depth = max(0, $depth - 1);
            }
            if ($depth === 0 && str_contains($seps, $ch)) {
                $out[] = $cur;
                $cur = '';
            } else {
                $cur .= $ch;
            }
        }
        $out[] = $cur;

        return array_values(array_filter(array_map('trim', $out), fn ($s) => $s !== ''));
    }
}
