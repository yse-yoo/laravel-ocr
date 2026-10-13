<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OcrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.ocr.base_url' => 'http://ocr.test/v1', 'services.ocr.model' => 'lighton']);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function fakeOcrServer(array $message): void
    {
        Http::fake(['ocr.test/*' => Http::response(['choices' => [['message' => $message]]])]);
    }

    public function test_index_renders_ocr_page(): void
    {
        $this->get(route('ocr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ocr'));
    }

    public function test_store_returns_markdown_blocks_and_preview_with_grounding(): void
    {
        $this->fakeOcrServer(['content' => '![title](80,70,920,140) 名称：ビスケット']);

        $this->postJson(route('ocr.store'), [
            'file' => UploadedFile::fake()->image('label.jpg', 3000, 1000),
            'grounding' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('markdown', '![title](80,70,920,140) 名称：ビスケット')
            ->assertJsonPath('blocks.0', ['label' => 'title', 'bbox' => [80, 70, 920, 140], 'text' => '名称：ビスケット'])
            ->assertJsonPath('image', fn (string $image) => str_starts_with($image, 'data:image/jpeg;base64,'));

        Http::assertSent(function (Request $request) {
            [$image, $prompt] = $request['messages'][0]['content'];
            $png = base64_decode(substr($image['image_url']['url'], strlen('data:image/png;base64,')));

            return $request->url() === 'http://ocr.test/v1/chat/completions'
                && $request['model'] === 'lighton'
                && $prompt === ['type' => 'text', 'text' => 'grounding']
                && getimagesizefromstring($png)[0] === 2048; // 長辺 2048px に縮小して送る
        });
    }

    public function test_store_without_grounding_returns_no_blocks(): void
    {
        $this->fakeOcrServer(['content' => '# 見出し']);

        $this->postJson(route('ocr.store'), ['file' => UploadedFile::fake()->image('label.png'), 'grounding' => '0'])
            ->assertOk()
            ->assertJsonPath('blocks', null);

        Http::assertSent(fn (Request $request) => count($request['messages'][0]['content']) === 1);
    }

    public function test_store_reads_text_from_reasoning_when_ollama_returns_empty_content(): void
    {
        $this->fakeOcrServer(['content' => '', 'reasoning' => '本文']);

        $this->postJson(route('ocr.store'), ['file' => UploadedFile::fake()->image('label.png')])
            ->assertOk()
            ->assertJsonPath('markdown', '本文');
    }

    public function test_store_rejects_files_that_are_not_images(): void
    {
        Http::fake();

        $this->postJson(route('ocr.store'), ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file field must be a file of type: image/*.']);

        Http::assertNothingSent();
    }

    public function test_store_returns_bad_gateway_with_message_when_ocr_server_fails(): void
    {
        Http::fake(['ocr.test/*' => Http::response(['error' => 'model not found'], 404)]);

        $this->postJson(route('ocr.store'), ['file' => UploadedFile::fake()->image('label.png')])
            ->assertStatus(502)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'model not found'));
    }
}
