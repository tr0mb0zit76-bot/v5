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
