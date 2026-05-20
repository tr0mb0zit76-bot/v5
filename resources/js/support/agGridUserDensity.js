import { router } from '@inertiajs/vue3';
import { defaultGridDensity, resolveGridDensity } from '@/Components/Grid/grid-density.js';
import { readLocalCrmAppearance, resolveCrmAppearance } from '@/support/crmAppearance.js';

/** Раньше у каждого грида был свой ключ — один раз переносим в общий. */
const LEGACY_DENSITY_STORAGE_PREFIXES = [
    'orders_grid_density',
    'leads_grid_density',
    'contractors_grid_density',
    'documents_grid_density',
    'fleet_vehicles_grid_density',
    'fleet_drivers_grid_density',
    'tasks_grid_density',
];

export const CRM_AG_GRID_DENSITY_CHANGED = 'crm-ag-grid-density-changed';

export function agGridDensityStorageKey(userId) {
    return `crm_ag_grid_density_${userId}`;
}

/**
 * @param {string|number} userId
 * @param {{ ui_preferences?: { ag_grid_density?: string } }|null|undefined} authUser
 * @returns {'compact'|'normal'|'comfortable'}
 */
export function readPersistedAgGridDensity(userId, authUser) {
    if (typeof window === 'undefined') {
        return resolveGridDensity(authUser?.ui_preferences?.ag_grid_density ?? defaultGridDensity).key;
    }

    const unifiedKey = agGridDensityStorageKey(userId);
    try {
        const unified = window.localStorage.getItem(unifiedKey);
        if (unified) {
            return resolveGridDensity(unified).key;
        }

        for (const prefix of LEGACY_DENSITY_STORAGE_PREFIXES) {
            const legacy = window.localStorage.getItem(`${prefix}_${userId}`);
            if (legacy) {
                const key = resolveGridDensity(legacy).key;
                window.localStorage.setItem(unifiedKey, key);

                return key;
            }
        }
    } catch {
        /* ignore */
    }

    const fromServer = authUser?.ui_preferences?.ag_grid_density;

    return resolveGridDensity(fromServer ?? defaultGridDensity).key;
}

/**
 * @param {string|number} userId
 * @param {'compact'|'normal'|'comfortable'} densityKey
 */
export function writeLocalAgGridDensity(userId, densityKey) {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        window.localStorage.setItem(agGridDensityStorageKey(userId), densityKey);
        window.dispatchEvent(new CustomEvent(CRM_AG_GRID_DENSITY_CHANGED, { detail: densityKey }));
    } catch {
        /* ignore */
    }
}

let persistTimer = null;

/**
 * Сохраняет выбор в профиле (сервер) с небольшой задержкой, чтобы не ддосить при быстрых кликах.
 *
 * @param {'compact'|'normal'|'comfortable'} densityKey
 */
export function schedulePersistAgGridDensityToProfile(densityKey) {
    if (typeof window === 'undefined') {
        return;
    }

    window.clearTimeout(persistTimer);
    persistTimer = window.setTimeout(() => {
        const appearance = resolveCrmAppearance({
            ui_preferences: {
                ...readLocalCrmAppearance(),
                ag_grid_density: densityKey,
            },
        });

        router.patch(
            '/profile/ui-preferences',
            {
                button_radius: appearance.button_radius,
                primary_accent: appearance.primary_accent,
                tab_style: appearance.tab_style,
                ag_grid_density: densityKey,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }, 450);
}
