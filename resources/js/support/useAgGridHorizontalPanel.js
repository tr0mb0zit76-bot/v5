import { nextTick, onMounted, onUnmounted, ref } from 'vue';

import { observeAgGridPanelLayout, resolveAgGridBottomScrollbarWidth } from '@/support/agGridHorizontalScroll.js';

/**
 * Нижний горизонтальный скролл + синхронизация с center viewport.
 * Высота грида — через flex (класс orders-grid-ag-host), без JS-пикселей.
 *
 * @param {object} options
 * @param {import('vue').Ref<HTMLElement | null | undefined>} options.gridPanel
 * @param {import('vue').Ref<HTMLElement | null | undefined>} options.bottomScrollbar
 * @param {import('vue').Ref<{ $el?: HTMLElement } | null | undefined>} options.agGrid
 * @param {import('vue').Ref<import('ag-grid-community').GridApi | null | undefined>} options.gridApi
 */
export function useAgGridHorizontalPanel({ gridPanel, bottomScrollbar, agGrid, gridApi }) {
    const bottomScrollbarWidth = ref(0);

    let isSyncingHorizontalScroll = false;
    let removeCenterViewportListener = null;
    let disposePanelResizeObserver = null;

    const getCenterViewport = () => agGrid.value?.$el?.querySelector('.ag-viewport.ag-center-cols-viewport') ?? null;

    const syncBottomScrollbar = () => {
        const centerViewport = getCenterViewport();

        if (!centerViewport) {
            return;
        }

        bottomScrollbarWidth.value = resolveAgGridBottomScrollbarWidth(gridApi.value, centerViewport);

        if (bottomScrollbar.value && !isSyncingHorizontalScroll) {
            bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
        }
    };

    const onBottomScrollbarScroll = () => {
        if (isSyncingHorizontalScroll) {
            return;
        }

        const centerViewport = getCenterViewport();

        if (!centerViewport) {
            return;
        }

        isSyncingHorizontalScroll = true;
        centerViewport.scrollLeft = bottomScrollbar.value?.scrollLeft ?? 0;

        requestAnimationFrame(() => {
            isSyncingHorizontalScroll = false;
        });
    };

    const attachCenterViewportListener = () => {
        removeCenterViewportListener?.();

        const centerViewport = getCenterViewport();

        if (!centerViewport) {
            return;
        }

        const handleCenterViewportScroll = () => {
            if (isSyncingHorizontalScroll) {
                return;
            }

            isSyncingHorizontalScroll = true;

            if (bottomScrollbar.value) {
                bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
            }

            requestAnimationFrame(() => {
                isSyncingHorizontalScroll = false;
            });
        };

        centerViewport.addEventListener('scroll', handleCenterViewportScroll, { passive: true });
        removeCenterViewportListener = () => {
            centerViewport.removeEventListener('scroll', handleCenterViewportScroll);
        };
    };

    const refreshAgGridPanelLayout = () => {
        syncBottomScrollbar();
        attachCenterViewportListener();
    };

    onMounted(() => {
        nextTick(() => {
            disposePanelResizeObserver = observeAgGridPanelLayout(gridPanel.value, refreshAgGridPanelLayout);
        });
        window.addEventListener('resize', syncBottomScrollbar);
    });

    onUnmounted(() => {
        disposePanelResizeObserver?.();
        disposePanelResizeObserver = null;
        removeCenterViewportListener?.();
        removeCenterViewportListener = null;
        window.removeEventListener('resize', syncBottomScrollbar);
    });

    return {
        bottomScrollbarWidth,
        syncBottomScrollbar,
        onBottomScrollbarScroll,
        attachCenterViewportListener,
        refreshAgGridPanelLayout,
    };
}
