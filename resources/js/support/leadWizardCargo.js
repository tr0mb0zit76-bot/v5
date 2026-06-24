import {
    applyDictionaryItems,
    dictionaryOptionByCode,
    dictionaryOptionByValue,
    normalizeDictionaryItems,
    normalizeNullableNumber,
    parseLocaleDecimal,
} from '@/support/wizardDictionaryHelpers.js';

export function blankLeadCargoItem() {
    return {
        name: '',
        description: '',
        weight_value: null,
        weight_kg: null,
        weight_unit: 'kg',
        volume_m3: null,
        length_m: null,
        width_m: null,
        height_m: null,
        diameter_m: null,
        pack_type_id: null,
        pack_type_label: '',
        package_type: null,
        loading_type_id: null,
        loading_type_ids: [],
        loading_type_code: null,
        loading_type_label: '',
        loading_type_items: [],
        truck_body_type_id: null,
        truck_body_type_ids: [],
        truck_body_type_code: null,
        truck_body_type_label: '',
        truck_body_type_items: [],
        trailer_type_id: null,
        trailer_type_ids: [],
        trailer_type_code: null,
        trailer_type_label: '',
        trailer_type_items: [],
        package_count: null,
        dangerous_goods: false,
        dangerous_class: '',
        hs_code: '',
        cargo_type_id: null,
        cargo_type_label: '',
        cargo_type: 'general',
        is_oversized: false,
        is_fragile: false,
    };
}

function defaultCargoTypeOption(cargoTypeOptions) {
    return cargoTypeOptions[0] ?? { value: 1, code: 'general', label: 'Общий груз' };
}

export function normalizeLeadCargoItem(raw = {}, dictionaries = {}) {
    const {
        cargoTypeOptions = [],
        packageTypeOptions = [],
        loadingTypeOptions = [],
        truckBodyTypeOptions = [],
        trailerTypeOptions = [],
    } = dictionaries;

    const selectedCargoType = dictionaryOptionByValue(cargoTypeOptions, raw.cargo_type_id)
        ?? dictionaryOptionByCode(cargoTypeOptions, raw.cargo_type)
        ?? defaultCargoTypeOption(cargoTypeOptions);

    let cargoType = selectedCargoType.code ?? (raw.cargo_type && String(raw.cargo_type).trim() !== '' ? raw.cargo_type : 'general');
    if (cargoType === 'general' && Boolean(raw.dangerous_goods)) {
        cargoType = 'dangerous';
    }

    const effectiveCargoType = dictionaryOptionByCode(cargoTypeOptions, cargoType) ?? selectedCargoType;
    const selectedPackageType = dictionaryOptionByValue(packageTypeOptions, raw.pack_type_id)
        ?? dictionaryOptionByCode(packageTypeOptions, raw.package_type);
    const selectedLoadingType = dictionaryOptionByValue(loadingTypeOptions, raw.loading_type_id)
        ?? dictionaryOptionByCode(loadingTypeOptions, raw.loading_type_code);
    const selectedTruckBodyType = dictionaryOptionByValue(truckBodyTypeOptions, raw.truck_body_type_id)
        ?? dictionaryOptionByCode(truckBodyTypeOptions, raw.truck_body_type_code);
    const selectedTrailerType = dictionaryOptionByValue(trailerTypeOptions, raw.trailer_type_id)
        ?? dictionaryOptionByCode(trailerTypeOptions, raw.trailer_type_code);

    const loadingTypeItems = normalizeDictionaryItems(raw.loading_type_items, loadingTypeOptions, selectedLoadingType);
    const truckBodyTypeItems = normalizeDictionaryItems(raw.truck_body_type_items, truckBodyTypeOptions, selectedTruckBodyType);
    const trailerTypeItems = normalizeDictionaryItems(raw.trailer_type_items, trailerTypeOptions, selectedTrailerType);
    const weightValue = raw.weight_value ?? raw.weight_kg ?? null;

    return {
        ...blankLeadCargoItem(),
        ...raw,
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
    };
}

export function normalizeLeadCargoItems(items, dictionaries = {}) {
    if (!Array.isArray(items) || items.length === 0) {
        return [normalizeLeadCargoItem({}, dictionaries)];
    }

    return items.map((item) => normalizeLeadCargoItem(item, dictionaries));
}

export function applyCargoTypeOption(item, cargoTypeOptions) {
    const option = dictionaryOptionByValue(cargoTypeOptions, item.cargo_type_id)
        ?? defaultCargoTypeOption(cargoTypeOptions);

    item.cargo_type_id = normalizeNullableNumber(option.value);
    item.cargo_type = option.code ?? item.cargo_type ?? 'general';
    item.cargo_type_label = option.label ?? '';
    item.dangerous_goods = item.cargo_type === 'dangerous';
    item.is_oversized = item.cargo_type === 'oversized';
    item.is_fragile = item.cargo_type === 'fragile';
}

export function applyPackageTypeOption(item, packageTypeOptions) {
    const option = dictionaryOptionByValue(packageTypeOptions, item.pack_type_id);
    item.pack_type_id = option ? normalizeNullableNumber(option.value) : null;
    item.package_type = option?.code ?? null;
    item.pack_type_label = option?.label ?? '';
}

export function applyLoadingTypeOption(item, loadingTypeOptions) {
    applyDictionaryItems(item, loadingTypeOptions, 'loading_type_ids', 'loading_type_id', 'loading_type_code', 'loading_type_label', 'loading_type_items');
}

export function applyTruckBodyTypeOption(item, truckBodyTypeOptions) {
    applyDictionaryItems(item, truckBodyTypeOptions, 'truck_body_type_ids', 'truck_body_type_id', 'truck_body_type_code', 'truck_body_type_label', 'truck_body_type_items');
}

export function applyTrailerTypeOption(item, trailerTypeOptions) {
    applyDictionaryItems(item, trailerTypeOptions, 'trailer_type_ids', 'trailer_type_id', 'trailer_type_code', 'trailer_type_label', 'trailer_type_items');
}

export function cargoWeightInKg(item) {
    const value = parseLocaleDecimal(item.weight_value ?? item.weight_kg ?? 0) ?? 0;

    return item.weight_unit === 't' ? value * 1000 : value;
}

export function cargoPackageCountFactor(item) {
    const count = parseLocaleDecimal(item.package_count);

    if (count !== null && count > 0) {
        return Math.trunc(count);
    }

    return 1;
}

export function cargoLineTotalWeightKg(item) {
    return cargoWeightInKg(item) * cargoPackageCountFactor(item);
}

export function cargoLineTotalVolumeM3(item) {
    const per = parseLocaleDecimal(item.volume_m3);
    if (per === null || per <= 0) {
        return 0;
    }

    return per * cargoPackageCountFactor(item);
}

export function cargoHasDimensions(item) {
    return [item.length_m, item.width_m, item.height_m].some((value) => value !== null && value !== undefined && String(value).trim() !== '');
}

export function cargoDimensionFieldsEmpty(item) {
    return [item.length_m, item.width_m, item.height_m].every(
        (value) => value === null || value === undefined || String(value).trim() === '',
    );
}

export function cargoDimensionsLabel(item) {
    const length = parseLocaleDecimal(item.length_m);
    const width = parseLocaleDecimal(item.width_m);
    const height = parseLocaleDecimal(item.height_m);

    const lengthLabel = length !== null ? length.toFixed(2) : '—';
    const widthLabel = width !== null ? width.toFixed(2) : '—';
    const heightLabel = height !== null ? height.toFixed(2) : '—';

    return `${lengthLabel}×${widthLabel}×${heightLabel} м`;
}

export function cargoComputedVolumeM3(item) {
    const length = parseLocaleDecimal(item.length_m);
    const width = parseLocaleDecimal(item.width_m);
    const height = parseLocaleDecimal(item.height_m);

    if (length === null || width === null || height === null || length <= 0 || width <= 0 || height <= 0) {
        return null;
    }

    return length * width * height;
}

export function leadCargoSummary(items) {
    const list = Array.isArray(items) ? items : [];

    return {
        totalWeight: list.reduce((sum, item) => sum + cargoLineTotalWeightKg(item), 0),
        totalVolume: list.reduce((sum, item) => sum + cargoLineTotalVolumeM3(item), 0),
        totalPackages: list.reduce((sum, item) => sum + (parseLocaleDecimal(item.package_count) ?? 0), 0),
    };
}
