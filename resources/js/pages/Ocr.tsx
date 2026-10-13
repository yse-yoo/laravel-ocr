import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import Layout, { CameraInput, fileInputClass } from '@/layout';
import { toImages } from '@/pdf';
import type { OcrResult } from '@/types';

type Page = OcrResult & { page: number };

const COLORS: Record<string, string> = {
    title: '#e11d48',
    text: '#2563eb',
    table: '#16a34a',
    chart: '#d97706',
    image: '#9333ea',
};
const colorOf = (label: string) => COLORS[label.replace(/\+$/, '')] ?? '#64748b';

// useHttp は 422 以外の失敗で response 付きのエラーを投げる（OCR サーバーの失敗は 502 で { message }）
function errorMessage(e: unknown): string {
    const data = (e as { response?: { data?: unknown } } | null)?.response?.data;
    if (typeof data === 'string') {
        try {
            const message = JSON.parse(data).message;
            if (message) return message;
        } catch {
            // JSON でなければ下の汎用メッセージにする
        }
    }
    return e instanceof Error ? e.message : String(e);
}

export default function Ocr() {
    const [file, setFile] = useState<File | null>(null);
    const [grounding, setGrounding] = useState(false);
    const [pages, setPages] = useState<Page[]>([]);
    const [running, setRunning] = useState(false);
    const [error, setError] = useState<string | null>(null);
    // XSRF トークンの付与と 422（バリデーションエラー）の受け取りは useHttp が行う
    const http = useHttp<{ file: File | null; grounding: string }, OcrResult>({ file: null, grounding: '0' });

    // PDF はブラウザでページごとの画像にし、1 ページずつ送って結果を順に表示する
    async function run(e: React.FormEvent) {
        e.preventDefault();
        if (!file) return;
        setPages([]);
        setError(null);
        http.clearErrors();
        setRunning(true);
        let page = 0;
        try {
            for await (const image of toImages(file)) {
                page++;
                http.transform(() => ({ file: image, grounding: grounding ? '1' : '0' }));
                const result = await http.post('/ocr');
                if (!result) return; // 422: http.errors に入る
                setPages((p) => [...p, { ...result, page }]);
            }
        } catch (e) {
            setError(`page ${page}: ${errorMessage(e)}`);
        } finally {
            setRunning(false);
        }
    }

    return (
        <Layout
            title="LightOnOCR"
            description="PDF または画像をアップロードして OCR します。"
            link={{ href: '/food', label: '食品成分を保存する' }}
        >
            <div className="flex flex-col gap-6">
                <form
                    onSubmit={run}
                    className="flex flex-wrap items-center gap-4 rounded-lg border border-neutral-300 p-4 dark:border-neutral-700"
                >
                    <input
                        type="file"
                        accept="application/pdf,image/*"
                        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        className={fileInputClass}
                    />
                    <CameraInput onChange={setFile} />
                    {file && <span className="max-w-48 truncate text-sm text-neutral-500">{file.name}</span>}
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={grounding} onChange={(e) => setGrounding(e.target.checked)} />
                        grounding（レイアウト＋座標）
                    </label>
                    <button
                        type="submit"
                        disabled={!file || running}
                        className="ml-auto rounded bg-foreground px-4 py-2 text-sm font-medium text-background disabled:opacity-40"
                    >
                        {running ? `処理中… (${pages.length} ページ完了)` : 'OCR 実行'}
                    </button>
                </form>

                {http.errors.file && (
                    <p className="rounded bg-red-100 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                        {http.errors.file}
                    </p>
                )}
                {error && (
                    <p className="rounded bg-red-100 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">{error}</p>
                )}

                {pages.map((p) => (
                    <PageView key={p.page} page={p} />
                ))}
            </div>
        </Layout>
    );
}

function PageView({ page }: { page: Page }) {
    const [hover, setHover] = useState<number | null>(null);
    const [copied, setCopied] = useState(false);
    const blocks = page.blocks;

    async function copy() {
        await navigator.clipboard.writeText(blocks ? JSON.stringify(blocks, null, 2) : page.markdown);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    }

    return (
        <section className="rounded-lg border border-neutral-300 dark:border-neutral-700">
            <header className="flex items-center justify-between border-b border-neutral-300 px-4 py-2 text-sm font-semibold dark:border-neutral-700">
                page {page.page}
                <button
                    onClick={copy}
                    className="rounded px-2 py-1 text-xs font-normal hover:bg-neutral-200 dark:hover:bg-neutral-800"
                >
                    {copied ? 'コピーしました' : blocks ? 'JSON をコピー' : 'Markdown をコピー'}
                </button>
            </header>
            <div className="grid gap-4 p-4 md:grid-cols-2">
                <div className="relative self-start">
                    <img
                        src={page.image}
                        alt={`page ${page.page}`}
                        className="w-full border border-neutral-200 dark:border-neutral-800"
                    />
                    {blocks?.map((b, i) => {
                        const [x1, y1, x2, y2] = b.bbox;
                        return (
                            <div
                                key={i}
                                onMouseEnter={() => setHover(i)}
                                onMouseLeave={() => setHover(null)}
                                className="absolute border-2"
                                style={{
                                    left: `${x1 / 10}%`,
                                    top: `${y1 / 10}%`,
                                    width: `${(x2 - x1) / 10}%`,
                                    height: `${(y2 - y1) / 10}%`,
                                    borderColor: colorOf(b.label),
                                    background: hover === i ? `${colorOf(b.label)}33` : undefined,
                                }}
                                title={b.label}
                            />
                        );
                    })}
                </div>
                <div className="max-h-[80vh] overflow-auto">
                    {blocks ? (
                        <ul className="flex flex-col gap-2">
                            {blocks.map((b, i) => (
                                <li
                                    key={i}
                                    onMouseEnter={() => setHover(i)}
                                    onMouseLeave={() => setHover(null)}
                                    className="rounded border-l-4 p-2 text-sm"
                                    style={{
                                        borderColor: colorOf(b.label),
                                        background: hover === i ? `${colorOf(b.label)}1a` : undefined,
                                    }}
                                >
                                    <div className="mb-1 font-mono text-xs text-neutral-500">
                                        {b.label} [{b.bbox.join(', ')}]
                                    </div>
                                    <pre className="font-sans whitespace-pre-wrap">{b.text}</pre>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <pre className="text-sm whitespace-pre-wrap">{page.markdown}</pre>
                    )}
                </div>
            </div>
        </section>
    );
}
