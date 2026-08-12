export const TRANSPORT_DOCUMENT_TYPES = ['waybill', 'etrn', 'cmr'];

export const PAPER_TRANSPORT_DOCUMENT_TYPES = ['waybill', 'cmr'];

export const EPD_DOCUMENT_TYPES = ['etrn', 'expedition_receipt'];

export const TRANSPORT_DOCUMENT_LABEL = 'ТН / CMR / ТСД';

export const ETRN_DOCUMENT_LABEL = 'ЭТрН';

export const EXPEDITION_RECEIPT_LABEL = 'Экспедиторская расписка';

export function isTransportDocumentType(type) {
    return TRANSPORT_DOCUMENT_TYPES.includes(String(type ?? ''));
}

export function isPaperTransportDocumentType(type) {
    return PAPER_TRANSPORT_DOCUMENT_TYPES.includes(String(type ?? ''));
}

export function isEpdDocumentType(type) {
    return EPD_DOCUMENT_TYPES.includes(String(type ?? ''));
}

export function transportDocumentLabel(type) {
    const normalized = String(type ?? '');

    if (isPaperTransportDocumentType(normalized)) {
        return TRANSPORT_DOCUMENT_LABEL;
    }

    if (normalized === 'etrn') {
        return ETRN_DOCUMENT_LABEL;
    }

    if (normalized === 'expedition_receipt') {
        return EXPEDITION_RECEIPT_LABEL;
    }

    return null;
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
    const withoutTransport = list.filter((opt) => !isTransportDocumentType(opt.value) && !isEpdDocumentType(opt.value));
    const hasTransport = list.some((opt) => isTransportDocumentType(opt.value) || isEpdDocumentType(opt.value));

    if (!hasTransport) {
        return list;
    }

    return [
        ...withoutTransport,
        { value: 'waybill', label: TRANSPORT_DOCUMENT_LABEL },
        { value: 'etrn', label: ETRN_DOCUMENT_LABEL },
        { value: 'expedition_receipt', label: EXPEDITION_RECEIPT_LABEL },
    ];
}

export const TRANSPORT_SUBTYPE_OPTIONS = [
    { value: 'waybill', label: 'Бумажная ТН' },
    { value: 'etrn', label: ETRN_DOCUMENT_LABEL },
    { value: 'cmr', label: 'CMR' },
];
