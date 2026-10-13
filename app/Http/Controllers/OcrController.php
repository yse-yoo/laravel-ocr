<?php

namespace App\Http\Controllers;

use App\Services\LightOnOcr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OcrController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Ocr');
    }

    /**
     * multipart/form-data: file（画像 1 枚）, grounding=("1"|"0")
     * PDF はブラウザ側でページごとの画像にして 1 ページずつ送ってくる
     */
    public function store(Request $request, LightOnOcr $ocr): JsonResponse
    {
        set_time_limit(300);
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/*'],
        ]);
        $grounding = $request->input('grounding') === '1';

        try {
            $png = $ocr->toPng($request->file('file')->get());
            $markdown = $ocr->ocr($png, $grounding);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'image' => $ocr->preview($png),
            'markdown' => $markdown,
            'blocks' => $grounding ? LightOnOcr::parseGrounding($markdown) : null,
        ]);
    }
}
