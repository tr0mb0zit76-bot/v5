export const CARRIER_MODE_SINGLE = 'single';
export const CARRIER_MODE_SPLIT = 'split';

export function blankSplitCarrier(slot = 1) {
    return {
        slot,
        contractor_id: null,
        contractor_name: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
}

export function blankPerformer(stage, carrierMode = CARRIER_MODE_SINGLE) {
    const performer = {
        stage,
        carrier_mode: carrierMode,
        contractor_id: null,
        contractor_name: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
        split_carriers: [],
    };

    if (carrierMode === CARRIER_MODE_SPLIT) {
        performer.split_carriers = [blankSplitCarrier(1), blankSplitCarrier(2)];
    }

    return performer;
}

export function normalizePerformer(performer = {}) {
    const mode = performer?.carrier_mode === CARRIER_MODE_SPLIT ? CARRIER_MODE_SPLIT : CARRIER_MODE_SINGLE;
    const normalized = {
        stage: performer?.stage ?? '',
        carrier_mode: mode,
        contractor_id: performer?.contractor_id ?? null,
        contractor_name: performer?.contractor_name ?? null,
        fleet_vehicle_id: performer?.fleet_vehicle_id ?? null,
        fleet_driver_id: performer?.fleet_driver_id ?? null,
        split_carriers: [],
    };

    if (mode === CARRIER_MODE_SPLIT) {
        const slots = Array.isArray(performer?.split_carriers) ? performer.split_carriers : [];
        normalized.split_carriers = slots.length >= 2
            ? slots.map((row, index) => ({
                slot: Number(row?.slot ?? index + 1),
                contractor_id: row?.contractor_id ?? null,
                contractor_name: row?.contractor_name ?? null,
                fleet_vehicle_id: row?.fleet_vehicle_id ?? null,
                fleet_driver_id: row?.fleet_driver_id ?? null,
            }))
            : [blankSplitCarrier(1), blankSplitCarrier(2)];
    }

    return normalized;
}

export function isPerformerSplit(performer) {
    return performer?.carrier_mode === CARRIER_MODE_SPLIT;
}

export function splitCarrierSlotLabel(slot) {
    return `Исполнитель ${slot}`;
}

export function performerFleetCacheKey(legIndex, slotIndex = null) {
    return slotIndex === null ? String(legIndex) : `${legIndex}-${slotIndex}`;
}

export function expandPerformersForCarrierSlots(performers) {
    const list = Array.isArray(performers) ? performers : [];
    const expanded = [];

    list.forEach((performer) => {
        if (isPerformerSplit(performer)) {
            (performer.split_carriers ?? []).forEach((slot) => {
                expanded.push({
                    stage: performer.stage,
                    carrier_slot: Number(slot.slot ?? 1),
                    contractor_id: slot.contractor_id ?? null,
                    contractor_name: slot.contractor_name ?? null,
                });
            });

            return;
        }

        expanded.push({
            stage: performer.stage,
            carrier_slot: null,
            contractor_id: performer.contractor_id ?? null,
            contractor_name: performer.contractor_name ?? null,
        });
    });

    return expanded;
}

export function costMatchesPerformerSlot(cost, performer, slot = null) {
    const costSlot = cost?.carrier_slot ?? null;
    const targetSlot = slot?.slot ?? slot ?? null;

    if (String(cost?.stage ?? '') !== String(performer?.stage ?? '')) {
        return false;
    }

    if (!isPerformerSplit(performer)) {
        return costSlot === null || costSlot === undefined || costSlot === '';
    }

    return Number(costSlot) === Number(targetSlot);
}

export function contractorCostRowsFromPerformers(performers) {
    const rows = [];

    (Array.isArray(performers) ? performers : []).forEach((performer) => {
        if (isPerformerSplit(performer)) {
            (performer.split_carriers ?? []).forEach((slot) => {
                rows.push({
                    performer,
                    slot,
                    stage: performer.stage,
                    carrier_slot: Number(slot.slot ?? 1),
                    contractor_id: slot.contractor_id ?? null,
                });
            });

            return;
        }

        rows.push({
            performer,
            slot: null,
            stage: performer.stage,
            carrier_slot: null,
            contractor_id: performer.contractor_id ?? null,
        });
    });

    return rows;
}
