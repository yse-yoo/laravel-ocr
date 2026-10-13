<?php

namespace Database\Factories;

use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Food>
 */
class FoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image' => 'data:image/jpeg;base64,/9j/',
            'ocr_text' => '名称：ビスケット',
            'data' => [
                'productName' => fake()->word(),
                'ingredientsText' => '小麦粉、砂糖／香料',
                'ingredients' => ['小麦粉', '砂糖'],
                'additives' => ['香料'],
                'allergens' => ['小麦'],
                'nutrition' => [
                    'per' => '1袋（30g）当たり',
                    'energyKcal' => 152,
                    'proteinG' => 2.1,
                    'fatG' => 6.8,
                    'carbohydrateG' => 20.5,
                    'sugarG' => null,
                    'fiberG' => null,
                    'saltG' => 0.2,
                ],
            ],
        ];
    }
}
