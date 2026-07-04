import { onMounted, onUnmounted, ref } from 'vue';

const DEFAULT_INTERVAL_MS = 8000;

function isDocumentVisible() {
    return typeof document === 'undefined' || document.visibilityState === 'visible';
}

export function useMessengerPolling(messenger, {
    intervalMs = DEFAULT_INTERVAL_MS,
    enabled = true,
} = {}) {
    const polling = ref(false);
    let timerId = null;

    async function pollOnce() {
        if (!enabled || !isDocumentVisible() || polling.value) {
            return;
        }

        polling.value = true;

        try {
            await messenger.loadConversations({ background: true });

            if (messenger.activeConversationId.value !== null) {
                await messenger.refreshThread({ background: true });
            }
        } finally {
            polling.value = false;
        }
    }

    function startPolling() {
        stopPolling();
        timerId = window.setInterval(() => {
            pollOnce();
        }, intervalMs);
    }

    function stopPolling() {
        if (timerId !== null) {
            window.clearInterval(timerId);
            timerId = null;
        }
    }

    function handleVisibilityChange() {
        if (isDocumentVisible()) {
            pollOnce();
        }
    }

    function handlePushReceived() {
        pollOnce();
    }

    onMounted(() => {
        if (!enabled) {
            return;
        }

        startPolling();
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('crm-mobile-push-received', handlePushReceived);
    });

    onUnmounted(() => {
        stopPolling();
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('crm-mobile-push-received', handlePushReceived);
    });

    return {
        polling,
        pollOnce,
        startPolling,
        stopPolling,
    };
}
