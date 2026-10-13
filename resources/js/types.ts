// サーバー（app/Http/Controllers, app/Services）が返すデータの型

export type Block = {
    label: string; // text / title / table / chart / image ...
    bbox: [number, number, number, number]; // [x1, y1, x2, y2] 0〜1000 正規化
    text: string;
};

// POST /ocr が 1 ページ（画像 1 枚）ごとに返すもの
export type OcrResult = {
    image: string; // プレビュー用 JPEG の data URL
    markdown: string;
    blocks: Block[] | null; // grounding のときだけ
};

export type Nutrition = {
    per: string | null; // 例: "1袋（30g）当たり"
    energyKcal: number | null;
    proteinG: number | null;
    fatG: number | null;
    carbohydrateG: number | null;
    sugarG: number | null;
    fiberG: number | null;
    saltG: number | null;
};

export type FoodInfo = {
    productName: string | null;
    ingredientsText: string | null; // 原材料名の表示そのまま
    ingredients: string[]; // 原材料（「／」より前）
    additives: string[]; // 添加物（「／」より後）
    allergens: string[];
    nutrition: Nutrition;
};

export type FoodRecord = {
    id: number;
    createdAt: string;
    image: string; // プレビュー用 JPEG の data URL
    ocrText: string;
    data: FoodInfo;
};
