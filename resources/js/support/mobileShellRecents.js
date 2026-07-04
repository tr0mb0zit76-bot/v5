const STORAGE_KEY = 'crm-mobile-shell-recents';
const MAX_RECENTS = 12;

export function readMobileRecents() {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]');

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function pushMobileRecent(entity) {
    if (typeof window === 'undefined' || !entity?.kind || !entity?.id) {
        return;
    }

    const entry = {
        kind: String(entity.kind),
        id: Number(entity.id),
        label: String(entity.label ?? ''),
        subtitle: entity.subtitle ? String(entity.subtitle) : null,
        url: String(entity.url ?? ''),
        opened_at: Date.now(),
    };

    const list = readMobileRecents()
        .filter((item) => !(item.kind === entry.kind && Number(item.id) === entry.id));

    list.unshift(entry);
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(0, MAX_RECENTS)));
}

export function clearMobileRecents() {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(STORAGE_KEY);
}
