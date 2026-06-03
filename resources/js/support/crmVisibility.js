export const SALES_ASSISTANT_SUBMODULE_KEYS = [
    'sales_assistant_scripts',
    'sales_assistant_book',
    'sales_assistant_book_analytics',
    'sales_assistant_trainer',
    'sales_assistant_trainer_analytics',
    'sales_assistant_counter',
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
