/*
 * Focus service worker: makes the app installable and quick to open.
 * Privacy rule: anything under /api or /sanctum is never cached — personal data stays out
 * of the browser cache. Only the page shell and versioned build files are stored.
 */
const CACHE = 'focus-shell-v1';
const SHELL = '/';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')) return;

    // Hashed build files never change: serve from cache, fetch once.
    if (url.pathname.startsWith('/app/assets/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
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
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(SHELL, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(SHELL)),
        );
    }
});
