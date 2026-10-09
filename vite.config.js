import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            hotFile: process.env.VITE_HOT_FILE || 'public/hot',
        }),
    ],
    server: {
        ...(process.env.VITE_DOCKER === '1' ? {
            host: '0.0.0.0',
            port: Number(process.env.VITE_PORT || 5173),
            strictPort: true,
            origin: `http://localhost:${process.env.VITE_PORT || 5173}`,
            hmr: { host: 'localhost', clientPort: Number(process.env.VITE_PORT || 5173) },
            cors: { origin: [process.env.APP_URL, 'http://localhost:8080', 'http://127.0.0.1:8080'].filter(Boolean) },
        } : {}),
        watch: {
            usePolling: process.env.VITE_DOCKER === '1',
            interval: 500,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
