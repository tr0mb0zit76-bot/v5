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
    const clientWidth = centerViewport?.clientWidth ?? 0;

    return Math.max(columnTotal, fromContainer, fromViewport, clientWidth);
}

const DEFAULT_CRM_COMMAND_BAR_RESERVE_PX = 100;

/**
 * Резерв снизу под фиксированную панель «Джарвис» (crm-layout-footer).
 */
export function resolveCrmGridFooterReservePx() {
    const footer = document.querySelector('.crm-layout-footer');

    if (!footer) {
        return DEFAULT_CRM_COMMAND_BAR_RESERVE_PX;
    }

    const height = footer.getBoundingClientRect().height;

    return Math.max(DEFAULT_CRM_COMMAND_BAR_RESERVE_PX, Math.ceil(height) + 8);
}

/**
 * Высота области ag-grid с учётом нижнего скролла и command bar.
 *
 * @param {HTMLElement | null | undefined} panelElement
 * @param {HTMLElement | null | undefined} bottomScrollbarElement
 * @param {{ minHeight?: number }} [options]
 */
export function resolveAgGridViewportHeight(panelElement, bottomScrollbarElement, options = {}) {
    const minHeight = options.minHeight ?? 280;

    if (!panelElement) {
        return minHeight;
    }

    const sectionTop = panelElement.getBoundingClientRect().top;
    const bottomScrollbarHeight = bottomScrollbarElement?.offsetHeight ?? 18;
    const commandBarFooter = document.querySelector('.crm-layout-footer') ?? document.querySelector('footer');
    const footerTop = commandBarFooter?.getBoundingClientRect().top ?? window.innerHeight;
    const footerReserve = resolveCrmGridFooterReservePx();

    return Math.max(
        minHeight,
        Math.floor(footerTop - sectionTop - bottomScrollbarHeight - footerReserve),
    );
}
