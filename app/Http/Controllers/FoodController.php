<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Services\FoodExtractor;
use App\Services\LightOnOcr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FoodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Food', [
            'foods' => Food::latest('id')->get()->map->toRecord(),
        ]);
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
