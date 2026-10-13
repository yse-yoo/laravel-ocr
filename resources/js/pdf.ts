// PDF をブラウザ側でページごとの PNG にする（サーバーに Poppler などを入れずに済む）
// cmaps などの補助ファイルは vite.config.ts が public/pdfjs/ にコピーしている
const ASSETS = '/pdfjs/';

export const isPdf = (file: File) => file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');

export async function* pdfPages(file: File): AsyncGenerator<File> {
    // pdf.js は大きいので PDF を選んだときだけ読み込む
    const [pdfjs, { default: workerSrc }] = await Promise.all([
        import('pdfjs-dist'),
        import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
    ]);
    pdfjs.GlobalWorkerOptions.workerSrc = workerSrc;

    const task = pdfjs.getDocument({
        data: await file.arrayBuffer(),
        cMapUrl: `${ASSETS}cmaps/`, // 日本語フォントを埋め込んでいない PDF 用
        standardFontDataUrl: `${ASSETS}standard_fonts/`,
        wasmUrl: `${ASSETS}wasm/`,
        iccUrl: `${ASSETS}iccs/`,
    });
    try {
        const doc = await task.promise;
        for (let n = 1; n <= doc.numPages; n++) {
            const page = await doc.getPage(n);
            const { width, height } = page.getViewport({ scale: 1 });
            // 推奨前処理: 400DPI 相当で描画 → 長辺 2048px に縮小。縮小後の大きさで直接描画する
            const viewport = page.getViewport({ scale: Math.min(400 / 72, 2048 / Math.max(width, height)) });
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            await page.render({ canvas, viewport }).promise;
            page.cleanup();

            const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png'));
            if (!blob) throw new Error(`page ${n} を画像にできませんでした`);
            yield new File([blob], `page-${n}.png`, { type: 'image/png' });
        }
    } finally {
        await task.destroy();
    }
}

// 画像はそのまま、PDF ならページごとの画像を返す
export async function* toImages(file: File): AsyncGenerator<File> {
    if (isPdf(file)) yield* pdfPages(file);
    else yield file;
}
