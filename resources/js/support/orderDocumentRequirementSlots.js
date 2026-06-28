import { stageLabel, toStageKey } from '@/support/orderPrintFormSlots.js';
import { expandPerformersForCarrierSlots, filterExternalCarrierSlots, isOwnFleetCarrierOnly, splitCarrierSlotLabel } from '@/support/orderPerformers.js';
import { TRANSPORT_DOCUMENT_LABEL, TRANSPORT_DOCUMENT_TYPES } from '@/support/orderDocumentTypes.js';

const REQUEST_TYPES = ['request', 'contract_request'];
const CLOSING_TYPES = ['upd', 'invoice_factura', 'act'];
const WAYBILL_TYPES = TRANSPORT_DOCUMENT_TYPES;

function isCashPaymentForm(paymentForm) {
    return String(paymentForm ?? '').trim().toLowerCase() === 'cash';
}

function closingRequiredForPaymentForm(paymentForm) {
    return !isCashPaymentForm(paymentForm);
}

const CLOSING_DESCRIPTION = 'УПД, счёт-фактура или акт: статус «Отправлен» или «Подписан».';

function primaryCarrierTransportLabel(performers, clientRequestMode) {
    const slots = carrierRequestSlots(performers, clientRequestMode);
    const withContractor = slots.find((slot) => slot.contractorId);

    if (withContractor?.contractorName) {
        return String(withContractor.contractorName).trim();
    }

    const legs = Array.isArray(performers) ? performers : [];

    for (const performer of legs) {
        const name = performer?.contractor_name ? String(performer.contractor_name).trim() : '';

        if (name !== '') {
            return name;
        }
    }

    return null;
}

function buildWaybillRule(performers, clientRequestMode) {
    return {
        key: 'waybill',
        label: TRANSPORT_DOCUMENT_LABEL,
        description: 'Бумажная ТН, CMR, ЭТрН, ТСД или пакет файлов по маршруту: статус «Отправлен» или «Подписан». Можно прикрепить несколько файлов.',
        party: 'carrier',
        accepted_types: WAYBILL_TYPES,
        slot_kind: 'waybill',
        slot_key: 'waybill',
        contractor_id: null,
        order_leg_stage: null,
        counterparty_label: primaryCarrierTransportLabel(performers, clientRequestMode),
        allows_multiple: true,
    };
}

function buildOwnFleetCarrierOnlyRules(performers, clientRequestMode = 'single_request', paymentContext = {}) {
    const customerSlot = {
        slotKey: 'customer-all',
        orderLegStage: null,
        contractorId: null,
        contractorName: null,
        labelSuffix: '',
    };

    const customerPaymentForm = paymentContext?.customer ?? null;

    const rules = [
        {
            key: `customer_request:${customerSlot.slotKey}`,
            label: 'Заявка заказчика',
            description: 'Загружаемый файл: статус «Отправлен» или «Подписан». Печатная форма: финальный PDF и подписи по шаблону.',
            party: 'customer',
            accepted_types: REQUEST_TYPES,
            slot_kind: 'customer_request',
            slot_key: customerSlot.slotKey,
            contractor_id: customerSlot.contractorId,
            order_leg_stage: customerSlot.orderLegStage,
            counterparty_label: customerSlot.contractorName,
        },
    ];

    if (closingRequiredForPaymentForm(customerPaymentForm)) {
        rules.push({
            key: `customer_closing:${customerSlot.slotKey}`,
            label: 'Закрывающий документ заказчику',
            description: CLOSING_DESCRIPTION,
            party: 'customer',
            accepted_types: [...CLOSING_TYPES],
            slot_kind: 'customer_closing',
            slot_key: customerSlot.slotKey,
            contractor_id: customerSlot.contractorId,
            order_leg_stage: customerSlot.orderLegStage,
            counterparty_label: customerSlot.contractorName,
        });
    }

    rules.push(buildWaybillRule(performers, clientRequestMode));

    return rules;
}

/**
 * @param {string|null|undefined} customerPaymentForm
 * @param {Array<Record<string, unknown>>} contractorsCosts
 * @returns {{customer: string|null, carriers: Record<number, string|null>}}
 */
export function buildDocumentPaymentContext(customerPaymentForm, contractorsCosts = []) {
    /** @type {Record<number, string|null>} */
    const carriers = {};

    (Array.isArray(contractorsCosts) ? contractorsCosts : []).forEach((row) => {
        const contractorId = row?.contractor_id != null && row?.contractor_id !== ''
            ? Number(row.contractor_id)
            : null;

        if (contractorId) {
            carriers[contractorId] = row?.payment_form != null ? String(row.payment_form) : null;
        }
    });

    return {
        customer: customerPaymentForm != null && String(customerPaymentForm).trim() !== ''
            ? String(customerPaymentForm)
            : null,
        carriers,
    };
}

/**
 * @param {Array<{stage?: string, contractor_id?: number|null, contractor_name?: string|null}>} performers
 * @param {string} clientRequestMode
 * @returns {Array<{slotKey: string, orderLegStage: string|null, contractorId: number|null, contractorName: string|null, labelSuffix: string}>}
 */
export function customerRequestSlots(performers, clientRequestMode) {
    const legs = Array.isArray(performers) ? performers : [];

    if (clientRequestMode !== 'split_by_leg' || legs.length <= 1) {
        return [{
            slotKey: 'customer-all',
            orderLegStage: null,
            contractorId: null,
            contractorName: null,
            labelSuffix: '',
        }];
    }

    return legs.map((performer) => {
        const stage = toStageKey(performer.stage ?? 'leg_1');

        return {
            slotKey: `customer-${stage}`,
            orderLegStage: stage,
            contractorId: null,
            contractorName: null,
            labelSuffix: ` · ${stageLabel(stage)}`,
        };
    });
}

/**
 * @param {Array<{stage?: string, contractor_id?: number|null, contractor_name?: string|null}>} performers
 * @param {string} clientRequestMode
 */
export function carrierRequestSlots(performers, clientRequestMode) {
    const allPerformers = Array.isArray(performers) ? performers : [];
    const expanded = filterExternalCarrierSlots(expandPerformersForCarrierSlots(allPerformers));

    if (expanded.length === 0) {
        if (allPerformers.length === 0) {
            return [{
                slotKey: 'carrier-empty',
                orderLegStage: null,
                contractorId: null,
                contractorName: null,
                labelSuffix: '',
            }];
        }

        return [];
    }

    const hasSplitOnLeg = expanded.some((row) => row.carrier_slot != null);
    const multiplePhysicalLegs = allPerformers.length > 1;

    if ((clientRequestMode === 'split_by_leg' && multiplePhysicalLegs) || hasSplitOnLeg) {
        return expanded.map((performer) => {
            const stage = toStageKey(performer.stage ?? 'leg_1');
            const contractorId = performer.contractor_id ? Number(performer.contractor_id) : null;
            const name = performer.contractor_name ? String(performer.contractor_name).trim() : '';
            const slotLabel = performer.carrier_slot ? ` · ${splitCarrierSlotLabel(performer.carrier_slot)}` : '';
            const suffix = name !== ''
                ? ` · ${name}${slotLabel} · ${stageLabel(stage)}`
                : `${slotLabel} · ${stageLabel(stage)}`;
            const slotPart = performer.carrier_slot ? `-slot${performer.carrier_slot}` : '';
            const slotKey = contractorId !== null
                ? `carrier-${contractorId}-${stage}${slotPart}`
                : `carrier-leg-${stage}${slotPart}`;

            return {
                slotKey,
                orderLegStage: stage,
                contractorId,
                contractorName: name !== '' ? name : null,
                carrier_slot: performer.carrier_slot ?? null,
                labelSuffix: suffix,
            };
        });
    }

    const legs = expanded.filter((p) => p?.contractor_id);

    if (legs.length === 0) {
        return [{
            slotKey: 'carrier-empty',
            orderLegStage: null,
            contractorId: null,
            contractorName: null,
            labelSuffix: '',
        }];
    }

    const groups = new Map();

    legs.forEach((performer) => {
        const contractorId = Number(performer.contractor_id);
        if (!groups.has(contractorId)) {
            groups.set(contractorId, []);
        }
        groups.get(contractorId).push(performer);
    });

    return [...groups.entries()].map(([contractorId, groupLegs]) => {
        const name = groupLegs[0]?.contractor_name ? String(groupLegs[0].contractor_name).trim() : '';
        const legTitles = groupLegs.map((p) => stageLabel(p.stage ?? 'leg_1')).join(', ');
        const suffix = name !== ''
            ? (groupLegs.length > 1 ? ` · ${name} (${legTitles})` : ` · ${name}`)
            : (groupLegs.length > 1 ? ` · ${legTitles}` : '');

        return {
            slotKey: `carrier-${contractorId}`,
            orderLegStage: null,
            contractorId: Number(contractorId),
            contractorName: name !== '' ? name : null,
            carrier_slot: null,
            labelSuffix: suffix,
        };
    });
}

/**
 * Цели прикрепления подписанного документа к перевозчику (плечо + контрагент + слот split).
 *
 * @param {Array<Record<string, unknown>>} performers
 * @param {string} clientRequestMode
 * @returns {Array<{key: string, stage: string|null, contractor_id: number, carrier_slot: number|null, label: string}>}
 */
export function carrierAttachTargetOptions(performers, clientRequestMode) {
    return carrierRequestSlots(performers, clientRequestMode)
        .filter((slot) => slot.contractorId)
        .map((slot) => ({
            key: slot.slotKey,
            stage: slot.orderLegStage,
            contractor_id: Number(slot.contractorId),
            carrier_slot: slot.carrier_slot ?? null,
            label: `${slot.contractorName || 'Перевозчик'}${slot.labelSuffix || ''}`,
        }));
}

/**
 * @param {Array<{stage?: string, contractor_id?: number|null, contractor_name?: string|null}>} performers
 * @param {string} clientRequestMode
 * @returns {Array<Record<string, unknown>>}
 */
export function buildDocumentRequirementRules(
    performers,
    clientRequestMode = 'single_request',
    additionalCosts = [],
    paymentContext = {},
) {
    if (isOwnFleetCarrierOnly(performers)) {
        return buildOwnFleetCarrierOnlyRules(performers, clientRequestMode, paymentContext);
    }

    const mode = clientRequestMode === 'split_by_leg' ? 'split_by_leg' : 'single_request';
    const rules = [];
    const customerPaymentForm = paymentContext?.customer ?? null;
    const carrierPaymentForms = paymentContext?.carriers ?? {};

    customerRequestSlots(performers, mode).forEach((slot) => {
        rules.push({
            key: `customer_request:${slot.slotKey}`,
            label: `Заявка заказчика${slot.labelSuffix}`,
            description: 'Загружаемый файл: статус «Отправлен» или «Подписан». Печатная форма: финальный PDF и подписи по шаблону.',
            party: 'customer',
            accepted_types: REQUEST_TYPES,
            slot_kind: 'customer_request',
            slot_key: slot.slotKey,
            contractor_id: slot.contractorId,
            order_leg_stage: slot.orderLegStage,
            counterparty_label: slot.contractorName,
        });
    });

    customerRequestSlots(performers, mode).forEach((slot) => {
        if (!closingRequiredForPaymentForm(customerPaymentForm)) {
            return;
        }

        rules.push({
            key: `customer_closing:${slot.slotKey}`,
            label: `Закрывающий документ заказчику${slot.labelSuffix}`,
            description: CLOSING_DESCRIPTION,
            party: 'customer',
            accepted_types: [...CLOSING_TYPES],
            slot_kind: 'customer_closing',
            slot_key: slot.slotKey,
            contractor_id: slot.contractorId,
            order_leg_stage: slot.orderLegStage,
            counterparty_label: slot.contractorName,
        });
    });

    carrierRequestSlots(performers, mode).forEach((slot) => {
        rules.push({
            key: `carrier_request:${slot.slotKey}`,
            label: `Заявка перевозчику${slot.labelSuffix}`,
            description: 'Загружаемый файл: статус «Отправлен» или «Подписан». Печатная форма: финальный PDF и подписи по шаблону.',
            party: 'carrier',
            accepted_types: REQUEST_TYPES,
            slot_kind: 'carrier_request',
            slot_key: slot.slotKey,
            contractor_id: slot.contractorId,
            order_leg_stage: slot.orderLegStage,
            counterparty_label: slot.contractorName,
        });
    });

    carrierRequestSlots(performers, mode).forEach((slot) => {
        const carrierPaymentForm = slot.contractorId != null
            ? (carrierPaymentForms[Number(slot.contractorId)] ?? null)
            : null;

        if (!closingRequiredForPaymentForm(carrierPaymentForm)) {
            return;
        }

        rules.push({
            key: `carrier_closing:${slot.slotKey}`,
            label: `Закрывающий документ перевозчика${slot.labelSuffix}`,
            description: CLOSING_DESCRIPTION,
            party: 'carrier',
            accepted_types: [...CLOSING_TYPES],
            slot_kind: 'carrier_closing',
            slot_key: slot.slotKey,
            contractor_id: slot.contractorId,
            order_leg_stage: slot.orderLegStage,
            counterparty_label: slot.contractorName,
        });
    });

    (Array.isArray(additionalCosts) ? additionalCosts : []).forEach((row) => {
        const contractorId = row?.contractor_id != null && row?.contractor_id !== ''
            ? Number(row.contractor_id)
            : null;

        if (!contractorId) {
            return;
        }

        const rowId = String(row?.id ?? '').trim();
        const slotKey = rowId !== '' ? `contractor-${contractorId}-${rowId}` : `contractor-${contractorId}`;
        const name = row?.contractor_name ? String(row.contractor_name).trim() : '';
        const suffix = name !== '' ? ` · ${name}` : '';

        const contractorPaymentForm = row?.payment_form ?? null;

        if (!closingRequiredForPaymentForm(contractorPaymentForm)) {
            return;
        }

        rules.push({
            key: `contractor_closing:${slotKey}`,
            label: `Закрывающий документ подрядчику${suffix}`,
            description: CLOSING_DESCRIPTION,
            party: 'contractor',
            accepted_types: [...CLOSING_TYPES],
            slot_kind: 'contractor_closing',
            slot_key: slotKey,
            contractor_id: contractorId,
            order_leg_stage: null,
            counterparty_label: name !== '' ? name : null,
        });
    });

    rules.push(buildWaybillRule(performers, mode));

    return rules;
}

/**
 * @param {Record<string, unknown>} document
 * @param {Record<string, unknown>} rule
 */
/**
 * @param {Array<Record<string, unknown>>} rules
 * @param {Record<string, unknown>} criteria
 */
export function findRequirementRuleForUpload(rules, criteria) {
    const list = Array.isArray(rules) ? rules : [];
    const party = String(criteria.party ?? '');
    const type = String(criteria.type ?? '');
    const stage = criteria.stage ? toStageKey(String(criteria.stage)) : null;
    const contractorId = criteria.contractor_id != null ? Number(criteria.contractor_id) : null;

    return list.find((rule) => {
        const accepted = Array.isArray(rule.accepted_types) ? rule.accepted_types : [];

        if (!accepted.includes(type) || String(rule.party ?? '') !== party) {
            return false;
        }

        const ruleStage = rule.order_leg_stage ? toStageKey(String(rule.order_leg_stage)) : null;
        const ruleContractorId = rule.contractor_id != null ? Number(rule.contractor_id) : null;

        if (ruleStage !== null && ruleStage !== stage) {
            return false;
        }

        if (ruleContractorId !== null && ruleContractorId !== contractorId) {
            return false;
        }

        if (ruleStage === null && stage !== null && party === 'customer' && String(rule.slot_key) === 'customer-all') {
            return false;
        }

        return true;
    }) ?? null;
}

export function documentMatchesRequirementRule(document, rule) {
    const accepted = Array.isArray(rule.accepted_types) ? rule.accepted_types : [];
    const type = String(document?.type ?? '');

    if (!accepted.includes(type)) {
        return false;
    }

    const docParty = String(document?.party ?? 'internal');
    const ruleParty = String(rule.party ?? '');
    const isWaybillSlot = String(rule.slot_kind ?? '') === 'waybill';

    if (isWaybillSlot) {
        if (!['carrier', 'internal'].includes(docParty)) {
            return false;
        }
    } else if (docParty !== ruleParty) {
        return false;
    }

    if (isWaybillSlot) {
        return true;
    }

    const ruleStage = rule.order_leg_stage ? toStageKey(String(rule.order_leg_stage)) : null;
    const docStageRaw = document?.order_leg_stage ?? document?.stage ?? null;
    const docStage = docStageRaw ? toStageKey(String(docStageRaw)) : null;

    const ruleContractorId = rule.contractor_id != null ? Number(rule.contractor_id) : null;
    const docContractorId = document?.carrier_contractor_id != null
        ? Number(document.carrier_contractor_id)
        : (document?.contractor_id != null ? Number(document.contractor_id) : null);

    if (ruleStage !== null) {
        if (docStage !== ruleStage) {
            return false;
        }
    } else if (docStage !== null && rule.slot_kind !== 'waybill') {
        const slotKey = rule.slot_key ? String(rule.slot_key) : '';
        const aggregatedCustomer = String(rule.party) === 'customer' && slotKey === 'customer-all';
        const aggregatedCarrier = String(rule.party) === 'carrier' && ruleContractorId !== null;

        if (aggregatedCustomer || !aggregatedCarrier) {
            return false;
        }
    }

    if (ruleContractorId !== null) {
        if (docContractorId !== ruleContractorId) {
            return false;
        }
    } else if (ruleStage !== null && String(rule.party) === 'carrier') {
        if (docStage !== ruleStage) {
            return false;
        }
    } else if (docContractorId !== null && String(rule.party) === 'carrier') {
        return false;
    }

    const ruleSlotKey = rule.slot_key ? String(rule.slot_key) : null;
    const docSlotKey = document?.requirement_slot_key ? String(document.requirement_slot_key) : null;

    if (ruleSlotKey !== null && docSlotKey !== null && docSlotKey !== ruleSlotKey) {
        return false;
    }

    return true;
}
