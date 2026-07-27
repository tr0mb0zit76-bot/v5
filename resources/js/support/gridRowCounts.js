export function refreshGridDisplayedRowCount(gridApi, totalFallback = 0) {
    if (!gridApi) {
        return totalFallback;
    }

    try {
        return gridApi.getDisplayedRowCount?.() ?? totalFallback;
    } catch {
        return totalFallback;
    }
}

export function isGridFilterActive(gridApi, quickSearch = '') {
    if (String(quickSearch ?? '').trim() !== '') {
        return true;
    }

    if (!gridApi) {
        return false;
    }

    try {
        const model = gridApi.getFilterModel?.() ?? {};

        return Object.keys(model).length > 0;
    } catch {
        return false;
    }
}

export function buildGridRowStatusLabel({
    displayedCount = 0,
    totalCount = 0,
    selectedCount = 0,
    quickSearch = '',
    getGridApi = null,
    suffix = '',
} = {}) {
    const filtered = isGridFilterActive(getGridApi?.(), quickSearch) || displayedCount !== totalCount;
    const parts = [];

    if (selectedCount > 0) {
        parts.push(`Выбрано: ${selectedCount}`);
    }

    if (filtered) {
        parts.push(`В отборе: ${displayedCount} из ${totalCount}`);
    } else {
        parts.push(`Строк: ${totalCount}`);
    }

    if (suffix) {
        parts.push(suffix);
    }

    return parts.join(' · ');
}
