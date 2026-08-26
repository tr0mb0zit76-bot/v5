const EPD_SLOT_KINDS = new Set(['etrn', 'expedition_receipt']);

const EPD_TYPE_LABELS = {
    etrn: 'ЭТрН',
    expedition_receipt: 'Экспедиторская расписка',
};

function isEpdSlotKind(slotKind) {
    return EPD_SLOT_KINDS.has(String(slotKind ?? ''));
}

function findEdoAcknowledgement(edoAcknowledgements, context) {
    return (Array.isArray(edoAcknowledgements) ? edoAcknowledgements : []).find((row) => (
        row?.party === context.party
        && row?.document_type === context.document_type
        && String(row?.slot_key ?? '') === String(context.slot_key ?? '')
        && Number(row?.contractor_id ?? 0) === Number(context.contractor_id ?? 0)
    )) ?? null;
}

function findSignedDocumentForEpdType(signedDocuments, context) {
    return (Array.isArray(signedDocuments) ? signedDocuments : []).find((document) => {
        if (!document || document.type !== context.document_type) {
            return false;
        }

        const direction = String(document.direction ?? document.metadata?.direction ?? 'incoming');
        if (direction === 'outgoing') {
            return false;
        }

        const party = document.party ?? document.metadata?.party ?? 'internal';

        if (party !== context.party) {
            return false;
        }

        return ['sent', 'signed'].includes(String(document.status ?? ''));
    }) ?? null;
}

function buildEpdAckRow(baseRow, signedDocuments, edoAcknowledgements) {
    const documentType = String(baseRow.slot_kind ?? baseRow.type ?? '');
    const context = {
        party: baseRow.party,
        document_type: documentType,
        slot_key: baseRow.slot_key ?? documentType,
        contractor_id: Number(baseRow.contractor_id ?? 0),
    };

    const matchedDocument = findSignedDocumentForEpdType(signedDocuments, context);
    const edoAcknowledgement = findEdoAcknowledgement(edoAcknowledgements, context);
    const edoActive = Boolean(edoAcknowledgement?.received_via_edo && edoAcknowledgement?.document_number);
    const typeLabel = EPD_TYPE_LABELS[documentType] ?? documentType;

    if (matchedDocument) {
        const hasScan = Boolean(matchedDocument.uploaded_file_preview_url);

        return {
            ...matchedDocument,
            requirement_key: baseRow.requirement_key ?? baseRow.key,
            requirement_label: baseRow.requirement_label ?? baseRow.label,
            slot_kind: baseRow.slot_kind,
            slot_key: baseRow.slot_key,
            contractor_id: baseRow.contractor_id,
            counterparty_label: baseRow.counterparty_label,
            type: documentType,
            type_label: typeLabel,
            checklist_completed: true,
            is_placeholder: false,
            is_epd_edo_row: false,
            closing_edo_controls: !hasScan,
            edo_acknowledgement: edoAcknowledgement,
            edo_toggle_label: 'Отправлен',
            is_required: baseRow.is_required ?? true,
        };
    }

    return {
        _localKey: `epd-edo-${baseRow.requirement_key ?? baseRow.key ?? documentType}`,
        requirement_key: baseRow.requirement_key ?? baseRow.key,
        requirement_label: baseRow.requirement_label ?? baseRow.label,
        slot_kind: baseRow.slot_kind,
        slot_key: baseRow.slot_key,
        contractor_id: baseRow.contractor_id,
        counterparty_label: baseRow.counterparty_label,
        party: baseRow.party,
        type: documentType,
        type_label: typeLabel,
        number: edoAcknowledgement?.document_number ?? null,
        document_date: edoAcknowledgement?.document_date ?? null,
        original_name: null,
        uploaded_file_preview_url: null,
        // Бумажная ТН может закрыть ЭТрН через чек-лист (взаимозамена).
        checklist_completed: edoActive || Boolean(baseRow.checklist_completed),
        is_placeholder: true,
        is_epd_edo_row: true,
        closing_edo_controls: true,
        edo_acknowledgement: edoAcknowledgement,
        edo_toggle_label: 'Отправлен',
        is_required: baseRow.is_required ?? true,
        fulfilled_by_alternative: baseRow.fulfilled_by_alternative ?? null,
    };
}

/**
 * Expand ЭТрН / expedition_receipt checklist placeholders with EDO «отправлено» controls.
 *
 * @param {Array<Record<string, unknown>>} rows
 * @param {Array<Record<string, unknown>>} signedDocuments
 * @param {Array<Record<string, unknown>>} edoAcknowledgements
 */
export function expandEpdRowsForAck(rows, signedDocuments = [], edoAcknowledgements = []) {
    return (Array.isArray(rows) ? rows : []).map((row) => {
        if (!isEpdSlotKind(row.slot_kind)) {
            return row;
        }

        return buildEpdAckRow(row, signedDocuments, edoAcknowledgements);
    });
}

export function isEpdEdoRow(row) {
    return Boolean(row?.is_epd_edo_row);
}

export function rowHasEpdEdoControls(row) {
    return Boolean(row?.is_epd_edo_row || (isEpdSlotKind(row?.slot_kind) && row?.closing_edo_controls));
}
