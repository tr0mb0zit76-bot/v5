import { ref } from 'vue';

const PULL_THRESHOLD_PX = 72;

export function usePullToRefresh(getScrollElement, onRefresh) {
    const pullReady = ref(false);
    const refreshing = ref(false);
    let startY = 0;
    let tracking = false;

    async function triggerRefresh() {
        if (refreshing.value) {
            return;
        }

        refreshing.value = true;

        try {
            await onRefresh();
        } finally {
            refreshing.value = false;
            pullReady.value = false;
        }
    }

    function onTouchStart(event) {
        const element = typeof getScrollElement === 'function' ? getScrollElement() : getScrollElement?.value;

        if (!element || element.scrollTop > 2) {
            tracking = false;

            return;
        }

        startY = event.touches[0]?.clientY ?? 0;
        tracking = true;
        pullReady.value = false;
    }

    function onTouchMove(event) {
        if (!tracking) {
            return;
        }

        const element = typeof getScrollElement === 'function' ? getScrollElement() : getScrollElement?.value;

        if (!element || element.scrollTop > 2) {
            tracking = false;
            pullReady.value = false;

            return;
        }

        const delta = (event.touches[0]?.clientY ?? 0) - startY;
        pullReady.value = delta >= PULL_THRESHOLD_PX;
    }

    async function onTouchEnd() {
        if (!tracking) {
            return;
        }

        tracking = false;

        if (pullReady.value) {
            await triggerRefresh();
        }

        pullReady.value = false;
    }

    return {
        pullReady,
        refreshing,
        onTouchStart,
        onTouchMove,
        onTouchEnd,
        triggerRefresh,
    };
}
