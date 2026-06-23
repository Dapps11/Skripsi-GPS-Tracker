import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Ini kunci agar Vite bisa diakses via Ngrok
        hmr: {
            host: 'unhabitual-nonenigmatically-xochitl.ngrok-free.dev', 
        },
    },
});