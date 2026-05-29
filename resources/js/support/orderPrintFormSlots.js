export function toStageKey(label) {
    const match = String(label ?? '').match(/^Плечо (\d+)$/);

    if (match) {
        return `leg_${match[1]}`;
    }

    return String(label ?? '');
}

export function stageLabel(stage) {
    const match = String(stage ?? '').match(/^leg_(\d+)$/);

    if (match) {
        return `Плечо ${match[1]}`;
    }

    return String(stage ?? '');
}

export function stageMatches(left, right) {
    return toStageKey(left) === toStageKey(right);
}

function expandCarrierPerformersForPrint(performers) {
    const list = Array.isArray(performers) ? performers : [];
    const expanded = [];

    list.forEach((performer) => {
        if (performer?.carrier_mode === 'split' && Array.isArray(performer.split_carriers) && performer.split_carriers.length > 0) {
            performer.split_carriers.forEach((slot) => {
                expanded.push({
                    stage: performer.stage,
                    carrier_slot: Number(slot?.slot ?? 1),
                    contractor_id: slot?.contractor_id ?? null,
                    contractor_name: slot?.contractor_name ?? null,
                    execution_mode: slot?.execution_mode ?? null,
                });
            });

            return;
        }

        expanded.push({
            stage: performer.stage,
            carrier_slot: null,
            contractor_id: performer?.contractor_id ?? null,
            contractor_name: performer?.contractor_name ?? null,
            execution_mode: performer?.execution_mode ?? null,
        });
    });

    return expanded.filter((row) => row.execution_mode !== 'own_fleet');
}

/**
 * @param {Array<{stage?: string}>} performers
 * @param {string} clientRequestMode
 */
export function customerPrintSlots(performers, clientRequestMode) {
    const legs = Array.isArray(performers) ? performers : [];

    if (clientRequestMode !== 'split_by_leg' || legs.length <= 1) {
        return [{
            slotKey: 'customer-all',
            label: 'Печатная форма заказчик',
            orderLegStage: null,
        }];
    }

    return legs.map((performer) => ({
        slotKey: `customer-${toStageKey(performer.stage ?? 'leg_1')}`,
        label: `Печатная форма заказчик · ${stageLabel(performer.stage ?? 'leg_1')}`,
        orderLegStage: toStageKey(performer.stage ?? 'leg_1'),
    }));
}

/**
 * @param {Array<{stage?: string, contractor_id?: number|null, contractor_name?: string|null}>} performers
 */
export function carrierPrintSlots(performers) {
    const physicalLegs = Array.isArray(performers) ? performers : [];
    const expanded = expandCarrierPerformersForPrint(physicalLegs);
    const withCarrier = expanded.filter((row) => row?.contractor_id);

    if (withCarrier.length === 0) {
        return [{
            slotKey: 'carrier-empty',
            label: 'Печатная форма перевозчик',
            carrierContractorId: null,
            routeLegsAsTableRows: false,
            orderLegStage: null,
        }];
    }

    const hasSplitOnLeg = expanded.some((row) => row.carrier_slot != null);

    if (hasSplitOnLeg) {
        return withCarrier.map((row) => {
            const stage = toStageKey(row.stage ?? 'leg_1');
            const contractorId = Number(row.contractor_id);
            const name = row.contractor_name ? String(row.contractor_name).trim() : '';
            const slotLabel = row.carrier_slot ? ` · Исполнитель ${row.carrier_slot}` : '';

            return {
                slotKey: `carrier-${contractorId}-${stage}-slot${row.carrier_slot ?? 1}`,
                label: name !== ''
                    ? `Печатная форма перевозчик · ${name}${slotLabel} · ${stageLabel(stage)}`
                    : `Печатная форма перевозчик${slotLabel} · ${stageLabel(stage)}`,
                carrierContractorId: contractorId,
                carrierSlot: row.carrier_slot ?? null,
                routeLegsAsTableRows: false,
                orderLegStage: stage,
            };
        });
    }

    const groups = new Map();

    withCarrier.forEach((row) => {
        const contractorId = Number(row.contractor_id);
        if (!groups.has(contractorId)) {
            groups.set(contractorId, []);
        }
        groups.get(contractorId).push(row);
    });

    if (groups.size <= 1) {
        const contractorId = Number(withCarrier[0].contractor_id);
        const name = withCarrier[0].contractor_name ? String(withCarrier[0].contractor_name).trim() : '';

        return [{
            slotKey: `carrier-${contractorId}`,
            label: name !== '' ? `Печатная форма перевозчик · ${name}` : 'Печатная форма перевозчик',
            carrierContractorId: contractorId,
            routeLegsAsTableRows: physicalLegs.length > 1,
            orderLegStage: null,
        }];
    }

    return [...groups.entries()].map(([contractorId, groupLegs]) => {
        const name = groupLegs[0]?.contractor_name ? String(groupLegs[0].contractor_name).trim() : '';
        const legTitles = groupLegs.map((p) => stageLabel(p.stage ?? 'leg_1')).join(', ');

        return {
            slotKey: `carrier-${contractorId}`,
            label: name !== ''
                ? `Печатная форма перевозчик · ${name} (${legTitles})`
                : `Печатная форма перевозчик · ${legTitles}`,
            carrierContractorId: Number(contractorId),
            routeLegsAsTableRows: false,
            orderLegStage: null,
        };
    });
}

/**
 * @param {Array<Record<string, unknown>>} documents
 * @param {'customer'|'carrier'} party
 * @param {Record<string, unknown>} slot
 */
export function printWorkflowDocumentsForSlot(documents, party, slot) {
    const list = Array.isArray(documents) ? documents : [];

    return list.filter((doc) => {
        if (!doc?.is_print_workflow) {
            return false;
        }

        const docParty = String(doc.party ?? doc.print_party ?? 'internal');
        if (docParty !== party) {
            return false;
        }

        if (party === 'customer') {
            if (!slot.orderLegStage) {
                return !doc.order_leg_stage;
            }

            return toStageKey(doc.order_leg_stage ?? '') === toStageKey(slot.orderLegStage);
        }

        const slotCarrierId = slot.carrierContractorId ? Number(slot.carrierContractorId) : null;
        const docCarrierId = doc.carrier_contractor_id ? Number(doc.carrier_contractor_id) : null;

        if (slotCarrierId === null) {
            return docCarrierId === null;
        }

        return docCarrierId === slotCarrierId;
    });
}

/**
 * @param {Array<Record<string, unknown>>} documents
 */
export function signedRegistryDocuments(documents) {
    return (Array.isArray(documents) ? documents : []).filter((doc) => {
        if (doc?.is_print_workflow) {
            return false;
        }

        return String(doc?.status ?? '') === 'signed';
    });
}

/**
 * @param {Record<string, unknown>} document
 * @param {Array<Record<string, unknown>>} rules
 */
export function checklistMarksForDocument(document, rules) {
    const list = Array.isArray(rules) ? rules : [];

    return list
        .filter((rule) => {
            if (!Array.isArray(rule.accepted_types) || !rule.accepted_types.includes(document.type)) {
                return false;
            }

            return String(document.party ?? 'internal') === rule.party;
        })
        .map((rule) => rule.label);
}
