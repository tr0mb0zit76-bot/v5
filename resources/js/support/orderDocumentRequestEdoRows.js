import {
    findEdoAcknowledgement,
    findIncomingSignedDocument,
    isEdoAcknowledgementActive,
} from '@/support/edoAckLookup.js';

const REQUEST_SLOT_KINDS = new Set(['customer_request', 'carrier_request', 'contractor_request']);

const REQUEST_TYPE_LABELS = {
    request: 'Заявка',
    contract_request: 'Договор-заявка',
};

function isRequestSlotKind(slotKind) {
    return REQUEST_SLOT_KINDS.has(String(slotKind ?? ''));
}

function findSignedDocumentForRequestType(signedDocuments, context) {
    return findIncomingSignedDocument(signedDocuments, context, { matchContractorAndSlot: true });
}

function resolveRequestDocumentType(baseRow) {
    const accepted = Array.isArray(baseRow.accepted_types) ? baseRow.accepted_types : [];
    if (accepted.includes('request')) {
        return 'request';
    }
    if (accepted.includes('contract_request')) {
        return 'contract_request';
    }

    const current = String(baseRow.type ?? '');
    if (current === 'request' || current === 'contract_request') {
        return current;
    }

    return 'request';
}

function buildRequestEdoRow(baseRow, signedDocuments, edoAcknowledgements) {
    const documentType = resolveRequestDocumentType(baseRow);
    const context = {
        party: baseRow.party,
        document_type: documentType,
        slot_key: baseRow.slot_key ?? '',
        contractor_id: Number(baseRow.contractor_id ?? 0),
    };

    const matchedDocument = findSignedDocumentForRequestType(signedDocuments, context)
        ?? (documentType === 'request'
            ? findSignedDocumentForRequestType(signedDocuments, { ...context, document_type: 'contract_request' })
            : null);
    const resolvedType = matchedDocument?.type ?? documentType;
    const edoAcknowledgement = findEdoAcknowledgement(edoAcknowledgements, {
        ...context,
        document_type: resolvedType === 'contract_request' ? 'contract_request' : 'request',
    }) ?? findEdoAcknowledgement(edoAcknowledgements, { ...context, document_type: 'request' });
    const edoActive = isEdoAcknowledgementActive(edoAcknowledgement);
    const expectsEdo = Boolean(baseRow.expects_edo);
    const typeLabel = REQUEST_TYPE_LABELS[resolvedType] ?? baseRow.type_label ?? resolvedType;

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
            type: resolvedType,
            type_label: typeLabel,
            checklist_completed: true,
            is_placeholder: false,
            is_request_edo_row: false,
            closing_edo_controls: !hasScan || expectsEdo,
            expects_edo: expectsEdo,
            edo_scan_without_ack: hasScan && expectsEdo && !edoActive,
            edo_acknowledgement: edoAcknowledgement,
            is_required: baseRow.is_required ?? true,
        };
    }

    return {
        _localKey: `request-edo-${baseRow.requirement_key ?? baseRow.key ?? documentType}`,
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
        checklist_completed: edoActive || Boolean(baseRow.checklist_completed),
        is_placeholder: true,
        is_request_edo_row: true,
        closing_edo_controls: true,
        expects_edo: expectsEdo,
        edo_scan_without_ack: false,
        edo_acknowledgement: edoAcknowledgement,
        is_required: baseRow.is_required ?? true,
        fulfilled_by_alternative: baseRow.fulfilled_by_alternative ?? null,
    };
}

/**
 * Expand request checklist placeholders with EDO controls (зеркало closing/EPD).
 *
 * @param {Array<Record<string, unknown>>} rows
 * @param {Array<Record<string, unknown>>} signedDocuments
 * @param {Array<Record<string, unknown>>} edoAcknowledgements
 */
export function expandRequestRowsForEdo(rows, signedDocuments = [], edoAcknowledgements = []) {
    return (Array.isArray(rows) ? rows : []).map((row) => {
        if (!isRequestSlotKind(row.slot_kind)) {
            return row;
        }

        return buildRequestEdoRow(row, signedDocuments, edoAcknowledgements);
    });
}

export function isRequestEdoRow(row) {
    return Boolean(row?.is_request_edo_row);
}

export function rowHasRequestEdoControls(row) {
    return Boolean(row?.is_request_edo_row || (isRequestSlotKind(row?.slot_kind) && row?.closing_edo_controls));
}
