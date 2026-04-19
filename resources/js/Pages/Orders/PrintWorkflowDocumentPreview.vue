<template>
    <div
        class="flex min-h-0 flex-1 flex-col overflow-hidden bg-zinc-100 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-50"
    >
        <header class="shrink-0 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Предпросмотр заявки</div>
                    <h1 class="truncate text-lg font-semibold">{{ documentTitle }}</h1>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        Заказ {{ orderNumber }}
                        <span v-if="workflowStatusLabel" class="text-zinc-500"> · {{ workflowStatusLabel }}</span>
                    </p>
                </div>
                <Link
                    :href="route('orders.edit', orderId)"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:hover:bg-zinc-800"
                >
                    ← К редактированию заказа
                </Link>
            </div>
        </header>

        <main class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 overflow-hidden p-3">
            <div
                v-if="canAdjustOverlay && hasAnyOverlayImage"
                class="space-y-3 rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold">Позиционирование подписи и печати</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            Включи режим позиционирования, прокрути страницу документа и перетяни объекты.
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="positionModeEnabled" type="checkbox" class="rounded border-zinc-300" />
                        Режим позиционирования
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span>Подпись: X {{ signatureOffsetXmm }} мм, Y {{ signatureOffsetYmm }} мм</span>
                    <button
                        type="button"
                        class="rounded border border-zinc-300 px-2 py-1 hover:bg-zinc-100 dark:border-zinc-600 dark:hover:bg-zinc-800"
                        @click="resetPosition('signature')"
                    >
                        Сбросить подпись
                    </button>
                    <span>Печать: X {{ stampOffsetXmm }} мм, Y {{ stampOffsetYmm }} мм</span>
                    <button
                        type="button"
                        class="rounded border border-zinc-300 px-2 py-1 hover:bg-zinc-100 dark:border-zinc-600 dark:hover:bg-zinc-800"
                        @click="resetPosition('stamp')"
                    >
                        Сбросить печать
                    </button>
                    <button
                        type="button"
                        class="ml-auto inline-flex items-center rounded-lg bg-zinc-900 px-3 py-1.5 font-medium text-white hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :disabled="savingPositions"
                        @click="saveOverlayPositions"
                    >
                        {{ savingPositions ? 'Сохранение…' : 'Сохранить позицию' }}
                    </button>
                </div>
            </div>

            <div
                class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div ref="overlayCanvas" class="relative mx-auto w-full max-w-[980px]" :style="canvasStyle">
                    <iframe
                        :src="embedUrl"
                        :class="positionModeEnabled ? 'pointer-events-none' : 'pointer-events-auto'"
                        class="absolute inset-0 h-full w-full border-0"
                        title="Предпросмотр черновика"
                    />

                    <button
                        v-if="positionModeEnabled && stampOverlayImageUrl"
                        type="button"
                        class="absolute z-20 cursor-move rounded border border-emerald-500/70 bg-transparent"
                        :style="stampStyle"
                        @pointerdown="startDrag($event, 'stamp')"
                    >
                        <img :src="stampOverlayImageUrl" alt="Печать" class="h-full w-full select-none object-contain" draggable="false" />
                    </button>

                    <button
                        v-if="positionModeEnabled && signatureOverlayImageUrl"
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

        <footer class="shrink-0 border-t border-zinc-200 bg-white px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('orders.edit', orderId)"
                    class="text-sm font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    Вернуться и исправить данные
                </Link>
                <button
                    v-if="canRequestApproval"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    :disabled="submitting"
                    @click="sendForApproval"
                >
                    {{ submitting ? 'Отправка…' : 'Отправить на согласование' }}
                </button>
                <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Отправка на согласование сейчас недоступна.
                </p>
            </div>
        </footer>
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
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const props = defineProps({
    orderId: { type: Number, required: true },
    orderNumber: { type: String, required: true },
    documentId: { type: Number, required: true },
    documentTitle: { type: String, required: true },
    embedUrl: { type: String, required: true },
    workflowStatusLabel: { type: String, default: null },
    canRequestApproval: { type: Boolean, default: false },
    canAdjustOverlay: { type: Boolean, default: false },
    overlaySaveUrl: { type: String, default: null },
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
});

const submitting = ref(false);
const savingPositions = ref(false);
const positionModeEnabled = ref(Boolean(props.canAdjustOverlay));
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

const hasAnyOverlayImage = computed(() => Boolean(props.signatureOverlayImageUrl || props.stampOverlayImageUrl));

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
    if (!positionModeEnabled.value || event.button !== 0) {
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

function saveOverlayPositions() {
    if (!props.canAdjustOverlay || !props.overlaySaveUrl) {
        return;
    }

    savingPositions.value = true;

    router.post(
        props.overlaySaveUrl,
        {
            signature_offset_x_mm: signatureOffsetXmm.value,
            signature_offset_y_mm: signatureOffsetYmm.value,
            stamp_offset_x_mm: stampOffsetXmm.value,
            stamp_offset_y_mm: stampOffsetYmm.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                savingPositions.value = false;
            },
        },
    );
}

function sendForApproval() {
    submitting.value = true;
    router.post(
        route('orders.documents.request-approval', [props.orderId, props.documentId]),
        {},
        {
            preserveScroll: false,
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

onBeforeUnmount(() => {
    stopDrag();
});
</script>
