import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

import {
    applyAgGridCenterScrollableWidth,
    observeAgGridPanelLayout,
    resolveAgGridBottomScrollbarWidth,
    resolveAgGridViewportHeight,
} from '@/support/agGridHorizontalScroll.js';

/**
 * Нижний горизонтальный скролл, синхронизация с center viewport и явная высота грида.
 *
 * @param {object} options
 * @param {import('vue').Ref<HTMLElement | null | undefined>} options.gridPanel
 * @param {import('vue').Ref<HTMLElement | null | undefined>} options.bottomScrollbar
 * @param {import('vue').Ref<{ $el?: HTMLElement } | null | undefined>} options.agGrid
 * @param {import('vue').Ref<import('ag-grid-community').GridApi | null | undefined>} options.gridApi
 */
export function useAgGridHorizontalPanel({ gridPanel, bottomScrollbar, agGrid, gridApi }) {
    const bottomScrollbarWidth = ref(0);
    const gridViewportHeight = ref(280);

    const gridContainerStyle = computed(() => ({
        height: `${gridViewportHeight.value}px`,
        minHeight: `${gridViewportHeight.value}px`,
        width: '100%',
    }));

    let isSyncingHorizontalScroll = false;
    let removeCenterViewportListener = null;
    let disposePanelResizeObserver = null;

    const getCenterViewport = () => agGrid.value?.$el?.querySelector('.ag-viewport.ag-center-cols-viewport') ?? null;

    const updateGridViewportHeight = () => {
        gridViewportHeight.value = resolveAgGridViewportHeight(gridPanel.value, bottomScrollbar.value);
    };

    const syncBottomScrollbar = () => {
        const centerViewport = getCenterViewport();

        if (!centerViewport) {
            return;
        }

        const scrollableWidth = resolveAgGridBottomScrollbarWidth(gridApi.value, centerViewport);
        bottomScrollbarWidth.value = scrollableWidth;
        applyAgGridCenterScrollableWidth(centerViewport, scrollableWidth);

        if (bottomScrollbar.value && !isSyncingHorizontalScroll) {
            bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
        }
    };

    const onBottomScrollbarScroll = () => {
        if (isSyncingHorizontalScroll) {
            return;
        }

        const centerViewport = getCenterViewport();

        if (!centerViewport || !bottomScrollbar.value) {
            return;
        }

        isSyncingHorizontalScroll = true;

        const bottomMax = Math.max(0, bottomScrollbar.value.scrollWidth - bottomScrollbar.value.clientWidth);
        const centerMax = Math.max(0, centerViewport.scrollWidth - centerViewport.clientWidth);

        centerViewport.scrollLeft = bottomMax > 0 && centerMax > 0
            ? (bottomScrollbar.value.scrollLeft / bottomMax) * centerMax
            : bottomScrollbar.value.scrollLeft;

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
                const bottomMax = Math.max(0, bottomScrollbar.value.scrollWidth - bottomScrollbar.value.clientWidth);
                const centerMax = Math.max(0, centerViewport.scrollWidth - centerViewport.clientWidth);

                if (bottomMax > 0 && centerMax > 0) {
                    bottomScrollbar.value.scrollLeft = (centerViewport.scrollLeft / centerMax) * bottomMax;
                } else {
                    bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
                }
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
        updateGridViewportHeight();
        syncBottomScrollbar();
        attachCenterViewportListener();
    };

    onMounted(() => {
        nextTick(() => {
            refreshAgGridPanelLayout();
            disposePanelResizeObserver = observeAgGridPanelLayout(gridPanel.value, () => {
                updateGridViewportHeight();
                syncBottomScrollbar();
            });
        });
        window.addEventListener('resize', refreshAgGridPanelLayout);
    });

    onUnmounted(() => {
        disposePanelResizeObserver?.();
        disposePanelResizeObserver = null;
        removeCenterViewportListener?.();
        removeCenterViewportListener = null;
        window.removeEventListener('resize', refreshAgGridPanelLayout);
    });

    return {
        bottomScrollbarWidth,
        gridContainerStyle,
        gridViewportHeight,
        syncBottomScrollbar,
        onBottomScrollbarScroll,
        attachCenterViewportListener,
        refreshAgGridPanelLayout,
    };
}
