import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import Icons from 'unplugin-icons/vite';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue(), Icons({ compiler: 'vue3', scale: 1 })],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./apps/web/src', import.meta.url)) },
        dedupe: ['vue', 'vue-router', 'pinia'],
    },
    test: {
        environment: 'jsdom',
        include: ['apps/web/src/**/*.test.ts', 'packages/*/src/**/*.test.ts'],
    },
});
