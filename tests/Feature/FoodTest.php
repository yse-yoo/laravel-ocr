<?php

namespace Tests\Feature;

use App\Models\Food;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'services.ocr.base_url' => 'http://ocr.test/v1',
            'services.food.base_url' => 'http://food.test/v1',
            'services.food.model' => 'gemma',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function fakeServers(string $ocrText, array $extracted): void
    {
        Http::fake([
            'ocr.test/*' => Http::response(['choices' => [['message' => ['content' => $ocrText]]]]),
            'food.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($extracted)]]]]),
        ]);
    }

    public function test_index_lists_saved_foods_newest_first(): void
    {
        $older = Food::factory()->create();
        $newer = Food::factory()->create();

        $this->get(route('food.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Food')
                ->has('foods', 2)
                ->where('foods.0.id', $newer->id)
                ->where('foods.1.id', $older->id)
                ->has('foods.0', fn (Assert $food) => $food
                    ->where('ocrText', '名称：ビスケット')
                    ->where('data.allergens', ['小麦'])
                    ->etc()));
    }

    public function test_store_saves_ocr_text_and_extracted_food_info(): void
    {
        $this->fakeServers('原材料名：小麦粉、砂糖／乳化剤（大豆由来）', [
            'productName' => 'ビスケット',
            'ingredientsText' => '原材料名：小麦粉、砂糖／乳化剤（大豆由来）',
            'allergens' => ['小麦', '大豆'],
            'nutrition' => ['per' => '1袋当たり', 'energyKcal' => 152, 'proteinG' => null, 'fatG' => null,
                'carbohydrateG' => null, 'sugarG' => null, 'fiberG' => null, 'saltG' => 0.2],
        ]);

        $this->from(route('food.index'))
            ->post(route('food.store'), ['file' => UploadedFile::fake()->image('label.jpg')])
            ->assertRedirect(route('food.index'))
            ->assertSessionHasNoErrors();

        $food = Food::sole();
        $this->assertSame('原材料名：小麦粉、砂糖／乳化剤（大豆由来）', $food->ocr_text);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $food->image);
        $this->assertSame('ビスケット', $food->data['productName']);
        $this->assertSame('小麦粉、砂糖／乳化剤（大豆由来）', $food->data['ingredientsText']); // 「原材料名：」は除く
        $this->assertSame(['小麦粉', '砂糖'], $food->data['ingredients']);
        $this->assertSame(['乳化剤（大豆由来）'], $food->data['additives']);
        $this->assertSame(['小麦', '大豆'], $food->data['allergens']);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://food.test/v1/chat/completions'
            && $request['model'] === 'gemma'
            && $request['reasoning_effort'] === 'none'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['messages'][1]['content'] === '原材料名：小麦粉、砂糖／乳化剤（大豆由来）');
    }

    public function test_store_reports_error_and_saves_nothing_when_ocr_server_fails(): void
    {
        Http::fake(['ocr.test/*' => Http::response('down', 500)]);

        $this->from(route('food.index'))
            ->post(route('food.store'), ['file' => UploadedFile::fake()->image('label.jpg')])
            ->assertRedirect(route('food.index'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseEmpty('food');
    }

    public function test_store_rejects_files_that_are_not_images(): void
    {
        Http::fake();

        $this->from(route('food.index'))
            ->post(route('food.store'), ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['file' => 'The file field must be a file of type: image/*.']);

        Http::assertNothingSent();
        $this->assertDatabaseEmpty('food');
    }

    public function test_destroy_deletes_the_food(): void
    {
        $food = Food::factory()->create();

        $this->from(route('food.index'))
            ->delete(route('food.destroy', $food))
            ->assertRedirect(route('food.index'));

        $this->assertModelMissing($food);
    }

    public function test_destroy_returns_not_found_for_unknown_food(): void
    {
        $this->delete(route('food.destroy', 999))->assertNotFound();
    }
}
