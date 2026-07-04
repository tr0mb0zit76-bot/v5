const ORDER_PUSH_KINDS = new Set([
    'order_document_approval',
    'order_document_approved',
    'order_closing_documents_required',
    'contractor_limit_approval',
]);

const ACCOUNTING_PUSH_KINDS = new Set([
    'order_closing_documents_required',
]);

export function resolveMobilePushNavigation(data = {}) {
    const kind = String(data.kind ?? '');
    const conversationId = Number(data.conversation_id ?? 0);
    const orderId = Number(data.order_id ?? 0);
    const actionUrl = String(data.action_url ?? '');

    if (kind === 'chat_message' && conversationId > 0) {
        return {
            tab: 'chats',
            conversationId,
        };
    }

    if (ORDER_PUSH_KINDS.has(kind) && orderId > 0) {
        return {
            tab: ACCOUNTING_PUSH_KINDS.has(kind) ? 'documents' : 'orders',
            orderId,
            actionUrl: actionUrl || null,
        };
    }

    if (actionUrl !== '') {
        return {
            actionUrl,
        };
    }

    return null;
}

export function dispatchMobilePushNavigation(data = {}) {
    const target = resolveMobilePushNavigation(data);

    if (target === null) {
        return false;
    }

    window.dispatchEvent(new CustomEvent('crm-mobile-navigate', {
        detail: target,
    }));

    return true;
}
