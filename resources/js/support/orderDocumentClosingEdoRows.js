const CLOSING_SLOT_KINDS = new Set(['customer_closing', 'carrier_closing', 'contractor_closing']);

const CLOSING_TYPE_ROWS = [
    { type: 'upd', label: 'УПД' },
    { type: 'invoice_factura', label: 'Счёт-фактура' },
    { type: 'act', label: 'Акт' },
];

function isClosingSlotKind(slotKind) {
    return CLOSING_SLOT_KINDS.has(String(slotKind ?? ''));
}

function findEdoAcknowledgement(edoAcknowledgements, context) {
    return (Array.isArray(edoAcknowledgements) ? edoAcknowledgements : []).find((row) => (
        row?.party === context.party
        && row?.document_type === context.document_type
        && String(row?.slot_key ?? '') === String(context.slot_key ?? '')
        && Number(row?.contractor_id ?? 0) === Number(context.contractor_id ?? 0)
    )) ?? null;
}

function findSignedDocumentForClosingType(signedDocuments, context) {
    return (Array.isArray(signedDocuments) ? signedDocuments : []).find((document) => {
        if (!document || document.type !== context.document_type) {
            return false;
        }

        const party = document.party ?? document.metadata?.party ?? 'internal';

        if (party !== context.party) {
            return false;
        }

        const contractorId = Number(
            document.carrier_contractor_id
            ?? document.metadata?.carrier_contractor_id
            ?? document.metadata?.contractor_id
            ?? 0,
        );
        const slotKey = String(document.requirement_slot_key ?? document.metadata?.requirement_slot_key ?? '');

        if (Number(context.contractor_id ?? 0) > 0 && contractorId !== Number(context.contractor_id ?? 0)) {
            return false;
        }

        if (String(context.slot_key ?? '') !== '' && slotKey !== '' && slotKey !== String(context.slot_key ?? '')) {
            return false;
        }

        return ['sent', 'signed'].includes(String(document.status ?? ''));
    }) ?? null;
}

function buildClosingTypeRow(baseRow, closingType, signedDocuments, edoAcknowledgements) {
    const context = {
        party: baseRow.party,
        document_type: closingType.type,
        slot_key: baseRow.slot_key ?? '',
        contractor_id: Number(baseRow.contractor_id ?? 0),
    };

    const matchedDocument = findSignedDocumentForClosingType(signedDocuments, context);
    const edoAcknowledgement = findEdoAcknowledgement(edoAcknowledgements, context);
    const edoActive = Boolean(edoAcknowledgement?.received_via_edo && edoAcknowledgement?.document_number);

    const typeLabelSuffix = baseRow.counterparty_label || baseRow.requirement_label || '';
    const typeLabel = typeLabelSuffix
        ? `${closingType.label} · ${typeLabelSuffix}`
        : closingType.label;

    if (matchedDocument) {
        return {
            ...matchedDocument,
            requirement_key: baseRow.requirement_key,
            requirement_label: baseRow.requirement_label,
            slot_kind: baseRow.slot_kind,
            slot_key: baseRow.slot_key,
            contractor_id: baseRow.contractor_id,
            counterparty_label: baseRow.counterparty_label,
            type: closingType.type,
            type_label: typeLabel,
            checklist_completed: true,
            is_placeholder: false,
            is_closing_edo_row: false,
            edo_acknowledgement: edoAcknowledgement,
            closing_package_key: baseRow.requirement_key,
        };
    }

    return {
        _localKey: `closing-edo-${baseRow.requirement_key}-${closingType.type}`,
        requirement_key: baseRow.requirement_key,
        requirement_label: baseRow.requirement_label,
        slot_kind: baseRow.slot_kind,
        slot_key: baseRow.slot_key,
        contractor_id: baseRow.contractor_id,
        counterparty_label: baseRow.counterparty_label,
        party: baseRow.party,
        type: closingType.type,
        type_label: typeLabel,
        number: edoAcknowledgement?.document_number ?? null,
        document_date: edoAcknowledgement?.document_date ?? null,
        original_name: null,
        uploaded_file_preview_url: null,
        checklist_completed: edoActive,
        is_placeholder: true,
        is_closing_edo_row: true,
        edo_acknowledgement: edoAcknowledgement,
        closing_package_key: baseRow.requirement_key,
    };
}

/**
 * @param {Array<Record<string, unknown>>} rows
 * @param {Array<Record<string, unknown>>} signedDocuments
 * @param {Array<Record<string, unknown>>} edoAcknowledgements
 */
export function expandClosingRowsForEdo(rows, signedDocuments = [], edoAcknowledgements = []) {
    const expanded = [];

    for (const row of rows) {
        if (!isClosingSlotKind(row.slot_kind)) {
            expanded.push(row);
            continue;
        }

        for (const closingType of CLOSING_TYPE_ROWS) {
            expanded.push(buildClosingTypeRow(row, closingType, signedDocuments, edoAcknowledgements));
        }
    }

    return expanded;
}

export function isClosingEdoRow(row) {
    return Boolean(row?.is_closing_edo_row);
}
