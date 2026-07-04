<script setup>
import axios from 'axios';
import { ArrowLeft, Search, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'uploaded']);

const step = ref('file');
const selectedFile = ref(null);
const orderSearch = ref('');
const orders = ref([]);
const ordersLoading = ref(false);
const selectedOrder = ref(null);
const slots = ref([]);
const slotsLoading = ref(false);
const uploading = ref(false);
const error = ref('');

const fileInput = ref(null);

const fileLabel = computed(() => selectedFile.value?.name ?? 'Файл не выбран');

async function loadOrders() {
    ordersLoading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(route('mobile.shell.orders'), {
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
        const { data } = await axios.get(route('mobile.shell.orders.document-slots', order.id), {
            headers: { Accept: 'application/json' },
        });
        slots.value = data.slots ?? [];
        step.value = 'slot';
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Не удалось загрузить слоты документов.';
    } finally {
        slotsLoading.value = false;
    }
}

function pickFile(event) {
    const file = event.target.files?.[0] ?? null;
    selectedFile.value = file;
    if (file) {
        step.value = 'order';
        loadOrders();
    }
}

async function uploadToSlot(slot) {
    if (!selectedFile.value || !selectedOrder.value) {
        return;
    }

    uploading.value = true;
    error.value = '';

    const form = new FormData();
    form.append('file', selectedFile.value);
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

    try {
        const { data } = await axios.post(route('documents.store'), form, {
            headers: {
                Accept: 'application/json',
            },
        });

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
    }
}

function resetState() {
    step.value = 'file';
    selectedFile.value = null;
    selectedOrder.value = null;
    orderSearch.value = '';
    orders.value = [];
    slots.value = [];
    error.value = '';
    uploading.value = false;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function goBack() {
    if (step.value === 'slot') {
        step.value = 'order';
        return;
    }

    if (step.value === 'order') {
        step.value = 'file';
    }
}

function closeWizard() {
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
                    class="flex h-9 w-9 items-center justify-center rounded-full text-zinc-200 active:bg-white/10"
                    @click="goBack"
                >
                    <ArrowLeft class="h-4 w-4" />
                </button>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-zinc-100">Прикрепить файл к заказу</div>
                    <div class="truncate text-xs text-zinc-500">
                        <span v-if="step === 'file'">Фото или PDF с телефона</span>
                        <span v-else-if="step === 'order'">{{ fileLabel }}</span>
                        <span v-else>{{ selectedOrder?.order_number }} · выберите слот</span>
                    </div>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
                <div v-if="step === 'file'" class="space-y-4">
                    <label class="flex cursor-pointer flex-col items-center justify-center rounded-3xl border border-dashed border-white/15 bg-white/[0.03] px-6 py-10 text-center active:bg-white/10">
                        <Upload class="mb-3 h-8 w-8 text-sky-300" />
                        <span class="text-sm font-semibold text-zinc-100">Выбрать файл</span>
                        <span class="mt-1 text-xs text-zinc-500">PDF, JPG, PNG, DOCX, XLSX</span>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,image/*"
                            class="hidden"
                            @change="pickFile"
                        />
                    </label>
                </div>

                <div v-else-if="step === 'order'" class="space-y-3">
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
                    </button>
                </div>

                <div v-else class="space-y-3">
                    <div v-if="slotsLoading" class="py-6 text-center text-sm text-zinc-500">Загрузка слотов…</div>
                    <button
                        v-for="slot in slots"
                        v-else
                        :key="slot.key"
                        type="button"
                        class="block w-full rounded-3xl border p-4 text-left active:opacity-90"
                        :class="slot.completed ? 'border-white/10 bg-white/[0.04]' : 'border-amber-500/20 bg-amber-500/10'"
                        :disabled="uploading"
                        @click="uploadToSlot(slot)"
                    >
                        <div class="text-sm font-semibold text-zinc-50">{{ slot.label }}</div>
                        <div class="mt-1 text-xs text-zinc-400">{{ slot.party }} · {{ slot.type }}</div>
                        <div v-if="slot.completed" class="mt-2 text-[10px] uppercase tracking-wide text-zinc-500">Уже есть файл</div>
                    </button>
                </div>

                <p v-if="error" class="mt-4 text-xs text-rose-300">{{ error }}</p>
                <p v-if="uploading" class="mt-4 text-center text-sm text-zinc-400">Загружаем в CRM…</p>
            </div>
        </div>
    </div>
</template>
