export function sanitizeDecimalInput(value) {
    if (value === null || value === undefined) {
        return '';
    }

    const trimmed = String(value).trim().replace(/\s+/g, '');

    if (trimmed === '') {
        return '';
    }

    if (trimmed.includes(',') && trimmed.includes('.')) {
        if (trimmed.lastIndexOf(',') > trimmed.lastIndexOf('.')) {
            return trimmed.replace(/\./g, '').replace(',', '.');
        }

        return trimmed.replace(/,/g, '');
    }

    return trimmed.replace(',', '.');
}

export function parseLocaleDecimal(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const sanitized = sanitizeDecimalInput(value);

    if (sanitized === '' || sanitized === '.' || sanitized === '-') {
        return null;
    }

    const parsed = Number(sanitized);

    return Number.isFinite(parsed) ? parsed : null;
}

export function normalizeNullableNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

export function dictionaryOptionByValue(options, value) {
    const normalized = normalizeNullableNumber(value);
    if (normalized === null) {
        return null;
    }

    return options.find((option) => Number(option.value) === normalized) ?? null;
}

export function dictionaryOptionByCode(options, code) {
    const normalized = code ? String(code).trim() : '';
    if (normalized === '') {
        return null;
    }

    return options.find((option) => option.code === normalized) ?? null;
}

export function dictionarySelectionLabel(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return 'Выберите';
    }

    const labels = items
        .map((item) => item?.label)
        .filter((label) => label !== null && label !== undefined && String(label).trim() !== '')
        .map((label) => String(label).trim());

    if (labels.length === 0) {
        return `Выбрано: ${items.length}`;
    }

    return labels.length <= 2 ? labels.join(', ') : `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`;
}

export function normalizeDictionaryItems(rawItems, options, fallbackOption) {
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

export function selectedDictionaryItems(options, ids) {
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

export function applyDictionaryItems(item, options, idsKey, idKey, codeKey, labelKey, itemsKey) {
    const selected = selectedDictionaryItems(options, item[idsKey]);
    const first = selected[0] ?? null;
    item[itemsKey] = selected;
    item[idKey] = first?.id ?? null;
    item[codeKey] = first?.code ?? null;
    item[labelKey] = first?.label ?? '';
}
