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
            ?? document.contractor_id
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

function buildClosingContext(baseRow, documentType) {
    return {
        party: baseRow.party,
        document_type: documentType,
        slot_key: baseRow.slot_key ?? '',
        contractor_id: Number(baseRow.contractor_id ?? 0),
    };
}

function isClosingTypeFulfilled(context, signedDocuments, edoAcknowledgements) {
    const matchedDocument = findSignedDocumentForClosingType(signedDocuments, context);

    if (matchedDocument) {
        return true;
    }

    const edoAcknowledgement = findEdoAcknowledgement(edoAcknowledgements, context);

    return Boolean(edoAcknowledgement?.received_via_edo && edoAcknowledgement?.document_number);
}

function resolveClosingTypesToShow(baseRow, signedDocuments, edoAcknowledgements) {
    const updFulfilled = isClosingTypeFulfilled(
        buildClosingContext(baseRow, 'upd'),
        signedDocuments,
        edoAcknowledgements,
    );
    const invoiceFulfilled = isClosingTypeFulfilled(
        buildClosingContext(baseRow, 'invoice_factura'),
        signedDocuments,
        edoAcknowledgements,
    );
    const actFulfilled = isClosingTypeFulfilled(
        buildClosingContext(baseRow, 'act'),
        signedDocuments,
        edoAcknowledgements,
    );
    const pairFulfilled = invoiceFulfilled && actFulfilled;

    if (updFulfilled) {
        return CLOSING_TYPE_ROWS.filter((row) => row.type === 'upd');
    }

    if (pairFulfilled) {
        return CLOSING_TYPE_ROWS.filter((row) => row.type !== 'upd');
    }

    if (invoiceFulfilled || actFulfilled) {
        return CLOSING_TYPE_ROWS.filter((row) => row.type !== 'upd');
    }

    return CLOSING_TYPE_ROWS;
}

function buildClosingTypeRow(baseRow, closingType, signedDocuments, edoAcknowledgements) {
    const context = buildClosingContext(baseRow, closingType.type);

    const matchedDocument = findSignedDocumentForClosingType(signedDocuments, context);
    const edoAcknowledgement = findEdoAcknowledgement(edoAcknowledgements, context);
    const edoActive = Boolean(edoAcknowledgement?.received_via_edo && edoAcknowledgement?.document_number);

    if (matchedDocument) {
        const hasScan = Boolean(matchedDocument.uploaded_file_preview_url);

        return {
            ...matchedDocument,
            requirement_key: baseRow.requirement_key,
            requirement_label: baseRow.requirement_label,
            slot_kind: baseRow.slot_kind,
            slot_key: baseRow.slot_key,
            contractor_id: baseRow.contractor_id,
            counterparty_label: baseRow.counterparty_label,
            type: closingType.type,
            type_label: closingType.label,
            checklist_completed: true,
            is_placeholder: false,
            is_closing_edo_row: false,
            closing_edo_controls: !hasScan,
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
        type_label: closingType.label,
        number: edoAcknowledgement?.document_number ?? null,
        document_date: edoAcknowledgement?.document_date ?? null,
        original_name: null,
        uploaded_file_preview_url: null,
        checklist_completed: edoActive,
        is_placeholder: true,
        is_closing_edo_row: true,
        closing_edo_controls: true,
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

        const closingTypes = resolveClosingTypesToShow(row, signedDocuments, edoAcknowledgements);

        for (const closingType of closingTypes) {
            expanded.push(buildClosingTypeRow(row, closingType, signedDocuments, edoAcknowledgements));
        }
    }

    return expanded;
}

export function isClosingEdoRow(row) {
    return Boolean(row?.is_closing_edo_row);
}

export function rowHasClosingEdoControls(row) {
    return Boolean(row?.closing_edo_controls ?? row?.is_closing_edo_row);
}

/** Подпись чекбокса/статуса ЭДО: заказчику отправляем, от перевозчика получаем. */
export function edoAcknowledgementToggleLabel(party) {
    if (party === 'customer') {
        return 'Отправлен';
    }

    if (party === 'carrier' || party === 'contractor') {
        return 'Получен';
    }

    return 'ЭДО';
}
