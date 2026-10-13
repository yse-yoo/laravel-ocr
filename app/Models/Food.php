<?php

namespace App\Models;

use Database\Factories\FoodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    /** @use HasFactory<FoodFactory> */
    use HasFactory;

    // 保存後に書き換えないので updated_at は持たない
    const UPDATED_AT = null;

    protected $fillable = ['image', 'ocr_text', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * フロントエンド（resources/js/types.ts の FoodRecord）に渡す形
     */
    public function toRecord(): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->created_at->toIso8601ZuluString(),
            'image' => $this->image,
            'ocrText' => $this->ocr_text,
            'data' => $this->data,
        ];
    }
}
