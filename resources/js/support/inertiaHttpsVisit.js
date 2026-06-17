import { router } from '@inertiajs/vue3';

/**
 * /documents/ на nginx за прокси часто даёт 301 → http://…/documents (без TLS в Location).
 * Всегда ходим на /documents без завершающего слэша.
 */
export function normalizeDocumentsPath(url) {
    if (typeof url !== 'string' || url === '') {
        return url;
    }

    try {
        if (url.startsWith('/') || url.startsWith('http://') || url.startsWith('https://')) {
            const parsed = new URL(url, typeof window !== 'undefined' ? window.location.origin : 'https://localhost');
            const path = parsed.pathname.replace(/\/+$/, '') || '/';

            if (path === '/documents') {
                parsed.pathname = '/documents';

                if (url.startsWith('/')) {
                    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
                }

                return parsed.href;
            }
        }
    } catch {
        /* ignore */
    }

    if (url === '/documents/' || url.endsWith('/documents/')) {
        return url.replace(/\/documents\/+$/, '/documents');
    }

    return url;
}

/**
 * Выравнивает схему абсолютного URL того же хоста под протокол текущей страницы.
 * На проде за прокси поднимает http→https; локально (http без TLS) опускает https→http.
 * Относительные пути не превращаем в абсолютный URL.
 */
export function coerceHttpsUrl(url) {
    if (typeof window === 'undefined') {
        return typeof url === 'string' ? normalizeDocumentsPath(url) : url;
    }

    const raw = url instanceof URL ? url.href : url;

    if (typeof raw !== 'string' || raw === '') {
        return url;
    }

    let next = raw;

    if (next.startsWith('http://') || next.startsWith('https://')) {
        try {
            const parsed = new URL(next);

            if (parsed.hostname === window.location.hostname) {
                const pageIsHttps = window.location.protocol === 'https:';
                const urlIsHttps = parsed.protocol === 'https:';

                if (pageIsHttps && !urlIsHttps) {
                    next = parsed.href.replace(/^http:/, 'https:');
                } else if (!pageIsHttps && urlIsHttps) {
                    next = parsed.href.replace(/^https:/, 'http:');
                } else {
                    next = parsed.href;
                }
            }
        } catch {
            return url;
        }
    }

    next = normalizeDocumentsPath(next);

    if (next.startsWith('https://') || next.startsWith('http://')) {
        return url instanceof URL ? new URL(next) : next;
    }

    if (next.startsWith('/')) {
        return url instanceof URL ? url : next;
    }

    return next;
}

function applyVisitUrl(visit, next) {
    if (next instanceof URL || (typeof next === 'string' && next.includes('://'))) {
        visit.url = next instanceof URL ? next : new URL(String(next));
    } else if (typeof next === 'string') {
        visit.url = next;
    }
}

/** Безопасный переход по пути меню (относительный URL текущего origin). */
export function visitInertiaPath(path) {
    const url = coerceHttpsUrl(path);
    const pathname = (() => {
        try {
            return new URL(url, typeof window !== 'undefined' ? window.location.origin : 'https://localhost').pathname;
        } catch {
            return typeof url === 'string' ? url : '';
        }
    })();

    const isOrdersIndex = pathname === '/orders';

    router.visit(url, isOrdersIndex ? { preserveState: false } : {});
}

/** Ziggy в HTML может содержать схему из APP_URL — выравниваем под протокол страницы. */
export function ensureZiggyUsesPageProtocol() {
    if (typeof window === 'undefined' || !globalThis.Ziggy?.url) {
        return;
    }

    try {
        const ziggyBase = new URL(globalThis.Ziggy.url, window.location.origin);

        if (ziggyBase.hostname === window.location.hostname
            && ziggyBase.protocol !== window.location.protocol) {
            globalThis.Ziggy.url = window.location.origin;
        }
    } catch {
        /* ignore */
    }
}

function patchGlobalRoute() {
    if (typeof window === 'undefined' || typeof window.route !== 'function' || window.route.__httpsPatched) {
        return;
    }

    const original = window.route;

    const wrapped = function (...args) {
        const result = original.apply(this, args);

        if (typeof result === 'string') {
            return coerceHttpsUrl(result);
        }

        if (result && typeof result === 'object' && 'url' in result) {
            return { ...result, url: coerceHttpsUrl(result.url) };
        }

        return result;
    };

    wrapped.__httpsPatched = true;
    window.route = wrapped;
}

function patchRouterVisit() {
    if (router.visit.__httpsPatched) {
        return;
    }

    const originalVisit = router.visit.bind(router);

    router.visit = (href, options = {}) => {
        if (typeof href === 'string') {
            return originalVisit(coerceHttpsUrl(href), options);
        }

        if (href instanceof URL) {
            return originalVisit(coerceHttpsUrl(href), options);
        }

        if (href !== null && typeof href === 'object' && 'url' in href && 'method' in href) {
            return originalVisit({ ...href, url: coerceHttpsUrl(href.url) }, options);
        }

        return originalVisit(href, options);
    };

    router.visit.__httpsPatched = true;
}

ensureZiggyUsesPageProtocol();
patchGlobalRoute();
patchRouterVisit();

router.on('before', (event) => {
    const visit = event.detail?.visit;

    if (!visit?.url) {
        return;
    }

    const current = visit.url instanceof URL ? visit.url.href : visit.url;
    const next = coerceHttpsUrl(current);

    if (next !== current) {
        applyVisitUrl(visit, next);
    }
});
