<template>
    <div class="flex min-h-dvh flex-col bg-zinc-100 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-50">
        <header class="shrink-0 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Предпросмотр наложений шаблона</div>
                    <h1 class="text-lg font-semibold">{{ templateName }}</h1>
                </div>
                <Link
                    :href="backUrl"
                    class="inline-flex items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:hover:bg-zinc-800"
                >
                    ← К шаблонам
                </Link>
            </div>
        </header>

        <main class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-3">
            <div
                v-if="!documentPreview.pdf_preview_available"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                role="alert"
            >
                {{ documentPreview.hint }}
            </div>

            <div
                v-if="overlayPositioningEnabled"
                class="space-y-3 rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="text-sm font-semibold">Позиционирование перетаскиванием</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Прокрути страницу, перетащи подпись/печать поверх реального предпросмотра и сохрани.
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span>Подпись: X {{ signatureOffsetXmm }} мм, Y {{ signatureOffsetYmm }} мм</span>
                    <button type="button" class="rounded border border-zinc-300 px-2 py-1 hover:bg-zinc-100 dark:border-zinc-600 dark:hover:bg-zinc-800" @click="resetPosition('signature')">Сбросить подпись</button>
                    <span>Печать: X {{ stampOffsetXmm }} мм, Y {{ stampOffsetYmm }} мм</span>
                    <button type="button" class="rounded border border-zinc-300 px-2 py-1 hover:bg-zinc-100 dark:border-zinc-600 dark:hover:bg-zinc-800" @click="resetPosition('stamp')">Сбросить печать</button>
                    <button
                        type="button"
                        class="ml-auto inline-flex items-center rounded-lg bg-zinc-900 px-3 py-1.5 font-medium text-white hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :disabled="saving"
                        @click="savePositions"
                    >
                        {{ saving ? 'Сохранение…' : 'Сохранить позицию' }}
                    </button>
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100"
                role="status"
            >
                Для этого шаблона отключены смещения из CRM: подпись и печать вставляются в DOCX только в местах плейсхолдеров. Ниже — предпросмотр с уже встроенными изображениями (как при печати).
            </div>

            <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div ref="overlayCanvas" class="relative mx-auto w-full max-w-[980px]" :style="canvasStyle">
                    <iframe
                        :src="embedUrl"
                        :class="overlayPositioningEnabled ? 'pointer-events-none' : ''"
                        class="absolute inset-0 h-full w-full border-0"
                        title="Template preview"
                    />

                    <button
                        v-if="overlayPositioningEnabled && stampOverlayImageUrl"
                        type="button"
                        class="absolute z-20 cursor-move rounded border border-emerald-500/70 bg-transparent"
                        :style="stampStyle"
                        @pointerdown="startDrag($event, 'stamp')"
                    >
                        <img :src="stampOverlayImageUrl" alt="Печать" class="h-full w-full select-none object-contain" draggable="false" />
                    </button>

                    <button
                        v-if="overlayPositioningEnabled && signatureOverlayImageUrl"
                        type="button"
                        class="absolute z-30 cursor-move rounded border border-sky-500/70 bg-transparent"
                        :style="signatureStyle"
                        @pointerdown="startDrag($event, 'signature')"
                    >
                        <img :src="signatureOverlayImageUrl" alt="Подпись" class="h-full w-full select-none object-contain" draggable="false" />
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    OVERLAY_PREVIEW_SIGNATURE_ANCHOR_LEGACY,
    OVERLAY_PREVIEW_STAMP_ANCHOR_LEGACY,
    useOverlayPreviewGeometry,
} from '@/composables/useOverlayPreviewGeometry';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'configuration', activeLeafKey: 'templates' }, () => page),
});

const props = defineProps({
    documentPreview: {
        type: Object,
        default: () => ({
            driver: 'html',
            gotenberg_url_configured: false,
            pdf_preview_available: false,
            hint: '',
        }),
    },
    templateId: { type: Number, required: true },
    templateName: { type: String, required: true },
    entityType: { type: String, required: true },
    embedUrl: { type: String, required: true },
    saveUrl: { type: String, required: true },
    backUrl: { type: String, required: true },
    orderId: { type: Number, default: null },
    leadId: { type: Number, default: null },
    signatureOverlayImageUrl: { type: String, default: null },
    stampOverlayImageUrl: { type: String, default: null },
    signatureOffsetXmm: { type: Number, default: 0 },
    signatureOffsetYmm: { type: Number, default: 0 },
    stampOffsetXmm: { type: Number, default: 0 },
    stampOffsetYmm: { type: Number, default: 0 },
    signatureWidthMm: { type: Number, default: 42 },
    signatureHeightMm: { type: Number, default: 18 },
    stampWidthMm: { type: Number, default: 30 },
    stampHeightMm: { type: Number, default: 30 },
    overlayPositioningEnabled: { type: Boolean, default: true },
});

const saving = ref(false);
const dragState = ref(null);
const overlayCanvas = ref(null);

const { pxPerMm, pageHeightPx, buildOverlayStyle } = useOverlayPreviewGeometry(overlayCanvas);

const canvasStyle = computed(() => ({
    minHeight: `${Math.max(Math.round(pageHeightPx.value), 1700)}px`,
}));

const signatureOffsetXmm = ref(Number(props.signatureOffsetXmm || 0));
const signatureOffsetYmm = ref(Number(props.signatureOffsetYmm || 0));
const stampOffsetXmm = ref(Number(props.stampOffsetXmm || 0));
const stampOffsetYmm = ref(Number(props.stampOffsetYmm || 0));

const signatureStyle = computed(() =>
    buildOverlayStyle(
        OVERLAY_PREVIEW_SIGNATURE_ANCHOR_LEGACY,
        Number(props.signatureWidthMm || 42),
        Number(props.signatureHeightMm || 18),
        signatureOffsetXmm.value,
        signatureOffsetYmm.value,
    ),
);

const stampStyle = computed(() =>
    buildOverlayStyle(
        OVERLAY_PREVIEW_STAMP_ANCHOR_LEGACY,
        Number(props.stampWidthMm || 30),
        Number(props.stampHeightMm || 30),
        stampOffsetXmm.value,
        stampOffsetYmm.value,
    ),
);

function roundTenths(value) {
    return Math.round(value * 10) / 10;
}

function setOffsets(overlay, x, y) {
    if (overlay === 'signature') {
        signatureOffsetXmm.value = roundTenths(x);
        signatureOffsetYmm.value = roundTenths(y);

        return;
    }

    stampOffsetXmm.value = roundTenths(x);
    stampOffsetYmm.value = roundTenths(y);
}

function startDrag(event, overlay) {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();

    dragState.value = {
        overlay,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startOffsetX: overlay === 'signature' ? signatureOffsetXmm.value : stampOffsetXmm.value,
        startOffsetY: overlay === 'signature' ? signatureOffsetYmm.value : stampOffsetYmm.value,
    };

    window.addEventListener('pointermove', onDragMove);
    window.addEventListener('pointerup', stopDrag);
}

function onDragMove(event) {
    if (dragState.value === null) {
        return;
    }

    const px = pxPerMm.value;
    const deltaXmm = (event.clientX - dragState.value.startClientX) / px;
    const deltaYmm = (event.clientY - dragState.value.startClientY) / px;

    setOffsets(
        dragState.value.overlay,
        dragState.value.startOffsetX + deltaXmm,
        dragState.value.startOffsetY + deltaYmm,
    );
}

function stopDrag() {
    if (dragState.value === null) {
        return;
    }

    dragState.value = null;
    window.removeEventListener('pointermove', onDragMove);
    window.removeEventListener('pointerup', stopDrag);
}

function resetPosition(overlay) {
    setOffsets(overlay, 0, 0);
}

function savePositions() {
    saving.value = true;

    router.post(
        props.saveUrl,
        {
            signature_offset_x_mm: signatureOffsetXmm.value,
            signature_offset_y_mm: signatureOffsetYmm.value,
            stamp_offset_x_mm: stampOffsetXmm.value,
            stamp_offset_y_mm: stampOffsetYmm.value,
            order_id: props.orderId,
            lead_id: props.leadId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

onBeforeUnmount(() => {
    stopDrag();
});
</script>
