import { router } from '@inertiajs/vue3';

/**
 * На HTTPS-странице поднимает http:// того же хоста до https://.
 * Относительные пути (/orders, /documents/) не превращаем в абсолютный URL — иначе Inertia/axios
 * хуже переживают ответы прокси; главное — не ходить на http:// вручную.
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

    if (raw.startsWith('https://')) {
        return url;
    }

    if (raw.startsWith('/')) {
        return url instanceof URL ? url : raw;
    }

    return url;
}

/** Пути CRM, для которых на nginx нельзя оставлять URL без слэша (каталог public/documents/ на старых деплоях). */
export function normalizeCrmInertiaPath(path) {
    if (typeof path !== 'string') {
        return path;
    }

    if (path === '/documents') {
        return '/documents/';
    }

    return path;
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
    router.visit(coerceHttpsUrl(normalizeCrmInertiaPath(path)));
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
        if (typeof href === 'string') {
            return originalVisit(coerceHttpsUrl(normalizeCrmInertiaPath(href)), options);
        }

        if (href instanceof URL) {
            return originalVisit(coerceHttpsUrl(href), options);
        }

        if (href !== null && typeof href === 'object' && 'url' in href && 'method' in href) {
            const visitUrl = href.url instanceof URL ? href.url.href : href.url;
            const normalized =
                typeof visitUrl === 'string'
                    ? coerceHttpsUrl(normalizeCrmInertiaPath(visitUrl))
                    : coerceHttpsUrl(visitUrl);

            return originalVisit({ ...href, url: normalized }, options);
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
    const normalized =
        typeof current === 'string' ? normalizeCrmInertiaPath(current) : current;
    const next = coerceHttpsUrl(normalized);

    if (next !== current) {
        applyVisitUrl(visit, next);
    }
});
