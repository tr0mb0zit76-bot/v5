import { stageLabel, toStageKey } from '@/support/orderPrintFormSlots.js';

const REQUEST_TYPES = ['request', 'contract_request'];
const CLOSING_TYPES = ['upd', 'invoice_factura', 'act'];
const WAYBILL_TYPES = ['waybill', 'cmr', 'etrn'];

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
    const legs = (Array.isArray(performers) ? performers : []).filter((p) => p?.contractor_id);

    if (legs.length === 0) {
        return [{
            slotKey: 'carrier-empty',
            orderLegStage: null,
            contractorId: null,
            contractorName: null,
            labelSuffix: '',
        }];
    }

    if (clientRequestMode === 'split_by_leg' && legs.length > 1) {
        return legs.map((performer) => {
            const stage = toStageKey(performer.stage ?? 'leg_1');
            const contractorId = Number(performer.contractor_id);
            const name = performer.contractor_name ? String(performer.contractor_name).trim() : '';
            const suffix = name !== '' ? ` · ${name} · ${stageLabel(stage)}` : ` · ${stageLabel(stage)}`;

            return {
                slotKey: `carrier-${contractorId}-${stage}`,
                orderLegStage: stage,
                contractorId,
                contractorName: name !== '' ? name : null,
                labelSuffix: suffix,
            };
        });
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
            labelSuffix: suffix,
        };
    });
}

/**
 * @param {Array<{stage?: string, contractor_id?: number|null, contractor_name?: string|null}>} performers
 * @param {string} clientRequestMode
 * @returns {Array<Record<string, unknown>>}
 */
export function buildDocumentRequirementRules(performers, clientRequestMode = 'single_request') {
    const mode = clientRequestMode === 'split_by_leg' ? 'split_by_leg' : 'single_request';
    const rules = [];

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
        rules.push({
            key: `customer_closing:${slot.slotKey}`,
            label: `Закрывающий документ заказчику${slot.labelSuffix}`,
            description: 'УПД, счёт-фактура или акт: статус «Отправлен» или «Подписан».',
            party: 'customer',
            accepted_types: CLOSING_TYPES,
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
        rules.push({
            key: `carrier_closing:${slot.slotKey}`,
            label: `Закрывающий документ перевозчика${slot.labelSuffix}`,
            description: 'УПД, счёт-фактура или акт: статус «Отправлен» или «Подписан».',
            party: 'carrier',
            accepted_types: CLOSING_TYPES,
            slot_kind: 'carrier_closing',
            slot_key: slot.slotKey,
            contractor_id: slot.contractorId,
            order_leg_stage: slot.orderLegStage,
            counterparty_label: slot.contractorName,
        });
    });

    rules.push({
        key: 'waybill',
        label: 'ТН / ЭТрН / пакет товаросопровождающих',
        description: 'Бумажная ТН, CMR, ЭТрН или пакет файлов по маршруту: статус «Отправлен» или «Подписан». Можно прикрепить несколько файлов.',
        party: 'internal',
        accepted_types: WAYBILL_TYPES,
        slot_kind: 'waybill',
        slot_key: 'waybill',
        contractor_id: null,
        order_leg_stage: null,
        counterparty_label: null,
        allows_multiple: true,
    });

    return rules;
}

/**
 * @param {Record<string, unknown>} document
 * @param {Record<string, unknown>} rule
 */
export function documentMatchesRequirementRule(document, rule) {
    const accepted = Array.isArray(rule.accepted_types) ? rule.accepted_types : [];
    const type = String(document?.type ?? '');

    if (!accepted.includes(type)) {
        return false;
    }

    if (String(document?.party ?? 'internal') !== String(rule.party ?? '')) {
        return false;
    }

    const ruleStage = rule.order_leg_stage ? toStageKey(String(rule.order_leg_stage)) : null;
    const docStage = document?.order_leg_stage ? toStageKey(String(document.order_leg_stage)) : null;

    if (ruleStage !== null) {
        if (docStage !== ruleStage) {
            return false;
        }
    } elseif (docStage !== null && rule.slot_kind !== 'waybill') {
        return false;
    }

    const ruleContractorId = rule.contractor_id != null ? Number(rule.contractor_id) : null;
    const docContractorId = document?.carrier_contractor_id != null
        ? Number(document.carrier_contractor_id)
        : (document?.contractor_id != null ? Number(document.contractor_id) : null);

    if (ruleContractorId !== null) {
        if (docContractorId !== ruleContractorId) {
            return false;
        }
    } elseif (docContractorId !== null && String(rule.party) === 'carrier') {
        return false;
    }

    const ruleSlotKey = rule.slot_key ? String(rule.slot_key) : null;
    const docSlotKey = document?.requirement_slot_key ? String(document.requirement_slot_key) : null;

    if (ruleSlotKey !== null && docSlotKey !== null && docSlotKey !== ruleSlotKey) {
        return false;
    }

    return true;
}
