import tailwindcss from '@tailwindcss/vite';
import Icons from 'unplugin-icons/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig, loadEnv } from 'vite';

// The API is reached through this dev server (same origin), so session cookies and CSRF work
// exactly as in production, where Laravel serves the built app from apps/api/public/app.
export default defineConfig(({ command, mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const api = env.FOCUS_API_URL ?? 'http://127.0.0.1:8000';

    return {
        base: command === 'build' ? '/app/' : '/',
        // Iconify icons are compiled to inline SVG components at build time — no runtime API calls.
        plugins: [vue(), Icons({ compiler: 'vue3', scale: 1 }), tailwindcss()],
        resolve: {
            alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
            dedupe: ['vue', 'vue-router', 'pinia'],
        },
        server: {
            port: 5173,
            strictPort: true,
            proxy: {
                '/api': { target: api },
                '/sanctum': { target: api },
            },
        },
        build: {
            outDir: fileURLToPath(new URL('../api/public/app', import.meta.url)),
            emptyOutDir: true,
        },
    };
});
