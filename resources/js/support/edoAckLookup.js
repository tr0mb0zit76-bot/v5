/**
 * Общий lookup ЭДО-отметок и входящих сканов для closing / request / EPD строк.
 */

/**
 * @param {Array<Record<string, unknown>>} edoAcknowledgements
 * @param {{ party?: string, document_type?: string, slot_key?: string, contractor_id?: number }} context
 */
export function findEdoAcknowledgement(edoAcknowledgements, context) {
    return (Array.isArray(edoAcknowledgements) ? edoAcknowledgements : []).find((row) => (
        row?.party === context.party
        && row?.document_type === context.document_type
        && String(row?.slot_key ?? '') === String(context.slot_key ?? '')
        && Number(row?.contractor_id ?? 0) === Number(context.contractor_id ?? 0)
    )) ?? null;
}

/**
 * Входящий sent/signed файл нужного типа.
 * @param {Array<Record<string, unknown>>} signedDocuments
 * @param {{ party?: string, document_type?: string, slot_key?: string, contractor_id?: number }} context
 * @param {{ matchContractorAndSlot?: boolean }} [options]
 */
export function findIncomingSignedDocument(signedDocuments, context, options = {}) {
    const matchContractorAndSlot = options.matchContractorAndSlot !== false;

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

        if (matchContractorAndSlot) {
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
        }

        return ['sent', 'signed'].includes(String(document.status ?? ''));
    }) ?? null;
}

/**
 * @param {Record<string, unknown>|null|undefined} acknowledgement
 */
export function isEdoAcknowledgementActive(acknowledgement) {
    return Boolean(acknowledgement?.received_via_edo && acknowledgement?.document_number);
}
