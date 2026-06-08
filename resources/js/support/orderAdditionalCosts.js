import { blankSingleInstallmentSchedule, normalizePaymentSchedule } from '@/support/orderPaymentScheduleUi.js';

function normalizePaymentFormCode(value, fallback = 'no_vat') {
    const raw = String(value ?? '').trim();

    return raw !== '' ? raw : fallback;
}

export function blankAdditionalCostRow(orderDate = null) {
    return {
        id: crypto.randomUUID?.() ?? `additional-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        contractor_id: null,
        contractor_name: null,
        service_date: orderDate ? String(orderDate).slice(0, 10) : null,
        amount: null,
        currency: 'RUB',
        payment_form: 'no_vat',
        payment_schedule: blankSingleInstallmentSchedule(),
        payment_terms: '',
    };
}

export function normalizeAdditionalCostRow(row = {}, fallbackDate = null) {
    const merged = {
        ...blankAdditionalCostRow(fallbackDate),
        ...row,
        payment_schedule: normalizePaymentSchedule(row.payment_schedule),
    };

    merged.id = String(row.id ?? merged.id);
    merged.service_date = merged.service_date
        ? String(merged.service_date).slice(0, 10)
        : (row.incurred_date ? String(row.incurred_date).slice(0, 10) : (fallbackDate ? String(fallbackDate).slice(0, 10) : null));
    merged.payment_form = normalizePaymentFormCode(merged.payment_form, 'no_vat');
    merged.payment_terms = String(merged.payment_terms ?? '').trim();
    merged.contractor_id = merged.contractor_id != null && merged.contractor_id !== ''
        ? Number(merged.contractor_id)
        : null;
    merged.amount = merged.amount != null && merged.amount !== '' ? Number(merged.amount) : null;

    return merged;
}

export function normalizeAdditionalCostsList(rows, fallbackDate = null) {
    return (Array.isArray(rows) ? rows : []).map((row) => normalizeAdditionalCostRow(row, fallbackDate));
}

export function sumAdditionalCostsAmount(rows) {
    return (Array.isArray(rows) ? rows : []).reduce((sum, row) => sum + Number(row?.amount || 0), 0);
}

export function migrateLegacyAdditionalContractorCosts(contractorsCosts, fallbackDate = null) {
    const legCosts = [];
    const additionalCosts = [];

    (Array.isArray(contractorsCosts) ? contractorsCosts : []).forEach((row) => {
        if (!row || typeof row !== 'object') {
            return;
        }

        if (row.is_additional || String(row.stage ?? '').startsWith('additional')) {
            additionalCosts.push(normalizeAdditionalCostRow({
                id: row.id,
                contractor_id: row.contractor_id,
                contractor_name: row.contractor_name,
                service_date: row.incurred_date ?? row.service_date ?? fallbackDate,
                amount: row.amount,
                currency: row.currency,
                payment_form: row.payment_form,
                payment_schedule: row.payment_schedule,
                payment_terms: row.payment_terms,
            }, fallbackDate));

            return;
        }

        legCosts.push(row);
    });

    return { legCosts, additionalCosts };
}

export function serializeAdditionalCostsForSubmit(rows) {
    return normalizeAdditionalCostsList(rows).map((row) => ({
        id: row.id,
        contractor_id: row.contractor_id,
        contractor_name: row.contractor_name ? String(row.contractor_name).trim() || null : null,
        service_date: row.service_date || null,
        amount: row.amount,
        currency: row.currency || 'RUB',
        payment_form: normalizePaymentFormCode(row.payment_form, 'no_vat'),
        payment_schedule: row.payment_schedule || {},
        payment_terms: row.payment_terms ?? '',
    }));
}
