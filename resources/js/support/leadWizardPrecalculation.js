import { stageLabel } from '@/support/leadWizardPerformers.js';

let nextLineId = 1;

export function blankLeadPrecalculation() {
    return {
        status: 'draft',
        freight: blankLeadPrecalculationFreight(),
        goods_lines: [],
        service_lines: [],
        computed: null,
    };
}

export function blankLeadPrecalculationFreight() {
    return {
        to_border_total: 0,
        after_border_total: 0,
        distribution_basis: 'invoice_rub',
    };
}

export const precalculationStatusOptions = [
    { value: 'draft', label: 'Черновик' },
    { value: 'ready', label: 'Готов' },
    { value: 'archived', label: 'Архив' },
];

export const freightDistributionOptions = [
    { value: 'invoice_rub', label: 'По инвойсу в ₽' },
    { value: 'weight_kg', label: 'По весу' },
    { value: 'equal', label: 'Поровну' },
];

export function normalizeLeadPrecalculation(precalculation) {
    if (!precalculation || typeof precalculation !== 'object') {
        return blankLeadPrecalculation();
    }

    return {
        status: ['draft', 'ready', 'archived'].includes(precalculation.status) ? precalculation.status : 'draft',
        freight: normalizeLeadPrecalculationFreight(precalculation.freight),
        goods_lines: Array.isArray(precalculation.goods_lines)
            ? precalculation.goods_lines.map((line, index) => normalizeGoodsLine(line, index))
            : [],
        service_lines: Array.isArray(precalculation.service_lines)
            ? precalculation.service_lines.map((line, index) => normalizeServiceLine(line, index))
            : [],
        computed: precalculation.computed ?? null,
    };
}

export function normalizeLeadPrecalculationFreight(freight = {}) {
    const basis = ['invoice_rub', 'weight_kg', 'equal'].includes(freight.distribution_basis)
        ? freight.distribution_basis
        : 'invoice_rub';

    return {
        to_border_total: parseOptionalNumber(freight.to_border_total) ?? 0,
        after_border_total: parseOptionalNumber(freight.after_border_total) ?? 0,
        distribution_basis: basis,
    };
}

export function freightAllocationForLine(computed, lineId) {
    const rows = computed?.freight_allocation ?? [];

    return rows.find((row) => row.line_id === lineId) ?? null;
}

export function normalizeGoodsLine(line = {}, index = 0) {
    const currency = String(line.currency ?? 'USD').trim().toUpperCase() || 'USD';

    return {
        id: line.id ?? `goods_${index + 1}`,
        description: line.description ?? '',
        tn_ved_code: String(line.tn_ved_code ?? '').trim(),
        tn_ved_label: line.tn_ved_label ?? '',
        quantity: Math.max(1, Number.parseInt(String(line.quantity ?? '1'), 10) || 1),
        weight_kg: parseOptionalNumber(line.weight_kg),
        invoice_amount: parseOptionalNumber(line.invoice_amount),
        currency,
        exchange_rate: parseOptionalNumber(line.exchange_rate),
        freight_to_border: parseOptionalNumber(line.freight_to_border) ?? 0,
        freight_after_border: parseOptionalNumber(line.freight_after_border) ?? 0,
        other_costs: parseOptionalNumber(line.other_costs) ?? 0,
        vehicle_age_years: Math.max(0, Number.parseInt(String(line.vehicle_age_years ?? '0'), 10) || 0),
        include_utilization_fee: line.include_utilization_fee !== false,
    };
}

export function normalizeServiceLine(line = {}, index = 0) {
    const kind = line.kind === 'logistics' ? 'logistics' : 'other';

    return {
        id: line.id ?? `service_${index + 1}`,
        kind,
        title: line.title ?? (kind === 'logistics' ? 'Логистика' : 'Услуга'),
        stage: line.stage ?? null,
        amount: parseOptionalNumber(line.amount),
        currency: String(line.currency ?? 'RUB').trim().toUpperCase() || 'RUB',
    };
}

export function blankGoodsLine(defaultCurrency = 'USD') {
    nextLineId += 1;

    return normalizeGoodsLine({
        id: `goods_${Date.now()}_${nextLineId}`,
        currency: defaultCurrency,
    });
}

export function blankServiceLine(kind = 'other') {
    nextLineId += 1;

    return normalizeServiceLine({
        id: `service_${Date.now()}_${nextLineId}`,
        kind,
    });
}

export function serviceLinesFromPerformers(performers) {
    const lines = [];

    (Array.isArray(performers) ? performers : []).forEach((performer, index) => {
        const amount = parseOptionalNumber(performer?.estimated_cost);
        if (amount === null) {
            return;
        }

        lines.push(normalizeServiceLine({
            id: `leg_service_${index + 1}`,
            kind: 'logistics',
            title: `Логистика · ${stageLabel(performer.stage)}`,
            stage: performer.stage,
            amount,
            currency: 'RUB',
        }, index));
    });

    return lines;
}

export function mergeServiceLinesFromPerformers(existingLines, performers) {
    const performerLines = serviceLinesFromPerformers(performers);
    const nonLogistics = (Array.isArray(existingLines) ? existingLines : [])
        .filter((line) => line.kind !== 'logistics');

    return [...performerLines, ...nonLogistics.map((line, index) => normalizeServiceLine(line, index))];
}

export async function calculateLeadPrecalculation(precalculation) {
    const response = await fetch(route('leads.precalculation.calculate'), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(normalizeLeadPrecalculation(precalculation)),
    });

    if (!response.ok) {
        throw new Error('Не удалось выполнить расчёт предрасчёта.');
    }

    return response.json();
}

export async function searchLeadPrecalculationTnVed(query, limit = 30) {
    const params = new URLSearchParams({
        q: query,
        limit: String(limit),
    });

    const response = await fetch(`${route('leads.precalculation.tn-ved.search')}?${params.toString()}`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        return [];
    }

    const payload = await response.json();

    return Array.isArray(payload.items) ? payload.items : [];
}

function parseOptionalNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numeric = Number(String(value).replace(/\s+/g, '').replace(',', '.'));

    if (!Number.isFinite(numeric) || numeric < 0) {
        return null;
    }

    return numeric;
}

export function formatPrecalculationMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

export { stageLabel };
