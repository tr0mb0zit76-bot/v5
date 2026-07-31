export const SALES_ASSISTANT_SUBMODULE_KEYS = [
    'sales_assistant_scripts',
    'sales_assistant_book',
    'sales_assistant_book_analytics',
    'sales_assistant_trainer',
    'sales_assistant_trainer_analytics',
    'sales_assistant_counter',
];

export const MODULES_SUBMODULE_KEYS = [
    'modules_catalog',
    'modules_how_much_fits',
    'modules_how_much_costs',
    'modules_import_cost',
    'modules_proposal_templates',
];

export const OWN_FLEET_SUBMODULE_KEYS = [
    'fleet_trips',
    'fleet_efficiency',
];

export const SETTINGS_SUBMODULE_KEYS = [
    'settings_system',
    'settings_motivation',
];

/**
 * @param {string[]} areas
 * @param {string} submoduleKey
 */
export function hasSalesAssistantSubmoduleAccess(areas, submoduleKey) {
    if (!Array.isArray(areas) || !SALES_ASSISTANT_SUBMODULE_KEYS.includes(submoduleKey)) {
        return false;
    }

    if (areas.includes(submoduleKey)) {
        return true;
    }

    if (!areas.includes('scripts')) {
        return false;
    }

    return !SALES_ASSISTANT_SUBMODULE_KEYS.some((key) => areas.includes(key));
}

/**
 * @param {string[]} areas
 * @param {string} submoduleKey
 */
export function hasModulesSubmoduleAccess(areas, submoduleKey) {
    if (!Array.isArray(areas) || !MODULES_SUBMODULE_KEYS.includes(submoduleKey)) {
        return false;
    }

    if (areas.includes(submoduleKey)) {
        return true;
    }

    if (!areas.includes('modules')) {
        return false;
    }

    // Parent «modules» alone = full access; with any child listed = selective.
    return !MODULES_SUBMODULE_KEYS.some((key) => areas.includes(key));
}

/**
 * @param {string[]} areas
 * @param {string} submoduleKey
 */
export function hasOwnFleetSubmoduleAccess(areas, submoduleKey) {
    if (!Array.isArray(areas) || !OWN_FLEET_SUBMODULE_KEYS.includes(submoduleKey)) {
        return false;
    }

    if (areas.includes(submoduleKey)) {
        return true;
    }

    // Легаси: ТС (drivers) открывает рейсы/эффективность — как на бэкенде.
    if (areas.includes('drivers')) {
        return true;
    }

    if (!areas.includes('own_fleet')) {
        return false;
    }

    return !OWN_FLEET_SUBMODULE_KEYS.some((key) => areas.includes(key));
}

/**
 * @param {string[]} areas
 * @param {string} submoduleKey
 */
export function hasSettingsSubmoduleAccess(areas, submoduleKey) {
    if (!Array.isArray(areas) || !SETTINGS_SUBMODULE_KEYS.includes(submoduleKey)) {
        return false;
    }

    if (areas.includes(submoduleKey)) {
        return true;
    }

    if (!areas.includes('settings')) {
        return false;
    }

    return !SETTINGS_SUBMODULE_KEYS.some((key) => areas.includes(key));
}
