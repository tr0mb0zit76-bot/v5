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

export function formatUnreadSendersHint(conversations, conversationTitle) {
    const withUnread = (conversations ?? []).filter(
        (conversation) => Number(conversation?.unread_count) > 0,
    );

    if (withUnread.length === 0) {
        return '';
    }

    const senders = withUnread.map((conversation) => {
        if (conversation.type === 'direct') {
            return conversation.other_user?.name ?? conversationTitle(conversation);
        }

        const authorName = conversation.last_message?.author_name;
        const chatTitle = conversationTitle(conversation);

        if (authorName) {
            return `${authorName} · ${chatTitle}`;
        }

        return chatTitle;
    }).filter(Boolean);

    const unique = [...new Set(senders)];

    if (unique.length === 0) {
        return '';
    }

    if (unique.length === 1) {
        return `Новое сообщение от ${unique[0]}`;
    }

    return `Новые сообщения: ${unique.join(', ')}`;
}
