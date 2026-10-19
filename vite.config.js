import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const appUrl = process.env.APP_URL;
const origins = new Set([appUrl, 'http://localhost:8080', 'http://127.0.0.1:8080'].filter(Boolean));
if (appUrl) {
    const configuredUrl = new URL(appUrl);
    if (['localhost', '127.0.0.1'].includes(configuredUrl.hostname)) {
        for (const hostname of ['localhost', '127.0.0.1']) {
            const alias = new URL(configuredUrl);
            alias.hostname = hostname;
            origins.add(alias.origin);
        }
    }
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            hotFile: process.env.VITE_HOT_FILE || 'public/hot',
        }),
    ],
    server: {
<<<<<<< HEAD
        ...(process.env.VITE_DOCKER === '1' ? {
            host: '0.0.0.0',
            port: Number(process.env.VITE_PORT || 5173),
            strictPort: true,
            origin: `http://localhost:${process.env.VITE_PORT || 5173}`,
            hmr: { host: 'localhost', clientPort: Number(process.env.VITE_PORT || 5173) },
            cors: { origin: [...origins] },
        } : {}),
=======
        ...(process.env.VITE_DOCKER === '1'
            ? {
                  host: '0.0.0.0',
                  port: Number(process.env.VITE_PORT || 5173),
                  strictPort: true,
                  origin: `http://localhost:${process.env.VITE_PORT || 5173}`,
                  hmr: { host: 'localhost', clientPort: Number(process.env.VITE_PORT || 5173) },
                  cors: {
                      origin: [process.env.APP_URL, 'http://localhost:8080', 'http://127.0.0.1:8080'].filter(Boolean),
                  },
              }
            : {}),
>>>>>>> main
        watch: {
            usePolling: process.env.VITE_DOCKER === '1',
            interval: 500,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
