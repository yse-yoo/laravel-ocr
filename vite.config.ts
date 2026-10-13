import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { cpSync } from 'node:fs';
import { defineConfig, loadEnv, type Plugin } from 'vite';

// pdf.js が実行時に読む補助ファイル（resources/js/pdf.ts）を public/pdfjs/ に置く
function pdfjsAssets(): Plugin {
    return {
        name: 'pdfjs-assets',
        buildStart() {
            for (const dir of ['cmaps', 'standard_fonts', 'wasm', 'iccs']) {
                cpSync(`node_modules/pdfjs-dist/${dir}`, `public/pdfjs/${dir}`, { recursive: true });
            }
        },
    };
}

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia({ ssr: false }),
        react(),
        tailwindcss(),
        pdfjsAssets(),
    ],
    resolve: {
        alias: { '@': '/resources/js' },
    },
    server: {
        // LAN 内の他の端末からも読み込めるよう、全インターフェースで待ち受けて APP_URL のホストを案内する
        host: '0.0.0.0',
        hmr: { host: new URL(loadEnv(mode, process.cwd(), 'APP_URL').APP_URL ?? 'http://localhost').hostname },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
