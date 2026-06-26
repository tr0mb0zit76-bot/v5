/**
 * Ширина нижнего горизонтального скролла: сумма колонок, а не scrollWidth viewport
 * (при flex-колонках viewport может не расширяться, пока ячейки ещё сжимаются).
 *
 * @param {import('ag-grid-community').GridApi | null | undefined} gridApi
 * @param {HTMLElement | null | undefined} centerViewport
 */
export function resolveAgGridBottomScrollbarWidth(gridApi, centerViewport) {
    if (!centerViewport) {
        return 1;
    }

    let columnTotal = 0;

    if (gridApi?.getDisplayedCenterColumns) {
        for (const column of gridApi.getDisplayedCenterColumns()) {
            columnTotal += column.getActualWidth?.() ?? 0;
        }
    }

    const container = centerViewport.querySelector('.ag-center-cols-container');
    const fromContainer = Math.max(container?.scrollWidth ?? 0, container?.offsetWidth ?? 0);

    // Только сумма колонок и фактическая ширина тела — без хедера и scrollLeft,
    // иначе скролл «уезжает» в пустоту и заголовок расходится с ячейками.
    return Math.max(columnTotal, fromContainer, 1);
}

/**
 * Выравнивает ширину прокручиваемой области ag-grid с расчётной (иначе последняя колонка обрезается).
 *
 * @param {HTMLElement | null | undefined} centerViewport
 * @param {number} scrollableWidth
 */
export function applyAgGridCenterScrollableWidth(centerViewport, scrollableWidth) {
    if (!centerViewport || !Number.isFinite(scrollableWidth) || scrollableWidth <= 0) {
        return;
    }

    const viewportWidth = centerViewport.clientWidth ?? 0;
    const container = centerViewport.querySelector('.ag-center-cols-container');

    if (!container) {
        return;
    }

    if (scrollableWidth <= viewportWidth + 1) {
        container.style.removeProperty('min-width');

        return;
    }

    container.style.minWidth = `${Math.ceil(scrollableWidth)}px`;
}

/**
 * Высота области ag-grid внутри панели, минус нижний горизонтальный скролл.
 * Сначала берём фактическую высоту flex-панели; если она схлопнулась (~0) — fallback до command bar.
 *
 * @param {HTMLElement | null | undefined} panelElement
 * @param {HTMLElement | null | undefined} bottomScrollbarElement
 * @param {{ minHeight?: number, footerGap?: number }} [options]
 */
export function resolveAgGridViewportHeight(panelElement, bottomScrollbarElement, options = {}) {
    const minHeight = options.minHeight ?? 280;
    const footerGap = options.footerGap ?? 8;

    if (!panelElement) {
        return minHeight;
    }

    const bottomScrollbarHeight = bottomScrollbarElement?.offsetHeight ?? 18;
    const panelHeight = panelElement.getBoundingClientRect().height;

    if (panelHeight > 0) {
        return Math.max(minHeight, Math.floor(panelHeight - bottomScrollbarHeight));
    }

    const panelTop = panelElement.getBoundingClientRect().top;
    const commandBarFooter = document.querySelector('.crm-layout-footer') ?? document.querySelector('footer');
    const footerTop = commandBarFooter?.getBoundingClientRect().top ?? window.innerHeight;

    return Math.max(
        minHeight,
        Math.floor(footerTop - panelTop - bottomScrollbarHeight - footerGap),
    );
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
