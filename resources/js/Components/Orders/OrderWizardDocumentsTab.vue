<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Paperclip } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import OrderSignedDocumentsTable from '@/Components/Orders/OrderSignedDocumentsTable.vue';
import PrintWorkflowDocList from '@/Components/Orders/PrintWorkflowDocList.vue';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import {
    carrierPrintSlots,
    customerPrintSlots,
    printWorkflowDocumentsForSlot,
    signedRegistryDocuments,
} from '@/support/orderPrintFormSlots.js';
import { crmBtnCreate } from '@/support/crmUi.js';

const signedDocuments = defineModel('signedDocuments', { type: Array, default: () => [] });

const props = defineProps({
    order: { type: Object, default: null },
    performers: { type: Array, default: () => [] },
    clientRequestMode: { type: String, default: 'single_request' },
    isOrderFormEditable: { type: Boolean, default: true },
    allDocuments: { type: Array, default: () => [] },
    printFormTemplateOptionsCustomer: { type: Array, default: () => [] },
    printFormTemplateOptionsCarrier: { type: Array, default: () => [] },
    documentTypeOptions: { type: Array, default: () => [] },
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    documentTabValidationMessages: { type: Array, default: () => [] },
    documentStorage: { type: Object, default: () => ({}) },
});

const page = usePage();
const documentUploadHint = computed(() => page.props.document_upload_limits?.hint_ru ?? '');

const customerSlots = computed(() => customerPrintSlots(props.performers, props.clientRequestMode));
const carrierSlots = computed(() => carrierPrintSlots(props.performers));

const templateSelection = reactive({});
const workflowRejectTargetId = ref(null);
const workflowRejectReason = ref('');

const showAttachModal = ref(false);
const attachSubmitting = ref(false);
const attachError = ref('');
const deletingDocId = ref(null);
const attachForm = reactive({
    party: 'customer',
    type: 'request',
    number: '',
    document_date: '',
    stage: null,
    file: null,
});

const orderDocumentGlobalFileInputRef = ref(null);
const orderDocumentGlobalDropActive = ref(false);

watch(
    () => props.allDocuments,
    (docs) => {
        signedDocuments.value = signedRegistryDocuments(docs).map((doc) => ({ ...doc }));
    },
    { immediate: true, deep: true },
);

function templateOptionLabel(template) {
    const parts = [template.name];

    if (template.contractor_name) {
        parts.push(`(${template.contractor_name})`);
    }

    if (template.is_default) {
        parts.push('· по умолчанию');
    }

    return parts.join(' ');
}

function printWorkflowDocumentTitle(document) {
    return document?.print_template_name || document?.original_name || 'Документ';
}

function docsForCustomerSlot(slot) {
    return printWorkflowDocumentsForSlot(props.allDocuments, 'customer', slot);
}

function docsForCarrierSlot(slot) {
    return printWorkflowDocumentsForSlot(props.allDocuments, 'carrier', slot);
}

function createPrintWorkflow(slot, party) {
    if (!props.order?.id) {
        return;
    }

    const templateId = templateSelection[slot.slotKey];
    if (!templateId) {
        return;
    }

    const payload = {
        print_form_template_id: templateId,
    };

    if (slot.orderLegStage) {
        payload.order_leg_stage = slot.orderLegStage;
    }

    if (slot.carrierContractorId) {
        payload.carrier_contractor_id = slot.carrierContractorId;
    }

    if (slot.routeLegsAsTableRows) {
        payload.route_legs_as_table_rows = true;
    }

    router.post(route('orders.documents.from-template', props.order.id), payload, { preserveScroll: true });
}

function postWorkflowAction(action, documentId) {
    if (!props.order?.id) {
        return;
    }

    const routeNames = {
        'request-approval': 'orders.documents.request-approval',
        'regenerate-draft': 'orders.documents.regenerate-draft',
        approve: 'orders.documents.approve',
    };

    const routeName = routeNames[action];
    if (!routeName) {
        return;
    }

    router.post(route(routeName, [props.order.id, documentId]), {}, { preserveScroll: true });
}

function toggleWorkflowReject(documentId) {
    workflowRejectTargetId.value = workflowRejectTargetId.value === documentId ? null : documentId;
    workflowRejectReason.value = '';
}

function submitWorkflowReject(documentId) {
    if (!props.order?.id || !workflowRejectReason.value.trim()) {
        return;
    }

    router.post(
        route('orders.documents.reject', [props.order.id, documentId]),
        { rejection_reason: workflowRejectReason.value },
        {
            preserveScroll: true,
            onFinish: () => {
                workflowRejectTargetId.value = null;
                workflowRejectReason.value = '';
            },
        },
    );
}

async function finalizeWorkflowPdf(doc, event) {
    const file = event.target?.files?.[0];
    if (!file || !props.order?.id) {
        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});

    const formData = new FormData();
    formData.append('file', file);

    router.post(route('orders.documents.finalize', [props.order.id, doc.id]), formData, {
        preserveScroll: true,
        onFinish: () => {
            if (event.target) {
                event.target.value = '';
            }
        },
    });
}

function confirmDiscardPrintWorkflow(doc) {
    if (!props.order?.id || !window.confirm('Удалить черновик печатной формы?')) {
        return;
    }

    router.delete(route('orders.documents.discard-print-workflow', [props.order.id, doc.id]), {
        preserveScroll: true,
    });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function openAttachModal(preset = {}) {
    attachForm.party = preset.party ?? 'customer';
    attachForm.type = preset.type ?? 'request';
    attachForm.number = '';
    attachForm.document_date = '';
    attachForm.stage = preset.stage ?? null;
    attachForm.file = null;
    attachError.value = '';
    showAttachModal.value = true;
}

function closeAttachModal() {
    showAttachModal.value = false;
    attachForm.file = null;
}

async function submitAttach() {
    if (!props.order?.id || !attachForm.file) {
        attachError.value = 'Выберите файл.';

        return;
    }

    attachSubmitting.value = true;
    attachError.value = '';

    const body = new FormData();
    body.append('order_id', String(props.order.id));
    body.append('party', attachForm.party);
    body.append('type', attachForm.type);
    body.append('status', 'signed');
    if (attachForm.number) {
        body.append('number', attachForm.number);
    }
    if (attachForm.document_date) {
        body.append('document_date', attachForm.document_date);
    }
    body.append('file', attachForm.file);

    try {
        const response = await fetch(route('documents.store'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message ?? `Ошибка загрузки (${response.status})`);
        }

        closeAttachModal();
        router.reload({ only: ['order'], preserveScroll: true });
    } catch (error) {
        attachError.value = error?.message ?? 'Не удалось прикрепить документ';
    } finally {
        attachSubmitting.value = false;
    }
}

async function deleteSignedDocument(doc) {
    if (!doc?.id) {
        return;
    }

    const label = doc.original_name || doc.number || `#${doc.id}`;
    if (!window.confirm(`Удалить документ «${label}»?`)) {
        return;
    }

    deletingDocId.value = doc.id;

    try {
        const response = await fetch(route('documents.destroy', doc.id), {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message ?? `Ошибка удаления (${response.status})`);
        }

        signedDocuments.value = signedDocuments.value.filter((row) => row.id !== doc.id);
        router.reload({ only: ['order'], preserveScroll: true });
    } catch (error) {
        window.alert(error?.message ?? 'Не удалось удалить документ');
    } finally {
        deletingDocId.value = null;
    }
}

function updateSignedField({ id, field, value }) {
    const row = signedDocuments.value.find((doc) => doc.id === id);
    if (row) {
        row[field] = value;
    }
}

function triggerGlobalFilePick() {
    orderDocumentGlobalFileInputRef.value?.click();
}

async function onGlobalFileInputChange(event) {
    const [file] = event.target.files ?? [];
    if (event.target) {
        event.target.value = '';
    }

    if (!file) {
        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    attachForm.file = file;
    openAttachModal();
}

function onGlobalDragEnter() {
    if (props.isOrderFormEditable) {
        orderDocumentGlobalDropActive.value = true;
    }
}

function onGlobalDragLeave() {
    orderDocumentGlobalDropActive.value = false;
}

async function onGlobalDrop(event) {
    event.preventDefault();
    orderDocumentGlobalDropActive.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (!file || !props.isOrderFormEditable) {
        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    attachForm.file = file;
    openAttachModal();
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold">Документы</h2>
                <p class="text-sm text-zinc-500">Печатные формы, подписанные файлы и чек-лист для этапов оплаты</p>
            </div>
        </div>

        <div
            v-if="documentTabValidationMessages.length > 0"
            class="rounded-2xl border border-rose-200 bg-rose-50/80 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-100"
            role="alert"
        >
            <div class="font-medium">Не удалось сохранить заказ</div>
            <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                <li v-for="(msg, idx) in documentTabValidationMessages" :key="`doc-err-${idx}`">{{ msg }}</li>
            </ul>
        </div>

        <section class="space-y-4">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Печатные формы</h3>

            <div
                v-for="slot in customerSlots"
                :key="slot.slotKey"
                class="space-y-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/25"
            >
                <div>
                    <div class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">{{ slot.label }}</div>
                    <p class="mt-1 text-xs text-emerald-900/80 dark:text-emerald-200/80">
                        Шаблоны только для заказчика. Черновик → согласование → финальный PDF.
                    </p>
                </div>
                <template v-if="!order?.id">
                    <p class="text-xs text-emerald-900/80">Сохраните заказ, чтобы создать печатную форму.</p>
                </template>
                <template v-else>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1 space-y-1">
                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Шаблон</label>
                            <select
                                v-model="templateSelection[slot.slotKey]"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            >
                                <option :value="null">Выберите шаблон</option>
                                <option
                                    v-for="template in printFormTemplateOptionsCustomer"
                                    :key="`cust-tpl-${slot.slotKey}-${template.id}`"
                                    :value="template.id"
                                >
                                    {{ templateOptionLabel(template) }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50 dark:bg-emerald-600"
                            :disabled="!templateSelection[slot.slotKey] || !isOrderFormEditable"
                            @click="createPrintWorkflow(slot, 'customer')"
                        >
                            Создать в карточке
                        </button>
                    </div>
                    <PrintWorkflowDocList
                        :docs="docsForCustomerSlot(slot)"
                        :is-editable="isOrderFormEditable"
                        :document-storage="documentStorage"
                        :reject-target-id="workflowRejectTargetId"
                        :reject-reason="workflowRejectReason"
                        @workflow-action="postWorkflowAction"
                        @toggle-reject="toggleWorkflowReject"
                        @submit-reject="submitWorkflowReject"
                        @finalize="finalizeWorkflowPdf"
                        @discard="confirmDiscardPrintWorkflow"
                        @update:reject-reason="workflowRejectReason = $event"
                    />
                </template>
            </div>

            <div
                v-for="slot in carrierSlots"
                :key="`carrier-${slot.slotKey}`"
                class="space-y-3 rounded-2xl border border-rose-200/80 bg-rose-50/40 p-4 dark:border-rose-900/60 dark:bg-rose-950/25"
            >
                <div>
                    <div class="text-sm font-semibold text-rose-950 dark:text-rose-100">{{ slot.label }}</div>
                    <p class="mt-1 text-xs text-rose-900/80 dark:text-rose-200/80">
                        Шаблоны только для перевозчика.
                        <span v-if="slot.routeLegsAsTableRows"> Маршрут по плечам — таблица ${route_row_stage} в шаблоне.</span>
                    </p>
                </div>
                <template v-if="!order?.id">
                    <p class="text-xs text-rose-900/80">Сохраните заказ и укажите перевозчика на плече.</p>
                </template>
                <template v-else-if="!slot.carrierContractorId">
                    <p class="text-xs text-rose-900/80">Укажите перевозчика на вкладке «Основное».</p>
                </template>
                <template v-else>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1 space-y-1">
                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Шаблон</label>
                            <select
                                v-model="templateSelection[slot.slotKey]"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            >
                                <option :value="null">Выберите шаблон</option>
                                <option
                                    v-for="template in printFormTemplateOptionsCarrier"
                                    :key="`carr-tpl-${slot.slotKey}-${template.id}`"
                                    :value="template.id"
                                >
                                    {{ templateOptionLabel(template) }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800 disabled:opacity-50 dark:bg-rose-600"
                            :disabled="!templateSelection[slot.slotKey] || !isOrderFormEditable"
                            @click="createPrintWorkflow(slot, 'carrier')"
                        >
                            Создать в карточке
                        </button>
                    </div>
                    <PrintWorkflowDocList
                        :docs="docsForCarrierSlot(slot)"
                        :is-editable="isOrderFormEditable"
                        :document-storage="documentStorage"
                        :reject-target-id="workflowRejectTargetId"
                        :reject-reason="workflowRejectReason"
                        @workflow-action="postWorkflowAction"
                        @toggle-reject="toggleWorkflowReject"
                        @submit-reject="submitWorkflowReject"
                        @finalize="finalizeWorkflowPdf"
                        @discard="confirmDiscardPrintWorkflow"
                        @update:reject-reason="workflowRejectReason = $event"
                    />
                </template>
            </div>
        </section>

        <section class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="text-sm font-semibold">Прикрепить файл</div>
            <p class="text-xs text-zinc-500">Подписанные документы попадут в таблицу учёта ниже.</p>
            <input
                ref="orderDocumentGlobalFileInputRef"
                type="file"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                class="hidden"
                @change="onGlobalFileInputChange"
            >
            <div
                class="rounded-xl border border-dashed px-4 py-6 text-center transition-colors"
                :class="orderDocumentGlobalDropActive && isOrderFormEditable
                    ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-950/40'
                    : 'border-zinc-200 bg-zinc-50/40 dark:border-zinc-700 dark:bg-zinc-900/20'"
                @dragenter.prevent="onGlobalDragEnter"
                @dragleave.prevent="onGlobalDragLeave"
                @dragover.prevent
                @drop.prevent="onGlobalDrop"
            >
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Перетащите файл сюда или нажмите «Прикрепить файл»</p>
                <button
                    type="button"
                    class="mt-3 inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950"
                    :disabled="!isOrderFormEditable"
                    @click="triggerGlobalFilePick"
                >
                    <Paperclip class="h-4 w-4 text-zinc-500" />
                    Прикрепить файл
                </button>
            </div>
        </section>

        <section class="space-y-3">
            <div class="text-sm font-semibold">Учёт документов</div>
            <OrderSignedDocumentsTable
                :documents="signedDocuments"
                :document-type-options="documentTypeOptions"
                :required-document-rules="requiredDocumentRules"
                :can-edit="isOrderFormEditable"
                :deleting-id="deletingDocId"
                @delete="deleteSignedDocument"
                @update:field="updateSignedField"
            />
            <div
                v-if="requiredDocumentChecklist.length > 0"
                class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-3 text-xs dark:border-zinc-800 dark:bg-zinc-900/40"
            >
                <div class="font-medium text-zinc-800 dark:text-zinc-200">Чек-лист для «Оплата» / «Завершено»</div>
                <ul class="mt-2 space-y-1">
                    <li
                        v-for="item in requiredDocumentChecklist"
                        :key="item.key"
                        class="flex items-start gap-2"
                        :class="item.completed ? 'text-emerald-700 dark:text-emerald-300' : 'text-zinc-600 dark:text-zinc-400'"
                    >
                        <span>{{ item.completed ? '✓' : '○' }}</span>
                        <span>{{ item.label }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <Modal :show="showAttachModal" max-width="xl" @close="closeAttachModal">
            <section class="space-y-4 bg-white p-6 dark:bg-zinc-900">
                <h3 class="text-base font-semibold">Прикрепить подписанный документ</h3>
                <p v-if="documentUploadHint" class="text-xs text-zinc-500">{{ documentUploadHint }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-medium">Сторона</label>
                        <select v-model="attachForm.party" class="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="customer">Заказчик</option>
                            <option value="carrier">Перевозчик</option>
                            <option value="internal">Внутренний</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium">Тип</label>
                        <select v-model="attachForm.type" class="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium">Номер</label>
                        <input v-model="attachForm.number" type="text" class="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium">Дата</label>
                        <input v-model="attachForm.document_date" type="date" class="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>
                </div>
                <p v-if="attachForm.file" class="text-xs text-zinc-600">Файл: {{ attachForm.file.name }}</p>
                <p v-if="attachError" class="text-xs text-rose-600">{{ attachError }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border px-4 py-2 text-sm" @click="closeAttachModal">Отмена</button>
                    <button type="button" :class="crmBtnCreate" :disabled="attachSubmitting || !attachForm.file" @click="submitAttach">
                        {{ attachSubmitting ? 'Загрузка…' : 'Сохранить' }}
                    </button>
                </div>
            </section>
        </Modal>
    </div>
</template>
