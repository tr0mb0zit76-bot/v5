<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Реестр документов</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Единый список документов по заказам с быстрыми ссылками в карточку заказа. Состав строк совпадает с доступом в разделе «Заказы» (например, менеджер видит в основном только свои заказы).
                    Заявки и договоры из мастера — колонки «Заявка …» / «Договор …».
                </p>
            </div>
            <button
                type="button"
                class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                @click="openCreateModal()"
            >
                Добавить документ
            </button>
        </div>

        <DocumentsGrid :rows="props.rows" :user-id="userId" @open-create="openCreateModal" />

        <Modal :show="showDocumentModal" max-width="2xl" @close="closeDocumentModal">
            <section class="space-y-4 bg-white p-5 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">{{ modalMode === 'create' ? 'Добавить документ' : 'Редактировать документ' }}</h2>
                <form class="space-y-3" @submit.prevent="submitDocument">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs text-zinc-500">Заказ</label>
                            <select v-model="documentForm.order_id" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" required>
                                <option :value="null" disabled>Выберите заказ</option>
                                <option v-for="order in props.orders" :key="`ord-${order.id}`" :value="order.id">{{ orderLabel(order) }}</option>
                            </select>
                            <p v-if="documentForm.errors.order_id" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.order_id }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Сторона</label>
                            <select v-model="documentForm.party" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" required>
                                <option value="customer">Заказчик</option>
                                <option value="carrier">Перевозчик</option>
                                <option value="internal">Внутренний</option>
                            </select>
                            <p v-if="documentForm.errors.party" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.party }}</p>
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs text-zinc-500">Тип документа</label>
                            <select v-model="documentForm.type" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" required>
                                <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                            <p v-if="documentForm.errors.type" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.type }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Статус</label>
                            <select v-model="documentForm.status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" required>
                                <option value="draft">Черновик</option>
                                <option value="pending">Ожидает</option>
                                <option value="signed">Подписан</option>
                                <option value="sent">Отправлен</option>
                            </select>
                            <p v-if="documentForm.errors.status" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.status }}</p>
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs text-zinc-500">Номер документа</label>
                            <input v-model="documentForm.number" type="text" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <p v-if="documentForm.errors.number" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.number }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Дата документа</label>
                            <input v-model="documentForm.document_date" type="date" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <p v-if="documentForm.errors.document_date" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.document_date }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-zinc-500">Файл (договор до 3 МБ, остальные до 1 МБ)</label>
                        <input type="file" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" @change="onFilePicked">
                        <p v-if="documentForm.errors.file" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.file }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="closeDocumentModal">Отмена</button>
                        <button type="submit" class="rounded-xl bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200" :disabled="documentForm.processing">
                            {{ modalMode === 'create' ? 'Добавить' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </section>
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import DocumentsGrid from '@/Components/Documents/DocumentsGrid.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'documents' }, () => page),
});

const props = defineProps({
    rows: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
});

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const showDocumentModal = ref(false);
const modalMode = ref('create');
const editingDocumentId = ref(null);

const documentTypes = [
    { value: 'invoice', label: 'Счёт' },
    { value: 'upd', label: 'УПД' },
    { value: 'act', label: 'Акт' },
    { value: 'invoice_factura', label: 'Счёт-фактура' },
    { value: 'contract_request', label: 'Договор' },
    { value: 'waybill', label: 'Транспортная накладная' },
    { value: 'cmr', label: 'CMR' },
    { value: 'packing_list', label: 'Упаковочный лист' },
    { value: 'customs_declaration', label: 'Таможенная декларация' },
    { value: 'other', label: 'Прочее' },
];

const documentForm = useForm({
    order_id: null,
    party: 'customer',
    type: 'invoice',
    number: '',
    document_date: '',
    status: 'draft',
    file: null,
});

function orderLabel(order) {
    return order.order_number ? `${order.order_number} (${order.customer_name || '—'})` : `#${order.id}`;
}

function onFilePicked(event) {
    const [file] = event.target.files || [];
    documentForm.file = file ?? null;
}

function openCreateModal(orderId = null) {
    modalMode.value = 'create';
    editingDocumentId.value = null;
    documentForm.reset();
    documentForm.clearErrors();
    documentForm.order_id = orderId;
    documentForm.party = 'customer';
    documentForm.type = 'invoice';
    documentForm.status = 'draft';
    showDocumentModal.value = true;
}

function closeDocumentModal() {
    showDocumentModal.value = false;
    documentForm.reset();
    documentForm.clearErrors();
}

function submitDocument() {
    const method = modalMode.value === 'create' ? documentForm.post : documentForm.patch;
    const target = modalMode.value === 'create'
        ? route('documents.store')
        : route('documents.update', editingDocumentId.value);

    method(target, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDocumentModal();
        },
    });
}
</script>
