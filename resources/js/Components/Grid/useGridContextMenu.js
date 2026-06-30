import { reactive } from 'vue';

export function useGridContextMenu() {
  const contextMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    items: [],
  });

  function closeContextMenu() {
    contextMenu.open = false;
    contextMenu.items = [];
  }

  function openContextMenu(event, items) {
    if (!Array.isArray(items) || items.length === 0) {
      closeContextMenu();

      return;
    }

    if (event?.preventDefault) {
      event.preventDefault();
    }

    contextMenu.x = event?.clientX ?? 0;
    contextMenu.y = event?.clientY ?? 0;
    contextMenu.items = items;
    contextMenu.open = true;
  }

  function openCellContextMenu(params, buildItems) {
    const row = params?.node?.data;
    const items = typeof buildItems === 'function' ? buildItems(row, params) : [];

    openContextMenu(params?.event, items);
  }

  function openEmptyContextMenu(event, items, options = {}) {
    const target = event?.target;

    if (target?.closest?.('.ag-cell')) {
      return;
    }

    if (options.ignoreHeaders !== false && (
      target?.closest?.('.ag-header')
      || target?.closest?.('.ag-header-container')
      || target?.closest?.('.ag-floating-top')
      || target?.closest?.('.ag-floating-filter')
    )) {
      return;
    }

    openContextMenu(event, typeof items === 'function' ? items(event) : items);
  }

  return {
    contextMenu,
    closeContextMenu,
    openContextMenu,
    openCellContextMenu,
    openEmptyContextMenu,
  };
}
