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
 * Дата получения отдельно для заявки и закрывающих.
 * Поля показываем, если стороне нужна любая «дата получения» (ottn или fttn_receipt) —
 * иначе при графике только с ottn нельзя проставить дату на УПД.
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
        if (!scheduleNeedsTrackReceived(context.clientPaymentSchedule)) {
            return null;
        }

        return TRACK_FIELD_BY_SLOT[slotKind] ?? null;
    }

    if (party === 'carrier') {
        const kinds = carrierTrackBasisKindsForRow(rowMeta.contractorId, context.contractorsCosts);

        if (!kinds.ottn && !kinds.fttn_receipt) {
            return null;
        }

        return TRACK_FIELD_BY_SLOT[slotKind] ?? null;
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
    const customerNeeds = scheduleNeedsTrackReceived(context.clientPaymentSchedule);

    if (customerNeeds) {
        rows.push({
            key: 'customer_request',
            party: 'customer',
            partyLabel: 'Заказчик · заявка',
            field: TRACK_FIELD_BY_SLOT.customer_request,
            packageKind: 'request',
            basisLabels: basisLabelsForSchedule(context.clientPaymentSchedule),
            value: order?.track_received_date_customer_request ?? '',
        });

        rows.push({
            key: 'customer_closing',
            party: 'customer',
            partyLabel: 'Заказчик · закрывающие',
            field: TRACK_FIELD_BY_SLOT.customer_closing,
            packageKind: 'closing',
            basisLabels: basisLabelsForSchedule(context.clientPaymentSchedule),
            value: order?.track_received_date_customer_closing ?? '',
        });
    }

    const carrierCosts = Array.isArray(context.contractorsCosts) ? context.contractorsCosts : [];
    const carrierSchedulesNeeding = carrierCosts.filter((cost) => scheduleNeedsTrackReceived(cost?.payment_schedule));

    if (carrierSchedulesNeeding.length > 0) {
        const basisLabels = new Set();

        for (const cost of carrierSchedulesNeeding) {
            for (const label of basisLabelsForSchedule(cost.payment_schedule)) {
                basisLabels.add(label);
            }
        }

        const primaryCost = carrierSchedulesNeeding[0] ?? carrierCosts[0];
        const carrierLabel = resolveCarrierLabel(primaryCost, context.performers);
        const labels = [...basisLabels];

        rows.push({
            key: 'carrier_request',
            party: 'carrier',
            partyLabel: `${carrierLabel} · заявка`,
            field: TRACK_FIELD_BY_SLOT.carrier_request,
            packageKind: 'request',
            basisLabels: labels,
            value: order?.track_received_date_carrier_request ?? '',
        });

        rows.push({
            key: 'carrier_closing',
            party: 'carrier',
            partyLabel: `${carrierLabel} · закрывающие`,
            field: TRACK_FIELD_BY_SLOT.carrier_closing,
            packageKind: 'closing',
            basisLabels: labels,
            value: order?.track_received_date_carrier_closing ?? '',
        });
    }

    return rows;
}
