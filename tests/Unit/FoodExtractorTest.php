<?php

namespace Tests\Unit;

use App\Services\FoodExtractor;
use PHPUnit\Framework\TestCase;

class FoodExtractorTest extends TestCase
{
    public function test_split_ingredients_separates_additives_after_slash_and_keeps_parentheses(): void
    {
        $this->assertSame([
            'ingredients' => ['小麦粉（国内製造）', '砂糖', '植物油脂'],
            'additives' => ['乳化剤（大豆由来）', '香料', '着色料（カラメル、カロテン）'],
        ], FoodExtractor::splitIngredients('小麦粉（国内製造）、砂糖、植物油脂／乳化剤（大豆由来）、香料、着色料（カラメル、カロテン）'));
    }

    public function test_split_ingredients_treats_everything_as_ingredients_without_slash(): void
    {
        $this->assertSame(
            ['ingredients' => ['米', '塩'], 'additives' => []],
            FoodExtractor::splitIngredients('米、塩'),
        );
    }

    public function test_split_ingredients_returns_empty_lists_for_null(): void
    {
        $this->assertSame(['ingredients' => [], 'additives' => []], FoodExtractor::splitIngredients(null));
    }
}
