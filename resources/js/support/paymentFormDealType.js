/**
 * @param {Array<{ value: string, rate_percent?: number|null, is_vat?: boolean }>} paymentFormOptions
 */
function buildPaymentFormMeta(paymentFormOptions) {
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
        return { isVat: true, isNoVat: false, ratePercent: null };
    }

    if (key === 'no_vat') {
        return { isVat: false, isNoVat: true, ratePercent: null };
    }

    return null;
}

/**
 * @param {string} customerPaymentForm
 * @param {string[]} carrierPaymentForms
 * @param {Map<string, { isVat: boolean, isNoVat: boolean, ratePercent: number|null }>} paymentFormMeta
 */
export function classifyDealType(customerPaymentForm, carrierPaymentForms, paymentFormMeta) {
    const customer = String(customerPaymentForm ?? '').trim();
    const carriers = (carrierPaymentForms ?? []).map((value) => String(value ?? '').trim()).filter(Boolean);

    if (!customer || carriers.length === 0) {
        return { key: 'unknown', label: 'Появится после заполнения оплат' };
    }

    const customerMeta = resolveMeta(paymentFormMeta, customer);

    if (!customerMeta) {
        return { key: 'unknown', label: 'Появится после заполнения оплат' };
    }

    for (const carrier of carriers) {
        const carrierMeta = resolveMeta(paymentFormMeta, carrier);

        if (!carrierMeta) {
            continue;
        }

        if ((customerMeta.isVat && carrierMeta.isNoVat) || (customerMeta.isNoVat && carrierMeta.isVat)) {
            return { key: 'indirect', label: 'Кривая' };
        }
    }

    return { key: 'direct', label: 'Прямая' };
}

export function paymentFormMetaFromOptions(paymentFormOptions) {
    return buildPaymentFormMeta(paymentFormOptions);
}
