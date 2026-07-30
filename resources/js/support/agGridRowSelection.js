/**
 * Мультивыбор: «выбрать все» только по отфильтрованным (и external filter) строкам.
 * AG Grid 35+ по умолчанию selectAll: 'all' — отсюда массовые действия по всей базе.
 *
 * @param {Partial<import('ag-grid-community').MultiRowSelectionOptions>} [overrides]
 * @returns {import('ag-grid-community').MultiRowSelectionOptions}
 */
export function agGridFilteredMultiRowSelection(overrides = {}) {
  return {
    mode: 'multiRow',
    checkboxes: true,
    headerCheckbox: true,
    selectAll: 'filtered',
    enableClickSelection: false,
    ...overrides,
  };
}

/**
 * Снять выделение со строк, скрытых текущим фильтром (column / quick / external).
 *
 * @param {import('ag-grid-community').GridApi|null|undefined} api
 */
export function pruneAgGridSelectionToDisplayed(api) {
  if (!api) {
    return;
  }

  const selected = api.getSelectedNodes?.() ?? [];

  for (const node of selected) {
    if (node && node.displayed === false) {
      node.setSelected(false);
    }
  }
}
