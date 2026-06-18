export const CARRIER_MODE_SINGLE = 'single';
export const CARRIER_MODE_SPLIT = 'split';
export const EXECUTION_MODE_OWN_FLEET = 'own_fleet';

export function isOwnFleetExecutionMode(mode) {
    return mode === EXECUTION_MODE_OWN_FLEET;
}

export function blankSplitCarrier(slot = 1) {
    return {
        slot,
        contractor_id: null,
        contractor_name: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
        execution_mode: null,
        fleet_trip_id: null,
        loading_actual: '',
        unloading_actual: '',
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
        execution_mode: null,
        fleet_trip_id: null,
        loading_actual: '',
        unloading_actual: '',
        loading_special_conditions: '',
        unloading_special_conditions: '',
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
        execution_mode: isOwnFleetExecutionMode(performer?.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
        fleet_trip_id: performer?.fleet_trip_id ?? null,
        loading_actual: performer?.loading_actual ?? '',
        unloading_actual: performer?.unloading_actual ?? '',
        loading_special_conditions: String(performer?.loading_special_conditions ?? '').trim(),
        unloading_special_conditions: String(performer?.unloading_special_conditions ?? '').trim(),
        split_carriers: [],
    };

    if (mode === CARRIER_MODE_SPLIT) {
        const legacyLoading = performer?.loading_actual ?? '';
        const legacyUnloading = performer?.unloading_actual ?? '';
        const slots = Array.isArray(performer?.split_carriers) ? performer.split_carriers : [];
        normalized.loading_actual = '';
        normalized.unloading_actual = '';
        normalized.split_carriers = slots.length >= 2
            ? slots.map((row, index) => ({
                slot: Number(row?.slot ?? index + 1),
                contractor_id: row?.contractor_id ?? null,
                contractor_name: row?.contractor_name ?? null,
                fleet_vehicle_id: row?.fleet_vehicle_id ?? null,
                fleet_driver_id: row?.fleet_driver_id ?? null,
                execution_mode: isOwnFleetExecutionMode(row?.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
                fleet_trip_id: row?.fleet_trip_id ?? null,
                loading_actual: row?.loading_actual ?? legacyLoading ?? '',
                unloading_actual: row?.unloading_actual ?? legacyUnloading ?? '',
            }))
            : [blankSplitCarrier(1), blankSplitCarrier(2)];
    }

    return normalized;
}

export function isPerformerSplit(performer) {
    return performer?.carrier_mode === CARRIER_MODE_SPLIT;
}

export function performerUsesOwnFleet(performer) {
    if (isPerformerSplit(performer)) {
        return (performer.split_carriers ?? []).some((slot) => isOwnFleetExecutionMode(slot?.execution_mode));
    }

    return isOwnFleetExecutionMode(performer?.execution_mode);
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
                    execution_mode: slot.execution_mode ?? null,
                });
            });

            return;
        }

        expanded.push({
            stage: performer.stage,
            carrier_slot: null,
            contractor_id: performer.contractor_id ?? null,
            contractor_name: performer.contractor_name ?? null,
            execution_mode: performer.execution_mode ?? null,
        });
    });

    return expanded;
}

export function filterExternalCarrierSlots(expanded) {
    return (Array.isArray(expanded) ? expanded : []).filter(
        (row) => !isOwnFleetExecutionMode(row?.execution_mode),
    );
}

export function isOwnFleetCarrierOnly(performers) {
    const all = Array.isArray(performers) ? performers : [];

    if (all.length === 0) {
        return false;
    }

    const expanded = expandPerformersForCarrierSlots(all);

    if (expanded.length === 0) {
        return false;
    }

    return expanded.every((row) => isOwnFleetExecutionMode(row?.execution_mode));
}

export function isDedicatedAdditionalCostStage(stage) {
    const value = String(stage ?? '');

    return value === 'additional' || /^additional_\d+$/.test(value);
}

export function isAdditionalContractorCost(cost) {
    const stage = String(cost?.stage ?? '');

    return Boolean(cost?.is_additional) || isDedicatedAdditionalCostStage(stage);
}

export function nextAdditionalContractorCostStage(existingRows) {
    const rows = Array.isArray(existingRows) ? existingRows : [];
    let index = 1;

    while (rows.some((row) => String(row?.stage ?? '') === `additional_${index}`)) {
        index += 1;
    }

    return `additional_${index}`;
}

export function costMatchesPerformerSlot(cost, performer, slot = null) {
    if (isAdditionalContractorCost(cost)) {
        return false;
    }

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

export function resolveExecutionModeForSlot(performer, slot = null) {
    if (isPerformerSplit(performer)) {
        const targetSlot = slot?.slot ?? slot ?? null;

        return (performer.split_carriers ?? []).find(
            (row) => Number(row?.slot ?? 0) === Number(targetSlot),
        )?.execution_mode ?? null;
    }

    return performer?.execution_mode ?? null;
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
                    execution_mode: slot.execution_mode ?? null,
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
            execution_mode: performer.execution_mode ?? null,
        });
    });

    return rows;
}
