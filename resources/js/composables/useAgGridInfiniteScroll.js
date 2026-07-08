import { onBeforeUnmount, ref, unref, watch } from 'vue';

/**
 * Подгружает следующую страницу, когда пользователь докручивает тело ag-Grid почти до конца.
 */
export function useAgGridInfiniteScroll({
    gridApi,
    hasMore,
    loadMore,
    threshold = 160,
}) {
    const loading = ref(false);
    let detachListener = null;

    async function maybeLoadMore(event) {
        if (loading.value || !unref(hasMore)) {
            return;
        }

        if (event?.direction && event.direction !== 'vertical') {
            return;
        }

        const api = unref(gridApi);
        if (!api) {
            return;
        }

        const viewport = api.getGridBodyViewportElement?.();
        if (!viewport) {
            return;
        }

        const remaining = viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight;
        if (remaining > threshold) {
            return;
        }

        loading.value = true;

        try {
            await loadMore();
        } finally {
            loading.value = false;
        }
    }

    function attach(api) {
        detach();

        if (!api) {
            return;
        }

        const handler = (event) => {
            void maybeLoadMore(event);
        };

        api.addEventListener('bodyScroll', handler);
        detachListener = () => api.removeEventListener('bodyScroll', handler);
    }

    function detach() {
        if (detachListener) {
            detachListener();
            detachListener = null;
        }
    }

    watch(
        () => unref(gridApi),
        (api) => attach(api),
        { immediate: true },
    );

    onBeforeUnmount(detach);

    return {
        loading,
    };
}
