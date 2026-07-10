import { computed, nextTick, ref, watch } from 'vue';
import {
    routePointCityValue,
    setRoutePointCity,
    syncRoutePointCityFromAddress,
} from '@/support/routePointNormalizedData.js';
import {
    blankPerformer,
    blankSplitCarrier,
    CARRIER_MODE_SINGLE,
    CARRIER_MODE_SPLIT,
    EXECUTION_MODE_OWN_FLEET,
    isOwnFleetExecutionMode,
    isPerformerSplit,
    performerFleetCacheKey,
    splitCarrierSlotLabel,
} from '@/support/orderPerformers.js';
import { todayIsoDate } from '@/support/orderActualDates.js';
import {
    isVirtualOwnFleetContractor,
    OWN_FLEET_CONTRACTOR_NAME,
} from '@/support/ownFleetCatalog.js';
import { stageLabel, stageMatches, toStageKey } from '@/support/orderWizardStageHelpers.js';

export { CARRIER_MODE_SINGLE, CARRIER_MODE_SPLIT };

export function useOrderWizardRouteTab(deps) {
    const {
        form,
        props,
        contractors,
        carrierOptions,
        normalizeNullableNumber,
        blankRoutePoint,
        highlightRequiredField,
        openCounterpartyModal,
        onPerformerActualDateInput,
        onSplitActualDateInput,
        syncContractorCostsFromPerformers,
        syncCargoAllocationMatrixSlots,
        getContractorById,
        ensureContractorInLocalList,
        applyCarrierDefaultsByStage,
        normalizeContractorCost,
        normalizePaymentSchedule,
        blankPaymentSchedule,
        costMatchesPerformerSlot,
        MIN_CONTRACTOR_QUERY_LENGTH,
        crmFieldFluid,
        crmSegmented,
        crmSegmentedBtn,
        crmSegmentedBtnActive,
    } = deps;

    const borderCrossingLegPicker = deps.borderCrossingLegPicker ?? ref('');
    const carrierSearch = deps.carrierSearch ?? ref({});
    const showCarrierResults = deps.showCarrierResults ?? ref({});
    const serverCarrierSearchResults = deps.serverCarrierSearchResults ?? ref({});
    const isSearchingCarriers = deps.isSearchingCarriers ?? ref({});
    const carrierSearchTimers = deps.carrierSearchTimers ?? ref({});
    const carrierSearchAbortControllers = deps.carrierSearchAbortControllers ?? ref({});
    const carrierSearchFetchSeq = deps.carrierSearchFetchSeq ?? ref({});
    const fleetOptionsCache = deps.fleetOptionsCache ?? ref({});
    const addressSuggestions = deps.addressSuggestions ?? ref({});
    const addressTimers = deps.addressTimers ?? {};
    const draggedRoutePointIndex = deps.draggedRoutePointIndex ?? ref(null);
    const dragOverRoutePointIndex = deps.dragOverRoutePointIndex ?? ref(null);
    const maxActualDate = todayIsoDate();
    const routePointInlineBtn =
        'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';

function addPerformer() {
    const stage = `leg_${form.performers.length + 1}`;

    form.performers.push(blankPerformer(stage));
    syncContractorCostsFromPerformers();
    syncRoutePointsFromPerformers();
}

function setPerformerCarrierMode(legIndex, mode) {
    const performer = form.performers[legIndex];

    if (!performer || performer.carrier_mode === mode) {
        return;
    }

    if (mode === CARRIER_MODE_SPLIT) {
        const firstCarrier = performer.contractor_id
            ? {
                contractor_id: performer.contractor_id,
                contractor_name: performer.contractor_name,
                fleet_vehicle_id: performer.fleet_vehicle_id,
                fleet_driver_id: performer.fleet_driver_id,
            }
            : {};

        const stage = performer.stage;
        const singleCostRow = form.financial_term.contractors_costs.find(
            (cost) => costMatchesPerformerSlot(cost, performer, null),
        );

        performer.carrier_mode = CARRIER_MODE_SPLIT;
        const legDates = {
            loading_actual: performer.loading_actual || '',
            unloading_actual: performer.unloading_actual || '',
        };
        performer.split_carriers = [
            { ...blankSplitCarrier(1), ...firstCarrier, ...legDates },
            { ...blankSplitCarrier(2), ...legDates },
        ];
        performer.contractor_id = null;
        performer.contractor_name = null;
        performer.fleet_vehicle_id = null;
        performer.fleet_driver_id = null;
        performer.loading_actual = '';
        performer.unloading_actual = '';

        if (singleCostRow) {
            const sharedPayment = {
                payment_form: singleCostRow.payment_form,
                payment_schedule: JSON.parse(JSON.stringify(singleCostRow.payment_schedule ?? blankPaymentSchedule())),
                payment_terms: singleCostRow.payment_terms,
            };
            form.financial_term.contractors_costs = form.financial_term.contractors_costs.filter(
                (cost) => !stageMatches(cost.stage, stage),
            );
            performer.split_carriers.forEach((slot) => {
                if (!normalizeNullableNumber(slot.contractor_id)) {
                    return;
                }

                form.financial_term.contractors_costs.push(normalizeContractorCost({
                    ...sharedPayment,
                    stage,
                    carrier_slot: Number(slot.slot ?? 1),
                    contractor_id: slot.contractor_id,
                    amount: singleCostRow.amount,
                    currency: singleCostRow.currency ?? 'RUB',
                }));
            });
        }
    } else {
        const firstSlot = performer.split_carriers?.[0] ?? blankSplitCarrier(1);
        performer.carrier_mode = CARRIER_MODE_SINGLE;
        performer.contractor_id = firstSlot.contractor_id ?? null;
        performer.contractor_name = firstSlot.contractor_name ?? null;
        performer.fleet_vehicle_id = firstSlot.fleet_vehicle_id ?? null;
        performer.fleet_driver_id = firstSlot.fleet_driver_id ?? null;
        performer.loading_actual = firstSlot.loading_actual ?? '';
        performer.unloading_actual = firstSlot.unloading_actual ?? '';
        performer.split_carriers = [];
    }

    syncContractorCostsFromPerformers();
}

function addSplitCarrier(legIndex) {
    const performer = form.performers[legIndex];

    if (!performer || !isPerformerSplit(performer) || performer.split_carriers.length >= 4) {
        return;
    }

    performer.split_carriers.push(blankSplitCarrier(performer.split_carriers.length + 1));
    syncContractorCostsFromPerformers();
}

function removeSplitCarrier(legIndex, slotIndex) {
    const performer = form.performers[legIndex];

    if (!performer || !isPerformerSplit(performer) || performer.split_carriers.length <= 2) {
        return;
    }

    performer.split_carriers.splice(slotIndex, 1);
    performer.split_carriers = performer.split_carriers.map((slot, index) => ({
        ...slot,
        slot: index + 1,
    }));
    syncContractorCostsFromPerformers();
}

function parsePerformerCarrierTarget(kind, index) {
    if (kind === 'performer-slot') {
        const [legIndex, slotIndex] = String(index).split('-').map((value) => Number(value));

        return { legIndex, slotIndex, kind };
    }

    return { legIndex: Number(index), slotIndex: null, kind };
}

function splitCarrierAt(legIndex, slotIndex) {
    return form.performers[legIndex]?.split_carriers?.[slotIndex] ?? null;
}

function removePerformer(index) {
    const performer = form.performers[index];

    if (!performer) {
        return;
    }

    const removedStage = performer.stage;

    removeCarrierDocumentsForStage(removedStage);

    form.performers.splice(index, 1);
    form.route_points = form.route_points.filter((point) => !stageMatches(point.stage, removedStage));
    normalizeRoutePointSequences();

    if (form.performers.length > 0) {
        reindexLegStagesAndRemap();
    }

    if (form.performers.length <= 1) {
        form.financial_term.client_request_mode = 'single_request';
    }

    syncContractorCostsFromPerformers();
    syncCargoAllocationMatrixSlots({ pruneOrphans: true });
}

function remapStageReferences(fromStage, toStage) {
    if (stageMatches(fromStage, toStage)) {
        return;
    }

    form.route_points.forEach((point) => {
        if (stageMatches(point.stage, fromStage)) {
            point.stage = toStage;
        }
    });

    form.financial_term.contractors_costs.forEach((row) => {
        if (isAdditionalContractorCost(row)) {
            return;
        }

        if (stageMatches(row.stage, fromStage)) {
            row.stage = toStage;
        }
    });

    form.documents.forEach((doc) => {
        if (doc.party === 'carrier' && doc.stage && stageMatches(doc.stage, fromStage)) {
            doc.stage = toStage;
        }
    });
}

/**
 * После удаления плеча оставшиеся «Плечо 2» и т.д. перенумеровываются в leg_1, leg_2…
 */
function reindexLegStagesAndRemap() {
    const oldStages = form.performers.map((p) => p.stage);

    form.performers = form.performers.map((performer, i) => ({
        ...performer,
        stage: `leg_${i + 1}`,
    }));

    const newStages = form.performers.map((p) => p.stage);

    for (let i = 0; i < form.performers.length; i++) {
        if (!stageMatches(oldStages[i], newStages[i])) {
            remapStageReferences(oldStages[i], newStages[i]);
        }
    }
}

function removeCarrierDocumentsForStage(stage) {
    form.documents = form.documents.filter((doc) => {
        if (doc.party !== 'carrier' || !doc.stage) {
            return true;
        }

        return !stageMatches(doc.stage, stage);
    });
}

/**
 * Убирает плечи, для которых не осталось ни одной точки маршрута (например после удаления этапов).
 */
function pruneEmptyLegPerformers() {
    const stagesWithPoints = new Set(form.route_points.map((p) => toStageKey(p.stage)));
    const before = form.performers.length;

    const filtered = form.performers.filter((p) => stagesWithPoints.has(toStageKey(p.stage)));

    if (filtered.length === before) {
        return;
    }

    const removedStages = form.performers
        .filter((p) => !stagesWithPoints.has(toStageKey(p.stage)))
        .map((p) => p.stage);

    removedStages.forEach((stage) => removeCarrierDocumentsForStage(stage));

    form.performers = filtered;

    if (form.performers.length === 0) {
        form.performers = [blankPerformer('leg_1')];
        syncRoutePointsFromPerformers();
    } else {
        reindexLegStagesAndRemap();
    }

    if (form.performers.length <= 1) {
        form.financial_term.client_request_mode = 'single_request';
    }

    syncContractorCostsFromPerformers();
}

function onRoutePointLegChanged() {
    nextTick(() => {
        pruneEmptyLegPerformers();
    });
}

function carrierSearchKey(kind, index) {
    return `${kind}-${index}`;
}

function carrierSearchValue(kind, index) {
    return carrierSearch.value[carrierSearchKey(kind, index)] ?? '';
}

function setCarrierSearchValue(kind, index, value) {
    carrierSearch.value = {
        ...carrierSearch.value,
        [carrierSearchKey(kind, index)]: value,
    };
}

function setCarrierResultsVisible(kind, index, visible) {
    showCarrierResults.value = {
        ...showCarrierResults.value,
        [carrierSearchKey(kind, index)]: visible,
    };
}

function isCarrierResultsVisible(kind, index) {
    return Boolean(showCarrierResults.value[carrierSearchKey(kind, index)]);
}

function performerCarrierSearchLabel(performerIndex, contractorId) {
    const row = form.performers[performerIndex];
    if (isOwnFleetExecutionMode(row?.execution_mode)) {
        return OWN_FLEET_CONTRACTOR_NAME;
    }

    const id = normalizeNullableNumber(contractorId);
    if (id === null) {
        return '';
    }

    const contractor = getContractorById(id);
    const fromLookup = contractor?.name ? String(contractor.name).trim() : '';
    if (fromLookup) {
        return fromLookup;
    }

    const fromRow = row?.contractor_name ? String(row.contractor_name).trim() : '';

    return fromRow || '';
}

function filteredCarrierResults(kind, index) {
    const query = carrierSearchValue(kind, index).trim().toLowerCase();
    let selectedContractorId = null;

    if (kind === 'performer-slot') {
        const target = parsePerformerCarrierTarget(kind, index);
        selectedContractorId = normalizeNullableNumber(splitCarrierAt(target.legIndex, target.slotIndex)?.contractor_id);
    } else if (kind === 'performer') {
        selectedContractorId = normalizeNullableNumber(form.performers[index]?.contractor_id);
    } else {
        selectedContractorId = normalizeNullableNumber(form.financial_term.contractors_costs[index]?.contractor_id);
    }

    const selectedContractor = getContractorById(selectedContractorId);

    const excludeVirtualOwnFleet = (contractors) => {
        if (!props.ownFleetContractor?.id) {
            return contractors;
        }

        const ownFleetId = Number(props.ownFleetContractor.id);

        return contractors.filter(
            (contractor) => contractor.id !== ownFleetId && !isVirtualOwnFleetContractor(contractor),
        );
    };

    // Get server search results for this specific field
    const serverResults = serverCarrierSearchResults.value[carrierSearchKey(kind, index)] || [];
    const serverIds = new Set(serverResults.map(c => c.id));

    if (query === '' || query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        const visibleContractors = excludeVirtualOwnFleet(carrierOptions.value.slice(0, 50));

        if (!selectedContractor || visibleContractors.some((contractor) => contractor.id === selectedContractor.id)) {
            return visibleContractors;
        }

        if (isVirtualOwnFleetContractor(selectedContractor)) {
            return visibleContractors;
        }

        return [selectedContractor, ...visibleContractors.slice(0, 49)];
    }

    // Combine server results with local results
    const localResults = carrierOptions.value
        .filter((contractor) => [contractor.name, contractor.full_name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .filter(c => !serverIds.has(c.id));

    return excludeVirtualOwnFleet([...serverResults, ...localResults].slice(0, 50));
}

function splitCarrierSearchLabel(legIndex, slotIndex, contractorId) {
    const slot = splitCarrierAt(legIndex, slotIndex);
    if (isOwnFleetExecutionMode(slot?.execution_mode)) {
        return OWN_FLEET_CONTRACTOR_NAME;
    }

    const id = normalizeNullableNumber(contractorId);
    if (id === null) {
        return '';
    }

    const contractor = getContractorById(id);
    const fromLookup = contractor?.name ? String(contractor.name).trim() : '';
    if (fromLookup) {
        return fromLookup;
    }

    const fromRow = slot?.contractor_name ? String(slot.contractor_name).trim() : '';

    return fromRow || '';
}

function selectOwnFleetPerformer(index) {
    const ownFleet = props.ownFleetContractor;
    if (!ownFleet?.id) {
        return;
    }

    ensureContractorInLocalList({
        ...ownFleet,
        type: 'carrier',
    });

    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: Number(ownFleet.id),
        contractor_name: ownFleet.name ? String(ownFleet.name).trim() || null : null,
        execution_mode: EXECUTION_MODE_OWN_FLEET,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, OWN_FLEET_CONTRACTOR_NAME);
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    loadFleetOptionsForLeg(index);
}

function selectOwnFleetSplitSlot(legIndex, slotIndex) {
    const ownFleet = props.ownFleetContractor;
    if (!ownFleet?.id) {
        return;
    }

    ensureContractorInLocalList({
        ...ownFleet,
        type: 'carrier',
    });

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = Number(ownFleet.id);
    slot.contractor_name = ownFleet.name ? String(ownFleet.name).trim() || null : null;
    slot.execution_mode = EXECUTION_MODE_OWN_FLEET;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, OWN_FLEET_CONTRACTOR_NAME);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    loadFleetOptionsForLeg(legIndex, slotIndex);
}

function selectSplitPerformerContractor(legIndex, slotIndex, contractor) {
    if (isVirtualOwnFleetContractor(contractor)) {
        selectOwnFleetSplitSlot(legIndex, slotIndex);

        return;
    }

    ensureContractorInLocalList(contractor);

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = Number(contractor.id);
    slot.contractor_name = contractor.name ? String(contractor.name).trim() || null : null;
    slot.execution_mode = null;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, contractor.name);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[legIndex].stage, contractor.id, slot.slot);
    loadFleetOptionsForLeg(legIndex, slotIndex);
}

function clearSplitPerformerContractor(legIndex, slotIndex) {
    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = null;
    slot.contractor_name = null;
    slot.execution_mode = null;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, '');
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    fleetOptionsCache.value = {
        ...fleetOptionsCache.value,
        [performerFleetCacheKey(legIndex, slotIndex)]: { vehicles: [], drivers: [] },
    };
}

function onSplitPerformerCarrierInput(legIndex, slotIndex, value) {
    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, value);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, true);

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    const typed = String(value ?? '').trim().toLowerCase();
    const selectedContractor = getContractorById(slot.contractor_id);
    const selectedName = String(selectedContractor?.name ?? slot.contractor_name ?? '').trim().toLowerCase();

    if (typed === '') {
        clearSplitPerformerContractor(legIndex, slotIndex);

        return;
    }

    if (normalizeNullableNumber(slot.contractor_id) !== null && selectedName !== '' && selectedName !== typed) {
        slot.contractor_id = null;
        slot.contractor_name = null;
        slot.execution_mode = null;
        slot.fleet_vehicle_id = null;
        slot.fleet_driver_id = null;
        syncContractorCostsFromPerformers();
    }
}

function restoreSplitPerformerCarrierSearch(legIndex, slotIndex) {
    window.setTimeout(() => {
        const slot = splitCarrierAt(legIndex, slotIndex);
        if (!slot) {
            return;
        }

        setCarrierSearchValue(
            'performer-slot',
            `${legIndex}-${slotIndex}`,
            splitCarrierSearchLabel(legIndex, slotIndex, slot.contractor_id),
        );
        setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    }, 120);
}

function selectPerformerContractor(index, contractor) {
    if (isVirtualOwnFleetContractor(contractor)) {
        selectOwnFleetPerformer(index);

        return;
    }

    ensureContractorInLocalList(contractor);

    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: Number(contractor.id),
        contractor_name: contractor.name ? String(contractor.name).trim() || null : null,
        execution_mode: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, contractor.name);
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[index].stage, contractor.id);
    loadFleetOptionsForLeg(index);
}

function clearPerformerContractor(index) {
    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: null,
        contractor_name: null,
        execution_mode: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, '');
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    fleetOptionsCache.value = { ...fleetOptionsCache.value, [index]: { vehicles: [], drivers: [] } };
}

function syncPerformerContractor(stage, contractorId) {
    const performer = form.performers.find((item) => stageMatches(item.stage, stage));

    if (!performer) {
        return;
    }

    performer.contractor_id = contractorId !== null ? Number(contractorId) : null;
    performer.contractor_name = null;
}

function onPerformerCarrierInput(index, value) {
    setCarrierSearchValue('performer', index, value);
    setCarrierResultsVisible('performer', index, true);

    const performer = form.performers[index];
    if (!performer) {
        return;
    }

    const typed = String(value ?? '').trim().toLowerCase();
    const selectedContractor = getContractorById(performer.contractor_id);
    const selectedName = String(selectedContractor?.name ?? performer.contractor_name ?? '').trim().toLowerCase();

    if (typed === '') {
        clearPerformerContractor(index);

        return;
    }

    if (normalizeNullableNumber(performer.contractor_id) !== null && selectedName !== '' && selectedName !== typed) {
        performer.contractor_id = null;
        performer.contractor_name = null;
        performer.execution_mode = null;
        performer.fleet_vehicle_id = null;
        performer.fleet_driver_id = null;
        syncContractorCostsFromPerformers();
    }
}

function restorePerformerCarrierSearch(index) {
    window.setTimeout(() => {
        const performer = form.performers[index];
        if (!performer) {
            return;
        }

        setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, performer.contractor_id));
        setCarrierResultsVisible('performer', index, false);
    }, 120);
}

async function loadFleetOptionsForLeg(legIndex, slotIndex = null) {
    const cacheKey = performerFleetCacheKey(legIndex, slotIndex);
    let contractorId = null;

    if (slotIndex !== null) {
        contractorId = normalizeNullableNumber(splitCarrierAt(legIndex, slotIndex)?.contractor_id);
    } else {
        contractorId = normalizeNullableNumber(form.performers[legIndex]?.contractor_id);
    }

    if (!contractorId) {
        fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles: [], drivers: [] } };

        return;
    }

    const requestOptions = {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'include',
    };

    const loadVehicles = fetch(`${route('fleet.options.vehicles')}?owner_contractor_id=${contractorId}`, requestOptions)
        .then(async (response) => {
            if (!response.ok) {
                return [];
            }

            const payload = await response.json();

            return Array.isArray(payload?.vehicles) ? payload.vehicles : [];
        })
        .catch(() => []);

    const loadDrivers = fetch(`${route('fleet.options.drivers')}?carrier_contractor_id=${contractorId}`, requestOptions)
        .then(async (response) => {
            if (!response.ok) {
                return [];
            }

            const payload = await response.json();

            return Array.isArray(payload?.drivers) ? payload.drivers : [];
        })
        .catch(() => []);

    const [vehicles, drivers] = await Promise.all([loadVehicles, loadDrivers]);
    const existing = fleetOptionsCache.value[cacheKey] ?? { vehicles: [], drivers: [] };
    const mergedVehicles = [...vehicles];
    const mergedDrivers = [...drivers];

    for (const item of existing.vehicles) {
        if (!mergedVehicles.some((entry) => Number(entry.id) === Number(item.id))) {
            mergedVehicles.push(item);
        }
    }

    for (const item of existing.drivers) {
        if (!mergedDrivers.some((entry) => Number(entry.id) === Number(item.id))) {
            mergedDrivers.push(item);
        }
    }

    fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles: mergedVehicles, drivers: mergedDrivers } };
}

function seedFleetOptionsFromPerformer(legIndex, slotIndex = null) {
    const cacheKey = performerFleetCacheKey(legIndex, slotIndex);
    const target = slotIndex !== null ? splitCarrierAt(legIndex, slotIndex) : form.performers[legIndex];

    if (!target) {
        return;
    }

    const vehicleId = normalizeNullableNumber(target.fleet_vehicle_id);
    const driverId = normalizeNullableNumber(target.fleet_driver_id);

    if (vehicleId === null && driverId === null) {
        return;
    }

    const existing = fleetOptionsCache.value[cacheKey] ?? { vehicles: [], drivers: [] };
    const vehicles = [...existing.vehicles];
    const drivers = [...existing.drivers];

    if (vehicleId !== null && target.fleet_vehicle_label && !vehicles.some((entry) => Number(entry.id) === vehicleId)) {
        vehicles.push({ id: vehicleId, label: target.fleet_vehicle_label });
    }

    if (driverId !== null && target.fleet_driver_label && !drivers.some((entry) => Number(entry.id) === driverId)) {
        drivers.push({ id: driverId, label: target.fleet_driver_label });
    }

    fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles, drivers } };
}

function preloadFleetOptionsForPerformers() {
    form.performers.forEach((performer, legIndex) => {
        if (isPerformerSplit(performer)) {
            performer.split_carriers.forEach((slot, slotIndex) => {
                seedFleetOptionsFromPerformer(legIndex, slotIndex);

                if (normalizeNullableNumber(slot.contractor_id) !== null) {
                    loadFleetOptionsForLeg(legIndex, slotIndex);
                }
            });

            return;
        }

        seedFleetOptionsFromPerformer(legIndex);

        if (normalizeNullableNumber(performer.contractor_id) !== null) {
            loadFleetOptionsForLeg(legIndex);
        }
    });
}

function fleetVehicleOptionsForLeg(legIndex, slotIndex = null) {
    return fleetOptionsCache.value[performerFleetCacheKey(legIndex, slotIndex)]?.vehicles ?? [];
}

function fleetDriverOptionsForLeg(legIndex, slotIndex = null) {
    return fleetOptionsCache.value[performerFleetCacheKey(legIndex, slotIndex)]?.drivers ?? [];
}

const routeChainLabel = computed(() => {
    if (form.route_points.length === 0) {
        return 'Маршрут пока не задан';
    }

    return form.route_points
        .slice()
        .sort((left, right) => Number(left.sequence ?? 0) - Number(right.sequence ?? 0))
        .map((point) => {
            if (point.type === 'border_crossing') {
                const parts = [
                    String(form.customs_post_code ?? '').trim(),
                    String(form.svh_name ?? '').trim(),
                    String(form.svh_address ?? '').trim(),
                ].filter((s) => s !== '');
                const label = parts.length > 0 ? parts.join(' · ') : 'СВХ / пост не указаны';

                return `${routePointTypeHeading(point.type)}: ${label}`;
            }

            return `${routePointTypeHeading(point.type)}: ${point.address || 'адрес не указан'}`;
        })
        .join(' → ');
});

const hasBorderCrossingPoint = computed(
    () => Array.isArray(form.route_points) && form.route_points.some((p) => p.type === 'border_crossing'),
);

function addRoutePoint(type) {
    form.route_points.push(blankRoutePoint(
        type,
        form.route_points.length + 1,
        form.performers[0]?.stage ?? 'leg_1',
    ));
}

function addRoutePointForLeg(stage, type) {
    const stagePoints = form.route_points
        .map((p, i) => ({ p, i }))
        .filter(({ p }) => stageMatches(p.stage, stage));
    let insertAt = form.route_points.length;
    if (type === 'border_crossing') {
        const firstUnload = stagePoints.find(({ p }) => p.type === 'unloading');
        if (firstUnload) {
            insertAt = firstUnload.i;
        } else if (stagePoints.length > 0) {
            insertAt = stagePoints[stagePoints.length - 1].i + 1;
        }
    } else if (stagePoints.length > 0) {
        insertAt = stagePoints[stagePoints.length - 1].i + 1;
    }
    form.route_points.splice(insertAt, 0, blankRoutePoint(type, 0, stage));
    normalizeRoutePointSequences();
}

function canRemoveRoutePoint(globalIndex) {
    const point = form.route_points[globalIndex];
    if (!point) {
        return false;
    }

    if (point.type === 'border_crossing') {
        return true;
    }

    const sameTypeOnLeg = form.route_points.filter(
        (candidate) => stageMatches(candidate.stage, point.stage) && candidate.type === point.type,
    ).length;

    return sameTypeOnLeg > 1;
}

function addRoutePointAfter(globalIndex) {
    const point = form.route_points[globalIndex];
    if (!point || point.type === 'border_crossing') {
        return;
    }

    form.route_points.splice(globalIndex + 1, 0, blankRoutePoint(point.type, 0, point.stage));
    normalizeRoutePointSequences();
}

function removeRoutePointAt(globalIndex) {
    if (!canRemoveRoutePoint(globalIndex)) {
        return;
    }

    removeItem(form.route_points, globalIndex);
}

function onBorderCrossingLegPickerChange() {
    const raw = borderCrossingLegPicker.value;
    if (raw === '' || raw === null || raw === undefined) {
        return;
    }
    const idx = Number.parseInt(String(raw), 10);
    if (!Number.isFinite(idx) || idx < 0) {
        borderCrossingLegPicker.value = '';

        return;
    }
    const performer = form.performers[idx];
    if (!performer) {
        borderCrossingLegPicker.value = '';

        return;
    }
    addRoutePointForLeg(performer.stage, 'border_crossing');
    borderCrossingLegPicker.value = '';
}

function routePointTypeHeading(type) {
    if (type === 'loading') {
        return 'Погрузка';
    }
    if (type === 'border_crossing') {
        return 'Граница';
    }

    return 'Выгрузка';
}

function routePointTimeBlockHeading(type) {
    if (type === 'loading') {
        return 'Время загрузки';
    }
    if (type === 'border_crossing') {
        return 'Окно (план)';
    }

    return 'Время выгрузки';
}

function routePointAddressHighlightValue(point) {
    if (point.type === 'border_crossing') {
        return String(point.address ?? '').trim() || String(point.planned_date ?? '').trim();
    }

    return point.address;
}

function normalizeRoutePointSequences() {
    form.route_points = form.route_points.map((point, index) => ({
        ...point,
        sequence: index + 1,
    }));
}

function syncRoutePointsFromPerformers() {
    const performerStages = form.performers.map((performer) => performer.stage);

    if (performerStages.length === 0) {
        form.route_points = [];

        return;
    }

    const existingPoints = Array.isArray(form.route_points)
        ? form.route_points.map((point, index) => ({
            ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), point.stage ?? performerStages[0]),
            ...point,
            stage: point.stage ?? performerStages[0],
        }))
        : [];

    const nextPoints = [];

    performerStages.forEach((stage) => {
        const stagePoints = existingPoints.filter((point) => stageMatches(point.stage, stage));
        const normalizedStagePoints = stagePoints.map((point) => ({
            ...point,
            stage,
        }));

        if (!normalizedStagePoints.some((point) => point.type === 'loading')) {
            normalizedStagePoints.unshift(blankRoutePoint('loading', 0, stage));
        }

        if (!normalizedStagePoints.some((point) => point.type === 'unloading')) {
            normalizedStagePoints.push(blankRoutePoint('unloading', 0, stage));
        }

        nextPoints.push(...normalizedStagePoints);
    });

    form.route_points = nextPoints.map((point, index) => ({
        ...point,
        sequence: index + 1,
    }));
}

function routePointOrdinal(index) {
    const currentPoint = form.route_points[index];

    return form.route_points
        .slice(0, index + 1)
        .filter((point) => point.type === currentPoint?.type)
        .length;
}

function routePointTitle(point, index) {
    const ordinal = routePointOrdinal(index);

    if (point.type === 'loading') {
        return `Погрузка ${ordinal}`;
    }
    if (point.type === 'border_crossing') {
        return `Прохождение границы ${ordinal}`;
    }

    return `Выгрузка ${ordinal}`;
}

function routePointCombinedContact(point) {
    if (point.type === 'loading') {
        return point.sender_contact || point.sender_phone || point.contact_person || point.contact_phone || '';
    }

    if (point.type === 'unloading') {
        return point.recipient_contact || point.recipient_phone || point.contact_person || point.contact_phone || '';
    }

    return point.contact_person || point.contact_phone || '';
}

function setRoutePointCombinedContact(point, value) {
    const normalizedValue = String(value ?? '').trim();
    point.contact_person = normalizedValue;
    point.contact_phone = '';

    if (point.type === 'loading') {
        point.sender_contact = normalizedValue;
        point.sender_phone = '';
    }

    if (point.type === 'unloading') {
        point.recipient_contact = normalizedValue;
        point.recipient_phone = '';
    }
}

/**
 * @return {Array<{ point: object, globalIndex: number }>}
 */
function routePointsWithIndicesForLeg(stage) {
    const result = [];

    form.route_points.forEach((point, globalIndex) => {
        if (stageMatches(point.stage, stage)) {
            result.push({ point, globalIndex });
        }
    });

    return result.sort((left, right) => Number(left.point.sequence ?? 0) - Number(right.point.sequence ?? 0));
}

function routePointsDragEnabled() {
    return form.performers.length <= 1;
}

function handleRoutePointDragStart(index, event) {
    if (!routePointsDragEnabled()) {
        return;
    }

    draggedRoutePointIndex.value = index;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
}

function handleRoutePointDragOver(index) {
    if (!routePointsDragEnabled()) {
        return;
    }

    if (draggedRoutePointIndex.value === null || draggedRoutePointIndex.value === index) {
        return;
    }

    dragOverRoutePointIndex.value = index;
}

function handleRoutePointDrop(targetIndex) {
    if (!routePointsDragEnabled()) {
        return;
    }

    const sourceIndex = draggedRoutePointIndex.value;

    if (sourceIndex === null || sourceIndex === targetIndex) {
        return;
    }

    const nextPoints = [...form.route_points];
    const [movedPoint] = nextPoints.splice(sourceIndex, 1);
    nextPoints.splice(targetIndex, 0, movedPoint);
    form.route_points = nextPoints;
    normalizeRoutePointSequences();
    draggedRoutePointIndex.value = null;
    dragOverRoutePointIndex.value = null;
}

function handleRoutePointDragEnd() {
    if (!routePointsDragEnabled()) {
        return;
    }

    draggedRoutePointIndex.value = null;
    dragOverRoutePointIndex.value = null;
}

function onRoutePointAddressInput(index) {
    const point = form.route_points[index];
    if (point) {
        syncRoutePointCityFromAddress(point);
    }
    queueAddressLookup(index);
}

function queueAddressLookup(index) {
    clearTimeout(addressTimers[index]);

    if (String(form.route_points[index]?.address ?? '').trim().length < 3) {
        addressSuggestions.value[index] = [];
        return;
    }

    addressTimers[index] = window.setTimeout(() => {
        fetchAddressSuggestions(index);
    }, 300);
}

async function fetchAddressSuggestions(index) {
    const query = form.route_points[index]?.address ?? '';

    try {
        const response = await fetch(`${route('orders.suggest-address')}?query=${encodeURIComponent(query)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        addressSuggestions.value[index] = Array.isArray(data.suggestions) ? data.suggestions : [];
    } catch (error) {
        console.error('Address suggestions error', error);
        addressSuggestions.value[index] = [];
    }
}

function selectAddress(index, suggestion) {
    const point = form.route_points[index];
    const existing = point.normalized_data || {};
    point.address = suggestion.value ?? '';
    const suggestedCity = suggestion.data?.city
        ?? suggestion.data?.settlement
        ?? suggestion.data?.city_with_type
        ?? existing.city
        ?? null;
    point.normalized_data = {
        ...existing,
        city: suggestedCity,
        region: suggestion.data?.region_with_type ?? suggestion.data?.region ?? existing.region ?? null,
        street: suggestion.data?.street_with_type ?? suggestion.data?.street ?? existing.street ?? null,
        house: suggestion.data?.house ?? existing.house ?? null,
        coordinates: {
            lat: suggestion.data?.geo_lat ?? existing.coordinates?.lat ?? null,
            lng: suggestion.data?.geo_lon ?? existing.coordinates?.lng ?? null,
        },
        kladr_id: suggestion.data?.kladr_id ?? existing.kladr_id ?? null,
        fias_id: suggestion.data?.fias_id ?? existing.fias_id ?? null,
    };
    syncRoutePointCityFromAddress(point);
    addressSuggestions.value[index] = [];
}

function performerHasLoadingActual(performer) {
    if (!performer) {
        return false;
    }

    if (isPerformerSplit(performer)) {
        return (performer.split_carriers ?? []).some(
            (slot) => slot?.loading_actual != null && String(slot.loading_actual).trim() !== '',
        );
    }

    return performer.loading_actual != null && String(performer.loading_actual).trim() !== '';
}

function wizardRouteLoadingHasActualDate() {
    if (Array.isArray(form.performers) && form.performers.some((performer) => performerHasLoadingActual(performer))) {
        return true;
    }

    if (!Array.isArray(form.route_points)) {
        return false;
    }

    return form.route_points.some(
        (p) => p.type === 'loading' && p.actual_date != null && String(p.actual_date).trim() !== '',
    );
}


    function removeItem(collection, index) {
        collection.splice(index, 1);
        nextTick(() => {
            pruneEmptyLegPerformers();
        });
    }

    watch(carrierSearch, (newSearchValues, oldSearchValues) => {
        for (const [key, value] of Object.entries(newSearchValues)) {
            const oldValue = oldSearchValues[key] || '';
            if (value !== oldValue) {
                const match = key.match(/^(\w+)-(\d+(?:-\d+)?)$/);
                if (match) {
                    const [, kind, indexStr] = match;
                    queueCarrierSearch(kind, indexStr, value);
                }
            }
        }
    }, { deep: true });

    function queueCarrierSearch(kind, index, query) {
        const key = carrierSearchKey(kind, index);

        if (carrierSearchTimers.value[key]) {
            clearTimeout(carrierSearchTimers.value[key]);
        }

        if (query.trim().length < MIN_CONTRACTOR_QUERY_LENGTH) {
            carrierSearchAbortControllers.value[key]?.abort();
            carrierSearchFetchSeq.value = {
                ...carrierSearchFetchSeq.value,
                [key]: (carrierSearchFetchSeq.value[key] ?? 0) + 1,
            };
            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [key]: [],
            };
            isSearchingCarriers.value = {
                ...isSearchingCarriers.value,
                [key]: false,
            };

            return;
        }

        carrierSearchTimers.value[key] = setTimeout(async () => {
            await searchCarriers(kind, index, query.trim());
        }, 550);
    }

    async function searchCarriers(kind, index, query) {
        if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
            const keyEmpty = carrierSearchKey(kind, index);
            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [keyEmpty]: [],
            };

            return;
        }

        const key = carrierSearchKey(kind, index);
        carrierSearchAbortControllers.value[key]?.abort();
        const ac = new AbortController();
        carrierSearchAbortControllers.value = {
            ...carrierSearchAbortControllers.value,
            [key]: ac,
        };
        const seq = (carrierSearchFetchSeq.value[key] ?? 0) + 1;
        carrierSearchFetchSeq.value = {
            ...carrierSearchFetchSeq.value,
            [key]: seq,
        };

        isSearchingCarriers.value = {
            ...isSearchingCarriers.value,
            [key]: true,
        };

        try {
            const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=carrier&limit=100`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                signal: ac.signal,
            });

            if (!response.ok) {
                throw new Error(`Carrier search failed with status ${response.status}`);
            }

            const data = await response.json();
            if (seq !== carrierSearchFetchSeq.value[key]) {
                return;
            }

            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [key]: data.contractors || [],
            };
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('Carrier search error', error);
            if (seq === carrierSearchFetchSeq.value[key]) {
                serverCarrierSearchResults.value = {
                    ...serverCarrierSearchResults.value,
                    [key]: [],
                };
            }
        } finally {
            if (seq === carrierSearchFetchSeq.value[key]) {
                isSearchingCarriers.value = {
                    ...isSearchingCarriers.value,
                    [key]: false,
                };
            }
        }
    }

    function registerInitialPerformerCarriers() {
        if (!Array.isArray(props.order?.performers)) {
            return;
        }

        props.order.performers.forEach((p, legIndex) => {
            const registerCarrier = (id, name) => {
                const normalizedId = normalizeNullableNumber(id);
                const normalizedName = name ? String(name).trim() : '';

                if (normalizedId !== null && normalizedName !== '') {
                    ensureContractorInLocalList({
                        id: normalizedId,
                        name: normalizedName,
                        type: 'carrier',
                        inn: null,
                        phone: null,
                        email: null,
                        is_own_company: false,
                    });
                }
            };

            if (p.carrier_mode === CARRIER_MODE_SPLIT && Array.isArray(p.split_carriers)) {
                p.split_carriers.forEach((slot, slotIndex) => {
                    registerCarrier(slot.contractor_id, slot.contractor_name);
                    setCarrierSearchValue(
                        'performer-slot',
                        `${legIndex}-${slotIndex}`,
                        splitCarrierSearchLabel(legIndex, slotIndex, slot.contractor_id),
                    );
                });

                return;
            }

            registerCarrier(p.contractor_id, p.contractor_name);
        });
    }

    function setupPerformersCarrierWatch() {
        watch(
            () => form.performers.map((performer) => ({
                stage: performer.stage,
                mode: performer.carrier_mode,
                contractor_id: performer.contractor_id,
                contractor_name: performer.contractor_name,
                split_carriers: (performer.split_carriers ?? []).map((slot) => [
                    slot.slot,
                    slot.contractor_id,
                    slot.contractor_name,
                    slot.fleet_vehicle_id,
                    slot.fleet_driver_id,
                ]),
            })),
            (performers, prev) => {
                performers.forEach((row, index) => {
                    const performer = form.performers[index];
                    if (!performer) {
                        return;
                    }

                    if (isPerformerSplit(performer)) {
                        performer.split_carriers.forEach((slot, slotIndex) => {
                            setCarrierSearchValue(
                                'performer-slot',
                                `${index}-${slotIndex}`,
                                splitCarrierSearchLabel(index, slotIndex, slot.contractor_id),
                            );

                            const prevSlot = prev?.[index]?.split_carriers?.[slotIndex];
                            const contractorChanged = prevSlot != null && prevSlot[1] !== slot.contractor_id;
                            if (contractorChanged) {
                                slot.fleet_vehicle_id = null;
                                slot.fleet_driver_id = null;
                            }
                            if (prev != null && contractorChanged) {
                                loadFleetOptionsForLeg(index, slotIndex);
                            }
                        });

                        return;
                    }

                    setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, row.contractor_id));
                    const costIndex = form.financial_term.contractors_costs.findIndex((cost) => stageMatches(cost.stage, row.stage));

                    if (costIndex !== -1) {
                        setCarrierSearchValue('cost', costIndex, performerCarrierSearchLabel(index, row.contractor_id));
                    }

                    const prevRow = prev?.[index];
                    if (prevRow && prevRow.contractor_id !== row.contractor_id) {
                        performer.fleet_vehicle_id = null;
                        performer.fleet_driver_id = null;
                    }

                    if (prev != null && prevRow && prevRow.contractor_id !== row.contractor_id) {
                        loadFleetOptionsForLeg(index);
                    }
                });
            },
            { deep: true, immediate: true },
        );
    }

    function setupInternationalTransportWatch() {
        watch(
            () => form.is_international_transport,
            (international) => {
                if (!international) {
                    form.route_points = form.route_points.filter((p) => p.type !== 'border_crossing');
                    normalizeRoutePointSequences();
                }
                borderCrossingLegPicker.value = '';
            },
        );
    }

    function initRouteTabSideEffects() {
        registerInitialPerformerCarriers();
        setupPerformersCarrierWatch();
        setupInternationalTransportWatch();
    }

    return {
        CARRIER_MODE_SINGLE,
        CARRIER_MODE_SPLIT,
        OWN_FLEET_CONTRACTOR_NAME,
        borderCrossingLegPicker,
        carrierSearch,
        showCarrierResults,
        fleetOptionsCache,
        addressSuggestions,
        draggedRoutePointIndex,
        dragOverRoutePointIndex,
        maxActualDate,
        routePointInlineBtn,
        routeChainLabel,
        hasBorderCrossingPoint,
        addPerformer,
        removePerformer,
        setPerformerCarrierMode,
        addSplitCarrier,
        removeSplitCarrier,
        stageLabel,
        toStageKey,
        stageMatches,
        onRoutePointLegChanged,
        carrierSearchKey,
        carrierSearchValue,
        setCarrierSearchValue,
        setCarrierResultsVisible,
        isCarrierResultsVisible,
        filteredCarrierResults,
        selectOwnFleetPerformer,
        selectOwnFleetSplitSlot,
        selectSplitPerformerContractor,
        clearSplitPerformerContractor,
        onSplitPerformerCarrierInput,
        restoreSplitPerformerCarrierSearch,
        selectPerformerContractor,
        clearPerformerContractor,
        onPerformerCarrierInput,
        restorePerformerCarrierSearch,
        loadFleetOptionsForLeg,
        fleetVehicleOptionsForLeg,
        fleetDriverOptionsForLeg,
        preloadFleetOptionsForPerformers,
        addRoutePointAfter,
        removeRoutePointAt,
        canRemoveRoutePoint,
        onBorderCrossingLegPickerChange,
        routePointTimeBlockHeading,
        routePointAddressHighlightValue,
        normalizeRoutePointSequences,
        syncRoutePointsFromPerformers,
        routePointTitle,
        routePointCombinedContact,
        setRoutePointCombinedContact,
        routePointsWithIndicesForLeg,
        routePointsDragEnabled,
        handleRoutePointDragStart,
        handleRoutePointDragOver,
        handleRoutePointDrop,
        handleRoutePointDragEnd,
        onRoutePointAddressInput,
        selectAddress,
        routePointCityValue,
        setRoutePointCity,
        syncRoutePointCityFromAddress,
        parsePerformerCarrierTarget,
        splitCarrierSearchLabel,
        performerCarrierSearchLabel,
        splitCarrierSlotLabel,
        isPerformerSplit,
        normalizeNullableNumber,
        highlightRequiredField,
        openCounterpartyModal,
        onPerformerActualDateInput,
        onSplitActualDateInput,
        wizardRouteLoadingHasActualDate,
        initRouteTabSideEffects,
        form,
        props,
        order: props.order,
        ownFleetContractor: props.ownFleetContractor,
        crmFieldFluid,
        crmSegmented,
        crmSegmentedBtn,
        crmSegmentedBtnActive,
    };
}
