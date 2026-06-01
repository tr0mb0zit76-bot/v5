import { router } from '@inertiajs/vue3';

/**
 * На HTTPS-странице поднимает http:// тот же хост и относительные пути до https://.
 * Inertia v2 хранит visit.url как объект URL.
 */
export function coerceHttpsUrl(url) {
    if (typeof window === 'undefined' || window.location.protocol !== 'https:') {
        return url;
    }

    const raw = url instanceof URL ? url.href : url;

    if (typeof raw !== 'string' || raw === '') {
        return url;
    }

    if (raw.startsWith('http://')) {
        try {
            const parsed = new URL(raw);

            if (parsed.hostname === window.location.hostname) {
                const httpsHref = parsed.href.replace(/^http:/, 'https:');

                return url instanceof URL ? new URL(httpsHref) : httpsHref;
            }
        } catch {
            return url;
        }
    }

    if (raw.startsWith('/')) {
        const httpsHref = new URL(raw, window.location.origin).href;

        return url instanceof URL ? new URL(httpsHref) : httpsHref;
    }

    return url;
}

function applyVisitUrl(visit, next) {
    if (next instanceof URL || (typeof next === 'string' && next.includes('://'))) {
        visit.url = next instanceof URL ? next : new URL(String(next));
    } else if (typeof next === 'string') {
        visit.url = next;
    }
}

/** Безопасный переход по пути меню (всегда от origin текущей страницы). */
export function visitInertiaPath(path) {
    router.visit(coerceHttpsUrl(path));
}

/** Ziggy в HTML может содержать http:// из закэшированного config — выравниваем под страницу. */
export function ensureZiggyUsesPageProtocol() {
    if (typeof window === 'undefined' || !globalThis.Ziggy?.url) {
        return;
    }

    if (window.location.protocol !== 'https:') {
        return;
    }

    try {
        const ziggyBase = new URL(globalThis.Ziggy.url, window.location.origin);

        if (ziggyBase.protocol === 'http:') {
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
        if (typeof href === 'string' || href instanceof URL) {
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
