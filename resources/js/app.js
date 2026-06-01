import './bootstrap';
import { ensureZiggyUsesPageProtocol } from './support/inertiaHttpsVisit.js';
import '../css/app.css';
import '../css/crm-sky-theme.css';
import '../css/crm-workspace-skin.css';
import '../css/crm-appearance.css';
import {
    applyCrmAppearanceToDocument,
    CRM_APPEARANCE_CHANGED,
    readLocalCrmAppearance,
    resolveCrmAppearance,
} from './support/crmAppearance.js';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

function documentTitleSuffixFromPage() {
    const suffix = router.page?.props?.document_title_suffix;
    if (typeof suffix === 'string' && suffix.trim() !== '') {
        return suffix.trim();
    }

    return 'CRM компании Автоальянс Смоленск';
}

const savedTheme = localStorage.getItem('theme');
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const shouldUseDarkTheme = savedTheme === 'dark' || (savedTheme === null && prefersDark);

document.documentElement.classList.toggle('dark', shouldUseDarkTheme);
document.documentElement.classList.remove('light');

applyCrmAppearanceToDocument(resolveCrmAppearance({ ui_preferences: readLocalCrmAppearance() }));

window.addEventListener(CRM_APPEARANCE_CHANGED, (event) => {
    applyCrmAppearanceToDocument(resolveCrmAppearance({
        ui_preferences: {
            ...readLocalCrmAppearance(),
            ...(event.detail ?? {}),
        },
    }));
});

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        let hasReloadedForServiceWorkerUpdate = false;

        const reloadForServiceWorkerUpdate = () => {
            if (hasReloadedForServiceWorkerUpdate) {
                return;
            }

            hasReloadedForServiceWorkerUpdate = true;
            window.location.reload();
        };

        navigator.serviceWorker.addEventListener('controllerchange', reloadForServiceWorkerUpdate);

        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                if (registration.waiting) {
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                }

                registration.addEventListener('updatefound', () => {
                    const installingWorker = registration.installing;

                    if (!installingWorker) {
                        return;
                    }

                    installingWorker.addEventListener('statechange', () => {
                        if (installingWorker.state !== 'installed' || !navigator.serviceWorker.controller) {
                            return;
                        }

                        installingWorker.postMessage({ type: 'SKIP_WAITING' });
                    });
                });
            })
            .catch((error) => {
                console.error('Service worker registration failed', error);
            });
    });
}

createInertiaApp({
    title: (title) => {
        const suffix = documentTitleSuffixFromPage();
        const t = typeof title === 'string' ? title.trim() : '';

        return t !== '' ? `${t} - ${suffix}` : suffix;
    },
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0284c7',
    },
});

router.on('success', (event) => {
    ensureZiggyUsesPageProtocol();

    const user = event.detail.page.props?.auth?.user ?? null;

    if (user) {
        applyCrmAppearanceToDocument(resolveCrmAppearance(user));
    }
});
