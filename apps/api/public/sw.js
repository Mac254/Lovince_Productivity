/*
 * Focus service worker: makes the app installable and quick to open.
 * Privacy rule: anything under /api or /sanctum is never cached — personal data stays out
 * of the browser cache. Only the page shell and versioned build files are stored.
 *
 * Build files live in their own cache. When a fresh page shell names a different entry script,
 * a new build has shipped, so that cache is emptied and only the new build's files fill it again.
 */
const SHELL_CACHE = 'focus-shell-v2';
const ASSET_CACHE = 'focus-assets-v2';
const SHELL = '/';

const entryOf = (html) => html.match(/src="([^"]*\/assets\/index-[^"]+\.js)"/)?.[1] ?? null;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.add(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    const keep = [SHELL_CACHE, ASSET_CACHE];
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => !keep.includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

async function storeShell(response) {
    const cache = await caches.open(SHELL_CACHE);
    const previous = await cache.match(SHELL);
    const [before, after] = await Promise.all([previous?.text(), response.clone().text()]);
    if (before !== undefined && entryOf(before) !== entryOf(after)) await caches.delete(ASSET_CACHE);
    await cache.put(SHELL, response);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')) return;

    // Hashed build files never change: serve from cache, fetch once.
    if (url.pathname.startsWith('/app/assets/')) {
        event.respondWith(
            caches.match(request, { cacheName: ASSET_CACHE }).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
        return;
    }

    // Pages: always try the network for the latest app, fall back to the cached shell offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) event.waitUntil(storeShell(response.clone()));
                    return response;
                })
                .catch(() => caches.match(SHELL, { cacheName: SHELL_CACHE })),
        );
    }
});
