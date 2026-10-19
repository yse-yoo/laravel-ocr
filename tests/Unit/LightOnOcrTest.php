<?php

namespace Tests\Unit;

use App\Services\LightOnOcr;
use PHPUnit\Framework\TestCase;

class LightOnOcrTest extends TestCase
{
    public function test_parse_grounding_splits_blocks_when_text_follows_on_next_line(): void
    {
        $blocks = LightOnOcr::parseGrounding("![title](80,70,920,140)\n# 見出し\n![text](10,200,990,400)\n本文1行目\n本文2行目");

        $this->assertSame([
            ['label' => 'title', 'bbox' => [80, 70, 920, 140], 'text' => '# 見出し'],
            ['label' => 'text', 'bbox' => [10, 200, 990, 400], 'text' => "本文1行目\n本文2行目"],
        ], $blocks);
    }

    public function test_parse_grounding_handles_ollama_output_with_text_on_same_line(): void
    {
        $blocks = LightOnOcr::parseGrounding("![title](37,60,294,120) 名称：ビスケット\n\n![table+](1,2,3,4) | a | b |");

        $this->assertSame([
            ['label' => 'title', 'bbox' => [37, 60, 294, 120], 'text' => '名称：ビスケット'],
            ['label' => 'table+', 'bbox' => [1, 2, 3, 4], 'text' => '| a | b |'],
        ], $blocks);
    }

    public function test_parse_grounding_returns_no_blocks_for_plain_markdown(): void
    {
        $this->assertSame([], LightOnOcr::parseGrounding("# 見出し\n本文"));
    }

    public function test_has_text_is_false_when_only_image_markers_are_returned(): void
    {
        $this->assertFalse(LightOnOcr::hasText('![image](image_1.png)'));
        $this->assertFalse(LightOnOcr::hasText("![image](image_1.png)\n\n![image](image_2.png)\n"));
        $this->assertFalse(LightOnOcr::hasText(''));
    }

    public function test_has_text_is_true_when_text_is_returned(): void
    {
        $this->assertTrue(LightOnOcr::hasText("![image](image_1.png)\n原材料名：小麦粉"));
        $this->assertTrue(LightOnOcr::hasText('名称：ビスケット'));
    }
}
