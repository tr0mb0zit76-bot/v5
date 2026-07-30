import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Изменяемый размер панели с сохранением в localStorage.
 * По умолчанию: якорь снизу по центру (ширина растёт в обе стороны, высота — вверх).
 *
 * @param {{
 *   storageKey: string,
 *   minWidth?: number,
 *   minHeight?: number,
 *   maxWidthRatio?: number,
 *   maxHeightRatio?: number,
 *   defaultWidth?: (maxWidth: number) => number,
 *   defaultHeight?: (maxHeight: number) => number,
 *   widthDeltaFactor?: number,
 *   heightGrowUp?: boolean,
 * }} options
 */
export function usePersistedPanelSize(options) {
    const storageKey = options.storageKey;
    const minWidth = options.minWidth ?? 320;
    const minHeight = options.minHeight ?? 240;
    const maxWidthRatio = options.maxWidthRatio ?? 0.75;
    const maxHeightRatio = options.maxHeightRatio ?? 2 / 3;
    const widthDeltaFactor = options.widthDeltaFactor ?? 2;
    const heightGrowUp = options.heightGrowUp !== false;
    const defaultWidthFn = options.defaultWidth ?? ((maxW) => Math.min(768, maxW));
    const defaultHeightFn = options.defaultHeight ?? ((maxH) => Math.min(720, maxH));

    function maxPanelWidth() {
        return Math.floor(window.innerWidth * maxWidthRatio);
    }

    function maxPanelHeight() {
        return Math.floor(window.innerHeight * maxHeightRatio);
    }

    function defaultPanelWidth() {
        return defaultWidthFn(maxPanelWidth());
    }

    function defaultPanelHeight() {
        return defaultHeightFn(maxPanelHeight());
    }

    const panelWidth = ref(defaultPanelWidth());
    const panelHeight = ref(defaultPanelHeight());

    const panelStyle = computed(() => ({
        width: `${panelWidth.value}px`,
        height: `${panelHeight.value}px`,
        maxWidth: `${Math.round(maxWidthRatio * 10000) / 100}vw`,
        maxHeight: `${Math.round(maxHeightRatio * 10000) / 100}vh`,
    }));

    let resizeListeners = null;

    function clampPanelSize() {
        panelWidth.value = Math.max(minWidth, Math.min(maxPanelWidth(), panelWidth.value));
        panelHeight.value = Math.max(minHeight, Math.min(maxPanelHeight(), panelHeight.value));
    }

    function persistPanelSize() {
        try {
            localStorage.setItem(
                storageKey,
                JSON.stringify({ width: panelWidth.value, height: panelHeight.value }),
            );
        } catch {
            // ignore quota / private mode
        }
    }

    function loadPanelSize() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) {
                return;
            }

            const parsed = JSON.parse(raw);
            if (typeof parsed?.width === 'number' && typeof parsed?.height === 'number') {
                panelWidth.value = parsed.width;
                panelHeight.value = parsed.height;
                clampPanelSize();
            }
        } catch {
            // ignore invalid storage
        }
    }

    function resetPanelSize() {
        panelWidth.value = defaultPanelWidth();
        panelHeight.value = defaultPanelHeight();
        persistPanelSize();
    }

    function stopResize() {
        if (!resizeListeners) {
            return;
        }

        document.removeEventListener('mousemove', resizeListeners.onMove);
        document.removeEventListener('mouseup', resizeListeners.onUp);
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        resizeListeners = null;
    }

    function startResize(event) {
        if (event.button !== 0) {
            return;
        }

        stopResize();

        const startX = event.clientX;
        const startY = event.clientY;
        const startWidth = panelWidth.value;
        const startHeight = panelHeight.value;

        const onMove = (moveEvent) => {
            const deltaX = moveEvent.clientX - startX;
            const deltaY = heightGrowUp
                ? startY - moveEvent.clientY
                : moveEvent.clientY - startY;

            panelWidth.value = Math.max(
                minWidth,
                Math.min(maxPanelWidth(), startWidth + deltaX * widthDeltaFactor),
            );
            panelHeight.value = Math.max(
                minHeight,
                Math.min(maxPanelHeight(), startHeight + deltaY),
            );
        };

        const onUp = () => {
            stopResize();
            persistPanelSize();
        };

        resizeListeners = { onMove, onUp };
        document.body.style.cursor = heightGrowUp ? 'nesw-resize' : 'nwse-resize';
        document.body.style.userSelect = 'none';
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }

    function onWindowResize() {
        clampPanelSize();
    }

    onMounted(() => {
        loadPanelSize();
        window.addEventListener('resize', onWindowResize);
    });

    onBeforeUnmount(() => {
        stopResize();
        window.removeEventListener('resize', onWindowResize);
    });

    return {
        panelWidth,
        panelHeight,
        panelStyle,
        startResize,
        resetPanelSize,
        clampPanelSize,
    };
}
