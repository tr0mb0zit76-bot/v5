const CACHE_VERSION = 'v7';
const CACHE_NAME = `logist-crm-shell-${CACHE_VERSION}`;
const ASSET_CACHE_NAME = `logist-crm-assets-${CACHE_VERSION}`;
const SHELL_URLS = [
    '/',
    '/manifest.webmanifest',
    '/assets/favicon/apple-touch-icon.png',
    '/assets/favicon/favicon-96x96.png',
    '/assets/favicon/web-app-manifest-192x192.png',
    '/assets/favicon/web-app-manifest-512x512.png',
];

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_URLS))
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => ![CACHE_NAME, ASSET_CACHE_NAME].includes(key))
                    .map((key) => caches.delete(key))
            )
        )
    );

    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);

    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    const isNavigationRequest = event.request.mode === 'navigate';
    const isStaticAsset = requestUrl.pathname.startsWith('/build/') || requestUrl.pathname.startsWith('/assets/');

    if (isNavigationRequest) {
        // Не перехватываем маршруты CRM: только сеть, без respondWith(fetch) — иначе при
        // недоступном сервере в консоли «Uncaught Failed to fetch» из sw.js.
        if (requestUrl.pathname !== '/') {
            return;
        }

        event.respondWith(
            (async () => {
                try {
                    const response = await fetch(event.request);

                    if (!response || response.status >= 400 || response.status === 206) {
                        return response;
                    }

                    const responseClone = response.clone();
                    const cache = await caches.open(CACHE_NAME);
                    await cache.put('/', responseClone);

                    return response;
                } catch {
                    return (await caches.match(event.request)) || (await caches.match('/'));
                }
            })(),
        );

        return;
    }

    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }

                return fetch(event.request)
                    .then((response) => {
                        if (!response || response.status >= 400 || response.status === 206) {
                            return response;
                        }

                        const responseClone = response.clone();

                        caches.open(ASSET_CACHE_NAME).then((cache) => cache.put(event.request, responseClone));

                        return response;
                    })
                    .catch(() => caches.match(event.request));
            })
        );
    }
});
