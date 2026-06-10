/**
 * @param {Array<{ value: string, rate_percent?: number|null, is_vat?: boolean }>} paymentFormOptions
 */
export function paymentFormMetaFromOptions(paymentFormOptions) {
    const map = new Map();

    for (const option of paymentFormOptions ?? []) {
        const value = String(option.value ?? '').trim();

        if (!value) {
            continue;
        }

        const ratePercent = option.rate_percent ?? null;
        const isVat = option.is_vat ?? (ratePercent !== null && ratePercent !== undefined);

        map.set(value, {
            isVat: Boolean(isVat),
            isNoVat: value === 'no_vat',
            ratePercent: ratePercent === null || ratePercent === undefined ? null : Number(ratePercent),
        });
    }

    return map;
}

function resolveMeta(paymentFormMeta, code) {
    const key = String(code ?? '').trim();

    if (!key) {
        return null;
    }

    if (paymentFormMeta.has(key)) {
        return paymentFormMeta.get(key);
    }

    if (key.startsWith('vat_')) {
        const match = key.match(/^vat_(\d+(?:\.\d+)?)$/);

        return {
            isVat: true,
            isNoVat: false,
            ratePercent: match ? Number(match[1]) : null,
        };
    }

    if (key === 'no_vat') {
        return { isVat: false, isNoVat: true, ratePercent: null };
    }

    if (key === 'cash') {
        return { isVat: false, isNoVat: false, ratePercent: null };
    }

    if (key === 'vat') {
        return { isVat: true, isNoVat: false, ratePercent: null };
    }

    return null;
}

function hasPositiveVatRate(paymentFormMeta, code) {
    const meta = resolveMeta(paymentFormMeta, code);

    return Boolean(meta?.isVat) && Number(meta?.ratePercent) > 0;
}

function ratePercentForCode(paymentFormMeta, code) {
    const meta = resolveMeta(paymentFormMeta, code);

    return meta?.ratePercent ?? null;
}

/**
 * Категория KPI: vat_all | vat | cash | vat_zero_22 | unknown.
 *
 * @param {string} customerPaymentForm
 * @param {string[]} carrierPaymentForms
 * @param {Map<string, { isVat: boolean, isNoVat: boolean, ratePercent: number|null }>} paymentFormMeta
 */
export function classifyDealType(customerPaymentForm, carrierPaymentForms = [], paymentFormMeta = new Map()) {
    const customer = String(customerPaymentForm ?? '').trim();
    const carriers = (carrierPaymentForms ?? []).map((value) => String(value ?? '').trim()).filter(Boolean);

    if (!customer || carriers.length === 0) {
        return { key: 'unknown', label: 'Появится после заполнения оплат' };
    }

    const customerRate = ratePercentForCode(paymentFormMeta, customer);
    const allCarriersCash = carriers.every((form) => form === 'cash');

    if (customerRate === 0 && allCarriersCash) {
        return { key: 'vat_zero_cash', label: 'НДС 0% / наличные' };
    }

    if (allCarriersCash) {
        return { key: 'cash', label: 'Наличка' };
    }
    const hasCarrier22 = carriers.some((form) => ratePercentForCode(paymentFormMeta, form) === 22);

    if (customerRate === 0 && hasCarrier22) {
        return { key: 'vat_zero_22', label: 'НДС 0% / 22%' };
    }

    if (hasPositiveVatRate(paymentFormMeta, customer) && carriers.every((form) => hasPositiveVatRate(paymentFormMeta, form))) {
        return { key: 'vat_all', label: 'НДС у всех' };
    }

    return { key: 'vat', label: 'Прочие НДС' };
}
