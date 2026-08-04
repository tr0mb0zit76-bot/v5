/**
 * Self-check: carrier print templates must use carrierOwnCompanyId (order 138 style subcontract).
 * Run: node resources/js/support/printFormTemplateMatching.selfcheck.mjs
 */
import {
    buildPrintFormTemplateContext,
    filterPrintFormTemplates,
    ownCompanyIdForPrintParty,
} from './printFormTemplateMatching.js';

const catalog = [
    {
        id: 15,
        name: 'Заявка с перевозчиком РФ АС',
        entity_type: 'order',
        party: 'carrier',
        own_company_id: 17,
        transport_scope: 'domestic',
        is_default: true,
        is_active: true,
        file_path: 'a.docx',
    },
    {
        id: 23,
        name: 'ДЗ с перевозчиком РФ Гросс',
        entity_type: 'order',
        party: 'internal',
        own_company_id: 323,
        has_carrier_basic_terms: true,
        has_customer_basic_terms: false,
        transport_scope: 'any',
        is_default: true,
        is_active: true,
        file_path: 'b.docx',
    },
];

const context = buildPrintFormTemplateContext({
    ownCompanyId: 17,
    carrierOwnCompanyId: 323,
    isInternationalTransport: false,
});

console.assert(ownCompanyIdForPrintParty(context, 'customer') === 17, 'customer own id');
console.assert(ownCompanyIdForPrintParty(context, 'carrier') === 323, 'carrier own id');

const carrierOptions = filterPrintFormTemplates(catalog, context, 'carrier');
console.assert(carrierOptions.length === 1, `expected 1 carrier template, got ${carrierOptions.length}`);
console.assert(carrierOptions[0].id === 23, `expected template 23, got ${carrierOptions[0]?.id}`);

const buggyContext = buildPrintFormTemplateContext({
    ownCompanyId: 17,
    isInternationalTransport: false,
});
const buggyCarrier = filterPrintFormTemplates(catalog, buggyContext, 'carrier');
console.assert(buggyCarrier.some((t) => t.id === 15), 'without carrierOwnCompanyId falls back to AS templates');

console.log('printFormTemplateMatching.selfcheck: ok');
