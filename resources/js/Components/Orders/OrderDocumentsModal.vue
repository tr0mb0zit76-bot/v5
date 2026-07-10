<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ExternalLink, Paperclip, Trash2, Upload } from 'lucide-vue-next';
import OrderSignedDocumentsTable from '@/Components/Orders/OrderSignedDocumentsTable.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import Modal from '@/Components/Modal.vue';
import { mergeDocumentUploadLimits } from '@/support/documentUploadClientCheck.js';
import { useDocumentUploadGate } from '@/support/documentUploadGate.js';
import axios from 'axios';
import {
    destroyDocumentRegistry,
    formatDocumentRegistryError,
    storeDocumentRegistry,
    updateDocumentRegistry,
} from '@/support/documentRegistryClient.js';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmBtnSecondary,
    crmFieldFluid,
    crmLabel,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
    crmModalFormBody,
    crmModalFormShell,
    crmPanel,
} from '@/support/crmUi.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    orderId: { type: Number, default: null },
    orderNumber: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const page = usePage();
const uploadGate = useDocumentUploadGate();
const documentUploadLimits = computed(() => mergeDocumentUploadLimits(
    page.props.document_upload_limits ?? {},
    page.props.document_optimize ?? {},
));

const loading = ref(false);
const loadError = ref('');
const documents = ref([]);
const documentTypeOptions = ref([]);
const requiredDocumentRules = ref([]);
const requiredDocumentChecklist = ref([]);
const edoAcknowledgements = ref([]);
const canEditEdoAcknowledgements = ref(false);
const deletingDocId = ref(null);

const signedDocuments = computed(() => documents.value
    .filter((doc) => !doc.is_print_workflow && doc.status === 'signed')
    .map((doc) => ({
        ...doc,
        uploaded_file_preview_url: doc.preview_url ?? doc.uploaded_file_preview_url ?? null,
    })));
const printWorkflowDocuments = computed(() => documents.value.filter((doc) => doc.is_print_workflow));

const addForm = reactive({
    party: 'customer',
    type: 'invoice',
    number: '',
    document_date: '',
    status: 'signed',
    file: null,
});
const addSubmitting = ref(false);
const addError = ref('');
const replaceDocId = ref(null);
const replaceFileInputRef = ref(null);
const addFileInputRef = ref(null);
const addDropActive = ref(false);
let addDropDepth = 0;

const modalTitle = computed(() => {
    const label = props.orderNumber || (props.orderId ? `#${props.orderId}` : '');

    return label ? `Документы — ${label}` : 'Документы заказа';
});

async function loadDocuments() {
    if (!props.orderId) {
        documents.value = [];

        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const response = await fetch(route('orders.documents.list', props.orderId), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Не удалось загрузить документы (${response.status})`);
        }

        const payload = await response.json();
        documents.value = Array.isArray(payload.documents) ? payload.documents : [];
        documentTypeOptions.value = Array.isArray(payload.document_type_options)
            ? payload.document_type_options
            : [];
        requiredDocumentRules.value = Array.isArray(payload.requiredDocumentRules)
            ? payload.requiredDocumentRules
            : [];
        requiredDocumentChecklist.value = Array.isArray(payload.requiredDocumentChecklist)
            ? payload.requiredDocumentChecklist
            : [];
        edoAcknowledgements.value = Array.isArray(payload.edo_acknowledgements)
            ? payload.edo_acknowledgements
            : [];
        canEditEdoAcknowledgements.value = Boolean(payload.can_edit_edo_acknowledgements);
    } catch (error) {
        loadError.value = error?.message ?? 'Ошибка загрузки документов';
        documents.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.show, props.orderId],
    ([visible]) => {
        if (visible && props.orderId) {
            loadDocuments();
        }
    },
    { immediate: true },
);

function closeModal() {
    addForm.file = null;
    addError.value = '';
    replaceDocId.value = null;
    emit('close');
}

async function assignAddFile(file) {
    if (!file) {
        return;
    }

    const accepted = await uploadGate.ensureDocumentWithinBudget(file, documentUploadLimits.value);
    if (!accepted) {
        addForm.file = null;

        return;
    }

    addForm.file = accepted;
    addError.value = '';
}

async function onAddFilePicked(event) {
    const [file] = event.target.files ?? [];
    if (event.target) {
        event.target.value = '';
    }

    if (file) {
        await assignAddFile(file);
    }
}

function onAddDragEnter() {
    addDropDepth += 1;
    addDropActive.value = true;
}

function onAddDragLeave() {
    addDropDepth = Math.max(0, addDropDepth - 1);
    if (addDropDepth === 0) {
        addDropActive.value = false;
    }
}

async function onAddDrop(event) {
    event.preventDefault();
    addDropDepth = 0;
    addDropActive.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) {
        await assignAddFile(file);
    }
}

function triggerAddFilePick() {
    addFileInputRef.value?.click();
}

async function submitAdd() {
    if (!props.orderId || !addForm.file) {
        addError.value = 'Выберите файл для загрузки.';

        return;
    }

    addSubmitting.value = true;
    addError.value = '';

    const body = new FormData();
    body.append('order_id', String(props.orderId));
    body.append('party', addForm.party);
    body.append('type', addForm.type);
    body.append('status', addForm.status);
    if (addForm.number) {
        body.append('number', addForm.number);
    }
    if (addForm.document_date) {
        body.append('document_date', addForm.document_date);
    }
    body.append('file', addForm.file);

    try {
        await storeDocumentRegistry(body);

        addForm.file = null;
        addForm.number = '';
        addForm.document_date = '';
        await loadDocuments();
    } catch (error) {
        addError.value = formatDocumentRegistryError(error);
    } finally {
        addSubmitting.value = false;
    }
}

function startReplace(doc) {
    replaceDocId.value = doc.id;
    replaceFileInputRef.value?.click();
}

async function onReplaceFilePicked(event) {
    const docId = replaceDocId.value;
    const [file] = event.target.files ?? [];
    replaceDocId.value = null;
    if (event.target) {
        event.target.value = '';
    }

    if (!docId || !file || !props.orderId) {
        return;
    }

    const doc = documents.value.find((item) => item.id === docId);
    if (!doc || doc.is_print_workflow) {
        return;
    }

    const accepted = await uploadGate.ensureDocumentWithinBudget(file, documentUploadLimits.value);
    if (!accepted) {
        return;
    }

    const body = new FormData();
    body.append('_method', 'PATCH');
    body.append('order_id', String(props.orderId));
    body.append('party', doc.party);
    body.append('type', doc.type);
    body.append('status', doc.status);
    if (doc.number) {
        body.append('number', doc.number);
    }
    if (doc.document_date) {
        body.append('document_date', doc.document_date);
    }
    body.append('file', accepted);

    try {
        await updateDocumentRegistry(docId, body);
        await loadDocuments();
    } catch (error) {
        window.alert(formatDocumentRegistryError(error));
    }
}

async function deleteDocument(doc) {
    if (!doc?.id || !props.orderId) {
        return;
    }

    const label = doc.original_name || doc.type_label || `#${doc.id}`;
    if (!window.confirm(`Удалить документ «${label}»?`)) {
        return;
    }

    deletingDocId.value = doc.id;

    try {
        if (doc.is_print_workflow) {
            await axios.delete(route('orders.documents.discard-print-workflow', [props.orderId, doc.id], false), {
                headers: { Accept: 'application/json' },
            });
        } else {
            await destroyDocumentRegistry(doc.id);
        }

        await loadDocuments();
    } catch (error) {
        window.alert(formatDocumentRegistryError(error));
    } finally {
        deletingDocId.value = null;
    }
}

function onEdoUpdated(payload) {
    if (Array.isArray(payload?.required_document_checklist)) {
        requiredDocumentChecklist.value = payload.required_document_checklist;
    }

    void loadDocuments();
}

function openWizardDocuments() {
    if (!props.orderId) {
        return;
    }

    router.get(route('orders.edit', props.orderId), { tab: 'documents' }, { preserveScroll: true });
}
</script>

<template>
    <Modal :show="show" max-width="7xl" @close="closeModal">
        <section :class="crmModalFormShell">
            <CrmModalHeader :title="modalTitle" @close="closeModal" />

            <div :class="`${crmModalFormBody} border-t border-zinc-200 px-5 py-5 dark:border-zinc-800 sm:px-6`">
                <form :class="`${crmPanel} space-y-4 p-4`" @submit.prevent="submitAdd">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Добавить документ</div>

                    <div :class="crmModalFieldsWrap">
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Сторона</label>
                            <select v-model="addForm.party" :class="crmFieldFluid">
                                <option value="customer">Заказчик</option>
                                <option value="carrier">Перевозчик</option>
                                <option value="internal">Внутренний</option>
                            </select>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Тип</label>
                            <select v-model="addForm.type" :class="crmFieldFluid">
                                <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div :class="crmModalFieldRow">
                            <label :class="crmModalFieldLabel">Статус</label>
                            <select v-model="addForm.status" :class="crmFieldFluid">
                                <option value="draft">Черновик</option>
                                <option value="pending">Ожидает</option>
                                <option value="signed">Подписан</option>
                                <option value="sent">Отправлен</option>
                            </select>
                        </div>
                        <div :class="crmModalFieldRow">
                            <label :class="crmModalFieldLabel">Номер</label>
                            <input v-model="addForm.number" type="text" :class="crmFieldFluid" />
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Дата</label>
                            <input v-model="addForm.document_date" type="date" :class="crmFieldFluid" />
                        </div>
                    </div>

                    <input
                        ref="addFileInputRef"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                        class="hidden"
                        @change="onAddFilePicked"
                    >
                    <div
                        class="rounded-xl border border-dashed px-4 py-5 text-center transition-colors"
                        :class="addDropActive
                            ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-950/40'
                            : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-950'"
                        @dragenter.prevent="onAddDragEnter"
                        @dragleave.prevent="onAddDragLeave"
                        @dragover.prevent
                        @drop.prevent="onAddDrop"
                    >
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ addForm.file ? addForm.file.name : 'Перетащите файл сюда или выберите на диске' }}
                        </p>
                        <button
                            type="button"
                            :class="`${crmBtnSecondary} mt-3`"
                            @click="triggerAddFilePick"
                        >
                            <Paperclip class="h-4 w-4 text-zinc-500" />
                            {{ addForm.file ? 'Заменить файл' : 'Выбрать файл' }}
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" :class="crmBtnCreate" :disabled="addSubmitting || !addForm.file">
                            {{ addSubmitting ? 'Загрузка…' : 'Прикрепить' }}
                        </button>
                    </div>
                    <p v-if="addError" class="text-xs text-rose-600">{{ addError }}</p>
                </form>

                <input
                    ref="replaceFileInputRef"
                    type="file"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                    class="hidden"
                    @change="onReplaceFilePicked"
                >

                <div class="mt-6">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Документы по заказу</div>
                        <button type="button" class="text-xs text-zinc-600 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-400" @click="openWizardDocuments">
                            Мастер заказа
                        </button>
                    </div>

                    <p v-if="loading" class="text-sm text-zinc-500">Загрузка…</p>
                    <p v-else-if="loadError" class="text-sm text-rose-600">{{ loadError }}</p>
                    <p v-if="!loading && !loadError && signedDocuments.length === 0 && printWorkflowDocuments.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                        Подписанных файлов пока нет. В таблице ниже — пять обязательных пунктов для этапов «Оплата» и «Завершено».
                    </p>

                    <OrderSignedDocumentsTable
                        v-if="!loading && !loadError"
                        class="mb-4"
                        :signed-documents="signedDocuments"
                        :document-type-options="documentTypeOptions"
                        :required-document-rules="requiredDocumentRules"
                        :required-document-checklist="requiredDocumentChecklist"
                        :edo-acknowledgements="edoAcknowledgements"
                        :can-edit-edo="canEditEdoAcknowledgements"
                        :order-id="orderId"
                        :can-edit="true"
                        :deleting-id="deletingDocId"
                        @delete="deleteDocument"
                        @edo-updated="onEdoUpdated"
                    />

                    <ul v-if="printWorkflowDocuments.length > 0" class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                        <li v-for="doc in printWorkflowDocuments" :key="doc.id" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-50">
                                        {{ doc.original_name || doc.type_label }}
                                    </span>
                                    <span
                                        v-if="doc.is_print_workflow"
                                        class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-violet-800 dark:bg-violet-950 dark:text-violet-200"
                                    >
                                        Печатная форма
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ doc.party_label }} · {{ doc.type_label }}
                                    <template v-if="doc.number"> · № {{ doc.number }}</template>
                                    <template v-if="doc.document_date"> · {{ doc.document_date }}</template>
                                    · {{ doc.status_label }}
                                    <template v-if="doc.workflow_status_label"> · {{ doc.workflow_status_label }}</template>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a
                                    v-if="doc.preview_url"
                                    :href="doc.preview_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs text-zinc-600 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-400"
                                >
                                    <ExternalLink class="h-3.5 w-3.5" />
                                    Открыть
                                </a>
                                <a
                                    v-else-if="doc.is_print_workflow"
                                    :href="doc.wizard_url"
                                    class="inline-flex items-center gap-1 text-xs text-zinc-600 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-400"
                                >
                                    В мастере
                                </a>
                                <button
                                    v-if="doc.can_replace"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    @click="startReplace(doc)"
                                >
                                    <Upload class="h-3.5 w-3.5" />
                                    Заменить
                                </button>
                                <button
                                    v-if="doc.can_delete"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                    @click="deleteDocument(doc)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Удалить
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end border-t border-zinc-200 px-5 py-4 dark:border-zinc-800 sm:px-6">
                <button type="button" :class="crmBtnNeutral" @click="closeModal">Закрыть</button>
            </div>
        </section>
    </Modal>
</template>
