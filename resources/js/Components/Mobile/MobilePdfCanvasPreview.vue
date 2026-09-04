<template>
    <div ref="rootEl" class="h-full overflow-y-auto overscroll-contain bg-zinc-800 px-2 py-2">
        <div
            v-if="renderError"
            class="flex min-h-full flex-col items-center justify-center gap-3 px-4 text-center"
        >
            <p class="text-sm text-rose-200">{{ renderError }}</p>
            <slot name="fallback" />
        </div>
        <div
            v-else-if="rendering"
            class="flex min-h-full items-center justify-center text-sm text-zinc-400"
        >
            Отрисовка страниц…
        </div>
        <div v-show="!rendering && !renderError" ref="pagesEl" class="mx-auto flex w-full max-w-3xl flex-col gap-2" />
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
let renderToken = 0;
let workerConfigured = false;

function clearPages() {
    if (pagesEl.value) {
        pagesEl.value.replaceChildren();
    }
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

async function renderPdf() {
    const token = ++renderToken;
    renderError.value = '';
    clearPages();

    if (! props.data || (props.data.byteLength ?? props.data.length ?? 0) === 0) {
        return;
    }

    rendering.value = true;

    try {
        await nextTick();

        if (token !== renderToken) {
            return;
        }

        const { getDocument } = await loadPdfJs();
        const bytes = props.data instanceof Uint8Array
            ? props.data
            : new Uint8Array(props.data);

        const pdf = await getDocument({ data: bytes.slice(0) }).promise;

        if (token !== renderToken) {
            return;
        }

        const containerWidth = Math.max(
            280,
            Math.floor((rootEl.value?.clientWidth || 360) - 16),
        );

        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            if (token !== renderToken) {
                return;
            }

            const page = await pdf.getPage(pageNumber);
            const unscaled = page.getViewport({ scale: 1 });
            const scale = Math.min(2, containerWidth / unscaled.width);
            const viewport = page.getViewport({ scale });
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            if (! context) {
                throw new Error('Canvas недоступен');
            }

            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.className = 'w-full rounded-lg bg-white shadow-sm';
            canvas.setAttribute('aria-label', `Страница ${pageNumber}`);

            pagesEl.value?.appendChild(canvas);
            await page.render({ canvasContext: context, canvas, viewport }).promise;
        }
    } catch (exception) {
        if (token === renderToken) {
            renderError.value = exception?.message
                ? `Не удалось показать PDF: ${exception.message}`
                : 'Не удалось показать PDF.';
            clearPages();
        }
    } finally {
        if (token === renderToken) {
            rendering.value = false;
        }
    }
}

watch(() => props.data, () => {
    renderPdf();
}, { immediate: true });

onBeforeUnmount(() => {
    renderToken += 1;
    clearPages();
});
</script>
