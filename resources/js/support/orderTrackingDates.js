import { BASIS_SUMMARY_PHRASE, ensureInstallmentSchedule } from '@/support/orderPaymentScheduleUi.js';

const TRACK_RECEIVED_BASIS = new Set(['ottn', 'fttn_receipt']);

const TRACK_RECEIVED_SLOT_KINDS = new Set([
    'customer_request',
    'customer_closing',
    'carrier_request',
    'carrier_closing',
]);

export function installmentNeedsTrackReceived(basis) {
    return TRACK_RECEIVED_BASIS.has(String(basis ?? '').toLowerCase());
}

export function scheduleNeedsTrackReceived(schedule) {
    const normalized = ensureInstallmentSchedule(schedule ?? {});

    return (normalized.installments ?? []).some((row) => installmentNeedsTrackReceived(row.basis));
}

/**
 * @param {object|null|undefined} schedule
 * @returns {{ ottn: boolean, fttn_receipt: boolean }}
 */
export function scheduleTrackBasisKinds(schedule) {
    const normalized = ensureInstallmentSchedule(schedule ?? {});
    const kinds = { ottn: false, fttn_receipt: false };

    for (const row of normalized.installments ?? []) {
        const basis = String(row.basis ?? '').toLowerCase();

        if (basis === 'ottn') {
            kinds.ottn = true;
        }

        if (basis === 'fttn_receipt') {
            kinds.fttn_receipt = true;
        }
    }

    return kinds;
}

function basisLabelsForSchedule(schedule) {
    const normalized = ensureInstallmentSchedule(schedule ?? {});
    const labels = new Set();

    for (const row of normalized.installments ?? []) {
        if (!installmentNeedsTrackReceived(row.basis)) {
            continue;
        }

        const basis = String(row.basis ?? '').toLowerCase();
        labels.add(BASIS_SUMMARY_PHRASE[basis] ?? basis);
    }

    return [...labels];
}

function slotKindFromRequirementKey(requirementKey) {
    const key = String(requirementKey ?? '').trim();

    if (key === '') {
        return null;
    }

    const separatorIndex = key.indexOf(':');

    return separatorIndex > 0 ? key.slice(0, separatorIndex) : null;
}

/**
 * @param {number|null|undefined} contractorId
 * @param {Array<object>} contractorsCosts
 * @returns {{ ottn: boolean, fttn_receipt: boolean }}
 */
function carrierTrackBasisKindsForRow(contractorId, contractorsCosts) {
    const costs = Array.isArray(contractorsCosts) ? contractorsCosts : [];
    const normalizedContractorId = Number(contractorId ?? 0);

    if (Number.isFinite(normalizedContractorId) && normalizedContractorId > 0) {
        const cost = costs.find((row) => Number(row?.contractor_id) === normalizedContractorId);

        return scheduleTrackBasisKinds(cost?.payment_schedule);
    }

    const merged = { ottn: false, fttn_receipt: false };

    for (const cost of costs) {
        const kinds = scheduleTrackBasisKinds(cost?.payment_schedule);
        merged.ottn = merged.ottn || kinds.ottn;
        merged.fttn_receipt = merged.fttn_receipt || kinds.fttn_receipt;
    }

    return merged;
}

/**
 * @param {number|null|undefined} contractorId
 * @param {Array<object>} contractorsCosts
 */
function contractorScheduleNeedsTrackReceived(contractorId, contractorsCosts) {
    const costs = Array.isArray(contractorsCosts) ? contractorsCosts : [];
    const normalizedContractorId = Number(contractorId ?? 0);

    if (Number.isFinite(normalizedContractorId) && normalizedContractorId > 0) {
        const cost = costs.find((row) => Number(row?.contractor_id) === normalizedContractorId);

        return scheduleNeedsTrackReceived(cost?.payment_schedule);
    }

    return costs.some((cost) => scheduleNeedsTrackReceived(cost?.payment_schedule));
}

/**
 * Одна дата на сторону (заказчик / перевозчик) — показываем во всех строках заявки и закрывающих,
 * если график оплаты этой стороны требует «дату получения».
 *
 * @param {{
 *   party?: string|null,
 *   slotKind?: string|null,
 *   contractorId?: number|null,
 * }} rowMeta
 * @param {{
 *   clientPaymentSchedule?: object,
 *   contractorsCosts?: Array<object>,
 * }} context
 * @returns {'track_received_date_customer'|'track_received_date_carrier'|null}
 */
export function resolveTrackFieldForRegistryRow(rowMeta, context) {
    const party = String(rowMeta.party ?? '');
    const slotKind = String(rowMeta.slotKind ?? '');

    if (!TRACK_RECEIVED_SLOT_KINDS.has(slotKind)) {
        return null;
    }

    if (party === 'customer' && scheduleNeedsTrackReceived(context.clientPaymentSchedule)) {
        return 'track_received_date_customer';
    }

    if (party === 'carrier' && contractorScheduleNeedsTrackReceived(rowMeta.contractorId, context.contractorsCosts)) {
        return 'track_received_date_carrier';
    }

    return null;
}

/**
 * @param {Array<Record<string, unknown>>} registryRows
 * @param {{
 *   clientPaymentSchedule?: object,
 *   contractorsCosts?: Array<object>,
 *   order?: object|null,
 * }} context
 * @param {Array<Record<string, unknown>>} requiredDocumentRules
 * @returns {Array<Record<string, unknown>>}
 */
export function attachTrackReceivedToRegistryRows(registryRows, context, requiredDocumentRules = []) {
    const rules = Array.isArray(requiredDocumentRules) ? requiredDocumentRules : [];
    const ruleByKey = new Map(rules.map((rule) => [String(rule.key ?? ''), rule]));
    const order = context.order ?? null;

    return (Array.isArray(registryRows) ? registryRows : []).map((row) => {
        const rule = row.requirement_key ? ruleByKey.get(String(row.requirement_key)) : null;
        const slotKind = rule?.slot_kind ?? slotKindFromRequirementKey(row.requirement_key);
        const trackField = resolveTrackFieldForRegistryRow({
            party: rule?.party ?? row.party,
            slotKind,
            contractorId: rule?.contractor_id ?? row.contractor_id ?? null,
        }, context);

        if (!trackField) {
            return row;
        }

        return {
            ...row,
            track_field: trackField,
            received_date: order?.[trackField] ?? '',
        };
    });
}

function resolveCarrierLabel(cost, performers) {
    const fromCost = String(cost?.contractor_name ?? '').trim();
    if (fromCost !== '') {
        return fromCost;
    }

    const contractorId = Number(cost?.contractor_id ?? 0);
    if (!Number.isFinite(contractorId) || contractorId <= 0) {
        return 'Перевозчик';
    }

    for (const performer of performers ?? []) {
        if (Number(performer?.contractor_id) === contractorId) {
            const name = String(performer?.contractor_name ?? '').trim();

            return name !== '' ? name : `Перевозчик #${contractorId}`;
        }
    }

    return `Перевозчик #${contractorId}`;
}

/**
 * @param {{
 *   clientPaymentSchedule?: object,
 *   contractorsCosts?: Array<object>,
 *   order?: object|null,
 *   performers?: Array<object>,
 * }} context
 * @returns {Array<{
 *   key: string,
 *   party: 'customer'|'carrier',
 *   partyLabel: string,
 *   field: string,
 *   basisLabels: string[],
 *   value: string,
 * }>}
 */
export function buildOrderTrackingDateRows(context) {
    const rows = [];
    const order = context.order ?? null;

    if (scheduleNeedsTrackReceived(context.clientPaymentSchedule)) {
        rows.push({
            key: 'customer',
            party: 'customer',
            partyLabel: 'Заказчик',
            field: 'track_received_date_customer',
            basisLabels: basisLabelsForSchedule(context.clientPaymentSchedule),
            value: order?.track_received_date_customer ?? '',
        });
    }

    const carrierCosts = Array.isArray(context.contractorsCosts) ? context.contractorsCosts : [];
    const carrierSchedules = carrierCosts
        .map((cost) => cost?.payment_schedule)
        .filter((schedule) => scheduleNeedsTrackReceived(schedule));

    if (carrierSchedules.length > 0) {
        const basisLabels = new Set();

        for (const schedule of carrierSchedules) {
            for (const label of basisLabelsForSchedule(schedule)) {
                basisLabels.add(label);
            }
        }

        const primaryCost = carrierCosts.find((cost) => scheduleNeedsTrackReceived(cost?.payment_schedule)) ?? carrierCosts[0];

        rows.push({
            key: 'carrier',
            party: 'carrier',
            partyLabel: resolveCarrierLabel(primaryCost, context.performers),
            field: 'track_received_date_carrier',
            basisLabels: [...basisLabels],
            value: order?.track_received_date_carrier ?? '',
        });
    }

    return rows;
}
