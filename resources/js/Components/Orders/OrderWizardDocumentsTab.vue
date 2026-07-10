<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Paperclip } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import OrderSignedDocumentsTable from '@/Components/Orders/OrderSignedDocumentsTable.vue';
import PrintWorkflowDocList from '@/Components/Orders/PrintWorkflowDocList.vue';
import { mergeDocumentUploadLimits } from '@/support/documentUploadClientCheck.js';
import { useDocumentUploadGate } from '@/support/documentUploadGate.js';
import {
    destroyDocumentRegistry,
    formatDocumentRegistryError,
    storeDocumentRegistry,
} from '@/support/documentRegistryClient.js';
import {
    buildDocumentRequirementRules,
    buildDocumentPaymentContext,
    carrierAttachTargetOptions,
    customerRequestSlots,
    documentMatchesRequirementRule,
    findRequirementRuleForUpload,
} from '@/support/orderDocumentRequirementSlots.js';
import { isTransportDocumentType } from '@/support/orderDocumentTypes.js';
import { stageLabel, toStageKey } from '@/support/orderPrintFormSlots.js';
import {
    carrierPrintSlots,
    customerPrintSlots,
    printWorkflowDocumentsForSlot,
    signedRegistryDocuments,
} from '@/support/orderPrintFormSlots.js';
import {
    buildPrintFormTemplateContext,
    defaultTemplateForContext,
    filterPrintFormTemplates,
} from '@/support/printFormTemplateMatching.js';
import {
    crmBtnCreate,
    crmFieldFluid,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
    crmModalPanel,
} from '@/support/crmUi.js';

const signedDocuments = defineModel('signedDocuments', { type: Array, default: () => [] });
const printFormTemplateSelection = defineModel('printFormTemplateSelection', { type: Object, default: () => ({}) });

const props = defineProps({
    order: { type: Object, default: null },
    performers: { type: Array, default: () => [] },
    additionalCosts: { type: Array, default: () => [] },
    clientRequestMode: { type: String, default: 'single_request' },
    isOrderFormEditable: { type: Boolean, default: true },
    allDocuments: { type: Array, default: () => [] },
    printFormTemplateCatalog: { type: Array, default: () => [] },
    printFormTemplateOptionsCustomer: { type: Array, default: () => [] },
    printFormTemplateOptionsCarrier: { type: Array, default: () => [] },
    ownCompanyId: { type: [Number, String, null], default: null },
    isInternationalTransport: { type: Boolean, default: false },
    customerId: { type: [Number, String, null], default: null },
    documentTypeOptions: { type: Array, default: () => [] },
    customerPaymentForm: { type: String, default: '' },
    clientPaymentSchedule: { type: Object, default: () => ({}) },
    contractorsCosts: { type: Array, default: () => [] },
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    documentEdoAcknowledgements: { type: Array, default: () => [] },
    canEditDocumentEdoAcknowledgements: { type: Boolean, default: false },
    documentTabValidationMessages: { type: Array, default: () => [] },
    documentStorage: { type: Object, default: () => ({}) },
    savedPrintFormTemplateSelection: { type: Object, default: () => ({}) },
});

const page = usePage();
const uploadGate = useDocumentUploadGate();
const documentUploadLimits = computed(() => mergeDocumentUploadLimits(
    page.props.document_upload_limits ?? {},
    page.props.document_optimize ?? {},
));

const customerSlots = computed(() => customerPrintSlots(props.performers, props.clientRequestMode));
const carrierSlots = computed(() => carrierPrintSlots(props.performers));

const printFormTemplateContext = computed(() => buildPrintFormTemplateContext({
    ownCompanyId: props.ownCompanyId,
    isInternationalTransport: props.isInternationalTransport,
    performers: props.performers,
    additionalCosts: props.additionalCosts,
    customerId: props.customerId ?? props.order?.customer_id ?? null,
    carrierId: props.order?.carrier_id ?? null,
}));

const printFormTemplateOptionsCustomer = computed(() => {
    const catalog = props.printFormTemplateCatalog?.length
        ? props.printFormTemplateCatalog
        : props.printFormTemplateOptionsCustomer;

    return filterPrintFormTemplates(catalog, printFormTemplateContext.value, 'customer');
});

const printFormTemplateOptionsCarrier = computed(() => {
    const catalog = props.printFormTemplateCatalog?.length
        ? props.printFormTemplateCatalog
        : props.printFormTemplateOptionsCarrier;

    return filterPrintFormTemplates(catalog, printFormTemplateContext.value, 'carrier');
});

const documentPaymentContext = computed(() => buildDocumentPaymentContext(
    props.customerPaymentForm,
    props.contractorsCosts,
));

const effectiveRequiredDocumentRules = computed(() => buildDocumentRequirementRules(
    props.performers,
    props.clientRequestMode,
    props.additionalCosts,
    documentPaymentContext.value,
));

const effectiveDocumentChecklist = computed(() => {
    const rules = effectiveRequiredDocumentRules.value;
    const documents = signedDocuments.value ?? [];
    const usedIds = new Set();

    return rules.map((rule) => {
        const matchedDocument = documents.find((document) => {
            if (document?.id && usedIds.has(document.id)) {
                return false;
            }

            const status = String(document?.status ?? '');

            if (!['sent', 'signed'].includes(status)) {
                return false;
            }

            return documentMatchesRequirementRule(document, rule);
        });

        if (matchedDocument?.id) {
            usedIds.add(matchedDocument.id);
        }

        return {
            ...rule,
            completed: matchedDocument !== undefined,
            matched_document_id: matchedDocument?.id ?? null,
        };
    });
});

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
    contractor_id: null,
    carrier_target_key: null,
    file: null,
});

const attachContractorOptions = computed(() => (
    Array.isArray(props.additionalCosts) ? props.additionalCosts : []
)
    .filter((row) => row?.contractor_id)
    .map((row) => ({
        id: Number(row.contractor_id),
        label: row.contractor_name ? String(row.contractor_name).trim() : `Подрядчик #${row.contractor_id}`,
        slotKey: row.id ? `contractor-${row.contractor_id}-${row.id}` : `contractor-${row.contractor_id}`,
    })));

const showAttachContractorPicker = computed(() => attachForm.party === 'contractor' && attachContractorOptions.value.length > 0);

const attachCustomerLegOptions = computed(() => customerRequestSlots(props.performers, props.clientRequestMode)
    .filter((slot) => slot.orderLegStage)
    .map((slot) => ({
        stage: slot.orderLegStage,
        label: `Плечо ${stageLabel(slot.orderLegStage)}`,
    })));

const attachCarrierTargetOptions = computed(() => carrierAttachTargetOptions(
    props.performers,
    props.clientRequestMode,
));

const showAttachCustomerLegPicker = computed(() => (
    attachForm.party === 'customer'
    && attachCustomerLegOptions.value.length > 1
));

const showAttachCarrierTargetPicker = computed(() => (
    attachForm.party === 'carrier'
    && attachCarrierTargetOptions.value.length > 1
));

const orderDocumentGlobalFileInputRef = ref(null);
const orderDocumentGlobalDropActive = ref(false);
const attachModalFileInputRef = ref(null);
const attachDropActive = ref(false);
let attachDropDepth = 0;

watch(
    () => props.allDocuments,
    (docs) => {
        signedDocuments.value = signedRegistryDocuments(docs).map((doc) => ({ ...doc }));
        hydrateTemplateSelectionFromWorkflowDocuments();
    },
    { immediate: true, deep: true },
);

watch(
    () => attachForm.type,
    (type) => {
        if (!isTransportDocumentType(type)) {
            return;
        }

        attachForm.party = 'carrier';
        attachForm.carrier_target_key = defaultAttachCarrierTargetKey();
    },
);

watch(
    () => attachForm.party,
    (party) => {
        attachForm.stage = defaultAttachCustomerStage();
        attachForm.carrier_target_key = defaultAttachCarrierTargetKey();
        if (party === 'contractor') {
            attachForm.contractor_id = attachContractorOptions.value[0]?.id ?? null;
        } else {
            attachForm.contractor_id = null;
        }
    },
);

function defaultTemplateForOptions(options, party) {
    const list = Array.isArray(options) ? options : [];

    if (list.length === 0 && props.printFormTemplateCatalog.length > 0) {
        return defaultTemplateForContext(props.printFormTemplateCatalog, printFormTemplateContext.value, party);
    }

    return list.find((template) => template.is_default) ?? list[0] ?? null;
}

function hydrateTemplateSelectionFromSavedState() {
    const saved = props.savedPrintFormTemplateSelection;
    if (!saved || typeof saved !== 'object') {
        return;
    }

    Object.entries(saved).forEach(([slotKey, templateId]) => {
        if (templateId != null && templateId !== '') {
            printFormTemplateSelection.value[slotKey] = Number(templateId);
        }
    });
}

function hydrateTemplateSelectionFromWorkflowDocuments() {
    const docs = Array.isArray(props.allDocuments) ? props.allDocuments : [];

    customerSlots.value.forEach((slot) => {
        if (printFormTemplateSelection.value[slot.slotKey]) {
            return;
        }

        const match = docsForCustomerSlot(slot).find((doc) => doc?.template_id);
        if (match?.template_id) {
            printFormTemplateSelection.value[slot.slotKey] = Number(match.template_id);
        }
    });

    carrierSlots.value.forEach((slot) => {
        if (!slot.carrierContractorId || printFormTemplateSelection.value[slot.slotKey]) {
            return;
        }

        const match = docsForCarrierSlot(slot).find((doc) => doc?.template_id);
        if (match?.template_id) {
            printFormTemplateSelection.value[slot.slotKey] = Number(match.template_id);
        }
    });
}

function exportPrintFormTemplateSelection() {
    return { ...printFormTemplateSelection.value };
}

defineExpose({
    exportPrintFormTemplateSelection,
});

function ensureDefaultTemplateSelection() {
    customerSlots.value.forEach((slot) => {
        if (printFormTemplateSelection.value[slot.slotKey]) {
            return;
        }

        const template = defaultTemplateForOptions(printFormTemplateOptionsCustomer.value, 'customer');
        if (template?.id) {
            printFormTemplateSelection.value[slot.slotKey] = template.id;
        }
    });

    carrierSlots.value.forEach((slot) => {
        if (!slot.carrierContractorId || printFormTemplateSelection.value[slot.slotKey]) {
            return;
        }

        const template = defaultTemplateForOptions(printFormTemplateOptionsCarrier.value, 'carrier');
        if (template?.id) {
            printFormTemplateSelection.value[slot.slotKey] = template.id;
        }
    });
}

function syncInvalidTemplateSelections() {
    const customerIds = new Set(printFormTemplateOptionsCustomer.value.map((template) => Number(template.id)));
    const carrierIds = new Set(printFormTemplateOptionsCarrier.value.map((template) => Number(template.id)));

    customerSlots.value.forEach((slot) => {
        const selectedId = Number(printFormTemplateSelection.value[slot.slotKey] ?? 0);
        if (selectedId > 0 && !customerIds.has(selectedId)) {
            delete printFormTemplateSelection.value[slot.slotKey];
        }
    });

    carrierSlots.value.forEach((slot) => {
        const selectedId = Number(printFormTemplateSelection.value[slot.slotKey] ?? 0);
        if (selectedId > 0 && !carrierIds.has(selectedId)) {
            delete printFormTemplateSelection.value[slot.slotKey];
        }
    });
}

watch(
    [customerSlots, carrierSlots, printFormTemplateOptionsCustomer, printFormTemplateOptionsCarrier],
    () => {
        hydrateTemplateSelectionFromSavedState();
        hydrateTemplateSelectionFromWorkflowDocuments();
        syncInvalidTemplateSelections();
        ensureDefaultTemplateSelection();
    },
    { immediate: true, deep: true },
);

function printCreateDisabledReason(slot, party) {
    if (!props.isOrderFormEditable) {
        return 'Карточка закрыта для правок: все печатные формы финализированы.';
    }

    if (!printFormTemplateSelection.value[slot.slotKey]) {
        return 'Выберите шаблон в списке.';
    }

    if (party === 'carrier' && !slot.carrierContractorId) {
        return 'Укажите перевозчика на вкладке «Маршрут».';
    }

    return '';
}

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

    const templateId = printFormTemplateSelection.value[slot.slotKey];
    if (!templateId) {
        return;
    }

    const payload = {
        print_form_template_id: templateId,
        print_party: party,
    };

    if (slot.orderLegStage) {
        payload.order_leg_stage = slot.orderLegStage;
    }

    if (slot.carrierContractorId) {
        payload.carrier_contractor_id = slot.carrierContractorId;
    }

    if (slot.carrierSlot) {
        payload.carrier_slot = slot.carrierSlot;
    }

    if (slot.routeLegsAsTableRows) {
        payload.route_legs_as_table_rows = true;
    }

    payload.is_international_transport = Boolean(props.isInternationalTransport);

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

function confirmDiscardPrintWorkflow(doc) {
    if (!props.order?.id || !window.confirm('Удалить черновик печатной формы?')) {
        return;
    }

    router.delete(route('orders.documents.discard-print-workflow', [props.order.id, doc.id]), {
        preserveScroll: true,
    });
}

function defaultAttachCustomerStage() {
    return attachCustomerLegOptions.value[0]?.stage ?? null;
}

function defaultAttachCarrierTargetKey() {
    return attachCarrierTargetOptions.value[0]?.key ?? null;
}

function resolveAttachCarrierTarget() {
    const options = attachCarrierTargetOptions.value;

    if (options.length === 0) {
        return null;
    }

    const selected = options.find((row) => row.key === attachForm.carrier_target_key);

    return selected ?? options[0] ?? null;
}

async function openAttachModal(preset = {}) {
    attachForm.party = preset.party ?? 'customer';
    attachForm.type = preset.type ?? 'request';
    attachForm.number = '';
    attachForm.document_date = '';
    attachForm.stage = preset.stage ?? defaultAttachCustomerStage();
    attachForm.carrier_target_key = preset.carrier_target_key
        ?? (preset.slot_key ? String(preset.slot_key) : null)
        ?? defaultAttachCarrierTargetKey();
    attachForm.contractor_id = preset.contractor_id ?? (attachForm.party === 'contractor' ? attachContractorOptions.value[0]?.id ?? null : null);
    attachForm.file = null;
    attachError.value = '';
    attachDropDepth = 0;
    attachDropActive.value = false;
    showAttachModal.value = true;

    if (preset.file) {
        await assignAttachFile(preset.file);
    }
}

function closeAttachModal() {
    showAttachModal.value = false;
    attachForm.file = null;
    attachDropDepth = 0;
    attachDropActive.value = false;
}

async function assignAttachFile(file) {
    if (!file) {
        return;
    }

    const accepted = await uploadGate.ensureDocumentWithinBudget(file, documentUploadLimits.value);
    if (!accepted) {
        attachForm.file = null;

        return;
    }

    attachForm.file = accepted;
    attachError.value = '';
}

function onAttachDragEnter() {
    attachDropDepth += 1;
    attachDropActive.value = true;
}

function onAttachDragLeave() {
    attachDropDepth = Math.max(0, attachDropDepth - 1);
    if (attachDropDepth === 0) {
        attachDropActive.value = false;
    }
}

async function onAttachDrop(event) {
    event.preventDefault();
    attachDropDepth = 0;
    attachDropActive.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) {
        await assignAttachFile(file);
    }
}

function triggerAttachFilePick() {
    attachModalFileInputRef.value?.click();
}

async function onAttachFileInputChange(event) {
    const [file] = event.target.files ?? [];
    if (event.target) {
        event.target.value = '';
    }

    if (file) {
        await assignAttachFile(file);
    }
}

async function submitAttach() {
    if (!props.order?.id || !attachForm.file) {
        attachError.value = 'Выберите файл.';

        return;
    }

    if (attachForm.party === 'carrier') {
        const carrierTarget = resolveAttachCarrierTarget();

        if (!carrierTarget?.contractor_id) {
            attachError.value = 'Укажите перевозчика на вкладке «Маршрут» или выберите его в списке.';

            return;
        }
    }

    attachSubmitting.value = true;
    attachError.value = '';

    const body = new FormData();
    body.append('order_id', String(props.order.id));
    body.append('party', attachForm.party);
    body.append('type', attachForm.type);
    body.append('status', 'signed');

    const carrierTarget = attachForm.party === 'carrier' ? resolveAttachCarrierTarget() : null;
    const carrierContractorId = carrierTarget?.contractor_id ? Number(carrierTarget.contractor_id) : null;
    const carrierSlot = carrierTarget?.carrier_slot ? Number(carrierTarget.carrier_slot) : null;
    const carrierStage = carrierTarget?.stage ? toStageKey(String(carrierTarget.stage)) : null;
    const contractorId = attachForm.party === 'contractor' && attachForm.contractor_id
        ? Number(attachForm.contractor_id)
        : null;

    if (showAttachCustomerLegPicker.value && attachForm.stage) {
        body.append('order_leg_stage', toStageKey(String(attachForm.stage)));
    }

    if (attachForm.party === 'carrier' && carrierStage) {
        body.append('order_leg_stage', carrierStage);
    }

    if (carrierContractorId) {
        body.append('carrier_contractor_id', String(carrierContractorId));
    }

    if (carrierSlot) {
        body.append('carrier_slot', String(carrierSlot));
    }

    if (contractorId) {
        body.append('contractor_id', String(contractorId));
    }

    const matchedRule = findRequirementRuleForUpload(effectiveRequiredDocumentRules.value, {
        party: attachForm.party,
        type: attachForm.type,
        stage: attachForm.party === 'carrier' ? carrierStage : attachForm.stage,
        contractor_id: contractorId ?? carrierContractorId,
    });

    if (matchedRule?.slot_key) {
        body.append('requirement_slot_key', String(matchedRule.slot_key));
    }

    if (attachForm.number) {
        body.append('number', attachForm.number);
    }
    if (attachForm.document_date) {
        body.append('document_date', attachForm.document_date);
    }
    body.append('file', attachForm.file);

    try {
        await storeDocumentRegistry(body);
        closeAttachModal();
        router.reload({ only: ['order'], preserveScroll: true });
    } catch (error) {
        attachError.value = formatDocumentRegistryError(error);
    } finally {
        attachSubmitting.value = false;
    }
}

function onEdoUpdated() {
    router.reload({
        only: ['requiredDocumentChecklist', 'documentEdoAcknowledgements'],
        preserveScroll: true,
    });
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
        await destroyDocumentRegistry(doc.id);
        signedDocuments.value = signedDocuments.value.filter((row) => row.id !== doc.id);
        router.reload({ only: ['order'], preserveScroll: true });
    } catch (error) {
        window.alert(formatDocumentRegistryError(error));
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

    await openAttachModal({ file });
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

    await openAttachModal({ file });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">Документы</h2>
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
                <div class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">{{ slot.label }}</div>
                <template v-if="!order?.id">
                    <p class="text-xs text-emerald-900/80">Сохраните заказ, чтобы создать печатную форму.</p>
                </template>
                <template v-else>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1 space-y-1">
                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Шаблон</label>
                            <select
                                v-model="printFormTemplateSelection[slot.slotKey]"
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
                            :disabled="!printFormTemplateSelection[slot.slotKey] || !isOrderFormEditable"
                            :title="printCreateDisabledReason(slot, 'customer')"
                            @click="createPrintWorkflow(slot, 'customer')"
                        >
                            Создать в карточке
                        </button>
                        <p v-if="printCreateDisabledReason(slot, 'customer')" class="text-xs text-zinc-500">
                            {{ printCreateDisabledReason(slot, 'customer') }}
                        </p>
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
                <div class="text-sm font-semibold text-rose-950 dark:text-rose-100">{{ slot.label }}</div>
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
                                v-model="printFormTemplateSelection[slot.slotKey]"
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
                            :disabled="!printFormTemplateSelection[slot.slotKey] || !isOrderFormEditable"
                            :title="printCreateDisabledReason(slot, 'carrier')"
                            @click="createPrintWorkflow(slot, 'carrier')"
                        >
                            Создать в карточке
                        </button>
                        <p v-if="printCreateDisabledReason(slot, 'carrier')" class="text-xs text-zinc-500">
                            {{ printCreateDisabledReason(slot, 'carrier') }}
                        </p>
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
                        @discard="confirmDiscardPrintWorkflow"
                        @update:reject-reason="workflowRejectReason = $event"
                    />
                </template>
            </div>
        </section>

        <section class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="text-sm font-semibold">Прикрепить файл</div>
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
                :signed-documents="signedDocuments"
                :document-type-options="documentTypeOptions"
                :required-document-rules="effectiveRequiredDocumentRules"
                :required-document-checklist="effectiveDocumentChecklist"
                :edo-acknowledgements="documentEdoAcknowledgements"
                :can-edit-edo="canEditDocumentEdoAcknowledgements"
                :can-edit="isOrderFormEditable"
                :deleting-id="deletingDocId"
                :order="order"
                :client-payment-schedule="clientPaymentSchedule"
                :contractors-costs="contractorsCosts"
                :performers="performers"
                @delete="deleteSignedDocument"
                @update:field="updateSignedField"
                @edo-updated="onEdoUpdated"
            />
        </section>

        <Modal :show="showAttachModal" max-width="xl" @close="closeAttachModal">
            <section :class="crmModalPanel">
                <CrmModalHeader title="Прикрепить подписанный документ" @close="closeAttachModal" />
                <div class="space-y-4 border-t border-zinc-200 px-5 py-5 dark:border-zinc-800 sm:px-6">
                <div :class="crmModalFieldsWrap">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Сторона</label>
                        <select v-model="attachForm.party" :class="crmFieldFluid">
                            <option value="customer">Заказчик</option>
                            <option value="carrier">Перевозчик</option>
                            <option value="contractor">Подрядчик</option>
                            <option value="internal">Внутренний</option>
                        </select>
                    </div>
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Тип</label>
                        <select v-model="attachForm.type" :class="crmFieldFluid">
                            <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div v-if="showAttachContractorPicker" :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Подрядчик</label>
                        <select v-model="attachForm.contractor_id" :class="crmFieldFluid">
                            <option
                                v-for="contractor in attachContractorOptions"
                                :key="`attach-contractor-${contractor.id}-${contractor.slotKey}`"
                                :value="contractor.id"
                            >
                                {{ contractor.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="showAttachCustomerLegPicker" :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Плечо</label>
                        <select v-model="attachForm.stage" :class="crmFieldFluid">
                            <option
                                v-for="leg in attachCustomerLegOptions"
                                :key="`attach-customer-leg-${leg.stage}`"
                                :value="leg.stage"
                            >
                                {{ leg.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="showAttachCarrierTargetPicker" :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Перевозчик</label>
                        <select v-model="attachForm.carrier_target_key" :class="crmFieldFluid">
                            <option
                                v-for="target in attachCarrierTargetOptions"
                                :key="`attach-carrier-${target.key}`"
                                :value="target.key"
                            >
                                {{ target.label }}
                            </option>
                        </select>
                    </div>
                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Номер</label>
                        <input v-model="attachForm.number" type="text" :class="crmFieldFluid" />
                    </div>
                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Дата</label>
                        <input v-model="attachForm.document_date" type="date" :class="crmFieldFluid" />
                    </div>
                </div>
                <input
                    ref="attachModalFileInputRef"
                    type="file"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                    class="hidden"
                    @change="onAttachFileInputChange"
                >
                <div
                    class="rounded-xl border border-dashed px-4 py-5 text-center transition-colors"
                    :class="attachDropActive
                        ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-950/40'
                        : 'border-zinc-200 bg-zinc-50/60 dark:border-zinc-700 dark:bg-zinc-900/40'"
                    @dragenter.prevent="onAttachDragEnter"
                    @dragleave.prevent="onAttachDragLeave"
                    @dragover.prevent
                    @drop.prevent="onAttachDrop"
                >
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ attachForm.file ? attachForm.file.name : 'Перетащите файл сюда или выберите на диске' }}
                    </p>
                    <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950"
                        @click="triggerAttachFilePick"
                    >
                        <Paperclip class="h-4 w-4 text-zinc-500" />
                        {{ attachForm.file ? 'Заменить файл' : 'Выбрать файл' }}
                    </button>
                </div>
                <p v-if="attachError" class="text-xs text-rose-600">{{ attachError }}</p>
                <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <button type="button" class="rounded-xl border px-4 py-2 text-sm" @click="closeAttachModal">Отмена</button>
                    <button type="button" :class="crmBtnCreate" :disabled="attachSubmitting || !attachForm.file" @click="submitAttach">
                        {{ attachSubmitting ? 'Загрузка…' : 'Сохранить' }}
                    </button>
                </div>
                </div>
            </section>
        </Modal>
    </div>
</template>
