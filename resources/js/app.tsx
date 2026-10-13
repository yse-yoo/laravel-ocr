import { createInertiaApp } from '@inertiajs/react';

// ページは resources/js/pages/*.tsx（@inertiajs/vite が解決する）
createInertiaApp({
    title: (title) => (title ? `${title} - LightOnOCR` : 'LightOnOCR'),
});
