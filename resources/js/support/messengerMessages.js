export function createClientMessageId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
}

export function appendUniqueMessage(messages, message) {
    if (!message) {
        return messages;
    }

    const messageId = Number(message.id);
    const clientMessageId = String(message.client_message_id ?? '');
    const exists = messages.some((candidate) =>
        Number(candidate.id) === messageId
        || (
            clientMessageId !== ''
            && String(candidate.client_message_id ?? '') === clientMessageId
        ),
    );

    return exists ? messages : [...messages, message];
}

export function formatMessengerFileSize(bytes) {
    const size = Number(bytes ?? 0);

    if (size < 1024) {
        return `${size} Б`;
    }

    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} КБ`;
    }

    return `${(size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1)} МБ`;
}
