const STORAGE_KEY = 'crm_command_bar_agent_thread_v1';
const MAX_MESSAGES = 40;

/**
 * @typedef {{ role: string, content: string, turnId?: string|null, feedback?: string|null }} AgentMessage
 */

/**
 * @returns {AgentMessage[]}
 */
export function loadAgentThread() {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw);
        const messages = Array.isArray(parsed?.messages) ? parsed.messages : [];

        return messages
            .filter((item) => item && (item.role === 'user' || item.role === 'assistant'))
            .map((item) => ({
                role: item.role,
                content: String(item.content ?? ''),
                turnId: item.turnId ?? null,
                feedback: item.feedback ?? null,
            }))
            .slice(-MAX_MESSAGES);
    } catch {
        return [];
    }
}

/**
 * @param {AgentMessage[]} messages
 */
export function saveAgentThread(messages) {
    if (typeof window === 'undefined') {
        return;
    }

    const trimmed = (Array.isArray(messages) ? messages : [])
        .filter((item) => item && (item.role === 'user' || item.role === 'assistant'))
        .slice(-MAX_MESSAGES)
        .map((item) => ({
            role: item.role,
            content: String(item.content ?? ''),
            turnId: item.turnId ?? null,
            feedback: item.feedback ?? null,
        }));

    if (trimmed.length === 0) {
        window.localStorage.removeItem(STORAGE_KEY);

        return;
    }

    window.localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
            messages: trimmed,
            updated_at: new Date().toISOString(),
        }),
    );
}

export function clearAgentThread() {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(STORAGE_KEY);
}

export function hasAgentThread() {
    return loadAgentThread().length > 0;
}
