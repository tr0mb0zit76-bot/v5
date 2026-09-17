/**
 * Зеркало App\Support\OrderCargoItemsPayloadNormalizer::cargoItemHasSubstance / RequiresName.
 */

export function cargoItemHasSubstance(item) {
    if (!item || typeof item !== 'object') {
        return false;
    }

    if (String(item.name ?? '').trim() !== '') {
        return true;
    }

    const textKeys = [
        'description',
        'dangerous_class',
        'hs_code',
        'pack_type_label',
        'loading_type_label',
        'truck_body_type_label',
        'trailer_type_label',
    ];
    if (textKeys.some((key) => String(item[key] ?? '').trim() !== '')) {
        return true;
    }

    const numericKeys = [
        'weight_value',
        'weight_kg',
        'volume_m3',
        'package_count',
        'length_value',
        'width_value',
        'height_value',
        'length_m',
        'width_m',
        'height_m',
        'diameter_m',
        'pack_type_id',
        'loading_type_id',
        'truck_body_type_id',
        'trailer_type_id',
    ];
    if (numericKeys.some((key) => {
        const raw = item[key];
        if (raw === null || raw === undefined || raw === '') {
            return false;
        }

        const n = Number(raw);

        return Number.isFinite(n) && n > 0;
    })) {
        return true;
    }

    const listKeys = [
        'loading_type_items',
        'truck_body_type_items',
        'trailer_type_items',
        'performer_allocations',
    ];

    return listKeys.some((key) => Array.isArray(item[key]) && item[key].length > 0);
}

export function cargoItemRequiresName(item) {
    return cargoItemHasSubstance(item) && String(item?.name ?? '').trim() === '';
}

/**
 * @returns {string[]}
 */
export function cargoItemsMissingNameMessages(cargoItems) {
    if (!Array.isArray(cargoItems)) {
        return [];
    }

    return cargoItems
        .map((item, index) => (cargoItemRequiresName(item) ? `Укажите наименование груза (позиция ${index + 1}).` : null))
        .filter(Boolean);
}
