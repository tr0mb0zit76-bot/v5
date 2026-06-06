/**
 * @param {Record<string, unknown>|null|undefined} selection
 * @param {Array<{ id: number, has_customer_basic_terms?: boolean, has_carrier_basic_terms?: boolean }>} catalog
 * @returns {{ customer: boolean, carrier: boolean, any: boolean }}
 */
export function basicTermsPartiesForTemplateSelection(selection, catalog) {
    const result = { customer: false, carrier: false, any: false };

    if (!selection || typeof selection !== 'object' || !Array.isArray(catalog)) {
        return result;
    }

    const catalogById = new Map(catalog.map((template) => [Number(template.id), template]));

    Object.values(selection).forEach((rawId) => {
        const template = catalogById.get(Number(rawId));

        if (!template) {
            return;
        }

        if (template.has_customer_basic_terms) {
            result.customer = true;
        }

        if (template.has_carrier_basic_terms) {
            result.carrier = true;
        }
    });

    result.any = result.customer || result.carrier;

    return result;
}
