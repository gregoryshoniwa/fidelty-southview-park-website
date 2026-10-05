import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/site.js', 'resources/js/resident/main.js', 'resources/js/partner/main.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
    resolve: { alias: { '@': '/resources/js' } },
    build: {
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/lucide-vue-next')) return 'icons';
                    if (id.includes('node_modules/vue') || id.includes('node_modules/@vue') || id.includes('pinia') || id.includes('vue-router')) return 'vue';
                    if (id.includes('node_modules/reka-ui')) return 'ui';
                },
            },
        },
    },
    // Pin to IPv4 so the dev URL is valid in the Content-Security-Policy (IPv6 literals like [::1] are rejected).
    server: { host: '127.0.0.1', port: 5173, strictPort: true, hmr: { host: '127.0.0.1' }, watch: { ignored: ['**/storage/framework/views/**'] } },
});
