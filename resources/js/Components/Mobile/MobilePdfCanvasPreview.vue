<template>
    <div class="relative h-full min-h-0">
        <div
            ref="rootEl"
            class="h-full overflow-auto overscroll-contain bg-zinc-800 px-2 py-2 touch-pan-x touch-pan-y"
            @touchstart.passive="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchEnd"
        >
            <div
                v-if="renderError"
                class="flex min-h-full flex-col items-center justify-center gap-3 px-4 text-center"
            >
                <p class="text-sm text-rose-200">{{ renderError }}</p>
                <slot name="fallback" />
            </div>
            <div
                v-else-if="rendering && !hasPages"
                class="flex min-h-full items-center justify-center text-sm text-zinc-400"
            >
                Отрисовка страниц…
            </div>
            <div
                v-show="!renderError"
                ref="pagesEl"
                class="mx-auto flex flex-col gap-2 origin-top-left"
                :style="pagesStyle"
            />
        </div>

        <div
            v-if="!renderError && props.data"
            class="pointer-events-none absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 items-center gap-1 rounded-full border border-white/15 bg-zinc-950/90 p-1 shadow-lg backdrop-blur"
        >
            <button
                type="button"
                class="pointer-events-auto inline-flex h-9 w-9 items-center justify-center rounded-full text-lg font-semibold text-zinc-100 active:bg-white/15 disabled:opacity-40"
                aria-label="Уменьшить"
                :disabled="zoom <= ZOOM_MIN || rendering"
                @click="zoomOut"
            >
                −
            </button>
            <button
                type="button"
                class="pointer-events-auto min-w-[3.25rem] rounded-full px-2 py-1.5 text-xs font-semibold tabular-nums text-zinc-200 active:bg-white/15 disabled:opacity-40"
                aria-label="Сбросить масштаб"
                :disabled="rendering"
                @click="resetZoom"
            >
                {{ zoomPercent }}%
            </button>
            <button
                type="button"
                class="pointer-events-auto inline-flex h-9 w-9 items-center justify-center rounded-full text-lg font-semibold text-zinc-100 active:bg-white/15 disabled:opacity-40"
                aria-label="Увеличить"
                :disabled="zoom >= ZOOM_MAX || rendering"
                @click="zoomIn"
            >
                +
            </button>
        </div>

        <div
            v-if="rendering && hasPages"
            class="pointer-events-none absolute inset-x-0 top-2 z-10 flex justify-center"
        >
            <span class="rounded-full bg-zinc-950/80 px-3 py-1 text-[11px] text-zinc-300">Обновление…</span>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const ZOOM_MIN = 0.5;
const ZOOM_MAX = 3;
const ZOOM_STEP = 0.25;

const props = defineProps({
    data: {
        default: null,
        validator: (value) => value == null
            || value instanceof ArrayBuffer
            || value instanceof Uint8Array,
    },
});

const rootEl = ref(null);
const pagesEl = ref(null);
const rendering = ref(false);
const renderError = ref('');
const zoom = ref(1);
const pinchLiveScale = ref(1);
const hasPages = ref(false);

let renderToken = 0;
let workerConfigured = false;
let pdfDoc = null;
let pdfBytesKey = '';
let renderTimer = null;
let pinchStartDistance = 0;
let pinchStartZoom = 1;
let pinchActive = false;

const zoomPercent = computed(() => Math.round(zoom.value * pinchLiveScale.value * 100));

const pagesStyle = computed(() => {
    if (pinchLiveScale.value === 1) {
        return undefined;
    }

    return {
        transform: `scale(${pinchLiveScale.value})`,
    };
});

function clearPages() {
    if (pagesEl.value) {
        pagesEl.value.replaceChildren();
    }
    hasPages.value = false;
}

function dropPdfDoc() {
    pdfDoc = null;
    pdfBytesKey = '';
}

function bytesKey(data) {
    if (! data) {
        return '';
    }

    const len = data.byteLength ?? data.length ?? 0;

    return `${len}:${Object.prototype.toString.call(data)}`;
}

async function loadPdfJs() {
    const pdfjs = await import('pdfjs-dist');
    const workerMod = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');

    if (! workerConfigured) {
        pdfjs.GlobalWorkerOptions.workerSrc = workerMod.default;
        workerConfigured = true;
    }

    return pdfjs;
}

async function ensurePdfDocument() {
    const key = bytesKey(props.data);

    if (! props.data || key === '') {
        dropPdfDoc();

        return null;
    }

    if (pdfDoc && pdfBytesKey === key) {
        return pdfDoc;
    }

    const { getDocument } = await loadPdfJs();
    const bytes = props.data instanceof Uint8Array
        ? props.data
        : new Uint8Array(props.data);

    pdfDoc = await getDocument({ data: bytes.slice(0) }).promise;
    pdfBytesKey = key;

    return pdfDoc;
}

function clampZoom(value) {
    return Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(value * 100) / 100));
}

function scheduleRender(delayMs = 0) {
    clearTimeout(renderTimer);
    renderTimer = setTimeout(() => {
        renderPdf();
    }, delayMs);
}

function zoomIn() {
    zoom.value = clampZoom(zoom.value + ZOOM_STEP);
    scheduleRender();
}

function zoomOut() {
    zoom.value = clampZoom(zoom.value - ZOOM_STEP);
    scheduleRender();
}

function resetZoom() {
    zoom.value = 1;
    pinchLiveScale.value = 1;
    scheduleRender();
}

function touchDistance(touches) {
    const [a, b] = [touches[0], touches[1]];
    const dx = a.clientX - b.clientX;
    const dy = a.clientY - b.clientY;

    return Math.hypot(dx, dy);
}

function onTouchStart(event) {
    if (event.touches.length !== 2 || renderError.value) {
        return;
    }

    pinchActive = true;
    pinchStartDistance = touchDistance(event.touches);
    pinchStartZoom = zoom.value;
    pinchLiveScale.value = 1;
}

function onTouchMove(event) {
    if (! pinchActive || event.touches.length !== 2 || pinchStartDistance <= 0) {
        return;
    }

    event.preventDefault();
    const ratio = touchDistance(event.touches) / pinchStartDistance;
    const nextZoom = clampZoom(pinchStartZoom * ratio);
    pinchLiveScale.value = nextZoom / pinchStartZoom;
}

function onTouchEnd(event) {
    if (! pinchActive) {
        return;
    }

    if (event.touches.length >= 2) {
        return;
    }

    const applied = clampZoom(pinchStartZoom * pinchLiveScale.value);
    pinchActive = false;
    pinchStartDistance = 0;
    pinchLiveScale.value = 1;
    zoom.value = applied;
    scheduleRender(40);
}

async function renderPdf() {
    const token = ++renderToken;
    renderError.value = '';

    if (! props.data || (props.data.byteLength ?? props.data.length ?? 0) === 0) {
        clearPages();
        dropPdfDoc();

        return;
    }

    rendering.value = true;

    try {
        await nextTick();

        if (token !== renderToken) {
            return;
        }

        const pdf = await ensurePdfDocument();

        if (! pdf || token !== renderToken) {
            return;
        }

        const containerWidth = Math.max(
            280,
            Math.floor((rootEl.value?.clientWidth || 360) - 16),
        );

        clearPages();

        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            if (token !== renderToken) {
                return;
            }

            const page = await pdf.getPage(pageNumber);
            const unscaled = page.getViewport({ scale: 1 });
            const fitScale = containerWidth / unscaled.width;
            const scale = Math.min(3, fitScale * zoom.value);
            const viewport = page.getViewport({ scale });
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            if (! context) {
                throw new Error('Canvas недоступен');
            }

            const cssWidth = Math.floor(viewport.width);
            const cssHeight = Math.floor(viewport.height);
            const outputScale = Math.min(2, window.devicePixelRatio || 1);

            canvas.width = Math.floor(cssWidth * outputScale);
            canvas.height = Math.floor(cssHeight * outputScale);
            canvas.style.width = `${cssWidth}px`;
            canvas.style.height = `${cssHeight}px`;
            canvas.className = 'max-w-none shrink-0 rounded-lg bg-white shadow-sm';
            canvas.setAttribute('aria-label', `Страница ${pageNumber}`);

            if (outputScale !== 1) {
                context.setTransform(outputScale, 0, 0, outputScale, 0, 0);
            }

            pagesEl.value?.appendChild(canvas);
            hasPages.value = true;
            await page.render({ canvasContext: context, canvas, viewport }).promise;
        }
    } catch (exception) {
        if (token === renderToken) {
            renderError.value = exception?.message
                ? `Не удалось показать PDF: ${exception.message}`
                : 'Не удалось показать PDF.';
            clearPages();
            dropPdfDoc();
        }
    } finally {
        if (token === renderToken) {
            rendering.value = false;
        }
    }
}

watch(() => props.data, () => {
    zoom.value = 1;
    pinchLiveScale.value = 1;
    dropPdfDoc();
    renderPdf();
}, { immediate: true });

onBeforeUnmount(() => {
    renderToken += 1;
    clearTimeout(renderTimer);
    clearPages();
    dropPdfDoc();
});
</script>
