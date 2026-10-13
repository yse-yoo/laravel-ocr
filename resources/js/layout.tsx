import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export const fileInputClass =
    'text-sm file:mr-3 file:rounded file:border-0 file:bg-neutral-200 file:px-3 file:py-1.5 dark:file:bg-neutral-800';

export default function Layout({
    title,
    description,
    link,
    children,
}: {
    title: string;
    description: string;
    link: { href: string; label: string };
    children: ReactNode;
}) {
    return (
        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <Head title={title} />
            <h1 className="mb-1 text-2xl font-bold">{title}</h1>
            <p className="mb-6 text-sm text-neutral-500">
                {description}
                <Link href={link.href} className="ml-2 underline">
                    {link.label}
                </Link>
            </p>
            {children}
        </main>
    );
}

// 背面カメラを直接開く（PC では通常のファイル選択になる）
export function CameraInput({ onChange }: { onChange: (file: File | null) => void }) {
    return (
        <label className="cursor-pointer rounded bg-neutral-200 px-3 py-1.5 text-sm dark:bg-neutral-800">
            カメラで撮影
            <input
                type="file"
                accept="image/*"
                capture="environment"
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
                className="sr-only"
            />
        </label>
    );
}
