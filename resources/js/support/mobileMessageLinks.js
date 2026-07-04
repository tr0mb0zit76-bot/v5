const ENTITY_KIND_LABELS = {
    document: 'Документ',
    order: 'Заказ',
    lead: 'Лид',
    contractor: 'Контрагент',
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

export function previewForCrmUrl(url) {
    const value = String(url ?? '');

    try {
        const parsed = new URL(value);
        const path = parsed.pathname;

        if (path.includes('/orders/') && path.includes('/edit')) {
            return { kind: 'order', label: 'Карточка заказа' };
        }

        if (path.includes('/leads/')) {
            return { kind: 'lead', label: 'Карточка лида' };
        }

        if (path.includes('/contractors/')) {
            return { kind: 'contractor', label: 'Контрагент' };
        }

        if (path.includes('tab=documents')) {
            return { kind: 'document', label: 'Документы заказа' };
        }
    } catch {
        return null;
    }

    return null;
}
