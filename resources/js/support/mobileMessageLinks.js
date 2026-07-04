const ENTITY_KIND_LABELS = {
    document: 'Документ',
    order: 'Заказ',
    lead: 'Лид',
    contractor: 'Контрагент',
    task: 'Задача',
};

export function entityKindLabel(kind) {
    return ENTITY_KIND_LABELS[kind] ?? 'Сущность';
}

export function splitMessageSegments(body) {
    const text = String(body ?? '');
    if (text === '') {
        return [];
    }

    const pattern = /(https?:\/\/[^\s]+)/g;
    const segments = [];
    let lastIndex = 0;
    let match = pattern.exec(text);

    while (match !== null) {
        if (match.index > lastIndex) {
            segments.push({ type: 'text', value: text.slice(lastIndex, match.index) });
        }

        segments.push({ type: 'url', value: match[0] });
        lastIndex = match.index + match[0].length;
        match = pattern.exec(text);
    }

    if (lastIndex < text.length) {
        segments.push({ type: 'text', value: text.slice(lastIndex) });
    }

    return segments.length > 0 ? segments : [{ type: 'text', value: text }];
}

function idFromPath(path, segment) {
    const match = String(path ?? '').match(new RegExp(`/${segment}/(\\d+)`));

    return match ? match[1] : null;
}

export function previewForCrmUrl(url) {
    const value = String(url ?? '');

    try {
        const parsed = new URL(value);
        const path = parsed.pathname;
        const search = parsed.search;

        const orderId = idFromPath(path, 'orders');
        if (orderId !== null) {
            const documentsTab = search.includes('tab=documents');

            return {
                kind: documentsTab ? 'document' : 'order',
                label: entityKindLabel(documentsTab ? 'document' : 'order'),
                title: documentsTab ? `Документы · заказ #${orderId}` : `Заказ #${orderId}`,
                subtitle: documentsTab ? 'Вкладка документов заказа' : 'Карточка заказа в CRM',
            };
        }

        const leadId = idFromPath(path, 'leads');
        if (leadId !== null) {
            return {
                kind: 'lead',
                label: entityKindLabel('lead'),
                title: `Лид #${leadId}`,
                subtitle: 'Карточка лида в CRM',
            };
        }

        const contractorId = idFromPath(path, 'contractors');
        if (contractorId !== null) {
            return {
                kind: 'contractor',
                label: entityKindLabel('contractor'),
                title: `Контрагент #${contractorId}`,
                subtitle: 'Карточка контрагента',
            };
        }

        const taskId = idFromPath(path, 'tasks');
        if (taskId !== null) {
            return {
                kind: 'task',
                label: entityKindLabel('task'),
                title: `Задача #${taskId}`,
                subtitle: 'Карточка задачи в CRM',
            };
        }
    } catch {
        return null;
    }

    return null;
}
