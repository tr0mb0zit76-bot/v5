<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Единый список документов по заказам: по подписи документа открывается предпросмотр файла (или экран печатной формы для заявок по шаблону). Состав строк совпадает с доступом в разделе «Заказы». Заявки и договоры из мастера — колонки «Заявка …» / «Договор …»."
            title="Реестр документов"
        >
            <template #actions>
                <button
                    type="button"
                    :class="crmBtnCreate"
                    @click="openCreateModal()"
                >
                    Добавить документ
                </button>
            </template>
        </CrmPageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                :class="viewMode === 'all' ? crmPillActive : crmPill"
                @click="viewMode = 'all'"
            >
                Все документы ({{ props.rows.length }})
            </button>
            <button
                type="button"
                class="inline-flex items-center text-sm transition"
                :class="viewMode === 'etrn'
                    ? 'rounded-full border border-emerald-800 bg-emerald-900 px-3 py-1.5 text-white dark:border-emerald-200 dark:bg-emerald-200 dark:text-emerald-950'
                    : crmPill"
                @click="viewMode = 'etrn'"
            >
                Журнал ЭТрН ({{ etrnRows.length }})
            </button>
        </div>

        <div :class="crmGridPanel">
            <DocumentsGrid
                :rows="visibleRows"
                :user-id="userId"
                :can-edit-track-received-dates="canEditTrackReceivedDates"
                @open-create="openCreateModal"
                @open-order-documents="openOrderDocumentsFromGrid"
            />
        </div>

        <OrderDocumentsModal
            :show="orderDocumentsModal.show"
            :order-id="orderDocumentsModal.orderId"
            :order-number="orderDocumentsModal.orderNumber"
            @close="closeOrderDocumentsModal"
        />

        <Modal :show="showDocumentModal" max-width="5xl" @close="closeDocumentModal">
            <section :class="crmModalFormShell">
                <CrmModalHeader
                    :eyebrow="modalMode === 'create' ? 'Новый документ' : 'Редактирование документа'"
                    :title="modalMode === 'create' ? 'Добавить документ' : 'Редактировать документ'"
                    @close="closeDocumentModal"
                />
                <form :class="`${crmModalFormBody} space-y-4 px-6 pb-6 pt-2`" @submit.prevent="submitDocument">
                    <div :class="crmModalFieldsWrap">
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Заказ</label>
                            <select v-model="documentForm.order_id" :class="crmFieldFluid" required>
                                <option :value="null" disabled>Выберите заказ</option>
                                <option v-for="order in props.orders" :key="`ord-${order.id}`" :value="order.id">{{ orderLabel(order) }}</option>
                            </select>
                            <p v-if="documentForm.errors.order_id" class="w-full text-xs text-rose-600">{{ documentForm.errors.order_id }}</p>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Сторона</label>
                            <select v-model="documentForm.party" :class="crmFieldFluid" required>
                                <option value="customer">Заказчик</option>
                                <option value="carrier">Перевозчик</option>
                                <option value="internal">Внутренний</option>
                            </select>
                            <p v-if="documentForm.errors.party" class="w-full text-xs text-rose-600">{{ documentForm.errors.party }}</p>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Тип</label>
                            <select v-model="documentForm.type" :class="crmFieldFluid" required>
                                <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                            <p v-if="documentForm.errors.type" class="w-full text-xs text-rose-600">{{ documentForm.errors.type }}</p>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Статус</label>
                            <select v-model="documentForm.status" :class="crmFieldFluid" required>
                                <option value="draft">Черновик</option>
                                <option value="pending">Ожидает</option>
                                <option value="signed">Подписан</option>
                                <option value="sent">Отправлен</option>
                            </select>
                            <p v-if="documentForm.errors.status" class="w-full text-xs text-rose-600">{{ documentForm.errors.status }}</p>
                        </div>
                        <div :class="`${crmModalFieldRow} flex-wrap`">
                            <label :class="crmModalFieldLabel">Номер</label>
                            <input v-model="documentForm.number" type="text" :class="crmFieldFluid">
                            <p v-if="documentForm.errors.number" class="w-full text-xs text-rose-600">{{ documentForm.errors.number }}</p>
                        </div>
                        <div :class="`${crmModalFieldRow} flex-wrap`">
                            <label :class="crmModalFieldLabel">Дата</label>
                            <input v-model="documentForm.document_date" type="date" :class="crmFieldFluid">
                            <p v-if="documentForm.errors.document_date" class="w-full text-xs text-rose-600">{{ documentForm.errors.document_date }}</p>
                        </div>
                    </div>
                    <div class="w-full">
                        <div
                            class="mt-2 rounded-xl border border-dashed px-4 py-5 text-center transition-colors"
                            :class="documentFileDrop.active
                                ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-950/40'
                                : 'border-zinc-200 bg-zinc-50/40 dark:border-zinc-700 dark:bg-zinc-900/20'"
                            @dragenter.prevent="onDocumentModalDragEnter"
                            @dragleave.prevent="onDocumentModalDragLeave"
                            @dragover.prevent="onDocumentModalDragOver"
                            @drop.prevent="onDocumentModalDrop"
                        >
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Перетащите файл сюда или выберите на диске</p>
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                                <span>Выбрать файл</span>
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                                    class="hidden"
                                    @change="onFilePicked"
                                >
                            </label>
                            <p v-if="documentForm.file?.name" class="mt-2 text-xs text-zinc-500">Выбран: {{ documentForm.file.name }}</p>
                        </div>
                        <p v-if="documentForm.errors.file" class="mt-1 text-xs text-rose-600">{{ documentForm.errors.file }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            :class="crmBtnNeutral"
                            @click="closeDocumentModal"
                        >
                            Отмена
                        </button>
                        <button
                            type="submit"
                            :class="crmBtnCreate"
                            :disabled="documentForm.processing"
                        >
                            {{ modalMode === 'create' ? 'Добавить' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </section>
        </Modal>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { mergeDocumentUploadLimits } from '@/support/documentUploadClientCheck.js';
import { useDocumentUploadGate } from '@/support/documentUploadGate.js';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import Modal from '@/Components/Modal.vue';
import DocumentsGrid from '@/Components/Documents/DocumentsGrid.vue';
import OrderDocumentsModal from '@/Components/Orders/OrderDocumentsModal.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmFieldFluid,
    crmGridPanel,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
    crmModalFormBody,
    crmModalFormShell,
    crmPill,
    crmPillActive,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'documents', mainFill: true }, () => page),
});

const props = defineProps({
    rows: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    can_edit_track_received_dates: { type: Boolean, default: false },
});

const page = usePage();
const uploadGate = useDocumentUploadGate();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const canEditTrackReceivedDates = computed(() => Boolean(props.can_edit_track_received_dates));
const documentUploadLimits = computed(() => mergeDocumentUploadLimits(
    page.props.document_upload_limits ?? {},
    page.props.document_optimize ?? {},
));
const showDocumentModal = ref(false);
const modalMode = ref('create');
const editingDocumentId = ref(null);
const viewMode = ref('all');

const orderDocumentsModal = reactive({
    show: false,
    orderId: null,
    orderNumber: '',
});

const documentFileDrop = reactive({ depth: 0, active: false });

const etrnRows = computed(() => {
    return props.rows.filter((row) => Array.isArray(row.transport_docs)
        && row.transport_docs.some((document) => document?.type === 'etrn'));
});

const visibleRows = computed(() => (viewMode.value === 'etrn' ? etrnRows.value : props.rows));

const documentTypes = [
    { value: 'invoice', label: 'Счёт' },
    { value: 'upd', label: 'УПД' },
    { value: 'act', label: 'Акт' },
    { value: 'invoice_factura', label: 'Счёт-фактура' },
    { value: 'contract_request', label: 'Договор' },
    { value: 'waybill', label: 'ТН / ЭТрН / CMR / ТСД' },
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

async function onFilePicked(event) {
    const [file] = event.target.files || [];
    if (file) {
        const accepted = await uploadGate.ensureDocumentWithinBudget(file, documentUploadLimits.value);
        documentForm.file = accepted;
    } else {
        documentForm.file = null;
    }
    const input = event.target;
    if (input && 'value' in input) {
        input.value = '';
    }
}

function onDocumentModalDragEnter() {
    documentFileDrop.depth += 1;
    documentFileDrop.active = true;
}

function onDocumentModalDragLeave() {
    documentFileDrop.depth = Math.max(0, documentFileDrop.depth - 1);
    if (documentFileDrop.depth === 0) {
        documentFileDrop.active = false;
    }
}

function onDocumentModalDragOver(event) {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
}

async function onDocumentModalDrop(event) {
    event.preventDefault();
    documentFileDrop.depth = 0;
    documentFileDrop.active = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (!file) {
        return;
    }

    const accepted = await uploadGate.ensureDocumentWithinBudget(file, documentUploadLimits.value);
    documentForm.file = accepted;
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
    documentFileDrop.depth = 0;
    documentFileDrop.active = false;
    documentForm.reset();
    documentForm.clearErrors();
}

function openOrderDocumentsFromGrid(row) {
    if (!row?.order_id) {
        return;
    }

    orderDocumentsModal.show = true;
    orderDocumentsModal.orderId = row.order_id;
    orderDocumentsModal.orderNumber = row.order_number || '';
}

function closeOrderDocumentsModal() {
    orderDocumentsModal.show = false;
    orderDocumentsModal.orderId = null;
    orderDocumentsModal.orderNumber = '';
    router.reload({ only: ['rows'], preserveScroll: true });
}

function submitDocument() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDocumentModal();
        },
    };

    if (modalMode.value === 'create') {
        documentForm.post(route('documents.store'), options);
    } else {
        documentForm.patch(route('documents.update', editingDocumentId.value), options);
    }
}
</script>
