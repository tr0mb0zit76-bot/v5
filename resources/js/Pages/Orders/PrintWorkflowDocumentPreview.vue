<template>
    <div
        class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-zinc-100 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-50"
    >
        <header class="shrink-0 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex w-full max-w-screen-2xl min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
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

        <main class="mx-auto flex min-h-0 w-full max-w-screen-2xl min-w-0 flex-1 flex-col gap-3 p-2 sm:p-4">
            <div
                v-if="documentPreview && !documentPreview.pdf_preview_available"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                role="status"
            >
                {{ documentPreview.hint }}
            </div>
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
                        :class="crmBtnCreate"
                        class="ml-auto py-1.5 text-sm"
                        :disabled="savingPositions"
                        @click="saveOverlayPositions"
                    >
                        {{ savingPositions ? 'Сохранение…' : 'Сохранить позицию' }}
                    </button>
                </div>
            </div>

            <div
                class="min-h-0 min-w-0 flex-1 overflow-auto overscroll-contain rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div
                    v-if="!canEmbedPdfPreview"
                    class="flex min-h-[480px] flex-col items-center justify-center gap-4 p-8 text-center"
                >
                    <p class="max-w-lg text-sm text-zinc-600 dark:text-zinc-300">
                        Встроенный предпросмотр в браузере доступен только для PDF (нужен Gotenberg).
                        DOCX во фрейме Chrome не открывает — откройте файл отдельно или скачайте.
                    </p>
                    <a
                        :href="embedUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:hover:bg-zinc-800"
                    >
                        Открыть черновик в новой вкладке
                    </a>
                </div>

                <div v-else ref="overlayCanvas" class="relative mx-auto w-full min-w-0 max-w-none" :style="canvasStyle">
                    <iframe
                        :src="embedUrl"
                        :class="iframePointerEventsClass"
                        class="absolute inset-0 h-full w-full border-0"
                        title="Предпросмотр черновика"
                    />

                    <button
                        v-if="positionModeEnabled && !readonlyOverlayDecorations && stampOverlayImageUrl"
                        type="button"
                        class="absolute z-20 cursor-move rounded border border-emerald-500/70 bg-transparent"
                        :style="stampStyle"
                        @pointerdown="startDrag($event, 'stamp')"
                    >
                        <img :src="stampOverlayImageUrl" alt="Печать" class="h-full w-full select-none object-contain" draggable="false" />
                    </button>

                    <button
                        v-if="positionModeEnabled && !readonlyOverlayDecorations && signatureOverlayImageUrl"
                        type="button"
                        class="absolute z-30 cursor-move rounded border border-sky-500/70 bg-transparent"
                        :style="signatureStyle"
                        @pointerdown="startDrag($event, 'signature')"
                    >
                        <img :src="signatureOverlayImageUrl" alt="Подпись" class="h-full w-full select-none object-contain" draggable="false" />
                    </button>

                    <div
                        v-if="readonlyOverlayDecorations && stampOverlayImageUrl"
                        class="pointer-events-none absolute z-20 rounded border border-emerald-500/40 bg-transparent"
                        :style="stampStyle"
                    >
                        <img :src="stampOverlayImageUrl" alt="Печать" class="h-full w-full select-none object-contain" draggable="false" />
                    </div>

                    <div
                        v-if="readonlyOverlayDecorations && signatureOverlayImageUrl"
                        class="pointer-events-none absolute z-30 rounded border border-sky-500/40 bg-transparent"
                        :style="signatureStyle"
                    >
                        <img :src="signatureOverlayImageUrl" alt="Подпись" class="h-full w-full select-none object-contain" draggable="false" />
                    </div>
                </div>
            </div>
        </main>

        <footer class="shrink-0 border-t border-zinc-200 bg-white px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex w-full max-w-screen-2xl min-w-0 flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        :href="route('orders.edit', orderId)"
                        class="text-sm font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-200"
                    >
                        {{ canWorkflowApprove ? '← К заказу' : 'Вернуться и исправить данные' }}
                    </Link>
                    <a
                        v-if="finalPdfDownloadUrl"
                        :href="finalPdfDownloadUrl"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-800"
                    >
                        Скачать PDF
                    </a>
                    <div v-else-if="canWorkflowApprove || canWorkflowReject" class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                            :disabled="submitting || rejectSubmitting"
                            @click="approveWorkflow"
                        >
                            {{ submitting ? 'Подписание…' : 'Подписать' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-rose-300 px-5 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-700 dark:text-rose-200 dark:hover:bg-rose-950/40"
                            :disabled="submitting || rejectSubmitting"
                            @click="toggleRejectPanel"
                        >
                            Отказать
                        </button>
                    </div>
                    <button
                        v-else-if="canRequestApproval"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :disabled="submitting"
                        @click="sendForApproval"
                    >
                        {{ submitting ? 'Отправка…' : 'Отправить на согласование' }}
                    </button>
                    <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">Действия по этому документу сейчас недоступны.</p>
                </div>
                <div
                    v-if="rejectPanelOpen && (canWorkflowApprove || canWorkflowReject)"
                    class="rounded-xl border border-rose-200 bg-rose-50/50 p-3 dark:border-rose-900 dark:bg-rose-950/30"
                >
                    <label class="mb-1 block text-xs font-medium text-rose-900 dark:text-rose-200">Комментарий к отказу</label>
                    <textarea
                        v-model="rejectReason"
                        rows="3"
                        class="mb-2 w-full rounded-lg border border-rose-200 bg-white px-2 py-1.5 text-sm dark:border-rose-800 dark:bg-zinc-950"
                        placeholder="Укажите причину отказа"
                    />
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-800 disabled:opacity-50"
                            :disabled="!rejectReason.trim() || rejectSubmitting"
                            @click="submitWorkflowReject"
                        >
                            {{ rejectSubmitting ? 'Отправка…' : 'Подтвердить отказ' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs dark:border-zinc-600"
                            @click="cancelRejectPanel"
                        >
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate } from '@/support/crmUi.js';
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
    canWorkflowApprove: { type: Boolean, default: false },
    canWorkflowReject: { type: Boolean, default: false },
    workflowApproveUrl: { type: String, default: '' },
    workflowRejectUrl: { type: String, default: '' },
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
    documentPreview: {
        type: Object,
        default: () => ({
            driver: 'html',
            gotenberg_url_configured: false,
            pdf_preview_available: false,
            hint: '',
        }),
    },
    readonlyOverlayDecorations: { type: Boolean, default: false },
    finalPdfDownloadUrl: { type: String, default: null },
});

const submitting = ref(false);
const rejectSubmitting = ref(false);
const rejectPanelOpen = ref(false);
const rejectReason = ref('');
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

function resetOverlayUiFromProps() {
    signatureOffsetXmm.value = Number(props.signatureOffsetXmm || 0);
    signatureOffsetYmm.value = Number(props.signatureOffsetYmm || 0);
    stampOffsetXmm.value = Number(props.stampOffsetXmm || 0);
    stampOffsetYmm.value = Number(props.stampOffsetYmm || 0);
    positionModeEnabled.value = Boolean(props.canAdjustOverlay);
    stopDrag();
}

watch(
    () => [
        props.documentId,
        props.signatureOffsetXmm,
        props.signatureOffsetYmm,
        props.stampOffsetXmm,
        props.stampOffsetYmm,
        props.canAdjustOverlay,
        props.readonlyOverlayDecorations,
    ],
    () => {
        resetOverlayUiFromProps();
    },
);

const hasAnyOverlayImage = computed(() => Boolean(props.signatureOverlayImageUrl || props.stampOverlayImageUrl));

const canEmbedPdfPreview = computed(() => Boolean(props.documentPreview?.pdf_preview_available));

const iframePointerEventsClass = computed(() =>
    positionModeEnabled.value && !props.readonlyOverlayDecorations ? 'pointer-events-none' : 'pointer-events-auto',
);

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

function approveWorkflow() {
    if (!props.workflowApproveUrl) {
        return;
    }
    submitting.value = true;
    router.post(props.workflowApproveUrl, {}, {
        preserveScroll: false,
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function toggleRejectPanel() {
    rejectPanelOpen.value = !rejectPanelOpen.value;
}

function cancelRejectPanel() {
    rejectPanelOpen.value = false;
    rejectReason.value = '';
}

function submitWorkflowReject() {
    if (!props.workflowRejectUrl || !rejectReason.value.trim()) {
        return;
    }
    rejectSubmitting.value = true;
    router.post(
        props.workflowRejectUrl,
        { rejection_reason: rejectReason.value.trim() },
        {
            preserveScroll: false,
            onFinish: () => {
                rejectSubmitting.value = false;
            },
        },
    );
}

onBeforeUnmount(() => {
    stopDrag();
});
</script>
