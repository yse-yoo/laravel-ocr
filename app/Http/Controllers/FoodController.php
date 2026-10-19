<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Services\FoodExtractor;
use App\Services\LightOnOcr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FoodController extends Controller
{
    /**
     * foods は画像を含んで重いので、PC の画面は foodsVersion だけをポーリングし、変わったときに foods を取り直す
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Food', [
            'foods' => fn () => Food::latest('id')->get()->map->toRecord(),
            'foodsVersion' => fn () => Food::count().':'.Food::max('id'),
            'phoneUrl' => fn () => $this->phoneUrl($request),
        ]);
    }

    /**
     * スマホで開く URL（QR コード用）。PC で localhost を開いていても、LAN の IP アドレスに置き換える
     */
    private function phoneUrl(Request $request): string
    {
        $url = route('food.index');
        if (! in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true)) {
            return $url;
        }

        // UDP は connect しても送信しないので、既定ルートで使う自分の IP だけが分かる
        // （VirtualBox や WSL の仮想アダプタのアドレスを拾わない）
        $socket = @stream_socket_client('udp://8.8.8.8:53', $errorCode, $errorMessage, 1);
        if ($socket === false) {
            return $url;
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        return $address ? (string) Uri::of($url)->withHost(Str::beforeLast($address, ':')) : $url;
    }

    /**
     * multipart/form-data: file（食品ラベルの画像。PDF はブラウザ側で 1 ページ目を画像にして送ってくる）
     * OCR → JSON 抽出 → DB 保存
     */
    public function store(Request $request, LightOnOcr $ocr, FoodExtractor $extractor): RedirectResponse
    {
        set_time_limit(300);
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/*'],
        ]);

        try {
            $png = $ocr->toPng($request->file('file')->get());
            $ocrText = $ocr->ocr($png, false);
            // 文字が無いまま LLM に渡すと、それらしい食品情報を作ってしまう
            if (! LightOnOcr::hasText($ocrText)) {
                return back()->withErrors(['file' => '画像から文字を読み取れませんでした。ラベルが画面いっぱいに写るように、ピントを合わせて撮り直してください。']);
            }
            Food::create([
                'image' => $ocr->preview($png),
                'ocr_text' => $ocrText,
                'data' => $extractor->extract($ocrText),
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(Food $food): RedirectResponse
    {
        $food->delete();

        return back();
    }
}
