/**
 * Чтение сохранённого состояния колонок AG Grid (тот же JSON, что пишет saveColumnState).
 *
 * @param {string} storageKey
 * @returns {Array<{ colId: string, hide?: boolean, width?: number, order?: number, sort?: string|null, sortIndex?: number|null }>|null}
 */
export function readPersistedAgGridColumnState(storageKey) {
  if (typeof window === 'undefined' || !storageKey) {
    return null;
  }

  try {
    const raw = localStorage.getItem(storageKey);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw);

    return Array.isArray(parsed) ? parsed : null;
  } catch {
    return null;
  }
}

/**
 * Порядок полей по сохранённому state + map colId → строка сохранения.
 *
 * @param {string[]} allowedFields
 * @param {Array<{ colId: string, order?: number }>|null|undefined} savedRows
 * @returns {{ orderedFields: string[], byColId: Map<string, object> }}
 */
export function buildLayoutIndex(allowedFields, savedRows) {
  const allowedSet = new Set(allowedFields);
  const filtered = (savedRows ?? []).filter((row) => row && allowedSet.has(row.colId));
  const byColId = new Map(filtered.map((row) => [row.colId, row]));

  const ordered = [...filtered]
    .sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0))
    .map((r) => r.colId)
    .filter((id) => allowedSet.has(id));

  const rest = allowedFields.filter((f) => !ordered.includes(f));

  return {
    orderedFields: [...ordered, ...rest],
    byColId,
  };
}

/**
 * Применить к colDef ширину / hide / сортировку из сохранённой строки.
 *
 * @param {object} colDef
 * @param {object|undefined} savedRow
 * @returns {object}
 */
export function applySavedToColDef(colDef, savedRow) {
  if (!savedRow) {
    return colDef;
  }

  const next = { ...colDef };

  if (typeof savedRow.width === 'number' && savedRow.width > 0) {
    next.width = savedRow.width;
    // AG Grid: width and flex on the same column are incompatible; persisted width must win after reload.
    if ('flex' in next) {
      delete next.flex;
    }
  }

  if (typeof savedRow.hide === 'boolean') {
    next.hide = savedRow.hide;
  }

  if (savedRow.sort === 'asc' || savedRow.sort === 'desc') {
    next.sort = savedRow.sort;
    if (typeof savedRow.sortIndex === 'number') {
      next.sortIndex = savedRow.sortIndex;
    }
  }

  return next;
}
