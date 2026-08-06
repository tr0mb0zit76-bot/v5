import { BASIS_SUMMARY_PHRASE, ensureInstallmentSchedule } from '@/support/orderPaymentScheduleUi.js';

const TRACK_RECEIVED_BASIS = new Set(['ottn', 'fttn_receipt']);

const TRACK_RECEIVED_SLOT_KINDS = new Set([
    'customer_request',
    'customer_closing',
    'carrier_request',
    'carrier_closing',
]);

const TRACK_FIELD_BY_SLOT = {
    customer_request: 'track_received_date_customer_request',
    customer_closing: 'track_received_date_customer_closing',
    carrier_request: 'track_received_date_carrier_request',
    carrier_closing: 'track_received_date_carrier_closing',
};

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

function basisLabelsForSchedule(schedule, packageKind = null) {
    const normalized = ensureInstallmentSchedule(schedule ?? {});
    const labels = new Set();

    for (const row of normalized.installments ?? []) {
        if (!installmentNeedsTrackReceived(row.basis)) {
            continue;
        }

        const basis = String(row.basis ?? '').toLowerCase();

        if (packageKind === 'request' && basis !== 'ottn') {
            continue;
        }

        if (packageKind === 'closing' && basis !== 'fttn_receipt') {
            continue;
        }

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
 * Дата получения отдельно для заявки (ottn) и закрывающих (fttn_receipt).
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
 * @returns {string|null}
 */
export function resolveTrackFieldForRegistryRow(rowMeta, context) {
    const party = String(rowMeta.party ?? '');
    const slotKind = String(rowMeta.slotKind ?? '');

    if (!TRACK_RECEIVED_SLOT_KINDS.has(slotKind)) {
        return null;
    }

    if (party === 'customer') {
        const kinds = scheduleTrackBasisKinds(context.clientPaymentSchedule);

        if (slotKind === 'customer_request' && kinds.ottn) {
            return TRACK_FIELD_BY_SLOT.customer_request;
        }

        if (slotKind === 'customer_closing' && kinds.fttn_receipt) {
            return TRACK_FIELD_BY_SLOT.customer_closing;
        }

        return null;
    }

    if (party === 'carrier') {
        const kinds = carrierTrackBasisKindsForRow(rowMeta.contractorId, context.contractorsCosts);

        if (slotKind === 'carrier_request' && kinds.ottn) {
            return TRACK_FIELD_BY_SLOT.carrier_request;
        }

        if (slotKind === 'carrier_closing' && kinds.fttn_receipt) {
            return TRACK_FIELD_BY_SLOT.carrier_closing;
        }

        return null;
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
 *   packageKind: 'request'|'closing',
 *   basisLabels: string[],
 *   value: string,
 * }>}
 */
export function buildOrderTrackingDateRows(context) {
    const rows = [];
    const order = context.order ?? null;
    const customerKinds = scheduleTrackBasisKinds(context.clientPaymentSchedule);

    if (customerKinds.ottn) {
        rows.push({
            key: 'customer_request',
            party: 'customer',
            partyLabel: 'Заказчик · заявка',
            field: TRACK_FIELD_BY_SLOT.customer_request,
            packageKind: 'request',
            basisLabels: basisLabelsForSchedule(context.clientPaymentSchedule, 'request'),
            value: order?.track_received_date_customer_request ?? '',
        });
    }

    if (customerKinds.fttn_receipt) {
        rows.push({
            key: 'customer_closing',
            party: 'customer',
            partyLabel: 'Заказчик · закрывающие',
            field: TRACK_FIELD_BY_SLOT.customer_closing,
            packageKind: 'closing',
            basisLabels: basisLabelsForSchedule(context.clientPaymentSchedule, 'closing'),
            value: order?.track_received_date_customer_closing ?? '',
        });
    }

    const carrierCosts = Array.isArray(context.contractorsCosts) ? context.contractorsCosts : [];
    const carrierKinds = { ottn: false, fttn_receipt: false };
    const requestSchedules = [];
    const closingSchedules = [];

    for (const cost of carrierCosts) {
        const kinds = scheduleTrackBasisKinds(cost?.payment_schedule);
        carrierKinds.ottn = carrierKinds.ottn || kinds.ottn;
        carrierKinds.fttn_receipt = carrierKinds.fttn_receipt || kinds.fttn_receipt;

        if (kinds.ottn) {
            requestSchedules.push(cost.payment_schedule);
        }

        if (kinds.fttn_receipt) {
            closingSchedules.push(cost.payment_schedule);
        }
    }

    const primaryCost = carrierCosts.find((cost) => scheduleNeedsTrackReceived(cost?.payment_schedule)) ?? carrierCosts[0];
    const carrierLabel = resolveCarrierLabel(primaryCost, context.performers);

    if (carrierKinds.ottn) {
        const basisLabels = new Set();
        for (const schedule of requestSchedules) {
            for (const label of basisLabelsForSchedule(schedule, 'request')) {
                basisLabels.add(label);
            }
        }

        rows.push({
            key: 'carrier_request',
            party: 'carrier',
            partyLabel: `${carrierLabel} · заявка`,
            field: TRACK_FIELD_BY_SLOT.carrier_request,
            packageKind: 'request',
            basisLabels: [...basisLabels],
            value: order?.track_received_date_carrier_request ?? '',
        });
    }

    if (carrierKinds.fttn_receipt) {
        const basisLabels = new Set();
        for (const schedule of closingSchedules) {
            for (const label of basisLabelsForSchedule(schedule, 'closing')) {
                basisLabels.add(label);
            }
        }

        rows.push({
            key: 'carrier_closing',
            party: 'carrier',
            partyLabel: `${carrierLabel} · закрывающие`,
            field: TRACK_FIELD_BY_SLOT.carrier_closing,
            packageKind: 'closing',
            basisLabels: [...basisLabels],
            value: order?.track_received_date_carrier_closing ?? '',
        });
    }

    return rows;
}
