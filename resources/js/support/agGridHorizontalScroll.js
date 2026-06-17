/**
 * Ширина нижнего горизонтального скролла: сумма колонок, а не scrollWidth viewport
 * (при flex-колонках viewport может не расширяться, пока ячейки ещё сжимаются).
 *
 * @param {import('ag-grid-community').GridApi | null | undefined} gridApi
 * @param {HTMLElement | null | undefined} centerViewport
 */
export function resolveAgGridBottomScrollbarWidth(gridApi, centerViewport) {
    let columnTotal = 0;

    if (gridApi?.getDisplayedCenterColumns) {
        for (const column of gridApi.getDisplayedCenterColumns()) {
            columnTotal += column.getActualWidth?.() ?? 0;
        }
    }

    const container = centerViewport?.querySelector?.('.ag-center-cols-container');
    const fromContainer = container?.scrollWidth ?? 0;
    const fromViewport = centerViewport?.scrollWidth ?? 0;
    const viewportWidth = centerViewport?.clientWidth ?? 0;
    const scrollLeft = centerViewport?.scrollLeft ?? 0;

    return Math.max(columnTotal, fromContainer, fromViewport, scrollLeft + viewportWidth, 1);
}

/**
 * @param {HTMLElement | null | undefined} panelElement
 * @param {() => void} callback
 * @returns {() => void}
 */
export function observeAgGridPanelLayout(panelElement, callback) {
    if (!panelElement || typeof ResizeObserver === 'undefined') {
        return () => {};
    }

    const observer = new ResizeObserver(() => {
        callback();
    });

    observer.observe(panelElement);

    return () => {
        observer.disconnect();
    };
}
