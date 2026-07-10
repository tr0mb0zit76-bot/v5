import { computed, watch } from 'vue';
import { parseLocaleDecimal, sanitizeDecimalInput } from '@/support/wizardDictionaryHelpers.js';
import {
    allocationWeightPlaceholder,
    cargoAllocationRowStatus,
    cargoLinePerPlaceWeightKg,
    ensureCargoAllocation,
    findCargoAllocation,
    needsCargoPerformerAllocation,
    normalizePerformerAllocations,
    performerAllocationColumns,
    pruneCargoAllocationsToColumns,
    remapCargoAllocationsToCanonicalStages,
    summarizeAllocationsForColumn,
} from '@/support/orderCargoPerformerAllocations.js';
import { isPerformerSplit, splitCarrierSlotLabel } from '@/support/orderPerformers.js';
import { stageLabel, stageMatches } from '@/support/orderWizardStageHelpers.js';

export function buildNormalizeCargoItem(props, normalizeNullableNumber) {
    function dictionaryOptionByValue(options, value) {
        const normalized = normalizeNullableNumber(value);
        if (normalized === null) {
            return null;
        }

        return options.find((option) => Number(option.value) === normalized) ?? null;
    }

    function dictionaryOptionByCode(options, code) {
        const normalized = code ? String(code).trim() : '';
        if (normalized === '') {
            return null;
        }

        return options.find((option) => option.code === normalized) ?? null;
    }

    function defaultCargoTypeOption() {
        return props.cargoTypeOptions[0] ?? { value: 1, code: 'general', label: 'Общий груз' };
    }

    function normalizeDictionaryItems(rawItems, options, fallbackOption) {
        const items = Array.isArray(rawItems) ? rawItems : [];
        const normalized = items
            .map((item) => {
                if (!item || typeof item !== 'object') {
                    return null;
                }

                const option = dictionaryOptionByValue(options, item.id) ?? dictionaryOptionByCode(options, item.code);

                return {
                    id: normalizeNullableNumber(option?.value ?? item.id),
                    code: option?.code ?? item.code ?? null,
                    label: option?.label ?? item.label ?? '',
                };
            })
            .filter((item) => item && (item.id !== null || item.code || item.label));

        if (normalized.length > 0) {
            return normalized;
        }

        return fallbackOption
            ? [{
                id: normalizeNullableNumber(fallbackOption.value),
                code: fallbackOption.code ?? null,
                label: fallbackOption.label ?? '',
            }]
            : [];
    }

    function normalizeAtiCargoPayload(payload) {
        if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
            return { ...payload };
        }

        return {};
    }

    return function normalizeCargoItem(raw = {}) {
        const selectedCargoType = dictionaryOptionByValue(props.cargoTypeOptions, raw.cargo_type_id)
            ?? dictionaryOptionByCode(props.cargoTypeOptions, raw.cargo_type)
            ?? defaultCargoTypeOption();
        let cargoType = selectedCargoType.code ?? (raw.cargo_type && String(raw.cargo_type).trim() !== '' ? raw.cargo_type : 'general');
        if (cargoType === 'general' && Boolean(raw.dangerous_goods)) {
            cargoType = 'dangerous';
        }
        const effectiveCargoType = dictionaryOptionByCode(props.cargoTypeOptions, cargoType) ?? selectedCargoType;
        const selectedPackageType = dictionaryOptionByValue(props.packageTypeOptions, raw.pack_type_id)
            ?? dictionaryOptionByCode(props.packageTypeOptions, raw.package_type);
        const selectedLoadingType = dictionaryOptionByValue(props.loadingTypeOptions, raw.loading_type_id)
            ?? dictionaryOptionByCode(props.loadingTypeOptions, raw.loading_type_code)
            ?? dictionaryOptionByCode(props.loadingTypeOptions, Array.isArray(raw.loading_types) ? raw.loading_types[0] : null)
            ?? dictionaryOptionByCode(props.loadingTypeOptions, Array.isArray(props.order?.loading_types) ? props.order.loading_types[0] : null);
        const selectedTruckBodyType = dictionaryOptionByValue(props.truckBodyTypeOptions, raw.truck_body_type_id)
            ?? dictionaryOptionByCode(props.truckBodyTypeOptions, raw.truck_body_type_code);
        const selectedTrailerType = dictionaryOptionByValue(props.trailerTypeOptions, raw.trailer_type_id)
            ?? dictionaryOptionByCode(props.trailerTypeOptions, raw.trailer_type_code);
        const loadingTypeItems = normalizeDictionaryItems(raw.loading_type_items, props.loadingTypeOptions, selectedLoadingType);
        const truckBodyTypeItems = normalizeDictionaryItems(raw.truck_body_type_items, props.truckBodyTypeOptions, selectedTruckBodyType);
        const trailerTypeItems = normalizeDictionaryItems(raw.trailer_type_items, props.trailerTypeOptions, selectedTrailerType);
        const weightValue = raw.weight_value ?? raw.weight_kg ?? null;

        return {
            name: raw.name ?? '',
            description: raw.description ?? '',
            weight_value: weightValue,
            weight_kg: weightValue,
            weight_unit: raw.weight_unit === 't' ? 't' : 'kg',
            volume_m3: raw.volume_m3 ?? null,
            length_m: raw.length_m ?? null,
            width_m: raw.width_m ?? null,
            height_m: raw.height_m ?? null,
            diameter_m: raw.diameter_m ?? null,
            pack_type_id: selectedPackageType ? normalizeNullableNumber(selectedPackageType.value) : normalizeNullableNumber(raw.pack_type_id),
            pack_type_label: raw.pack_type_label ?? selectedPackageType?.label ?? '',
            package_type: raw.package_type ?? selectedPackageType?.code ?? null,
            loading_type_id: loadingTypeItems[0]?.id ?? normalizeNullableNumber(raw.loading_type_id),
            loading_type_ids: loadingTypeItems.map((item) => item.id).filter((id) => id !== null),
            loading_type_code: loadingTypeItems[0]?.code ?? raw.loading_type_code ?? null,
            loading_type_label: loadingTypeItems[0]?.label ?? raw.loading_type_label ?? '',
            loading_type_items: loadingTypeItems,
            truck_body_type_id: truckBodyTypeItems[0]?.id ?? normalizeNullableNumber(raw.truck_body_type_id),
            truck_body_type_ids: truckBodyTypeItems.map((item) => item.id).filter((id) => id !== null),
            truck_body_type_code: truckBodyTypeItems[0]?.code ?? raw.truck_body_type_code ?? null,
            truck_body_type_label: truckBodyTypeItems[0]?.label ?? raw.truck_body_type_label ?? '',
            truck_body_type_items: truckBodyTypeItems,
            trailer_type_id: trailerTypeItems[0]?.id ?? normalizeNullableNumber(raw.trailer_type_id),
            trailer_type_ids: trailerTypeItems.map((item) => item.id).filter((id) => id !== null),
            trailer_type_code: trailerTypeItems[0]?.code ?? raw.trailer_type_code ?? null,
            trailer_type_label: trailerTypeItems[0]?.label ?? raw.trailer_type_label ?? '',
            trailer_type_items: trailerTypeItems,
            package_count: raw.package_count ?? null,
            dangerous_goods: cargoType === 'dangerous',
            dangerous_class: raw.dangerous_class ?? '',
            hs_code: raw.hs_code ?? '',
            cargo_type_id: effectiveCargoType ? normalizeNullableNumber(effectiveCargoType.value) : normalizeNullableNumber(raw.cargo_type_id),
            cargo_type_label: raw.cargo_type_label ?? effectiveCargoType?.label ?? '',
            cargo_type: cargoType,
            is_oversized: Boolean(raw.is_oversized ?? cargoType === 'oversized'),
            is_fragile: Boolean(raw.is_fragile ?? cargoType === 'fragile'),
            ati_cargo_payload: normalizeAtiCargoPayload(raw.ati_cargo_payload),
            performer_allocations: normalizePerformerAllocations(
                raw.performer_allocations ?? normalizeAtiCargoPayload(raw.ati_cargo_payload).performer_allocations,
            ),
        };
    };
}

export function useOrderWizardCargoTab(deps) {
    const {
        form,
        props,
        getContractorById,
        highlightRequiredField,
        crmFieldFluid,
        removeItem,
        cargoAllocationFieldClass,
        normalizeNullableNumber,
    } = deps;

    const normalizeCargoItem = buildNormalizeCargoItem(props, normalizeNullableNumber);

    function dictionaryOptionByValue(options, value) {
        const normalized = normalizeNullableNumber(value);
        if (normalized === null) {
            return null;
        }

        return options.find((option) => Number(option.value) === normalized) ?? null;
    }

    function defaultCargoTypeOption() {
        return props.cargoTypeOptions[0] ?? { value: 1, code: 'general', label: 'Общий груз' };
    }

    function applyCargoTypeOption(item) {
        const option = dictionaryOptionByValue(props.cargoTypeOptions, item.cargo_type_id) ?? defaultCargoTypeOption();
        item.cargo_type_id = normalizeNullableNumber(option.value);
        item.cargo_type = option.code ?? item.cargo_type ?? 'general';
        item.cargo_type_label = option.label ?? '';
        item.dangerous_goods = item.cargo_type === 'dangerous';
        item.is_oversized = item.cargo_type === 'oversized';
        item.is_fragile = item.cargo_type === 'fragile';
    }

    function applyPackageTypeOption(item) {
        const option = dictionaryOptionByValue(props.packageTypeOptions, item.pack_type_id);
        item.pack_type_id = option ? normalizeNullableNumber(option.value) : null;
        item.package_type = option?.code ?? null;
        item.pack_type_label = option?.label ?? '';
    }

    function selectedDictionaryItems(options, ids) {
        if (!Array.isArray(ids)) {
            return [];
        }

        return ids
            .map((id) => dictionaryOptionByValue(options, id))
            .filter(Boolean)
            .map((option) => ({
                id: normalizeNullableNumber(option.value),
                code: option.code ?? null,
                label: option.label ?? '',
            }));
    }

    function dictionarySelectionLabel(items) {
        if (!Array.isArray(items) || items.length === 0) {
            return 'Выберите';
        }

        const labels = items
            .map((item) => item?.label)
            .filter((label) => label !== null && label !== undefined && String(label).trim() !== '')
            .map((label) => String(label).trim());

        if (labels.length === 0) {
            return 'Выбрано: ' + items.length;
        }

        return labels.length <= 2 ? labels.join(', ') : `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`;
    }

    function applyDictionaryItems(item, options, idsKey, idKey, codeKey, labelKey, itemsKey) {
        const selected = selectedDictionaryItems(options, item[idsKey]);
        const first = selected[0] ?? null;
        item[itemsKey] = selected;
        item[idKey] = first?.id ?? null;
        item[codeKey] = first?.code ?? null;
        item[labelKey] = first?.label ?? '';
    }

    function applyLoadingTypeOption(item) {
        applyDictionaryItems(item, props.loadingTypeOptions, 'loading_type_ids', 'loading_type_id', 'loading_type_code', 'loading_type_label', 'loading_type_items');
    }

    function applyTruckBodyTypeOption(item) {
        applyDictionaryItems(item, props.truckBodyTypeOptions, 'truck_body_type_ids', 'truck_body_type_id', 'truck_body_type_code', 'truck_body_type_label', 'truck_body_type_items');
    }

    function applyTrailerTypeOption(item) {
        applyDictionaryItems(item, props.trailerTypeOptions, 'trailer_type_ids', 'trailer_type_id', 'trailer_type_code', 'trailer_type_label', 'trailer_type_items');
    }

    function normalizeAtiCargoPayload(payload) {
        if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
            return { ...payload };
        }

        return {};
    }

    function cargoWeightInKg(item) {
        const value = parseLocaleDecimal(item.weight_value ?? item.weight_kg ?? 0) ?? 0;
        if (item.weight_unit === 't') {
            return value * 1000;
        }

        return value;
    }

    function cargoPackageCountFactor(item) {
        const n = parseLocaleDecimal(item.package_count);
        if (n !== null && n > 0) {
            return Math.trunc(n);
        }

        return 1;
    }

    function cargoLineTotalWeightKg(item) {
        return cargoWeightInKg(item) * cargoPackageCountFactor(item);
    }

    function cargoLineTotalVolumeM3(item) {
        const per = parseLocaleDecimal(item.volume_m3);
        if (per === null || per <= 0) {
            return 0;
        }

        return per * cargoPackageCountFactor(item);
    }

    function cargoHasDimensions(item) {
        return [item.length_m, item.width_m, item.height_m].some((v) => v !== null && v !== undefined && String(v).trim() !== '');
    }

    function cargoDimensionsLabel(item) {
        const l = parseLocaleDecimal(item.length_m);
        const w = parseLocaleDecimal(item.width_m);
        const h = parseLocaleDecimal(item.height_m);

        const lengthLabel = l !== null ? l.toFixed(2) : '—';
        const widthLabel = w !== null ? w.toFixed(2) : '—';
        const heightLabel = h !== null ? h.toFixed(2) : '—';

        return `${lengthLabel}×${widthLabel}×${heightLabel} м`;
    }

    function cargoComputedVolumeM3(item) {
        const l = parseLocaleDecimal(item.length_m);
        const w = parseLocaleDecimal(item.width_m);
        const h = parseLocaleDecimal(item.height_m);

        if (l === null || w === null || h === null || l <= 0 || w <= 0 || h <= 0) {
            return null;
        }

        return l * w * h;
    }

    function cargoDimensionFieldsEmpty(item) {
        return [item.length_m, item.width_m, item.height_m].every(
            (v) => v === null || v === undefined || v === '',
        );
    }

    function onCargoDecimalInput(item, field, event) {
        item[field] = sanitizeDecimalInput(event.target.value);

        if (field === 'weight_value') {
            item.weight_kg = item.weight_value;
        }
    }

    function selectedLoadingTypeCodes() {
        const fromCargo = form.cargo_items
            .flatMap((item) => Array.isArray(item.loading_type_items) && item.loading_type_items.length > 0
                ? item.loading_type_items.map((selected) => selected.code)
                : [item.loading_type_code])
            .filter((value) => value !== null && value !== undefined && String(value).trim() !== '')
            .map((value) => String(value).trim());

        return [...new Set(fromCargo.length > 0 ? fromCargo : (Array.isArray(form.loading_types) ? form.loading_types : []))];
    }

    const cargoSummary = computed(() => {
        return form.cargo_items.reduce((summary, item) => {
            summary.totalWeight += cargoLineTotalWeightKg(item);
            summary.totalVolume += cargoLineTotalVolumeM3(item);
            summary.totalPackages += Number(item.package_count || 0);

            return summary;
        }, {
            totalWeight: 0,
            totalVolume: 0,
            totalPackages: 0,
        });
    });

    const needsCargoPerformerAllocationUi = computed(() => needsCargoPerformerAllocation(form.performers, isPerformerSplit));

    function allocationColumnContractorName(stage, carrierSlot) {
        const performer = form.performers.find((row) => stageMatches(row.stage, stage));
        if (!performer) {
            return '';
        }

        if (isPerformerSplit(performer)) {
            const slotNumber = Number(carrierSlot ?? 1);
            const slot = (performer.split_carriers ?? []).find(
                (row, index) => Number(row?.slot ?? index + 1) === slotNumber,
            );
            const fromRow = String(slot?.contractor_name ?? '').trim();
            if (fromRow !== '') {
                return fromRow;
            }

            return String(getContractorById(slot?.contractor_id)?.name ?? '').trim();
        }

        const fromRow = String(performer.contractor_name ?? '').trim();
        if (fromRow !== '') {
            return fromRow;
        }

        return String(getContractorById(performer.contractor_id)?.name ?? '').trim();
    }

    const cargoPerformerAllocationColumns = computed(() =>
        performerAllocationColumns(
            form.performers,
            stageLabel,
            splitCarrierSlotLabel,
            isPerformerSplit,
            allocationColumnContractorName,
        ),
    );

    const cargoPerformerAllocationColumnSummaries = computed(() =>
        cargoPerformerAllocationColumns.value.map((column) => ({
            ...column,
            ...summarizeAllocationsForColumn(form.cargo_items, column.stage, column.carrier_slot),
        })),
    );

    const cargoAllocationRowStatuses = computed(() =>
        form.cargo_items.map((item) =>
            cargoAllocationRowStatus(item, cargoPerformerAllocationColumns.value, stageLabel),
        ),
    );

    function syncCargoAllocationMatrixSlots(options = {}) {
        if (!needsCargoPerformerAllocationUi.value) {
            return;
        }

        remapCargoAllocationsToCanonicalStages(form.cargo_items);

        if (options.pruneOrphans !== true) {
            return;
        }

        const allowedKeys = new Set(cargoPerformerAllocationColumns.value.map((column) => column.key));
        pruneCargoAllocationsToColumns(form.cargo_items, allowedKeys);
    }

    function syncAllocationWeightFromPackages(row, item) {
        const packages = Number(row.package_count ?? 0);
        if (!Number.isFinite(packages) || packages <= 0) {
            return;
        }

        const perPlaceKg = cargoLinePerPlaceWeightKg(item);
        if (perPlaceKg <= 0) {
            return;
        }

        const explicit = row.weight_value;
        if (explicit !== null && explicit !== '' && Number(explicit) > 0) {
            return;
        }

        const totalKg = perPlaceKg * packages;
        row.weight_value = item.weight_unit === 't'
            ? Math.round((totalKg / 1000) * 1000) / 1000
            : Math.round(totalKg * 100) / 100;
    }

    function allocationWeightFieldPlaceholder(item, column) {
        const allocation = findCargoAllocation(item, column.stage, column.carrier_slot);
        if (!allocation) {
            return 'кг';
        }

        return allocationWeightPlaceholder(allocation, item);
    }

    function onCargoAllocationPackagesInput(item, column, rawValue) {
        const row = ensureCargoAllocation(item, column.stage, column.carrier_slot);
        row.package_count = rawValue === '' || rawValue === null ? null : Number(rawValue);
        syncAllocationWeightFromPackages(row, item);
        touchCargoItemAllocations(item);
    }

    function onCargoAllocationWeightInput(item, column, rawValue) {
        const row = ensureCargoAllocation(item, column.stage, column.carrier_slot);
        row.weight_value = rawValue === '' || rawValue === null ? null : Number(rawValue);
        touchCargoItemAllocations(item);
    }

    function touchCargoItemAllocations(item) {
        if (!Array.isArray(item.performer_allocations)) {
            item.performer_allocations = [];
        }
        item.performer_allocations = [...item.performer_allocations];
    }

    function addCargoItem() {
        form.cargo_items.push(normalizeCargoItem({}));
    }

    function performerAllocationsForSubmitItem(item) {
        const columns = cargoPerformerAllocationColumns.value ?? [];
        const fromMatrix = columns
            .map((column) => {
                const row = findCargoAllocation(item, column.stage, column.carrier_slot);
                if (!row) {
                    return null;
                }

                const packageCount = row.package_count;
                const weightValue = row.weight_value;
                const hasPackages = packageCount !== null && packageCount !== '' && Number.isFinite(Number(packageCount));
                const hasWeight = weightValue !== null && weightValue !== '' && Number.isFinite(Number(weightValue));

                if (!hasPackages && !hasWeight) {
                    return null;
                }

                return {
                    stage: column.stage,
                    carrier_slot: column.carrier_slot,
                    package_count: hasPackages ? Number(packageCount) : null,
                    weight_value: hasWeight ? Number(weightValue) : null,
                };
            })
            .filter(Boolean);

        if (fromMatrix.length > 0) {
            return normalizePerformerAllocations(fromMatrix);
        }

        return normalizePerformerAllocations(item.performer_allocations);
    }

    function serializeCargoItemsForSubmit() {
        return form.cargo_items.map((item) => {
            const performerAllocations = performerAllocationsForSubmitItem(item);
            const atiBase = normalizeAtiCargoPayload(item.ati_cargo_payload);

            return {
                name: item.name,
                description: item.description,
                weight_value: item.weight_value ?? item.weight_kg,
                weight_kg: item.weight_value ?? item.weight_kg,
                weight_unit: item.weight_unit === 't' ? 't' : 'kg',
                volume_m3: item.volume_m3,
                length_m: item.length_m,
                width_m: item.width_m,
                height_m: item.height_m,
                diameter_m: item.diameter_m,
                package_type: item.package_type,
                pack_type_id: normalizeNullableNumber(item.pack_type_id),
                pack_type_label: item.pack_type_label,
                loading_type_id: normalizeNullableNumber(item.loading_type_id),
                loading_type_code: item.loading_type_code,
                loading_type_label: item.loading_type_label,
                loading_type_items: item.loading_type_items || [],
                truck_body_type_id: normalizeNullableNumber(item.truck_body_type_id),
                truck_body_type_code: item.truck_body_type_code,
                truck_body_type_label: item.truck_body_type_label,
                truck_body_type_items: item.truck_body_type_items || [],
                trailer_type_id: normalizeNullableNumber(item.trailer_type_id),
                trailer_type_code: item.trailer_type_code,
                trailer_type_label: item.trailer_type_label,
                trailer_type_items: item.trailer_type_items || [],
                package_count: item.package_count,
                dangerous_goods: item.dangerous_goods,
                dangerous_class: item.dangerous_class,
                hs_code: item.hs_code,
                cargo_type: item.cargo_type,
                cargo_type_id: normalizeNullableNumber(item.cargo_type_id),
                cargo_type_label: item.cargo_type_label,
                is_oversized: item.is_oversized,
                is_fragile: item.is_fragile,
                performer_allocations: performerAllocations,
                ati_cargo_payload: {
                    ...atiBase,
                    performer_allocations: performerAllocations,
                },
            };
        });
    }

    const tabContext = {
        form,
        highlightRequiredField,
        cargoTypeOptions: props.cargoTypeOptions,
        packageTypeOptions: props.packageTypeOptions,
        loadingTypeOptions: props.loadingTypeOptions,
        truckBodyTypeOptions: props.truckBodyTypeOptions,
        trailerTypeOptions: props.trailerTypeOptions,
        cargoTitleSuggestions: props.cargoTitleSuggestions,
        crmFieldFluid,
        removeItem,
        addCargoItem,
        applyCargoTypeOption,
        applyPackageTypeOption,
        applyLoadingTypeOption,
        applyTruckBodyTypeOption,
        applyTrailerTypeOption,
        dictionarySelectionLabel,
        onCargoDecimalInput,
        cargoComputedVolumeM3,
        cargoLineTotalWeightKg,
        cargoPackageCountFactor,
        cargoWeightInKg,
        cargoLineTotalVolumeM3,
        cargoHasDimensions,
        cargoDimensionsLabel,
        cargoSummary,
        needsCargoPerformerAllocationUi,
        cargoPerformerAllocationColumns,
        cargoPerformerAllocationColumnSummaries,
        cargoAllocationRowStatuses,
        cargoAllocationFieldClass,
        findCargoAllocation,
        onCargoAllocationPackagesInput,
        onCargoAllocationWeightInput,
        allocationWeightFieldPlaceholder,
    };

    function initCargoTabSideEffects() {
        watch(
            () => form.performers.map((performer, index) => ({
                index,
                stage: performer.stage,
                carrier_mode: performer.carrier_mode,
                split_count: Array.isArray(performer.split_carriers) ? performer.split_carriers.length : 0,
            })),
            () => {
                syncCargoAllocationMatrixSlots({ pruneOrphans: true });
            },
        );

        watch(
            () => form.cargo_items,
            (items) => {
                items.forEach((item) => {
                    item.dangerous_goods = item.cargo_type === 'dangerous';
                    const v = cargoComputedVolumeM3(item);
                    if (v !== null) {
                        item.volume_m3 = Math.round(v * 1000) / 1000;
                    } else if (!cargoDimensionFieldsEmpty(item)) {
                        item.volume_m3 = null;
                    }
                });
            },
            { deep: true, immediate: true },
        );
    }

    return {
        tabContext,
        normalizeCargoItem,
        initCargoTabSideEffects,
        serializeCargoItemsForSubmit,
        selectedLoadingTypeCodes,
        syncCargoAllocationMatrixSlots,
        cargoPerformerAllocationColumns,
        needsCargoPerformerAllocationUi,
    };
}
