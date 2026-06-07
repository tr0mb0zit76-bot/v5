const STORAGE_KEY = 'crm_command_bar_agent_slug_v1';

export function loadAgentSlug(fallback = 'jarvis') {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        return raw && raw.length > 0 ? raw : fallback;
    } catch {
        return fallback;
    }
}

export function saveAgentSlug(slug) {
    try {
        if (!slug) {
            localStorage.removeItem(STORAGE_KEY);

            return;
        }

        localStorage.setItem(STORAGE_KEY, String(slug));
    } catch {
        // ignore quota / private mode
    }
}
