const ORDER_PUSH_KINDS = new Set([
    'order_document_approval',
    'order_document_approved',
    'order_closing_documents_required',
    'contractor_limit_approval',
]);

const ACCOUNTING_PUSH_KINDS = new Set([
    'order_closing_documents_required',
]);

export function parseOrderIdFromActionUrl(actionUrl) {
    const match = String(actionUrl ?? '').match(/\/orders\/(\d+)/);

    return match ? Number(match[1]) : 0;
}

export function resolveMobilePushNavigation(data = {}) {
    const kind = String(data.kind ?? '');
    let conversationId = Number(data.conversation_id ?? 0);
    let orderId = Number(data.order_id ?? 0);
    const actionUrl = String(data.action_url ?? '');

    if (orderId <= 0 && actionUrl !== '') {
        orderId = parseOrderIdFromActionUrl(actionUrl);
    }

    if (kind === 'chat_message' && conversationId > 0) {
        return {
            tab: 'chats',
            conversationId,
        };
    }

    if (ORDER_PUSH_KINDS.has(kind) && orderId > 0) {
        const tab = ACCOUNTING_PUSH_KINDS.has(kind) ? 'documents' : 'orders';

        return {
            tab,
            orderId,
            highlightType: tab === 'documents' ? 'attention-order' : 'order',
            actionUrl: actionUrl || null,
        };
    }

    if (actionUrl !== '') {
        const parsedOrderId = parseOrderIdFromActionUrl(actionUrl);

        if (parsedOrderId > 0 && actionUrl.includes('/orders/')) {
            const tab = actionUrl.includes('tab=documents') ? 'documents' : 'orders';

            return {
                tab,
                orderId: parsedOrderId,
                highlightType: tab === 'documents' ? 'attention-order' : 'order',
                actionUrl,
            };
        }

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
