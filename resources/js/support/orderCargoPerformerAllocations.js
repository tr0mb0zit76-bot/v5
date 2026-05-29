/**
 * Распределение груза по исполнителям (плечо + слот).
 */

/** Канонический ключ плеча для хранения и сопоставления (`leg_1`, не «Плечо 1»). */
export function normalizeAllocationStage(stage) {
    const raw = String(stage ?? '').trim();

    if (raw === '') {
        return '';
    }

    const legMatch = raw.match(/^leg_(\d+)$/i);

    if (legMatch) {
        return `leg_${legMatch[1]}`;
    }

    const plechoMatch = raw.match(/^Плечо\s+(\d+)$/);

    if (plechoMatch) {
        return `leg_${plechoMatch[1]}`;
    }

    return raw;
}

export function allocationColumnKey(stage, carrierSlot) {
    const normalizedStage = normalizeAllocationStage(stage);
    const slot = carrierSlot === null || carrierSlot === undefined || carrierSlot === ''
        ? 0
        : Number(carrierSlot);

    return `${normalizedStage}::${Number.isFinite(slot) && slot > 0 ? slot : 0}`;
}

/**
 * @param {Array<{ stage?: string, carrier_mode?: string, split_carriers?: Array<{ slot?: number, contractor_id?: number|null, contractor_name?: string|null }>, contractor_id?: number|null, contractor_name?: string|null }>} performers
 * @param {(stage: string) => string} stageLabelFn
 * @param {(slot: number) => string} slotLabelFn
 * @param {(performer: object) => boolean} isSplitFn
 * @param {(stage: string, carrierSlot: number|null) => string} contractorNameFn
 * @returns {Array<{ stage: string, carrier_slot: number|null, key: string, label: string }>}
 */
export function performerAllocationColumns(performers, stageLabelFn, slotLabelFn, isSplitFn, contractorNameFn = () => '') {
    const columns = [];

    (performers ?? []).forEach((performer) => {
        const stage = normalizeAllocationStage(performer?.stage);
        if (stage === '') {
            return;
        }

        if (isSplitFn(performer) && Array.isArray(performer.split_carriers) && performer.split_carriers.length > 0) {
            performer.split_carriers.forEach((slot, index) => {
                const slotNumber = Number(slot?.slot ?? index + 1);
                columns.push({
                    stage,
                    carrier_slot: slotNumber,
                    key: allocationColumnKey(stage, slotNumber),
                    label: formatAllocationColumnLabel(
                        stage,
                        slotNumber,
                        stageLabelFn,
                        slotLabelFn,
                        contractorNameFn(stage, slotNumber) || slot?.contractor_name || '',
                    ),
                });
            });

            return;
        }

        columns.push({
            stage,
            carrier_slot: null,
            key: allocationColumnKey(stage, null),
            label: formatAllocationColumnLabel(
                stage,
                null,
                stageLabelFn,
                slotLabelFn,
                contractorNameFn(stage, null) || performer?.contractor_name || '',
            ),
        });
    });

    return columns;
}

function formatAllocationColumnLabel(stage, carrierSlot, stageLabelFn, slotLabelFn, contractorName) {
    const stagePart = stageLabelFn(stage);
    const name = String(contractorName ?? '').trim();

    if (name !== '') {
        return `${stagePart} · ${name}`;
    }

    if (carrierSlot !== null && carrierSlot !== undefined && carrierSlot !== '') {
        return `${stagePart} · ${slotLabelFn(Number(carrierSlot))}`;
    }

    return stagePart;
}

export function needsCargoPerformerAllocation(performers, isSplitFn) {
    if (!Array.isArray(performers) || performers.length === 0) {
        return false;
    }

    if (performers.length > 1) {
        return true;
    }

    return isSplitFn(performers[0]);
}

/**
 * @returns {Array<{ stage: string, carrier_slot: number|null, package_count: number|null, weight_value: number|null }>}
 */
export function normalizePerformerAllocations(raw) {
    if (!Array.isArray(raw)) {
        return [];
    }

    return raw
        .filter((row) => row && typeof row === 'object')
        .map((row) => ({
            stage: normalizeAllocationStage(row.stage),
            carrier_slot: row.carrier_slot === null || row.carrier_slot === undefined || row.carrier_slot === ''
                ? null
                : Number(row.carrier_slot),
            package_count: row.package_count === null || row.package_count === '' ? null : Number(row.package_count),
            weight_value: row.weight_value === null || row.weight_value === '' ? null : Number(row.weight_value),
        }))
        .filter((row) => row.stage !== '');
}

/**
 * @param {{ performer_allocations?: Array<object> }} item
 */
export function findCargoAllocation(item, stage, carrierSlot) {
    const key = allocationColumnKey(stage, carrierSlot);

    return (item.performer_allocations ?? []).find(
        (row) => allocationColumnKey(row.stage, row.carrier_slot) === key,
    ) ?? null;
}

/**
 * @param {{ performer_allocations: Array<object> }} item
 */
export function ensureCargoAllocation(item, stage, carrierSlot) {
    if (!Array.isArray(item.performer_allocations)) {
        item.performer_allocations = [];
    }

    const existing = findCargoAllocation(item, stage, carrierSlot);
    if (existing) {
        return existing;
    }

    const row = {
        stage: normalizeAllocationStage(stage),
        carrier_slot: carrierSlot === null || carrierSlot === undefined || carrierSlot === ''
            ? null
            : Number(carrierSlot),
        package_count: null,
        weight_value: null,
    };
    item.performer_allocations.push(row);

    return row;
}

/** Вес одного места по строке груза (кг). */
export function cargoLinePerPlaceWeightKg(item) {
    const perPlace = Number(item.weight_value ?? item.weight_kg ?? 0);
    if (!Number.isFinite(perPlace) || perPlace <= 0) {
        return 0;
    }

    return item.weight_unit === 't' ? perPlace * 1000 : perPlace;
}

export function cargoLineTotalPackages(item) {
    const places = Number(item.package_count ?? 0);

    return Number.isFinite(places) && places > 0 ? places : 0;
}

export function cargoLineExpectedWeightKg(item) {
    const perPlaceKg = cargoLinePerPlaceWeightKg(item);
    const places = cargoLineTotalPackages(item);

    if (perPlaceKg <= 0 || places <= 0) {
        return 0;
    }

    return perPlaceKg * places;
}

/**
 * Вес в ячейке: явный ввод или места × вес места из позиции груза.
 */
export function resolvedAllocationWeightKg(allocation, item) {
    const explicit = Number(allocation?.weight_value ?? 0);
    if (Number.isFinite(explicit) && explicit > 0) {
        return item.weight_unit === 't' ? explicit * 1000 : explicit;
    }

    const packages = Number(allocation?.package_count ?? 0);
    const perPlaceKg = cargoLinePerPlaceWeightKg(item);
    if (Number.isFinite(packages) && packages > 0 && perPlaceKg > 0) {
        return perPlaceKg * packages;
    }

    return 0;
}

/**
 * @param {Array<{ package_count?: number|null, weight_value?: number|null, weight_unit?: string }>} cargoItems
 */
export function summarizeAllocationsForColumn(cargoItems, stage, carrierSlot) {
    let totalPackages = 0;
    let totalWeightKg = 0;
    let hasAny = false;

    (cargoItems ?? []).forEach((item) => {
        const allocation = findCargoAllocation(item, stage, carrierSlot);
        if (!allocation) {
            return;
        }

        const packages = Number(allocation.package_count ?? 0);
        if (Number.isFinite(packages) && packages > 0) {
            totalPackages += packages;
            hasAny = true;
        }

        const weightKg = resolvedAllocationWeightKg(allocation, item);
        if (weightKg > 0) {
            totalWeightKg += weightKg;
            hasAny = true;
        }
    });

    return {
        totalPackages,
        totalWeightKg,
        hasAny,
    };
}

/**
 * @param {Array<{ stage: string, carrier_slot: number|null }>} columns
 */
function groupColumnsByStage(columns) {
    /** @type {Map<string, Array<{ stage: string, carrier_slot: number|null }>>} */
    const map = new Map();

    (columns ?? []).forEach((column) => {
        const stage = normalizeAllocationStage(column.stage);
        if (stage === '') {
            return;
        }

        if (!map.has(stage)) {
            map.set(stage, []);
        }

        map.get(stage).push(column);
    });

    return map;
}

/**
 * Статус строки груза: суммируем только внутри плеча с несколькими исполнителями (split).
 *
 * @param {{ package_count?: number|null, weight_value?: number|null, weight_unit?: string, performer_allocations?: Array<object> }} item
 * @param {Array<{ stage: string, carrier_slot: number|null }>} columns
 * @param {(stage: string) => string} [stageLabelFn]
 * @param {number} [weightToleranceKg=0.5]
 */
export function cargoAllocationRowStatus(item, columns, stageLabelFn = (stage) => stage, weightToleranceKg = 0.5) {
    const expectedPackages = cargoLineTotalPackages(item);
    const expectedWeightKg = cargoLineExpectedWeightKg(item);
    const stageGroups = groupColumnsByStage(columns);

    /** @type {Array<{ stage: string, stageLabel: string, stagePackages: number, stageWeightKg: number, isSplitLeg: boolean, packagesMismatch: boolean, weightMismatch: boolean }>} */
    const legStatuses = [];
    let hasAnyAllocation = false;
    let isMismatch = false;

    stageGroups.forEach((stageColumns, stage) => {
        let stagePackages = 0;
        let stageWeightKg = 0;

        stageColumns.forEach((column) => {
            const allocation = findCargoAllocation(item, column.stage, column.carrier_slot);
            if (!allocation) {
                return;
            }

            const packages = Number(allocation.package_count ?? 0);
            if (Number.isFinite(packages) && packages > 0) {
                stagePackages += packages;
                hasAnyAllocation = true;
            }

            stageWeightKg += resolvedAllocationWeightKg(allocation, item);
        });

        const isSplitLeg = stageColumns.length > 1;
        const packagesMismatch = isSplitLeg
            && expectedPackages > 0
            && Math.abs(stagePackages - expectedPackages) > 0.001;
        const weightMismatch = isSplitLeg
            && expectedWeightKg > 0
            && Math.abs(stageWeightKg - expectedWeightKg) > weightToleranceKg;

        if (packagesMismatch || weightMismatch) {
            isMismatch = true;
        }

        legStatuses.push({
            stage,
            stageLabel: stageLabelFn(stage),
            stagePackages,
            stageWeightKg,
            isSplitLeg,
            packagesMismatch,
            weightMismatch,
        });
    });

    return {
        expectedPackages,
        expectedWeightKg,
        legStatuses,
        hasAnyAllocation,
        isMismatch,
    };
}

/** @deprecated use cargoAllocationRowStatus */
export function cargoAllocationRowMismatch(item, weightToleranceKg = 0.5) {
    const status = cargoAllocationRowStatus(item, [], (stage) => stage, weightToleranceKg);

    return {
        expectedPackages: status.expectedPackages,
        expectedWeightKg: status.expectedWeightKg,
        allocatedPackages: 0,
        allocatedWeightKg: 0,
        packagesMismatch: status.isMismatch,
        weightMismatch: status.isMismatch,
        hasAllocation: status.hasAnyAllocation,
        isMismatch: status.isMismatch,
    };
}

/**
 * @param {Array<object>} cargoItems
 * @param {Array<{ stage: string, carrier_slot: number|null }>} columns
 * @param {boolean} requireFullAllocation
 * @param {(stage: string) => string} [stageLabelFn]
 * @returns {string[]}
 */
export function validateCargoPerformerAllocations(cargoItems, columns, requireFullAllocation, stageLabelFn = (stage) => stage) {
    const messages = [];

    (cargoItems ?? []).forEach((item, index) => {
        const title = String(item.name ?? '').trim() || `Груз ${index + 1}`;
        const status = cargoAllocationRowStatus(item, columns, stageLabelFn);

        if (!requireFullAllocation) {
            return;
        }

        if (!status.hasAnyAllocation && (status.expectedPackages > 0 || status.expectedWeightKg > 0)) {
            messages.push(`«${title}»: укажите распределение по исполнителям.`);

            return;
        }

        status.legStatuses.forEach((leg) => {
            if (!leg.isSplitLeg) {
                return;
            }

            if (leg.packagesMismatch) {
                messages.push(
                    `«${title}», ${leg.stageLabel}: мест — ${leg.stagePackages} из ${status.expectedPackages}.`,
                );
            }

            if (leg.weightMismatch) {
                messages.push(
                    `«${title}», ${leg.stageLabel}: вес — ${formatKg(leg.stageWeightKg)} из ${formatKg(status.expectedWeightKg)} кг.`,
                );
            }
        });
    });

    return messages;
}

function formatKg(value) {
    return Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: 2 });
}

/**
 * @param {Array<object>} cargoItems
 */
export function remapCargoAllocationsToCanonicalStages(cargoItems) {
    (cargoItems ?? []).forEach((item) => {
        if (!Array.isArray(item.performer_allocations)) {
            return;
        }

        item.performer_allocations.forEach((row) => {
            row.stage = normalizeAllocationStage(row.stage);
        });
    });
}

/**
 * @param {Array<object>} cargoItems
 * @param {Set<string>} allowedKeys
 */
export function pruneCargoAllocationsToColumns(cargoItems, allowedKeys) {
    (cargoItems ?? []).forEach((item) => {
        if (!Array.isArray(item.performer_allocations)) {
            item.performer_allocations = [];

            return;
        }

        item.performer_allocations = item.performer_allocations.filter((row) =>
            allowedKeys.has(allocationColumnKey(row.stage, row.carrier_slot)),
        );
    });
}

/**
 * Подсказка веса в ячейке (кг), если вес не введён вручную.
 */
export function allocationWeightPlaceholder(allocation, item) {
    const kg = resolvedAllocationWeightKg(allocation, item);
    if (kg <= 0) {
        return 'кг';
    }

    return String(Math.round(kg * 100) / 100);
}
