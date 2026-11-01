import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';

const src = (path: string) => fileURLToPath(new URL(`./src/${path}`, import.meta.url));

// Components exported by @focus/ui, read from its barrel so new ones resolve without touching this file.
const uiBarrel = readFileSync(fileURLToPath(new URL('../../packages/ui/src/index.ts', import.meta.url)), 'utf8');
const uiComponents = new Set([...uiBarrel.matchAll(/\b(?:default|Lv\w+) as (\w+)/g)].map((match) => match[1]));

/**
 * Vue, router and Pinia APIs, the app's stores and composables, and every component from @focus/ui
 * or `src/components` and `src/features` are available without imports. Shared contract types,
 * `@/lib` helpers and the API client stay explicit so their source is visible where they are used.
 */
export const autoImports = () => [
    AutoImport({
        imports: [
            'vue',
            'vue-router',
            'pinia',
            { '@focus/ui': ['useToast', 'useSurfaceTone', 'usePagedList'] },
            { '@focus/motion': ['prefersReducedMotion', 'useAnimatedNumber', 'useStaggerIn'] },
        ],
        dirs: [src('composables'), src('stores')],
        dts: src('auto-imports.d.ts'),
        vueTemplate: true,
    }),
    Components({
        dirs: [src('components'), src('features')],
        deep: true,
        directoryAsNamespace: false,
        dts: src('components.d.ts'),
        resolvers: [(name: string) => (uiComponents.has(name) ? { name, from: '@focus/ui' } : undefined)],
    }),
];
