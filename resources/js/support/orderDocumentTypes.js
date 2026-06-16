export const TRANSPORT_DOCUMENT_TYPES = ['waybill', 'etrn', 'cmr'];

export const TRANSPORT_DOCUMENT_LABEL = 'ТН / ЭТрН / CMR / ТСД';

export function isTransportDocumentType(type) {
    return TRANSPORT_DOCUMENT_TYPES.includes(String(type ?? ''));
}

export function transportDocumentLabel(type) {
    return isTransportDocumentType(type) ? TRANSPORT_DOCUMENT_LABEL : null;
}

export function documentTypeDisplayLabel(type, typeLabels = new Map()) {
    const transport = transportDocumentLabel(type);

    if (transport !== null) {
        return transport;
    }

    return typeLabels.get(String(type ?? '')) ?? String(type ?? '—');
}

/**
 * @param {Array<{value: string, label: string}>} options
 * @returns {Array<{value: string, label: string}>}
 */
export function withTransportSubtypeOptions(options) {
    const list = Array.isArray(options) ? [...options] : [];
    const withoutTransport = list.filter((opt) => !isTransportDocumentType(opt.value));
    const hasTransport = list.some((opt) => isTransportDocumentType(opt.value));

    if (!hasTransport) {
        return list;
    }

    return [
        ...withoutTransport,
        { value: 'waybill', label: TRANSPORT_DOCUMENT_LABEL },
    ];
}

export const TRANSPORT_SUBTYPE_OPTIONS = [
    { value: 'waybill', label: 'Бумажная ТН' },
    { value: 'etrn', label: 'ЭТрН' },
    { value: 'cmr', label: 'CMR' },
];
