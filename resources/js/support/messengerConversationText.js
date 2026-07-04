function normalizePreviewBody(body) {
    return String(body ?? '').replace(/\s+/g, ' ').trim();
}

export function formatConversationPreview(conversation, currentUserId = null) {
    const last = conversation?.last_message;
    const body = last?.body;

    if (!body) {
        return 'Сообщений пока нет';
    }

    const text = normalizePreviewBody(body);
    const authorName = last?.author_name;
    const isOwn = currentUserId !== null
        && Number(last?.user_id) === Number(currentUserId);

    if (isOwn) {
        return `Вы: ${text}`;
    }

    if (authorName) {
        return `${authorName}: ${text}`;
    }

    return text;
}

export function buildDirectUnreadByUserId(conversations) {
    const map = new Map();

    for (const conversation of conversations ?? []) {
        if (conversation?.type !== 'direct') {
            continue;
        }

        const otherUserId = Number(conversation.other_user?.id ?? 0);
        const unreadCount = Number(conversation.unread_count ?? 0);

        if (otherUserId > 0 && unreadCount > 0) {
            map.set(otherUserId, unreadCount);
        }
    }

    return map;
}
