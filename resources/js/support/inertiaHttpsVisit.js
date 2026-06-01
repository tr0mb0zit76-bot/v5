import { router } from '@inertiajs/vue3';

/**
 * Inertia строит URL визита относительно page.url с сервера. Если APP_URL=http, а сайт открыт по HTTPS,
 * относительные пути (например /documents) превращаются в http://… и браузер блокирует запрос (Mixed Content).
 */
function upgradeVisitUrlForHttpsPage(url) {
    if (typeof url !== 'string' || typeof window === 'undefined') {
        return url;
    }

    if (window.location.protocol !== 'https:') {
        return url;
    }

    if (url.startsWith('http://')) {
        try {
            const parsed = new URL(url);

            if (parsed.hostname === window.location.hostname) {
                parsed.protocol = 'https:';

                return parsed.toString();
            }
        } catch {
            return url;
        }
    }

    if (url.startsWith('/')) {
        return new URL(url, window.location.origin).href;
    }

    return url;
}

router.on('before', (event) => {
    const visit = event.detail?.visit;

    if (!visit || typeof visit.url !== 'string') {
        return;
    }

    const next = upgradeVisitUrlForHttpsPage(visit.url);

    if (next !== visit.url) {
        visit.url = next;
    }
});
