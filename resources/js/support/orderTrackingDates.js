import { BASIS_SUMMARY_PHRASE, ensureInstallmentSchedule } from '@/support/orderPaymentScheduleUi.js';

const TRACK_RECEIVED_BASIS = new Set(['ottn', 'fttn_receipt']);

export function installmentNeedsTrackReceived(basis) {
    return TRACK_RECEIVED_BASIS.has(String(basis ?? '').toLowerCase());
}

export function scheduleNeedsTrackReceived(schedule) {
    const normalized = ensureInstallmentSchedule(schedule ?? {});

    return (normalized.installments ?? []).some((row) => installmentNeedsTrackReceived(row.basis));
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

/**
 * @param {{
 *   clientPaymentSchedule?: object,
 *   contractorsCosts?: Array<object>,
 *   order?: object|null,
 *   performers?: Array<object>,
 * }} context
 * @returns {Array<Record<string, unknown>>}
 */
export function buildTrackingRegistryRows(context) {
    return buildOrderTrackingDateRows(context).map((row) => ({
        _localKey: `tracking-${row.key}`,
        is_tracking_row: true,
        is_placeholder: false,
        party: row.party,
        party_label: row.partyLabel,
        type: 'tracking_received',
        type_label: row.basisLabels.length === 1 && row.basisLabels[0] === 'по оригиналам'
            ? 'Оригиналы'
            : 'Квиток / оригиналы',
        number: null,
        document_date: null,
        received_date: row.value ?? '',
        track_field: row.field,
        checklist_completed: false,
        requirement_label: 'Для расчёта плановой даты оплаты',
    }));
}
