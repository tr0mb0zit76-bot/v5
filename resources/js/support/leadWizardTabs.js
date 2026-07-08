export const LEAD_WIZARD_TAB_KEYS = {
    main: 'main',
    route: 'route',
    cargo: 'cargo',
    finance: 'finance',
    precalculation: 'precalculation',
    documents: 'documents',
    activities: 'activities',
    commercial: 'commercial',
};

/** @type {Record<string, string[]>} */
export const LEAD_WIZARD_TAB_KEYS_BY_PROFILE = {
    default: [
        LEAD_WIZARD_TAB_KEYS.main,
        LEAD_WIZARD_TAB_KEYS.route,
        LEAD_WIZARD_TAB_KEYS.cargo,
        LEAD_WIZARD_TAB_KEYS.precalculation,
        LEAD_WIZARD_TAB_KEYS.finance,
        LEAD_WIZARD_TAB_KEYS.documents,
        LEAD_WIZARD_TAB_KEYS.activities,
        LEAD_WIZARD_TAB_KEYS.commercial,
    ],
    'contract-signing': [
        LEAD_WIZARD_TAB_KEYS.main,
        LEAD_WIZARD_TAB_KEYS.documents,
        LEAD_WIZARD_TAB_KEYS.activities,
    ],
};

/**
 * @param {string|null|undefined} slug
 */
export function leadWizardCardProfile(slug) {
    if (slug === 'contract-signing') {
        return 'contract-signing';
    }

    return 'default';
}

/**
 * @param {string|null|undefined} slug
 * @returns {string[]}
 */
export function leadWizardVisibleTabKeys(slug) {
    const profile = leadWizardCardProfile(slug);

    return LEAD_WIZARD_TAB_KEYS_BY_PROFILE[profile] ?? LEAD_WIZARD_TAB_KEYS_BY_PROFILE.default;
}

/**
 * @param {number|string|null|undefined} businessProcessId
 * @param {Array<{ id: number|string, slug?: string|null }>} businessProcesses
 * @param {string|null|undefined} processSlugFromProgress
 * @returns {string|null}
 */
export function resolveLeadBusinessProcessSlug(businessProcessId, businessProcesses, processSlugFromProgress = null) {
    if (processSlugFromProgress) {
        return processSlugFromProgress;
    }

    if (businessProcessId === null || businessProcessId === undefined || businessProcessId === '') {
        return null;
    }

    const process = (businessProcesses ?? []).find(
        (item) => Number(item.id) === Number(businessProcessId),
    );

    return process?.slug ?? null;
}

/**
 * @param {string|null|undefined} slug
 */
export function isContractSigningLeadWizard(slug) {
    return leadWizardCardProfile(slug) === 'contract-signing';
}
