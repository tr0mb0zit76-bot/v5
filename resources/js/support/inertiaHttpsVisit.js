import { router } from '@inertiajs/vue3';

/**
 * На HTTPS-странице поднимает http:// тот же хост и относительные пути до https://.
 * Inertia v2 хранит visit.url как объект URL — строковая проверка не срабатывала.
 */
export function upgradeVisitUrlForHttpsPage(url) {
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
                return parsed.protocol === 'https:' ? raw : parsed.href.replace(/^http:/, 'https:');
            }
        } catch {
            return url;
        }
    }

    if (raw.startsWith('/')) {
        return new URL(raw, window.location.origin).href;
    }

    return url;
}

function applyVisitUrl(visit, next) {
    if (next instanceof URL || (typeof next === 'string' && next.includes('://'))) {
        visit.url = new URL(String(next));
    } else if (typeof next === 'string') {
        visit.url = next;
    }
}

/** Безопасный переход по пути меню (всегда от origin текущей страницы). */
export function visitInertiaPath(path) {
    if (typeof window !== 'undefined' && window.location.protocol === 'https:') {
        router.visit(new URL(path, window.location.origin).href);

        return;
    }

    router.visit(path);
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

ensureZiggyUsesPageProtocol();

router.on('before', (event) => {
    const visit = event.detail?.visit;

    if (!visit?.url) {
        return;
    }

    const current = visit.url instanceof URL ? visit.url.href : visit.url;
    const next = upgradeVisitUrlForHttpsPage(current);

    if (next !== current) {
        applyVisitUrl(visit, next);
    }
});
