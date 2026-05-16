import { readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';

/** Дефолт, если нет сохранённой ширины и autoSize ещё не сработал. */
export const AG_GRID_ID_COLUMN_WIDTH = 56;
export const AG_GRID_ID_COLUMN_MIN_WIDTH = 48;
export const AG_GRID_ID_COLUMN_MAX_WIDTH = 96;

/**
 * @param {object} columnDefinition
 * @returns {object}
 */
export function applyAgGridIdColumnSizing(columnDefinition) {
    return {
        ...columnDefinition,
        width: AG_GRID_ID_COLUMN_WIDTH,
        minWidth: AG_GRID_ID_COLUMN_MIN_WIDTH,
        maxWidth: AG_GRID_ID_COLUMN_MAX_WIDTH,
    };
}

/**
 * Подогнать колонку ID под заголовок и видимые значения, если пользователь не задал ширину вручную.
 *
 * @param {import('ag-grid-community').GridApi|null|undefined} api
 * @param {string|null|undefined} storageKey
 */
export function autoSizeIdColumnIfNotPersisted(api, storageKey) {
    if (!api) {
        return;
    }

    const saved = readPersistedAgGridColumnState(storageKey ?? '');
    const idRow = saved?.find((row) => row?.colId === 'id');
    if (typeof idRow?.width === 'number' && idRow.width > 0) {
        return;
    }

    const column = api.getColumn('id');
    if (!column || column.isVisible() === false) {
        return;
    }

    api.autoSizeColumns(['id'], false);
}
