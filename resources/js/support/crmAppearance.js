import { router } from '@inertiajs/vue3';

export const CRM_APPEARANCE_CHANGED = 'crm-appearance-changed';

export const CRM_APPEARANCE_DEFAULTS = {
    button_radius: 'rounded',
    primary_accent: 'sky',
    tab_style: 'filled',
    workspace_skin: 'sky',
};

const STORAGE_KEY = 'crm_appearance_v1';

let persistTimer = null;

/**
 * @param {{ ui_preferences?: Record<string, unknown> }|null|undefined} authUser
 */
export function resolveCrmAppearance(authUser) {
    const fromServer = pickAppearanceFields(authUser?.ui_preferences ?? {});
    const fromLocal = pickAppearanceFields(readLocalCrmAppearance());
    const hasAuthenticatedPreferences = authUser?.ui_preferences != null
        && typeof authUser.ui_preferences === 'object';

    const appearance = hasAuthenticatedPreferences
        ? { ...CRM_APPEARANCE_DEFAULTS, ...fromServer }
        : { ...CRM_APPEARANCE_DEFAULTS, ...fromLocal };

    const density = authUser?.ui_preferences?.ag_grid_density ?? readLocalCrmAppearance().ag_grid_density;

    if (density === 'compact' || density === 'normal' || density === 'comfortable') {
        appearance.ag_grid_density = density;
    }

    return appearance;
}

/**
 * @param {Record<string, unknown>|null|undefined} source
 */
function pickAppearanceFields(source) {
    if (!source || typeof source !== 'object') {
        return {};
    }

    const result = {};

    if (source.button_radius === 'sharp' || source.button_radius === 'rounded') {
        result.button_radius = source.button_radius;
    }

    if (source.primary_accent === 'emerald' || source.primary_accent === 'sky') {
        result.primary_accent = source.primary_accent;
    }

    if (source.tab_style === 'filled' || source.tab_style === 'underline') {
        result.tab_style = source.tab_style;
    }

    if (source.workspace_skin === 'classic' || source.workspace_skin === 'sky') {
        result.workspace_skin = source.workspace_skin;
    }

    return result;
}

export function readLocalCrmAppearance() {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return {};
        }

        const parsed = JSON.parse(raw);

        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
        return {};
    }
}

/**
 * @param {{ button_radius?: string, primary_accent?: string, tab_style?: string }} appearance
 */
export function writeLocalCrmAppearance(appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(pickAppearanceFields(appearance)));
        window.dispatchEvent(new CustomEvent(CRM_APPEARANCE_CHANGED, { detail: appearance }));
    } catch {
        /* ignore */
    }
}

/**
 * @param {{ button_radius?: string, primary_accent?: string, tab_style?: string }} appearance
 */
export function applyCrmAppearanceToDocument(appearance) {
    if (typeof document === 'undefined') {
        return;
    }

    const html = document.documentElement;
    const resolved = {
        ...CRM_APPEARANCE_DEFAULTS,
        ...pickAppearanceFields(appearance),
    };

    html.dataset.crmRadius = resolved.button_radius;
    html.dataset.crmAccent = resolved.primary_accent;
    html.dataset.crmTabStyle = resolved.tab_style;
    html.dataset.crmWorkspaceSkin = resolved.workspace_skin;
}

/**
 * @param {{ button_radius?: string, primary_accent?: string, tab_style?: string, ag_grid_density?: string }} payload
 */
export function schedulePersistCrmAppearance(payload) {
    if (typeof window === 'undefined') {
        return;
    }

    window.clearTimeout(persistTimer);
    persistTimer = window.setTimeout(() => {
        router.patch(route('profile.ui-preferences'), payload, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 450);
}

/**
 * @param {boolean} isActive
 * @param {{ tab_style?: string }} [appearance]
 */
export function crmTabButtonClasses(isActive, appearance = null) {
    const tabStyle = appearance?.tab_style
        ?? document.documentElement.dataset.crmTabStyle
        ?? CRM_APPEARANCE_DEFAULTS.tab_style;

    const classes = ['crm-tab-btn'];

    if (isActive) {
        classes.push('crm-tab-btn--active-filled');
    } else {
        classes.push('crm-tab-btn--inactive');
    }

    if (tabStyle === 'underline' && isActive) {
        /* active-filled already styled for underline via html[data-crm-tab-style] */
    }

    return classes.join(' ');
}
