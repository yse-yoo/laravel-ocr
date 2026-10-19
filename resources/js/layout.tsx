import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

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

const cameraButtonClass = 'cursor-pointer rounded bg-neutral-200 px-3 py-1.5 text-sm dark:bg-neutral-800';

// スマホ・タブレットなど、タッチ操作が主の端末か
export function isTouchDevice(): boolean {
    return window.matchMedia('(pointer: coarse)').matches;
}

// スマホは capture で標準カメラアプリを開き、
// PC は capture が無視されるので getUserMedia で Web カメラを使う
export function CameraInput({ onChange }: { onChange: (file: File | null) => void }) {
    const [isTouch] = useState(isTouchDevice);

    if (!isTouch) {
        return <WebcamCapture onChange={onChange} />;
    }

    return (
        <label className={cameraButtonClass}>
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

// getUserMedia は安全なコンテキスト（localhost か https）でしか使えない
function cameraErrorMessage(error: unknown): string {
    if (!navigator.mediaDevices?.getUserMedia) {
        return 'このページではカメラを使えません。localhost か https で開いてください。';
    }
    if (error instanceof DOMException && error.name === 'NotAllowedError') {
        return 'カメラの使用が許可されていません。ブラウザのアドレスバーから許可してください。';
    }
    if (error instanceof DOMException && error.name === 'NotFoundError') {
        return 'カメラが見つかりません。';
    }
    if (error instanceof DOMException && error.name === 'NotReadableError') {
        return 'カメラを開けません。ほかのアプリが使用中でないか確認してください。';
    }
    return 'カメラを起動できませんでした。';
}

// 撮影時に切り抜くガイド枠（映像に対する割合）。顔や背景を除いてラベルだけを OCR に渡す
const guideFrame = { x: 0.15, y: 0.1, width: 0.7, height: 0.8 };

// zoom は lib.dom の型に無い（Chrome / Edge で対応カメラのみ使える）
type ZoomRange = { min: number; max: number; step: number };

function WebcamCapture({ onChange }: { onChange: (file: File | null) => void }) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [videoSize, setVideoSize] = useState<{ width: number; height: number } | null>(null);
    const [zoomRange, setZoomRange] = useState<ZoomRange | null>(null);
    const [zoom, setZoom] = useState(1);
    const [error, setError] = useState<string | null>(null);

    function stopStream() {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setVideoSize(null);
        setZoomRange(null);
    }

    useEffect(() => stopStream, []);

    async function open() {
        setError(null);
        dialogRef.current?.showModal();
        try {
            // ideal は「できればこの値」。カメラの最大解像度に近いものが選ばれる
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: 3840 },
                    height: { ideal: 2160 },
                    zoom: true,
                } as MediaTrackConstraints,
                audio: false,
            });
            // 許可待ちの間にキャンセルされていたら、カメラを点けっぱなしにしない
            if (!dialogRef.current?.open) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
            }

            const [track] = stream.getVideoTracks();
            const range = (track.getCapabilities?.() as { zoom?: ZoomRange } | undefined)?.zoom;
            if (range && range.max > range.min) {
                setZoomRange(range);
                setZoom((track.getSettings() as { zoom?: number }).zoom ?? range.min);
            }
        } catch (e) {
            setError(cameraErrorMessage(e));
        }
    }

    function changeZoom(value: number) {
        setZoom(value);
        streamRef.current
            ?.getVideoTracks()[0]
            ?.applyConstraints({ advanced: [{ zoom: value } as MediaTrackConstraintSet] })
            .catch(() => setZoomRange(null));
    }

    function shoot() {
        const video = videoRef.current;
        if (!video || !videoSize) {
            return;
        }
        const crop = {
            x: Math.round(videoSize.width * guideFrame.x),
            y: Math.round(videoSize.height * guideFrame.y),
            width: Math.round(videoSize.width * guideFrame.width),
            height: Math.round(videoSize.height * guideFrame.height),
        };
        const canvas = document.createElement('canvas');
        canvas.width = crop.width;
        canvas.height = crop.height;
        canvas.getContext('2d')?.drawImage(video, crop.x, crop.y, crop.width, crop.height, 0, 0, crop.width, crop.height);
        canvas.toBlob(
            (blob) => {
                if (blob) {
                    onChange(new File([blob], `camera-${Date.now()}.jpg`, { type: 'image/jpeg' }));
                }
                dialogRef.current?.close();
            },
            'image/jpeg',
            0.92,
        );
    }

    return (
        <>
            <button type="button" onClick={open} className={cameraButtonClass}>
                カメラで撮影
            </button>
            <dialog
                ref={dialogRef}
                onClose={stopStream}
                className="m-auto w-full max-w-3xl rounded-lg bg-background p-4 text-foreground backdrop:bg-black/60"
            >
                <div className="flex flex-col gap-4">
                    {error ? (
                        <p className="rounded bg-red-100 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                            {error}
                        </p>
                    ) : (
                        <div className="relative overflow-hidden rounded bg-black">
                            {/* 表示枠を映像と同じ縦横比にして、ガイド枠の位置を撮影範囲と一致させる */}
                            <video
                                ref={videoRef}
                                autoPlay
                                playsInline
                                muted
                                onLoadedMetadata={(e) =>
                                    setVideoSize({ width: e.currentTarget.videoWidth, height: e.currentTarget.videoHeight })
                                }
                                style={{ aspectRatio: videoSize ? `${videoSize.width} / ${videoSize.height}` : '16 / 9' }}
                                className="block w-full"
                            />
                            {videoSize && (
                                <div
                                    className="pointer-events-none absolute rounded border-2 border-white shadow-[0_0_0_9999px_rgb(0_0_0/0.5)]"
                                    style={{
                                        left: `${guideFrame.x * 100}%`,
                                        top: `${guideFrame.y * 100}%`,
                                        width: `${guideFrame.width * 100}%`,
                                        height: `${guideFrame.height * 100}%`,
                                    }}
                                />
                            )}
                        </div>
                    )}
                    {videoSize && (
                        <p className="text-sm text-neutral-500">
                            枠の中にラベルを大きく写してください（カメラ {videoSize.width}×{videoSize.height}、保存{' '}
                            {Math.round(videoSize.width * guideFrame.width)}×{Math.round(videoSize.height * guideFrame.height)}）
                        </p>
                    )}
                    {zoomRange && (
                        <label className="flex items-center gap-3 text-sm">
                            ズーム
                            <input
                                type="range"
                                min={zoomRange.min}
                                max={zoomRange.max}
                                step={zoomRange.step || 0.1}
                                value={zoom}
                                onChange={(e) => changeZoom(Number(e.target.value))}
                                className="flex-1"
                            />
                            <span className="w-12 text-right tabular-nums">{zoom.toFixed(1)}x</span>
                        </label>
                    )}
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => dialogRef.current?.close()}
                            className="rounded bg-neutral-200 px-4 py-2 text-sm dark:bg-neutral-800"
                        >
                            キャンセル
                        </button>
                        <button
                            type="button"
                            onClick={shoot}
                            disabled={!videoSize}
                            className="rounded bg-foreground px-4 py-2 text-sm font-medium text-background disabled:opacity-40"
                        >
                            撮影
                        </button>
                    </div>
                </div>
            </dialog>
        </>
    );
}
