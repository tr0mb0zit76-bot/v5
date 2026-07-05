<script setup>
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, Search, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import MobileDocumentOptimizeSheet from '@/Components/Mobile/MobileDocumentOptimizeSheet.vue';
import { documentPartyLabel } from '@/support/mobileDocumentPartyLabels.js';
import {
    assessDocumentUploadBudget,
    mergeDocumentUploadLimits,
} from '@/support/documentUploadClientCheck.js';
import { formatFileSizeHuman } from '@/support/documentOptimizeClient.js';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    presetOrderId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['close', 'uploaded']);

const page = usePage();
const isExternalUser = computed(() => Boolean(page.props.auth?.user?.is_external));
const documentUploadLimits = computed(() => mergeDocumentUploadLimits(
    page.props.document_upload_limits ?? {},
    page.props.document_optimize ?? {},
));
const documentUploadHint = computed(() => documentUploadLimits.value?.hint_ru ?? '');

const step = ref('file');
const selectedFile = ref(null);
const budgetChecking = ref(false);
const optimizeSheetOpen = ref(false);
/** @type {import('vue').Ref<{ file: File, limits: object, budget: object }|null>} */
const optimizeSheetState = ref(null);
const orderSearch = ref('');
const orders = ref([]);
const ordersLoading = ref(false);
const selectedOrder = ref(null);
const slots = ref([]);
const slotsLoading = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const error = ref('');

const fileInput = ref(null);

const fileLabel = computed(() => selectedFile.value?.name ?? 'Файл не выбран');
const fileSizeLabel = computed(() => (
    selectedFile.value ? formatFileSizeHuman(selectedFile.value.size) : ''
));
const pendingSlots = computed(() => slots.value.filter((slot) => !slot.completed));
const completedSlots = computed(() => slots.value.filter((slot) => slot.completed));

async function loadOrders() {
    ordersLoading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(
            isExternalUser.value ? route('mobile.shell.counterparty.orders') : route('mobile.shell.orders'),
            {
            headers: { Accept: 'application/json' },
            params: orderSearch.value.trim() !== '' ? { q: orderSearch.value.trim() } : {},
        });
        orders.value = data.orders ?? [];
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Не удалось загрузить заказы.';
    } finally {
        ordersLoading.value = false;
    }
}

async function selectOrder(order) {
    selectedOrder.value = order;
    slotsLoading.value = true;
    error.value = '';

    try {
        const slotsRoute = isExternalUser.value
            ? route('mobile.shell.counterparty.orders.document-slots', order.id)
            : route('mobile.shell.orders.document-slots', order.id);
        const { data } = await axios.get(slotsRoute, {
            headers: { Accept: 'application/json' },
        });
        slots.value = data.slots ?? [];
        selectedOrder.value = data.order ?? order;
        step.value = 'slot';
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Не удалось загрузить слоты документов.';
    } finally {
        slotsLoading.value = false;
    }
}

async function bootstrapPresetOrder(orderId) {
    await loadOrders();

    let order = orders.value.find((item) => Number(item.id) === Number(orderId));

    if (!order) {
        try {
            const summaryRoute = isExternalUser.value
                ? route('mobile.shell.counterparty.orders.summary', orderId)
                : route('mobile.shell.orders.summary', orderId);
            const { data } = await axios.get(summaryRoute, {
                headers: { Accept: 'application/json' },
            });
            order = {
                id: data.order?.id ?? orderId,
                order_number: data.order?.order_number ?? `#${orderId}`,
                customer_name: data.order?.customer_name,
            };
        } catch {
            order = {
                id: orderId,
                order_number: `#${orderId}`,
            };
        }
    }

    await selectOrder(order);
}

function budgetErrorMessage(file, budget, limits) {
    const curMb = (file.size / 1024 / 1024).toFixed(2);
    const abs = Number(limits.server_upload_max_bytes) || Number(limits.absolute_max_bytes) || 0;

    if (budget.overAbsolute && abs > 0) {
        const maxMb = (abs / 1024 / 1024).toFixed(1);

        return `Файл слишком большой (${curMb} МиБ). Абсолютный предел около ${maxMb} МиБ.`;
    }

    const limMb = (budget.maxBytes / 1024 / 1024).toFixed(2);
    const kb = Math.round(Number(limits.bytes_per_page) / 1024) || 600;

    return `Размер (${curMb} МиБ) превышает лимит (до ${limMb} МиБ: ~${kb} КиБ × ${budget.pages} стр.).`;
}

function clearFileInput() {
    selectedFile.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function proceedAfterFileAccepted() {
    if (!selectedFile.value) {
        return;
    }

    if (props.presetOrderId) {
        bootstrapPresetOrder(props.presetOrderId);

        return;
    }

    step.value = 'order';
    loadOrders();
}

async function acceptFileWithBudget(file) {
    budgetChecking.value = true;
    error.value = '';

    try {
        const limits = documentUploadLimits.value;
        const budget = await assessDocumentUploadBudget(file, limits);

        if (budget.exceeds) {
            if (budget.canOptimize) {
                optimizeSheetState.value = { file, limits, budget };
                optimizeSheetOpen.value = true;

                return;
            }

            error.value = budgetErrorMessage(file, budget, limits);
            clearFileInput();

            return;
        }

        selectedFile.value = file;
        proceedAfterFileAccepted();
    } finally {
        budgetChecking.value = false;
    }
}

async function pickFile(event) {
    const file = event.target.files?.[0] ?? null;

    if (!file) {
        return;
    }

    await acceptFileWithBudget(file);
}

function onOptimizeAccepted(file) {
    optimizeSheetOpen.value = false;
    optimizeSheetState.value = null;
    selectedFile.value = file;
    proceedAfterFileAccepted();
}

function onOptimizeCancel() {
    optimizeSheetOpen.value = false;
    optimizeSheetState.value = null;
    clearFileInput();
}

async function uploadToSlot(slot) {
    if (!selectedFile.value || !selectedOrder.value || uploading.value) {
        return;
    }

    if (slot.completed) {
        const confirmed = window.confirm(
            `В слоте «${slot.label}» уже есть файл. Заменить его новым «${selectedFile.value.name}»?`,
        );

        if (!confirmed) {
            return;
        }
    }

    uploading.value = true;
    uploadProgress.value = 0;
    error.value = '';

    const form = new FormData();
    form.append('file', selectedFile.value);

    const uploadRoute = isExternalUser.value
        ? route('mobile.shell.counterparty.orders.documents.store', selectedOrder.value.id)
        : route('documents.store');

    if (isExternalUser.value) {
        form.append('type', slot.type);
        form.append('requirement_slot_key', slot.requirement_slot_key);
    } else {
        form.append('order_id', String(selectedOrder.value.id));
        form.append('party', slot.party);
        form.append('type', slot.type);
        form.append('status', 'sent');
        form.append('requirement_slot_key', slot.requirement_slot_key);

        if (slot.order_leg_stage) {
            form.append('order_leg_stage', slot.order_leg_stage);
        }

        if (slot.contractor_id) {
            form.append('contractor_id', String(slot.contractor_id));
        }
    }

    try {
        const { data } = await axios.post(uploadRoute, form, {
            headers: {
                Accept: 'application/json',
            },
            onUploadProgress: (event) => {
                if (!event.total) {
                    uploadProgress.value = 50;

                    return;
                }

                uploadProgress.value = Math.min(100, Math.round((event.loaded * 100) / event.total));
            },
        });

        uploadProgress.value = 100;
        emit('uploaded', data.document ?? null);
        emit('close');
        resetState();
    } catch (exception) {
        const validation = exception.response?.data?.errors;
        error.value = validation
            ? Object.values(validation).flat().join(' ')
            : (exception.response?.data?.message ?? 'Не удалось загрузить документ.');
    } finally {
        uploading.value = false;
        uploadProgress.value = 0;
    }
}

function resetState() {
    step.value = 'file';
    selectedFile.value = null;
    budgetChecking.value = false;
    optimizeSheetOpen.value = false;
    optimizeSheetState.value = null;
    selectedOrder.value = null;
    orderSearch.value = '';
    orders.value = [];
    slots.value = [];
    error.value = '';
    uploading.value = false;
    uploadProgress.value = 0;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function goBack() {
    if (uploading.value) {
        return;
    }

    if (step.value === 'slot') {
        step.value = 'order';

        return;
    }

    if (step.value === 'order') {
        step.value = 'file';
    }
}

function closeWizard() {
    if (uploading.value) {
        return;
    }

    emit('close');
    resetState();
}

let orderSearchTimer = null;

watch(orderSearch, () => {
    if (step.value !== 'order') {
        return;
    }

    clearTimeout(orderSearchTimer);
    orderSearchTimer = setTimeout(loadOrders, 250);
});

watch(() => props.open, (isOpen) => {
    if (!isOpen) {
        resetState();
    }
});
</script>

<template>
    <div
        v-if="open"
        class="absolute inset-0 z-30 flex flex-col justify-end bg-black/60"
        @click.self="closeWizard"
    >
        <div class="flex max-h-[82dvh] flex-col overflow-hidden rounded-t-3xl border border-white/10 bg-zinc-900">
            <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                <button
                    v-if="step !== 'file'"
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-zinc-200 active:bg-white/10 disabled:opacity-40"
                    :disabled="uploading"
                    @click="goBack"
                >
                    <ArrowLeft class="h-4 w-4" />
                </button>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-zinc-100">Прикрепить файл к заказу</div>
                    <div class="truncate text-xs text-zinc-500">
                        <span v-if="step === 'file'">Фото или PDF с телефона</span>
                        <span v-else-if="step === 'order'">
                            {{ fileLabel }}
                            <span v-if="fileSizeLabel" class="text-zinc-600"> · {{ fileSizeLabel }}</span>
                        </span>
                        <span v-else>{{ selectedOrder?.order_number }} · выберите слот</span>
                    </div>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
                <div v-if="uploading" class="mb-4 space-y-2 rounded-2xl border border-sky-500/30 bg-sky-500/10 p-4">
                    <div class="text-sm font-medium text-sky-100">Загрузка в CRM… {{ uploadProgress }}%</div>
                    <div class="h-2 overflow-hidden rounded-full bg-black/30">
                        <div
                            class="h-full rounded-full bg-sky-500 transition-all duration-200"
                            :style="{ width: `${uploadProgress}%` }"
                        />
                    </div>
                </div>

                <div v-if="step === 'file' && !uploading" class="space-y-4">
                    <div v-if="budgetChecking" class="py-6 text-center text-sm text-zinc-500">Проверка размера файла…</div>
                    <label
                        v-else
                        class="flex cursor-pointer flex-col items-center justify-center rounded-3xl border border-dashed border-white/15 bg-white/[0.03] px-6 py-10 text-center active:bg-white/10"
                    >
                        <Upload class="mb-3 h-8 w-8 text-sky-300" />
                        <span class="text-sm font-semibold text-zinc-100">Выбрать файл</span>
                        <span class="mt-1 text-xs text-zinc-500">PDF, JPG, PNG, DOCX, XLSX</span>
                        <span v-if="documentUploadHint" class="mt-2 max-w-xs text-[11px] leading-snug text-zinc-600">{{ documentUploadHint }}</span>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,image/*"
                            capture="environment"
                            class="hidden"
                            @change="pickFile"
                        />
                    </label>
                </div>

                <div v-else-if="step === 'order' && !uploading" class="space-y-3">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
                        <input
                            v-model="orderSearch"
                            class="w-full rounded-2xl border border-white/10 bg-zinc-950 py-2.5 pl-10 pr-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                            placeholder="Найти заказ"
                        />
                    </div>
                    <div v-if="ordersLoading" class="py-6 text-center text-sm text-zinc-500">Загрузка заказов…</div>
                    <button
                        v-for="order in orders"
                        v-else
                        :key="`upload-order-${order.id}`"
                        type="button"
                        class="block w-full rounded-3xl border border-white/10 bg-white/[0.04] p-4 text-left active:bg-white/10"
                        @click="selectOrder(order)"
                    >
                        <div class="text-sm font-semibold text-zinc-50">{{ order.order_number }}</div>
                        <div class="mt-1 text-xs text-zinc-400">{{ order.customer_name || 'Заказчик не указан' }}</div>
                        <div
                            v-if="order.documents_pending_count > 0"
                            class="mt-2 text-xs text-amber-200"
                        >
                            {{ order.documents_pending_count }} незакрытых слотов документов
                        </div>
                    </button>
                </div>

                <div v-else-if="step === 'slot' && !uploading" class="space-y-4">
                    <div v-if="slotsLoading" class="py-6 text-center text-sm text-zinc-500">Загрузка слотов…</div>
                    <template v-else>
                        <div v-if="pendingSlots.length" class="space-y-2">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-300">Требуют файл</div>
                            <button
                                v-for="slot in pendingSlots"
                                :key="slot.key"
                                type="button"
                                class="block w-full rounded-3xl border border-amber-500/25 bg-amber-500/10 p-4 text-left active:opacity-90"
                                @click="uploadToSlot(slot)"
                            >
                                <div class="text-sm font-semibold text-zinc-50">{{ slot.label }}</div>
                                <div class="mt-1 text-xs text-zinc-400">
                                    {{ documentPartyLabel(slot.party) }} · {{ slot.type }}
                                </div>
                            </button>
                        </div>

                        <div v-if="completedSlots.length" class="space-y-2">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Уже загружено</div>
                            <button
                                v-for="slot in completedSlots"
                                :key="slot.key"
                                type="button"
                                class="block w-full rounded-3xl border border-white/10 bg-white/[0.04] p-4 text-left active:opacity-90"
                                @click="uploadToSlot(slot)"
                            >
                                <div class="text-sm font-semibold text-zinc-50">{{ slot.label }}</div>
                                <div class="mt-1 text-xs text-zinc-400">
                                    {{ documentPartyLabel(slot.party) }} · {{ slot.type }}
                                </div>
                                <div class="mt-2 text-[10px] uppercase tracking-wide text-zinc-500">Нажмите, чтобы заменить</div>
                            </button>
                        </div>

                        <div v-if="pendingSlots.length === 0 && completedSlots.length === 0" class="py-6 text-center text-sm text-zinc-500">
                            Нет доступных слотов для этого заказа.
                        </div>
                    </template>
                </div>

                <p v-if="error" class="mt-4 text-xs text-rose-300">{{ error }}</p>
            </div>
        </div>

        <MobileDocumentOptimizeSheet
            :open="optimizeSheetOpen"
            :state="optimizeSheetState"
            @accept="onOptimizeAccepted"
            @cancel="onOptimizeCancel"
        />
    </div>
</template>
