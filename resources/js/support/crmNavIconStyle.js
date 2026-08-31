/**
 * Обёртка иконки пункта меню для data-crm-nav-icons (semantic / tinted).
 *
 * @param {string|null|undefined} menuKey
 */
export function crmNavIconWrapClass(menuKey) {
    if (!menuKey || typeof menuKey !== 'string') {
        return 'crm-nav-icon';
    }

    const safe = menuKey.replace(/[^a-z0-9_-]/gi, '');

    if (safe === '') {
        return 'crm-nav-icon';
    }

    return `crm-nav-icon crm-nav-icon--${safe}`;
}
