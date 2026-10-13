import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout, { CameraInput, fileInputClass } from '@/layout';
import { toImages } from '@/pdf';
import type { FoodRecord, Nutrition } from '@/types';

const NUTRITION: [keyof Nutrition, string, string][] = [
    ['energyKcal', 'エネルギー', 'kcal'],
    ['proteinG', 'たんぱく質', 'g'],
    ['fatG', '脂質', 'g'],
    ['carbohydrateG', '炭水化物', 'g'],
    ['sugarG', '糖質', 'g'],
    ['fiberG', '食物繊維', 'g'],
    ['saltG', '食塩相当量', 'g'],
];

// foods は FoodController@index から props で届く。保存・削除後は Inertia が自動で取り直す
export default function Food({ foods }: { foods: FoodRecord[] }) {
    const form = useForm<{ file: File | null }>({ file: null });
    const [converting, setConverting] = useState(false);
    const busy = converting || form.processing;

    async function run(e: React.FormEvent) {
        e.preventDefault();
        if (!form.data.file) return;
        form.clearErrors();
        // PDF なら 1 ページ目だけを画像にして送る
        setConverting(true);
        let image: File | undefined;
        try {
            const pages = toImages(form.data.file);
            image = (await pages.next()).value || undefined;
            await pages.return(undefined); // 2 ページ目以降は描画せず、pdf.js の後始末だけ走らせる
        } catch (err) {
            form.setError('file', err instanceof Error ? err.message : String(err));
        } finally {
            setConverting(false);
        }
        if (!image) return;
        form.transform(() => ({ file: image }));
        form.post('/food', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function remove(id: number) {
        router.delete(`/food/${id}`, { preserveScroll: true });
    }

    function download() {
        // 画像は除いて、抽出したデータだけを書き出す
        const rows = foods.map(({ id, createdAt, data }) => ({ id, createdAt, ...data }));
        const url = URL.createObjectURL(new Blob([JSON.stringify(rows, null, 2)], { type: 'application/json' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = 'foods.json';
        a.click();
        URL.revokeObjectURL(url);
    }

    const setFile = (file: File | null) => form.setData('file', file);

    return (
        <Layout
            title="食品成分"
            description="食品ラベルを撮影して、原材料・アレルゲン・栄養成分を JSON で保存します。"
            link={{ href: '/', label: 'OCR に戻る' }}
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
                    {form.data.file && (
                        <span className="max-w-48 truncate text-sm text-neutral-500">{form.data.file.name}</span>
                    )}
                    <button
                        type="submit"
                        disabled={!form.data.file || busy}
                        className="ml-auto rounded bg-foreground px-4 py-2 text-sm font-medium text-background disabled:opacity-40"
                    >
                        {busy ? '読み取り中…' : '読み取って保存'}
                    </button>
                </form>

                {form.errors.file && (
                    <p className="rounded bg-red-100 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                        {form.errors.file}
                    </p>
                )}

                <div className="flex items-center justify-between">
                    <h2 className="font-semibold">保存済み（{foods.length} 件）</h2>
                    <button
                        onClick={download}
                        disabled={foods.length === 0}
                        className="rounded px-2 py-1 text-sm hover:bg-neutral-200 disabled:opacity-40 dark:hover:bg-neutral-800"
                    >
                        JSON をダウンロード
                    </button>
                </div>

                {foods.map((f) => (
                    <FoodView key={f.id} food={f} onDelete={() => remove(f.id)} />
                ))}
            </div>
        </Layout>
    );
}

function FoodView({ food, onDelete }: { food: FoodRecord; onDelete: () => void }) {
    const d = food.data;
    return (
        <section className="rounded-lg border border-neutral-300 dark:border-neutral-700">
            <header className="flex items-center justify-between gap-2 border-b border-neutral-300 px-4 py-2 dark:border-neutral-700">
                <span className="font-semibold">{d.productName ?? '（商品名なし）'}</span>
                <span className="ml-auto text-xs text-neutral-500">
                    {new Date(food.createdAt).toLocaleString('ja-JP')}
                </span>
                <button onClick={onDelete} className="rounded px-2 py-1 text-xs hover:bg-neutral-200 dark:hover:bg-neutral-800">
                    削除
                </button>
            </header>
            <div className="grid gap-4 p-4 md:grid-cols-[200px_1fr]">
                <img src={food.image} alt="" className="w-full self-start border border-neutral-200 dark:border-neutral-800" />
                <div className="flex flex-col gap-3 text-sm">
                    {d.allergens.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {d.allergens.map((a) => (
                                <span
                                    key={a}
                                    className="rounded bg-amber-100 px-2 py-0.5 text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                                >
                                    {a}
                                </span>
                            ))}
                        </div>
                    )}
                    <table className="w-full max-w-sm">
                        <caption className="text-left text-xs text-neutral-500">栄養成分表示 {d.nutrition.per ?? ''}</caption>
                        <tbody>
                            {NUTRITION.map(([key, label, unit]) => (
                                <tr key={key} className="border-b border-neutral-200 dark:border-neutral-800">
                                    <td className="py-0.5">{label}</td>
                                    <td className="py-0.5 text-right font-mono">{d.nutrition[key] ?? '—'}</td>
                                    <td className="w-10 py-0.5 pl-1 text-neutral-500">{unit}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p>
                        <span className="text-xs text-neutral-500">原材料：</span>
                        {d.ingredients.join('、') || '—'}
                        {d.additives.length > 0 && ` ／ ${d.additives.join('、')}`}
                    </p>
                    <details>
                        <summary className="cursor-pointer text-xs text-neutral-500">JSON / OCR テキスト</summary>
                        <pre className="mt-2 overflow-auto rounded bg-neutral-100 p-2 text-xs dark:bg-neutral-900">
                            {JSON.stringify(d, null, 2)}
                        </pre>
                        <pre className="mt-2 overflow-auto rounded bg-neutral-100 p-2 text-xs whitespace-pre-wrap dark:bg-neutral-900">
                            {food.ocrText}
                        </pre>
                    </details>
                </div>
            </div>
        </section>
    );
}
