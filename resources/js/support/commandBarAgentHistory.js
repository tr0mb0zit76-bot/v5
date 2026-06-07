const STORAGE_KEY = 'crm_command_bar_agent_thread_v1';
const EXTENDED_STORAGE_KEY = 'crm_command_bar_extended_memory_v1';

const DEFAULT_LIMITS = {
    storage: 40,
    request: 20,
    llm: 10,
    storage_extended: 80,
    request_extended: 40,
    llm_extended: 20,
    can_extend: true,
};

/**
 * @typedef {{ role: string, content: string, turnId?: string|null, feedback?: string|null }} AgentMessage
 */

/**
 * @returns {typeof DEFAULT_LIMITS}
 */
export function resolveAgentHistoryLimits(pageProps) {
    const fromPage = pageProps?.ai_command_bar_history;

    if (!fromPage || typeof fromPage !== 'object') {
        return { ...DEFAULT_LIMITS };
    }

    return {
        storage: Number(fromPage.storage) || DEFAULT_LIMITS.storage,
        request: Number(fromPage.request) || DEFAULT_LIMITS.request,
        llm: Number(fromPage.llm) || DEFAULT_LIMITS.llm,
        storage_extended: Number(fromPage.storage_extended) || DEFAULT_LIMITS.storage_extended,
        request_extended: Number(fromPage.request_extended) || DEFAULT_LIMITS.request_extended,
        llm_extended: Number(fromPage.llm_extended) || DEFAULT_LIMITS.llm_extended,
        can_extend: fromPage.can_extend !== false,
    };
}

export function isAgentExtendedMemoryEnabled() {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(EXTENDED_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export function setAgentExtendedMemoryEnabled(enabled) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        if (enabled) {
            window.localStorage.setItem(EXTENDED_STORAGE_KEY, '1');
        } else {
            window.localStorage.removeItem(EXTENDED_STORAGE_KEY);
        }
    } catch {
        // ignore quota / private mode
    }
}

/**
 * @param {typeof DEFAULT_LIMITS} limits
 * @param {boolean} extended
 */
export function agentStorageLimit(limits, extended) {
    return extended ? limits.storage_extended : limits.storage;
}

/**
 * @param {typeof DEFAULT_LIMITS} limits
 * @param {boolean} extended
 */
export function agentRequestLimit(limits, extended) {
    return extended ? limits.request_extended : limits.request;
}

/**
 * @param {AgentMessage[]} messages
 * @param {number} maxMessages
 * @returns {AgentMessage[]}
 */
function trimMessages(messages, maxMessages) {
    return (Array.isArray(messages) ? messages : [])
        .filter((item) => item && (item.role === 'user' || item.role === 'assistant'))
        .slice(-maxMessages)
        .map((item) => ({
            role: item.role,
            content: String(item.content ?? ''),
            turnId: item.turnId ?? null,
            feedback: item.feedback ?? null,
        }));
}

/**
 * @param {typeof DEFAULT_LIMITS} limits
 * @param {boolean} extended
 * @returns {AgentMessage[]}
 */
export function loadAgentThread(limits = DEFAULT_LIMITS, extended = false) {
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

        return trimMessages(messages, agentStorageLimit(limits, extended));
    } catch {
        return [];
    }
}

/**
 * @param {AgentMessage[]} messages
 * @param {typeof DEFAULT_LIMITS} limits
 * @param {boolean} extended
 */
export function saveAgentThread(messages, limits = DEFAULT_LIMITS, extended = false) {
    if (typeof window === 'undefined') {
        return;
    }

    const trimmed = trimMessages(messages, agentStorageLimit(limits, extended));

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

/**
 * @param {typeof DEFAULT_LIMITS} limits
 * @param {boolean} extended
 */
export function hasAgentThread(limits = DEFAULT_LIMITS, extended = false) {
    return loadAgentThread(limits, extended).length > 0;
}

/**
 * @param {AgentMessage[]} messages
 * @param {typeof DEFAULT_LIMITS} limits
 * @param {boolean} extended
 * @returns {AgentMessage[]}
 */
export function historyForAgentRequest(messages, limits = DEFAULT_LIMITS, extended = false) {
    return trimMessages(messages, agentRequestLimit(limits, extended)).map((item) => ({
        role: item.role,
        content: item.content,
    }));
}
