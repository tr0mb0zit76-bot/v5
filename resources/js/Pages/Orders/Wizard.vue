<template>
    <OrderWizardShell
        :active-tab="activeTab"
        :is-mobile-standalone="isMobileStandalone"
        :is-editing="isEditing"
        :page-title="wizardPageTitle"
        :mobile-page-title="wizardMobilePageTitle"
        :desktop-subtitle="wizardDesktopSubtitle"
        :form-processing="form.processing"
        :customer-debt-blocked="customerDebtBlocked"
        :is-order-form-editable="isOrderFormEditable"
        :has-unsaved-document-files="hasUnsavedDocumentFiles"
        :core-validation-issues="coreValidationIssues"
        :tabs="tabs"
        :is-international-transport="form.is_international_transport"
        :order-status-badge-label="orderStatusBadgeLabel"
        :order-status-icon-meta="orderStatusIconMeta"
        :order-status-icon-key="orderStatusIconKey"
        :can-show-mark-disruption-button="canShowMarkDisruptionButton"
        :can-use-how-much-fits="canUseHowMuchFits"
        :wizard-body-inert="wizardBodyInert"
        @update:active-tab="activeTab = $event"
        @update:is-international-transport="form.is_international_transport = $event"
        @go-back="goBack"
        @submit="submit"
        @mark-disruption="markOrderDisruption"
        @open-how-much-fits="openHowMuchFitsFromOrder"
    >
        <OrderWizardBodyAlerts
            :is-editing="isEditing"
            :is-order-form-editable="isOrderFormEditable"
            :can-view-order-documents="canViewOrderDocuments"
            :can-edit-financial-fields="canEditFinancialFields"
            :save-attempted="saveAttempted"
            :core-validation-issues="coreValidationIssues"
        />
        <OrderWizardIntakePanel
            v-if="!isEditing"
            :intake-loading="intakeLoading"
            :intake-selected-file="intakeSelectedFile"
            :intake-error="intakeError"
            :intake-preview="intakePreview"
            @file-selected="onIntakeFileSelected"
            @extract="extractIntakeDraft"
            @apply="applyIntakeDraft"
        />
            <OrderWizardMainTab v-if="activeTab === 'main'" />

            <OrderWizardRouteTab v-else-if="activeTab === 'route'" />
            <OrderWizardCargoTab v-else-if="activeTab === 'cargo'" />

            <OrderWizardLeadPrecalculationSnapshot
                v-else-if="activeTab === 'lead_precalc' && leadPrecalculationSnapshot"
                :snapshot="leadPrecalculationSnapshot"
                :order-id="order.id"
            />

            <OrderWizardFinanceTab v-else-if="activeTab === 'finance'" />

            <OrderWizardNormsPenaltiesTab
                v-else-if="activeTab === 'norms_penalties'"
                v-model:client-norms-penalties="form.financial_term.client_norms_penalties"
                v-model:carrier-norms-by-leg="form.financial_term.carrier_norms_by_leg"
                :currency-options="currencyOptions"
                :is-order-form-editable="isOrderFormEditable"
                :validation-messages="normsPenaltiesTabValidationMessages"
                :stage-label="stageLabel"
                @sync-carrier-norms="syncCarrierNormsByLegFromPerformers"
            />

            <OrderWizardMailTab
                v-else-if="activeTab === 'mail' && order?.id && canAccessMail"
                :order-id="order.id"
                :threads="orderMailThreads"
                :compose-url="mailComposeUrl"
            />

            <OrderWizardTimelineTab
                v-else-if="activeTab === 'timeline' && order?.id && canViewOrderTimeline"
                :order-id="order.id"
            />

            <OrderWizardDocumentsTab
                v-else-if="activeTab === 'documents'"
                ref="documentsTabRef"
                v-model:signed-documents="form.documents"
                v-model:print-form-template-selection="printFormTemplateSelection"
                :order="order"
                :performers="form.performers"
                :additional-costs="form.financial_term.additional_costs"
                :client-request-mode="form.financial_term.client_request_mode"
                :is-order-form-editable="documentsTabEditable"
                :all-documents="orderAllDocuments"
                :print-form-template-catalog="printFormTemplateCatalog"
                :print-form-template-options-customer="printFormTemplateOptionsCustomer"
                :print-form-template-options-carrier="printFormTemplateOptionsCarrier"
                :own-company-id="form.own_company_id"
                :is-international-transport="form.is_international_transport"
                :customer-id="form.client_id"
                :customer-payment-form="form.financial_term.client_payment_form"
                :client-payment-schedule="form.financial_term.client_payment_schedule"
                :contractors-costs="form.financial_term.contractors_costs"
                :document-type-options="documentTypeOptions"
                :document-tab-validation-messages="documentTabValidationMessages"
                :document-storage="documentStorage"
                :saved-print-form-template-selection="order?.print_form_template_selection ?? {}"
                :document-edo-acknowledgements="documentEdoAcknowledgements"
                :can-edit-document-edo-acknowledgements="canEditDocumentEdoAcknowledgements"
            />

            <OrderWizardOrderNormsTab
                v-else-if="activeTab === 'order_norms'"
                v-model:basic-terms-draft="orderBasicTermsDraft"
                :order="order"
                :basic-terms="order?.basic_terms ?? null"
                :can-promote-basic-terms="order?.can_promote_basic_terms ?? false"
                :can-direct-promote="order?.can_direct_promote_basic_terms ?? false"
                :is-order-form-editable="isOrderFormEditable"
                :show-customer="orderNormsShowCustomer"
                :show-carrier="orderNormsShowCarrier"
            />
    </OrderWizardShell>

    <OrderWizardCounterpartyModal
        :show="showCounterpartyModal"
        :counterparty-target="counterpartyTarget"
        :counterparty-form="counterpartyForm"
        :counterparty-name-input="counterpartyNameInput"
        :inline-contractor-saving="inlineContractorSaving"
        :crm-field-fluid="crmFieldFluid"
        :crm-btn-neutral="crmBtnNeutral"
        :crm-btn-create="crmBtnCreate"
        :crm-modal-panel="crmModalPanel"
        @close="closeCounterpartyModal"
        @create="createInlineCounterparty"
    />

    <OrderWizardDocumentAttachModal
        :show="showOrderDocumentAttachModal"
        :title="orderDocumentAttachModalTitle"
        :preset-summary="orderDocumentAttachPresetSummary"
        :pending-file="orderDocumentAttachPendingFile"
        :preset-index="orderDocumentAttachPresetIndex"
        :target-kind="orderDocumentAttachTargetKind"
        :stage="orderDocumentAttachStage"
        :new-doc-type="orderDocumentAttachNewDocType"
        :performers="form.performers"
        :document-type-options="documentTypeOptions"
        :crm-field-fluid="crmFieldFluid"
        :crm-btn-neutral="crmBtnNeutral"
        :crm-btn-create="crmBtnCreate"
        :stage-label="stageLabel"
        :file-input-ref="orderDocumentAttachModalFileInputRef"
        @close="closeOrderDocumentAttachModal"
        @confirm="confirmOrderDocumentAttach"
        @file-change="onOrderDocumentAttachModalFileChange"
        @update:target-kind="orderDocumentAttachTargetKind = $event"
        @update:stage="orderDocumentAttachStage = $event"
        @update:new-doc-type="orderDocumentAttachNewDocType = $event"
    />
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, provide, reactive, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Calculator, ClipboardList, FileText, Gavel, History, Mail, MapPinned, Minus, Package, Plus, ScrollText, Wallet } from 'lucide-vue-next';
import OrderWizardBodyAlerts from '@/Components/Orders/OrderWizardBodyAlerts.vue';
import OrderWizardCounterpartyModal from '@/Components/Orders/OrderWizardCounterpartyModal.vue';
import OrderWizardDocumentAttachModal from '@/Components/Orders/OrderWizardDocumentAttachModal.vue';
import OrderWizardIntakePanel from '@/Components/Orders/OrderWizardIntakePanel.vue';
import OrderWizardLeadPrecalculationSnapshot from '@/Components/Orders/OrderWizardLeadPrecalculationSnapshot.vue';
import OrderWizardMailTab from '@/Components/Orders/OrderWizardMailTab.vue';
import OrderWizardMainTab from '@/Components/Orders/OrderWizardMainTab.vue';
import OrderWizardNormsPenaltiesTab from '@/Components/Orders/OrderWizardNormsPenaltiesTab.vue';
import OrderWizardOrderNormsTab from '@/Components/Orders/OrderWizardOrderNormsTab.vue';
import OrderWizardShell from '@/Components/Orders/OrderWizardShell.vue';
import OrderWizardTimelineTab from '@/Components/Orders/OrderWizardTimelineTab.vue';

const OrderWizardRouteTab = defineAsyncComponent(() => import('@/Components/Orders/OrderWizardRouteTab.vue'));
const OrderWizardCargoTab = defineAsyncComponent(() => import('@/Components/Orders/OrderWizardCargoTab.vue'));
const OrderWizardFinanceTab = defineAsyncComponent(() => import('@/Components/Orders/OrderWizardFinanceTab.vue'));
const OrderWizardDocumentsTab = defineAsyncComponent(() => import('@/Components/Orders/OrderWizardDocumentsTab.vue'));
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { ORDER_STATUS_ICON_META, resolveOrderStatusIconKey } from '@/support/orderStatusDisplay.js';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import { syncRoutePointCityFromAddress } from '@/support/routePointNormalizedData.js';
import { basicTermsPartiesForTemplateSelection } from '@/support/printFormBasicTerms.js';
import { EMPTY_ORDER_DOCUMENTS } from '@/support/emptyOrderDocuments.js';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmBtnSecondary,
    crmField,
    crmFieldFluid,
    crmModalPanel,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
} from '@/support/crmUi.js';
import * as orderPs from '@/support/orderPaymentScheduleUi.js';
import {
    blankPartyNormsPenalties,
    hasNormsPenaltiesContent,
    normalizePartyNormsPenalties,
} from '@/support/normsPenalties.js';
import {
    buildDocumentRequirementRules,
    documentMatchesRequirementRule,
} from '@/support/orderDocumentRequirementSlots.js';
import {
    blankAdditionalCostRow,
    migrateLegacyAdditionalContractorCosts,
    normalizeAdditionalCostsList,
    sumAdditionalCostsAmount,
} from '@/support/orderAdditionalCosts.js';
import CarrierPortalInviteButton from '@/Components/Orders/CarrierPortalInviteButton.vue';
import CustomerPortalInviteButton from '@/Components/Orders/CustomerPortalInviteButton.vue';
import OrderTrakloChatButton from '@/Components/Orders/OrderTrakloChatButton.vue';
import {
    blankPerformer,
    blankSplitCarrier,
    CARRIER_MODE_SINGLE,
    CARRIER_MODE_SPLIT,
    costMatchesPerformerSlot,
    isAdditionalContractorCost,
    EXECUTION_MODE_OWN_FLEET,
    isOwnFleetExecutionMode,
    isPerformerSplit,
    normalizePerformer,
    performerFleetCacheKey,
    splitCarrierSlotLabel,
} from '@/support/orderPerformers.js';
import { clampActualDateToToday, todayIsoDate } from '@/support/orderActualDates.js';
import { classifyDealType, paymentFormMetaFromOptions } from '@/support/paymentFormDealType.js';
import { buildNormalizeCargoItem, useOrderWizardCargoTab } from '@/composables/useOrderWizardCargoTab.js';
import { useOrderWizardCounterpartyModal } from '@/composables/useOrderWizardCounterpartyModal.js';
import { useOrderWizardIntake } from '@/composables/useOrderWizardIntake.js';
import { useOrderWizardDocumentAttach } from '@/composables/useOrderWizardDocumentAttach.js';
import { useOrderWizardFinanceTab } from '@/composables/useOrderWizardFinanceTab.js';
import { useOrderWizardRouteTab } from '@/composables/useOrderWizardRouteTab.js';
import { useOrderWizardSubmit } from '@/composables/useOrderWizardSubmit.js';
import { ORDER_WIZARD_CARGO_TAB_KEY } from '@/support/orderWizardCargoTabKey.js';
import { ORDER_WIZARD_FINANCE_TAB_KEY } from '@/support/orderWizardFinanceTabKey.js';
import { ORDER_WIZARD_MAIN_TAB_KEY } from '@/support/orderWizardMainTabKey.js';
import { ORDER_WIZARD_ROUTE_TAB_KEY } from '@/support/orderWizardRouteTabKey.js';
import { stageLabel, stageMatches, toStageKey } from '@/support/orderWizardStageHelpers.js';
import {
    isVirtualOwnFleetContractor,
    OWN_FLEET_CONTRACTOR_NAME,
} from '@/support/ownFleetCatalog.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const page = usePage();

const additionalExpenseAmountFieldClass =
    'h-9 w-[6.75rem] max-w-full rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-sm tabular-nums dark:border-zinc-700 dark:bg-zinc-950';
const cargoAllocationFieldClass =
    'h-8 w-full min-w-[4.5rem] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950';

const props = defineProps({
    order: { type: Object, default: null },
    orderTemplate: { type: Object, default: null },
    contractors: { type: Array, default: () => [] },
    ownCompanies: { type: Array, default: () => [] },
    ownFleetContractor: { type: Object, default: null },
    cargoTypeOptions: { type: Array, default: () => [] },
    packageTypeOptions: { type: Array, default: () => [] },
    loadingTypeOptions: { type: Array, default: () => [] },
    truckBodyTypeOptions: { type: Array, default: () => [] },
    trailerTypeOptions: { type: Array, default: () => [] },
    currencyOptions: { type: Array, default: () => [] },
    paymentFormOptions: { type: Array, default: () => [] },
    defaultClientPaymentFormCode: { type: String, default: '' },
    documentTypeOptions: { type: Array, default: () => [] },
    documentPartyOptions: { type: Array, default: () => [] },
    orderStatusOptions: { type: Array, default: () => [] },
    documentStatusOptions: { type: Array, default: () => [] },
    printFormTemplateCatalog: { type: Array, default: () => [] },
    printFormTemplateOptions: { type: Array, default: () => [] },
    printFormTemplateOptionsCustomer: { type: Array, default: () => [] },
    printFormTemplateOptionsCarrier: { type: Array, default: () => [] },
    orderDocumentWorkflow: { type: Object, default: () => ({ status_options: [] }) },
    documentStorage: {
        type: Object,
        default: () => ({
            driver: 'local',
            label: 'локальное хранилище приложения',
        }),
    },
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    documentEdoAcknowledgements: { type: Array, default: () => [] },
    canEditDocumentEdoAcknowledgements: { type: Boolean, default: false },
    currentUser: { type: Object, default: () => ({}) },
    responsibleUsers: { type: Array, default: () => [] },
    canAssignResponsible: { type: Boolean, default: false },
    bonusMultiplier: { type: Number, default: 0 },
    cargoTitleSuggestions: { type: Array, default: () => [] },
    canAccessMail: { type: Boolean, default: false },
    canViewOrderTimeline: { type: Boolean, default: false },
    orderMailThreads: { type: Array, default: () => [] },
    mailComposeDefaults: { type: Object, default: null },
});

const leadPrecalculationSnapshot = computed(() => props.order?.lead_precalculation_snapshot ?? null);

const tabs = computed(() => [
    { key: 'main', label: 'Основное', icon: ClipboardList },
    { key: 'route', label: 'Маршрут', icon: MapPinned },
    { key: 'cargo', label: 'Груз', icon: Package },
    ...(leadPrecalculationSnapshot.value
        ? [{ key: 'lead_precalc', label: 'Предрасчёт', icon: Calculator }]
        : []),
    { key: 'finance', label: 'Финансы', icon: Wallet },
    { key: 'norms_penalties', label: 'Нормативы / штрафы', icon: Gavel },
    { key: 'documents', label: 'Документы', icon: FileText },
    ...(showOrderNormsTab.value ? [{ key: 'order_norms', label: 'Нормы заявки', icon: ScrollText }] : []),
    ...(props.order?.id && props.canAccessMail ? [{ key: 'mail', label: 'Переписка', icon: Mail }] : []),
    ...(props.order?.id && props.canViewOrderTimeline ? [{ key: 'timeline', label: 'Лента', icon: History }] : []),
]);

const activeTab = ref('main');
const borderCrossingLegPicker = ref('');

const orderAllDocuments = computed(() => {
    const docs = props.order?.documents;

    return Array.isArray(docs) ? docs : EMPTY_ORDER_DOCUMENTS;
});

const mailComposeUrl = computed(() => {
    if (!props.canAccessMail || !props.order?.id) {
        return null;
    }

    if (props.mailComposeDefaults?.order_id) {
        return route('mail.index', { order_id: props.mailComposeDefaults.order_id });
    }

    return route('mail.index', { order_id: props.order.id });
});

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    const allowed = new Set(['main', 'route', 'cargo', 'finance', 'norms_penalties', 'documents', 'order_norms', 'mail', 'timeline']);
    if (tab && allowed.has(tab)) {
        activeTab.value = tab;
    }
    if (tab) {
        url.searchParams.delete('tab');
    }

    const intakeDraftParam = url.searchParams.get('intake_draft');
    if (intakeDraftParam && !isEditing.value) {
        void loadAndApplyIntakeDraftById(intakeDraftParam);
        url.searchParams.delete('intake_draft');
    }

    if (tab || intakeDraftParam) {
        const qs = url.searchParams.toString();
        const next = `${url.pathname}${qs ? `?${qs}` : ''}${url.hash}`;
        window.history.replaceState({}, '', next);
    }

    if (!form.is_international_transport && Array.isArray(form.route_points)) {
        const nextPoints = form.route_points.filter((p) => p.type !== 'border_crossing');
        if (nextPoints.length !== form.route_points.length) {
            form.route_points = nextPoints;
            normalizeRoutePointSequences();
        }
    }

    syncCargoAllocationMatrixSlots();

    preloadFleetOptionsForPerformers();

    (form.financial_term.additional_costs ?? []).forEach((row) => {
        const contractor = getContractorById(row?.contractor_id);
        const label = contractor?.name ?? row?.contractor_name ?? '';
        if (label && row?.id != null) {
            financeTab.setAdditionalCostSearchValue(row.id, label);
        }
    });

    if (!isEditing.value && form.own_company_id && !orderNumberManual.value) {
        void refreshSuggestedOrderNumber();
    }
});

const workflowTemplateId = ref(null);
const workflowRejectTargetId = ref(null);
const workflowRejectReason = ref('');
const contractors = ref([...props.contractors]);

const printWorkflowDocuments = computed(() => {
    const docs = props.order?.documents;
    if (!Array.isArray(docs)) {
        return [];
    }

    return docs.filter((d) => d.is_print_workflow);
});

function createPersistedPrintWorkflowDocument() {
    if (!props.order?.id || !workflowTemplateId.value) {
        return;
    }

    router.post(
        route('orders.documents.from-template', props.order.id),
        { print_form_template_id: workflowTemplateId.value },
        { preserveScroll: true },
    );
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
    if (workflowRejectTargetId.value === documentId) {
        cancelWorkflowReject();
    } else {
        workflowRejectTargetId.value = documentId;
        workflowRejectReason.value = '';
    }
}

function cancelWorkflowReject() {
    workflowRejectTargetId.value = null;
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
                cancelWorkflowReject();
            },
        },
    );
}

async function finalizeWorkflowPdf(doc, event) {
    const target = event.target;
    const file = target?.files?.[0];

    if (!file || !props.order?.id) {
        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});

    const formData = new FormData();
    formData.append('pdf', file);

    router.post(route('orders.documents.finalize', [props.order.id, doc.id]), formData, {
        forceFormData: true,
        preserveScroll: true,
    });

    target.value = '';
}

function confirmDiscardPrintWorkflow(doc) {
    if (!props.order?.id || !doc?.id) {
        return;
    }

    if (!window.confirm('Удалить черновик по шаблону из заказа? Файл DOCX будет удалён; для нового документа создайте его снова из шаблона.')) {
        return;
    }

    router.delete(route('orders.documents.discard-print-workflow', [props.order.id, doc.id]), {
        preserveScroll: true,
    });
}

if ((props.order ?? props.orderTemplate)?.client_snapshot) {
    const snap = (props.order ?? props.orderTemplate).client_snapshot;
    const exists = contractors.value.some((c) => Number(c.id) === Number(snap.id));

    if (!exists) {
        contractors.value.unshift({
            id: snap.id,
            name: snap.name,
            inn: snap.inn ?? null,
            type: snap.type ?? 'customer',
            phone: null,
            email: null,
            is_own_company: false,
        });
    }
}

const ownCompanyOptions = ref(
    props.ownCompanies.filter((company) => !isVirtualOwnFleetContractor(company)),
);

let applyCounterpartyContractor = () => {};
const {
    showCounterpartyModal,
    counterpartyNameInput,
    inlineContractorSaving,
    counterpartyTarget,
    counterpartyForm,
    openCounterpartyModal,
    closeCounterpartyModal,
    createInlineCounterparty,
} = useOrderWizardCounterpartyModal({
    contractors,
    ownCompanyOptions,
    applyContractor: (target, contractor) => applyCounterpartyContractor(target, contractor),
});

const clientSearch = ref('');
const showClientResults = ref(false);
const carrierSearch = ref({});
const showCarrierResults = ref({});
const fleetOptionsCache = ref({});
const saveAttempted = ref(false);
const addressSuggestions = ref({});
const addressTimers = {};
const draggedRoutePointIndex = ref(null);
const dragOverRoutePointIndex = ref(null);
const paymentFormMeta = computed(() => paymentFormMetaFromOptions(paymentFormOptions.value));

const paymentFormOptions = computed(() => {
    const raw = props.paymentFormOptions;
    if (Array.isArray(raw) && raw.length > 0) {
        return raw;
    }

    return [
        { value: 'vat_22', label: 'С НДС 22%' },
        { value: 'vat_5', label: 'С НДС 5%' },
        { value: 'vat_0', label: 'С НДС 0%' },
        { value: 'no_vat', label: 'Без НДС' },
        { value: 'cash', label: 'Нал' },
    ];
});

function defaultClientPaymentForm() {
    const c = props.defaultClientPaymentFormCode;
    if (c && String(c).trim() !== '') {
        return String(c).trim();
    }

    return paymentFormOptions.value.find((o) => String(o.value).startsWith('vat_'))?.value ?? 'no_vat';
}

/** Согласование кода с опциями <select>; legacy `vat` и текстовые подписи. */
function normalizePaymentFormCode(value, fallback) {
    const fb = fallback ?? defaultClientPaymentForm();
    if (value === null || value === undefined || value === '') {
        return fb;
    }
    const trimmed = String(value).trim();
    const allowed = new Set(paymentFormOptions.value.map((o) => o.value));
    allowed.add('vat');
    if (allowed.has(trimmed)) {
        if (trimmed === 'vat') {
            return defaultClientPaymentForm();
        }

        return trimmed;
    }
    const lower = trimmed.toLowerCase();
    if (lower.includes('без') && lower.includes('ндс')) {
        return 'no_vat';
    }
    if (lower.includes('нал')) {
        return 'cash';
    }
    if (lower.includes('ндс')) {
        return defaultClientPaymentForm();
    }

    return fb;
}
const clientRequestModeOptions = [
    { value: 'single_request', label: 'Одна заявка', description: 'Все плечи включаются в одну клиентскую заявку.' },
    { value: 'split_by_leg', label: 'Разбить по плечам', description: 'Для каждого плеча оформляется отдельная клиентская заявка.' },
];
const blankPaymentSchedule = orderPs.blankSingleInstallmentSchedule;
const normalizePaymentSchedule = orderPs.normalizePaymentSchedule;

function templatePartyShortLabel(party) {
    if (party === 'customer') {
        return 'заказчик';
    }
    if (party === 'carrier') {
        return 'перевозчик';
    }
    if (party === 'internal') {
        return 'внутр.';
    }

    return party ? String(party) : '';
}

function templateOptionLabel(template) {
    const suffix = [];
    const partyLabel = templatePartyShortLabel(template.party);
    if (partyLabel) {
        suffix.push(partyLabel);
    }

    if (template.contractor_name) {
        suffix.push(template.contractor_name);
    }

    if (template.is_default) {
        suffix.push('по умолчанию');
    }

    return suffix.length > 0 ? `${template.name} (${suffix.join(', ')})` : template.name;
}

function printWorkflowDocumentTitle(document) {
    return document?.print_template_name || document?.original_name || 'Документ';
}

function normalizeDocument(document = {}) {
    return {
        type: 'request',
        flow: 'uploaded',
        number: '',
        document_date: '',
        status: 'draft',
        template_id: null,
        file: null,
        original_name: '',
        generated_pdf_path: null,
        party: 'internal',
        stage: null,
        requirement_key: null,
        ...document,
    };
}

function previewDocumentDraft(document) {
    if (!props.order?.id || !document?.template_id) {
        return;
    }

    window.open(
        route('orders.templates.generate-draft', {
            order: props.order.id,
            printFormTemplate: document.template_id,
            preview: 1,
            preview_mode: 'browser',
        }),
        '_blank'
    );
}

function downloadDocumentDraft(document) {
    if (!props.order?.id || !document?.template_id) {
        return;
    }

    window.location.href = route('orders.templates.generate-draft', {
        order: props.order.id,
        printFormTemplate: document.template_id,
    });
}

function normalizeContractorCost(cost = {}) {
    const merged = {
        stage: '',
        carrier_slot: null,
        contractor_id: null,
        contractor_name: null,
        amount: null,
        currency: 'RUB',
        payment_form: 'no_vat',
        payment_schedule: blankPaymentSchedule(),
        payment_terms: '',
        execution_mode: null,
        is_additional: false,
        incurred_date: null,
        ...cost,
        payment_schedule: normalizePaymentSchedule(cost.payment_schedule),
    };
    merged.incurred_date = merged.incurred_date ? String(merged.incurred_date).slice(0, 10) : null;
    merged.execution_mode = isOwnFleetExecutionMode(merged.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null;
    merged.payment_form = normalizePaymentFormCode(merged.payment_form, 'no_vat');
    merged.payment_terms = String(merged.payment_terms ?? '').trim();

    return merged;
}

function blankRoutePoint(type, sequence, stage) {
    return {
        stage,
        type,
        sequence,
        address: '',
        normalized_data: {},
        planned_date: '',
        planned_time_from: '',
        planned_time_to: '',
        actual_date: '',
        actual_time: '',
        contact_person: '',
        contact_phone: '',
        sender_name: '',
        sender_contact: '',
        sender_phone: '',
        recipient_name: '',
        recipient_contact: '',
        recipient_phone: '',
    };
}

function normalizePartyNormsPenaltiesWithStage(raw) {
    const base = normalizePartyNormsPenalties(raw);

    return {
        ...base,
        stage: raw?.stage != null && String(raw.stage).trim() !== '' ? String(raw.stage).trim() : null,
    };
}

function normalizeCarrierNormsByLegList(existingRows, performers) {
    const existing = Array.isArray(existingRows) ? existingRows : [];
    const legs = Array.isArray(performers) ? performers : [];

    return legs.map((performer) => {
        const existingRow = existing.find((row) => stageMatches(row.stage, performer.stage));

        return normalizePartyNormsPenaltiesWithStage({
            ...existingRow,
            stage: performer.stage,
        });
    });
}

function normalizeLoadedContractorsCosts(rawCosts, order) {
    const migrated = migrateLegacyAdditionalContractorCosts(rawCosts, order?.order_date);

    return migrated.legCosts.map((cost) => normalizeContractorCost(cost));
}

function buildInitialAdditionalCosts(order) {
    const fromFinancial = normalizeAdditionalCostsList(
        order?.financial_term?.additional_costs ?? [],
        order?.order_date ?? null,
    );

    if (fromFinancial.length > 0) {
        return fromFinancial;
    }

    const migrated = migrateLegacyAdditionalContractorCosts(
        order?.financial_term?.contractors_costs ?? [],
        order?.order_date ?? null,
    );

    return migrated.additionalCosts;
}

function blankOrder() {
    return {
        status: 'new',
        manual_status: null,
        own_company_id: null,
        own_company_bank_account_id: null,
        client_id: null,
        order_owner_id: null,
        dispatcher_id: null,
        compensation_owner_percent: 100,
        compensation_dispatcher_percent: 0,
        order_date: new Date().toISOString().slice(0, 10),
        order_number: '',
        payment_terms: '',
        special_notes: '',
        svh_name: '',
        svh_address: '',
        customs_post_code: '',
        cargo_declared_sum: null,
        is_international_transport: false,
        loading_types: [],
        cargo_sender_name: '',
        cargo_sender_address: '',
        cargo_sender_contact: '',
        cargo_sender_phone: '',
        cargo_recipient_name: '',
        cargo_recipient_address: '',
        cargo_recipient_contact: '',
        cargo_recipient_phone: '',
        performers: [
            blankPerformer('leg_1'),
        ],
        route_points: [
            blankRoutePoint('loading', 1, 'leg_1'),
            blankRoutePoint('unloading', 2, 'leg_1'),
        ],
        cargo_items: [
            {
                name: '',
                description: '',
                weight_value: null,
                weight_kg: null,
                weight_unit: 'kg',
                volume_m3: null,
                length_m: null,
                width_m: null,
                height_m: null,
                diameter_m: null,
                pack_type_id: null,
                pack_type_label: '',
                package_type: null,
                loading_type_id: null,
                loading_type_ids: [],
                loading_type_code: null,
                loading_type_label: '',
                loading_type_items: [],
                truck_body_type_id: null,
                truck_body_type_ids: [],
                truck_body_type_code: null,
                truck_body_type_label: '',
                truck_body_type_items: [],
                trailer_type_id: null,
                trailer_type_ids: [],
                trailer_type_code: null,
                trailer_type_label: '',
                trailer_type_items: [],
                package_count: null,
                dangerous_goods: false,
                dangerous_class: '',
                hs_code: '',
                cargo_type_id: defaultCargoTypeOption()?.value ?? 1,
                cargo_type_label: defaultCargoTypeOption()?.label ?? 'Общий груз',
                cargo_type: defaultCargoTypeOption()?.code ?? 'general',
                is_oversized: false,
                is_fragile: false,
                ati_cargo_payload: {},
                performer_allocations: [],
            },
        ],
        financial_term: {
            client_price: null,
            client_currency: 'RUB',
            client_payment_form: defaultClientPaymentForm(),
            client_request_mode: 'single_request',
            client_payment_schedule: blankPaymentSchedule(),
            client_payment_terms: '',
            contractors_costs: [],
            additional_costs: [],
            kpi_percent: 0,
            client_norms_penalties: blankPartyNormsPenalties(),
            carrier_norms_by_leg: [],
        },
        additional_expenses: null,
        additional_expenses_payment_date: null,
        insurance: null,
        bonus: null,
        documents: [],
    };
}

function normalizeNullableNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function defaultCargoTypeOption() {
    return props.cargoTypeOptions[0] ?? { value: 1, code: 'general', label: 'Общий груз' };
}

const normalizeCargoItem = buildNormalizeCargoItem(props, normalizeNullableNumber);

const initialWizardPerformers = Array.isArray(props.order?.performers)
    ? props.order.performers.map((performer) => normalizePerformer({
        ...performer,
        stage: toStageKey(performer.stage ?? 'leg_1') || 'leg_1',
        contractor_id: normalizeNullableNumber(performer.contractor_id),
        contractor_name: performer.contractor_name ? String(performer.contractor_name).trim() || null : null,
        fleet_vehicle_id: normalizeNullableNumber(performer.fleet_vehicle_id),
        fleet_driver_id: normalizeNullableNumber(performer.fleet_driver_id),
        split_carriers: Array.isArray(performer.split_carriers)
            ? performer.split_carriers.map((slot, index) => ({
                ...slot,
                slot: Number(slot?.slot ?? index + 1),
                contractor_id: normalizeNullableNumber(slot?.contractor_id),
                fleet_vehicle_id: normalizeNullableNumber(slot?.fleet_vehicle_id),
                fleet_driver_id: normalizeNullableNumber(slot?.fleet_driver_id),
                loading_actual: slot?.loading_actual ?? '',
                unloading_actual: slot?.unloading_actual ?? '',
            }))
            : [],
    }))
    : blankOrder().performers;

const initialOrderPayload = computed(() => props.order ?? props.orderTemplate ?? null);

const maxActualDate = todayIsoDate();

function onPerformerActualDateInput(performer, field) {
    performer[field] = clampActualDateToToday(performer[field]);
}

function onSplitActualDateInput(slot, field) {
    slot[field] = clampActualDateToToday(slot[field]);
}

function clampCompensationPercent(value) {
    const numeric = Number(value);

    if (Number.isNaN(numeric)) {
        return 0;
    }

    return Math.min(100, Math.max(0, numeric));
}

function onCompensationOwnerPercentInput() {
    const owner = clampCompensationPercent(form.compensation_owner_percent);
    form.compensation_owner_percent = owner;
    form.compensation_dispatcher_percent = Math.round((100 - owner) * 100) / 100;
}

function onCompensationDispatcherPercentInput() {
    const dispatcher = clampCompensationPercent(form.compensation_dispatcher_percent);
    form.compensation_dispatcher_percent = dispatcher;
    form.compensation_owner_percent = Math.round((100 - dispatcher) * 100) / 100;
}

function defaultOrderOwnerId() {
    const fromOrder = normalizeNullableNumber(
        initialOrderPayload.value?.order_owner_id ?? initialOrderPayload.value?.responsible_id,
    );

    if (fromOrder) {
        return fromOrder;
    }

    const fallbackUserId = normalizeNullableNumber(props.responsibleUsers?.[0]?.id);

    if (fallbackUserId) {
        return fallbackUserId;
    }

    return normalizeNullableNumber(props.currentUser?.id);
}

function defaultCompensationPercents() {
    const split = initialOrderPayload.value?.compensation_split;

    if (split && typeof split === 'object') {
        return {
            owner: Number(split.order_owner_percent ?? 100),
            dispatcher: Number(split.dispatcher_percent ?? 0),
        };
    }

    return { owner: 100, dispatcher: 0 };
}

const compensationDefaults = defaultCompensationPercents();

const form = useForm({
    ...blankOrder(),
    ...(initialOrderPayload.value ?? {}),
    own_company_id: normalizeNullableNumber(initialOrderPayload.value?.own_company_id),
    own_company_bank_account_id: initialOrderPayload.value?.own_company_bank_account_id
        ? String(initialOrderPayload.value.own_company_bank_account_id)
        : null,
    client_id: normalizeNullableNumber(initialOrderPayload.value?.client_id),
    order_owner_id: defaultOrderOwnerId(),
    dispatcher_id: normalizeNullableNumber(initialOrderPayload.value?.dispatcher_id),
    compensation_owner_percent: compensationDefaults.owner,
    compensation_dispatcher_percent: compensationDefaults.dispatcher,
    manual_status: initialOrderPayload.value?.manual_status ?? null,
    additional_expenses: initialOrderPayload.value?.additional_expenses ?? null,
    additional_expenses_payment_date: initialOrderPayload.value?.additional_expenses_payment_date ?? initialOrderPayload.value?.order_date ?? null,
    insurance: initialOrderPayload.value?.insurance ?? null,
    bonus: initialOrderPayload.value?.bonus ?? null,
    loading_types: Array.isArray(initialOrderPayload.value?.loading_types)
        ? initialOrderPayload.value.loading_types
        : [],
    cargo_items: Array.isArray(initialOrderPayload.value?.cargo_items)
        ? initialOrderPayload.value.cargo_items.map((c) => normalizeCargoItem(c))
        : blankOrder().cargo_items,
    performers: initialWizardPerformers,
    route_points: Array.isArray(initialOrderPayload.value?.route_points)
        ? initialOrderPayload.value.route_points.map((point, index) => ({
            ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), toStageKey(point.stage ?? 'leg_1') || 'leg_1'),
            ...point,
            stage: toStageKey(point.stage ?? 'leg_1') || 'leg_1',
            sequence: Number(point.sequence ?? (index + 1)),
            normalized_data: point.normalized_data || {},
        }))
        : blankOrder().route_points,
    financial_term: {
        ...blankOrder().financial_term,
        ...(initialOrderPayload.value?.financial_term ?? {}),
        client_payment_schedule: normalizePaymentSchedule(initialOrderPayload.value?.financial_term?.client_payment_schedule),
        client_payment_terms:
            initialOrderPayload.value?.financial_term?.client_payment_terms
            ?? initialOrderPayload.value?.customer_payment_term
            ?? '',
        client_payment_form: normalizePaymentFormCode(
            initialOrderPayload.value?.financial_term?.client_payment_form ?? blankOrder().financial_term.client_payment_form,
            'vat',
        ),
        contractors_costs: normalizeLoadedContractorsCosts(initialOrderPayload.value?.financial_term?.contractors_costs, initialOrderPayload.value),
        additional_costs: buildInitialAdditionalCosts(initialOrderPayload.value),
        client_norms_penalties: normalizePartyNormsPenalties(initialOrderPayload.value?.financial_term?.client_norms_penalties),
        carrier_norms_by_leg: normalizeCarrierNormsByLegList(
            initialOrderPayload.value?.financial_term?.carrier_norms_by_leg,
            initialWizardPerformers,
        ),
    },
    documents: Array.isArray(initialOrderPayload.value?.documents)
        ? initialOrderPayload.value.documents
            .filter((document) => !document.is_print_workflow && document.status === 'signed')
            .map((document) => normalizeDocument({ ...document, status: 'signed', flow: 'uploaded' }))
        : [],
    svh_name: initialOrderPayload.value?.svh_name ?? '',
    svh_address: initialOrderPayload.value?.svh_address ?? '',
    customs_post_code: initialOrderPayload.value?.customs_post_code ?? '',
    cargo_declared_sum: initialOrderPayload.value?.cargo_declared_sum ?? null,
    is_international_transport: Boolean(initialOrderPayload.value?.is_international_transport),
});

const documentsTabRef = ref(null);

watch(() => form.dispatcher_id, (dispatcherId) => {
    if (!dispatcherId) {
        form.compensation_owner_percent = 100;
        form.compensation_dispatcher_percent = 0;
    }
});

const orderBasicTermsDraft = reactive({
    dirty: false,
    customer_basic_terms: undefined,
    carrier_basic_terms: undefined,
});

function clonePrintFormTemplateSelection(raw) {
    const next = {};

    if (!raw || typeof raw !== 'object') {
        return next;
    }

    Object.entries(raw).forEach(([slotKey, templateId]) => {
        if (templateId != null && templateId !== '') {
            next[slotKey] = Number(templateId);
        }
    });

    return next;
}

const printFormTemplateSelection = reactive(clonePrintFormTemplateSelection(props.order?.print_form_template_selection));

const orderNormsParties = computed(() => basicTermsPartiesForTemplateSelection(
    printFormTemplateSelection,
    props.printFormTemplateCatalog ?? [],
));

const showOrderNormsTab = computed(() => {
    if (!props.order?.basic_terms) {
        return false;
    }

    if (orderNormsParties.value.any) {
        return true;
    }

    const basicTerms = props.order.basic_terms;

    return Boolean(basicTerms.customer?.has_order_override || basicTerms.carrier?.has_order_override);
});

const orderNormsShowCustomer = computed(() => (
    orderNormsParties.value.customer || Boolean(props.order?.basic_terms?.customer?.has_order_override)
));

const orderNormsShowCarrier = computed(() => (
    orderNormsParties.value.carrier || Boolean(props.order?.basic_terms?.carrier?.has_order_override)
));

watch(
    () => props.order?.print_form_template_selection,
    (raw) => {
        Object.keys(printFormTemplateSelection).forEach((slotKey) => {
            delete printFormTemplateSelection[slotKey];
        });
        Object.assign(printFormTemplateSelection, clonePrintFormTemplateSelection(raw));
    },
    { deep: true },
);

watch(
    () => props.order?.basic_terms,
    () => {
        orderBasicTermsDraft.dirty = false;
        orderBasicTermsDraft.customer_basic_terms = undefined;
        orderBasicTermsDraft.carrier_basic_terms = undefined;
    },
    { deep: true },
);

watch(showOrderNormsTab, (visible) => {
    if (!visible && activeTab.value === 'order_norms') {
        activeTab.value = 'documents';
    }
});

const orderNumberManual = ref(Boolean(props.order?.order_number));
const suggestedOrderNumberCipher = ref('');

function onOrderNumberManualInput() {
    orderNumberManual.value = true;
}

async function refreshSuggestedOrderNumber() {
    const companyId = Number(form.own_company_id);
    if (!Number.isFinite(companyId) || companyId <= 0) {
        suggestedOrderNumberCipher.value = '';
        return;
    }

    try {
        const url = route('orders.suggest-order-number', { own_company_id: companyId });
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        suggestedOrderNumberCipher.value = data?.cipher ? String(data.cipher) : '';

        if (!orderNumberManual.value && data?.order_number) {
            form.order_number = String(data.order_number);
        }
    } catch (error) {
        console.error('suggest order number failed', error);
    }
}

watch(() => form.own_company_id, () => {
    form.own_company_bank_account_id = null;

    if (!isEditing.value && !orderNumberManual.value) {
        void refreshSuggestedOrderNumber();
    }
});

watch(
    () => form.is_international_transport,
    (international) => {
        if (!international) {
            form.route_points = form.route_points.filter((p) => p.type !== 'border_crossing');
            normalizeRoutePointSequences();
        }
        borderCrossingLegPicker.value = '';
    },
);

function resolveOwnCompanyRecord(companyId) {
    if (companyId == null || companyId === '') {
        return null;
    }
    const fromOwn = ownCompanyOptions.value.find((c) => Number(c.id) === Number(companyId));
    if (fromOwn) {
        return fromOwn;
    }
    return contractors.value.find((c) => Boolean(c.is_own_company) && Number(c.id) === Number(companyId)) ?? null;
}

function ownCompanyBankAccountLabel(row) {
    const label = String(row?.label ?? '').trim();
    if (label) {
        return label;
    }
    const digits = String(row?.account_number ?? '').replace(/\D/g, '');
    if (digits.length >= 4) {
        return `Р/с …${digits.slice(-4)}`;
    }
    return 'Расчётный счёт';
}

const selectableOwnCompanyBankAccounts = computed(() => {
    const company = resolveOwnCompanyRecord(form.own_company_id);
    const raw = company?.bank_accounts;
    if (!Array.isArray(raw)) {
        return [];
    }
    return raw.filter((row) => row && row.id != null && String(row.id).trim() !== '');
});

const showOwnCompanyBankAccountPicker = computed(() => selectableOwnCompanyBankAccounts.value.length > 1);

const calculatedCompensation = ref({
    kpi_percent: 0,
    delta: 0,
    salary_accrued: 0,
    deal_type: 'unknown',
});

const isCalculatingCompensation = ref(false);
let compensationDebounceTimer = null;
let compensationRequestVersion = 0;

async function calculateCompensation() {
    if (isCalculatingCompensation.value) {
        return;
    }

    const requestVersion = ++compensationRequestVersion;
    isCalculatingCompensation.value = true;

    try {
        const response = await fetch(route('orders.calculate-compensation'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                customer_rate: form.financial_term.client_price,
                carrier_rate: form.financial_term.contractors_costs.reduce((sum, cost) => sum + Number(cost.amount || 0), 0),
                additional_expenses: sumAdditionalCostsAmount(form.financial_term.additional_costs),
                insurance: Number(form.insurance || 0),
                bonus: Number(form.bonus || 0),
                manager_id: form.order_owner_id ?? props.currentUser?.id,
                dispatcher_id: form.dispatcher_id,
                compensation_owner_percent: form.dispatcher_id ? form.compensation_owner_percent : 100,
                compensation_dispatcher_percent: form.dispatcher_id ? form.compensation_dispatcher_percent : 0,
                order_date: form.order_date,
                customer_payment_form: normalizePaymentFormCode(form.financial_term.client_payment_form, defaultClientPaymentForm()),
                contractors_costs: legContractorCosts.value,
            }),
        });

        if (!response.ok) {
            throw new Error(`Расчёт компенсации: HTTP ${response.status}`);
        }

        const result = await response.json();

        if (requestVersion !== compensationRequestVersion) {
            return;
        }

        calculatedCompensation.value = result;
        
        // Update the form's KPI percentage with the calculated value
        form.financial_term.kpi_percent = result.kpi_percent || 0;
    } catch (error) {
        console.error('Compensation calculation error', error);
        calculatedCompensation.value = {
            kpi_percent: 0,
            delta: 0,
            salary_accrued: 0,
            deal_type: 'unknown',
        };
        // Reset KPI to 0 on error
        form.financial_term.kpi_percent = 0;
    } finally {
        isCalculatingCompensation.value = false;
    }
}

watch(
    [
        () => form.financial_term.client_price,
        () => form.financial_term.contractors_costs,
        () => form.financial_term.client_payment_form,
        () => form.financial_term.additional_costs,
        () => form.insurance,
        () => form.bonus,
        () => form.order_date,
        () => form.client_id,
        () => form.performers,
        () => form.order_owner_id,
        () => form.dispatcher_id,
        () => form.compensation_owner_percent,
        () => form.compensation_dispatcher_percent,
    ],
    () => {
        if (compensationDebounceTimer !== null) {
            clearTimeout(compensationDebounceTimer);
        }

        compensationDebounceTimer = setTimeout(() => {
            calculateCompensation();
        }, 400);
    },
    { deep: true, immediate: true },
);

const isEditing = computed(() => props.order !== null);

const wizardPageTitle = computed(() => {
    if (isEditing.value) {
        return form.order_number || `Заказ #${props.order?.id ?? ''}`;
    }

    return 'Добавление';
});

const wizardMobilePageTitle = computed(() => (isEditing.value ? '' : 'Новый заказ'));

const wizardDesktopSubtitle = computed(() => (isEditing.value ? 'Карточка заказа' : 'Новый заказ'));

const canUseHowMuchFits = computed(() => {
    const role = page.props.auth?.user?.role ?? {};
    const areas = role.visibility_areas ?? [];

    return Boolean(role.is_admin) || role.name === 'admin' || areas.includes('modules_how_much_fits') || areas.includes('modules');
});

function openHowMuchFitsFromOrder() {
    if (!props.order?.id) {
        return;
    }

    router.get(route('modules.how-much-fits.index', { order: props.order.id }));
}

function mergeFinancialTermFromIntake(patchTerm) {
    if (!patchTerm || typeof patchTerm !== 'object') {
        return;
    }

    const current = form.financial_term;
    const merged = {
        ...current,
        ...patchTerm,
    };

    merged.client_payment_schedule = normalizePaymentSchedule(
        patchTerm.client_payment_schedule !== undefined
            ? patchTerm.client_payment_schedule
            : current.client_payment_schedule,
    );

    if (Array.isArray(patchTerm.contractors_costs)) {
        merged.contractors_costs = patchTerm.contractors_costs.map((row) => normalizeContractorCost(row));
    } else if (Array.isArray(merged.contractors_costs)) {
        merged.contractors_costs = merged.contractors_costs.map((row) => normalizeContractorCost(row));
    }

    form.financial_term = merged;
}

const orderStatusBadgeLabel = computed(() => {
    const manual = form.manual_status != null && String(form.manual_status).trim() !== '' ? String(form.manual_status).trim() : null;
    const code = manual ?? form.status;
    const opt = props.orderStatusOptions.find((o) => o.value === code);

    return opt?.label ?? code ?? '—';
});

const orderStatusIconKey = computed(() => {
    const manual = form.manual_status != null && String(form.manual_status).trim() !== '' ? String(form.manual_status).trim() : null;
    const effective = manual ?? form.status;
    const row = { manual_status: form.manual_status, status: form.status };

    return resolveOrderStatusIconKey(row, effective);
});

const orderStatusIconMeta = computed(() => Boolean(orderStatusIconKey.value && ORDER_STATUS_ICON_META[orderStatusIconKey.value]));

/** Ложь, когда владелец заказа не может менять карточку (все печатные заявки финализированы). */
const isOrderFormEditable = computed(() => {
    if (!isEditing.value) {
        return true;
    }

    return props.order?.can_edit_order !== false;
});

const canViewOrderDocuments = computed(() => props.order?.can_view_order_documents === true);

const canManageOrderDocuments = computed(() => props.order?.can_manage_order_documents === true);

/** Вкладка «Документы»: загрузка и печатные формы не зависят от блокировки карточки заказа. */
const documentsTabEditable = computed(() => canManageOrderDocuments.value || isOrderFormEditable.value);

const wizardBodyInert = computed(() => {
    if (!isEditing.value) {
        return false;
    }

    if (activeTab.value === 'documents' && canViewOrderDocuments.value) {
        return false;
    }

    return !isOrderFormEditable.value;
});

function performerHasLoadingActual(performer) {
    if (!performer) {
        return false;
    }

    if (isPerformerSplit(performer)) {
        return (performer.split_carriers ?? []).some(
            (slot) => slot?.loading_actual != null && String(slot.loading_actual).trim() !== '',
        );
    }

    return performer.loading_actual != null && String(performer.loading_actual).trim() !== '';
}

function wizardRouteLoadingHasActualDate() {
    if (Array.isArray(form.performers) && form.performers.some((performer) => performerHasLoadingActual(performer))) {
        return true;
    }

    if (!Array.isArray(form.route_points)) {
        return false;
    }

    return form.route_points.some(
        (p) => p.type === 'loading' && p.actual_date != null && String(p.actual_date).trim() !== '',
    );
}

const canShowMarkDisruptionButton = computed(() => {
    if (!isEditing.value || !isOrderFormEditable.value || !props.order?.id) {
        return false;
    }

    const role = props.currentUser?.role_name ?? page.props.auth?.user?.role?.name;
    if (role !== 'admin' && role !== 'supervisor') {
        return false;
    }

    const manual = form.manual_status != null && String(form.manual_status).trim() !== '' ? String(form.manual_status).trim() : null;
    const effective = manual ?? String(form.status || 'new').trim();
    if (effective === 'new') {
        return false;
    }

    if (['disruption', 'cancelled', 'closed'].includes(effective)) {
        return false;
    }

    return ! wizardRouteLoadingHasActualDate();
});
const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});
const selectedClient = computed(() => contractors.value.find((contractor) => Number(contractor.id) === Number(form.client_id)) ?? null);
const carrierOptions = computed(() => contractors.value.filter((contractor) => contractor.type === 'carrier' || contractor.type === 'both'));

const customerDebtBlocked = computed(() => !isEditing.value && Boolean(selectedClient.value?.debt_limit_reached));

const hasSelectedCarrier = computed(() => {
    const performerCarrier = form.performers.some((performer) => Number(performer?.contractor_id || 0) > 0);
    const financialCarrier = form.financial_term.contractors_costs.some((cost) => Number(cost?.contractor_id || 0) > 0);

    return performerCarrier || financialCarrier;
});

const hasClientPrice = computed(() => Number(form.financial_term.client_price || 0) > 0);

const coreValidationIssues = computed(() => {
    const issues = [];

    if (!form.client_id) {
        issues.push('Заказчик');
    }

    if (!form.order_date) {
        issues.push('Дата заказа');
    }

    if (!hasSelectedCarrier.value) {
        issues.push('Перевозчик');
    }

    if (!hasClientPrice.value) {
        issues.push('Цена клиента');
    }

    return issues;
});

const coreRequiredFieldsValid = computed(() => coreValidationIssues.value.length === 0);

const mandatoryWizardFields = new Set([
    'client_id',
    'order_date',
]);

const coreRequiredHighlightClass = 'border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/30';
const defaultFieldBorderClass = 'border-zinc-200 dark:border-zinc-700';

/** Подсветка полей, обязательных для сохранения заказа (см. coreValidationIssues). */
const highlightRequiredField = (fieldName, value, conditionValue = null) => {
    if (fieldName === 'client_currency') {
        if (conditionValue && (!value || value === '' || value === null)) {
            return coreRequiredHighlightClass;
        }

        return defaultFieldBorderClass;
    }

    if (fieldName === 'client_price') {
        const price = Number(value);
        if (!value || value === '' || Number.isNaN(price) || price <= 0) {
            return coreRequiredHighlightClass;
        }

        return defaultFieldBorderClass;
    }

    if (fieldName === 'performer_carrier') {
        if (hasSelectedCarrier.value) {
            return defaultFieldBorderClass;
        }

        if (normalizeNullableNumber(value) === null) {
            return coreRequiredHighlightClass;
        }

        return defaultFieldBorderClass;
    }

    if (!mandatoryWizardFields.has(fieldName)) {
        return defaultFieldBorderClass;
    }

    if (!value || value === '' || value === null) {
        return coreRequiredHighlightClass;
    }

    return defaultFieldBorderClass;
};
const orderPeriodPreview = computed(() => {
    if (!form.order_date) {
        return {
            label: 'Появится после выбора даты',
        };
    }

    const [year, month, day] = String(form.order_date).split('-').map(Number);

    if (!year || !month || !day) {
        return {
            label: 'Некорректная дата',
        };
    }

    const lastDayOfMonth = new Date(year, month, 0).getDate();
    const startDay = day <= 15 ? 1 : 16;
    const endDay = day <= 15 ? 15 : lastDayOfMonth;
    const paddedMonth = String(month).padStart(2, '0');

    return {
        label: `${String(startDay).padStart(2, '0')}-${String(endDay).padStart(2, '0')}.${paddedMonth}.${year}`,
    };
});
const dealTypePreview = computed(() => {
    const clientPaymentForm = String(form.financial_term.client_payment_form ?? '').trim();
    const carrierPaymentForms = form.financial_term.contractors_costs
        .map((cost) => String(cost.payment_form ?? '').trim())
        .filter((value) => value !== '');

    return classifyDealType(clientPaymentForm, carrierPaymentForms, paymentFormMeta.value);
});

/** Меньше порога не фильтруем и не даём «поиск по одной букве» — только общий топ без сужения. */
const MIN_CONTRACTOR_QUERY_LENGTH = 2;

const filteredClients = computed(() => {
    const query = clientSearch.value.trim().toLowerCase();

    // Filter contractors that can be customers (type === 'customer' or type === 'both')
    const customerContractors = contractors.value.filter((contractor) => 
        contractor.type === 'customer' || contractor.type === 'both'
    );

    if (query === '' || query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        return customerContractors.slice(0, 50); // Увеличено с 8 до 50
    }

    return customerContractors
        .filter((contractor) => [contractor.name, contractor.full_name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .slice(0, 50); // Увеличено с 8 до 50
});

// Server-side search for clients
const serverSearchResults = ref([]);
const isSearchingClients = ref(false);
const searchTimer = ref(null);
const clientSearchAbortController = ref(null);
const clientSearchFetchSeq = ref(0);

// Server-side search for carriers
const serverCarrierSearchResults = ref({});
const isSearchingCarriers = ref({});
const carrierSearchTimers = ref({});
const carrierSearchAbortControllers = ref({});
const carrierSearchFetchSeq = ref({});

watch(clientSearch, (newQuery) => {
    clearTimeout(searchTimer.value);

    const trimmed = newQuery.trim();

    if (trimmed.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        clientSearchAbortController.value?.abort();
        clientSearchFetchSeq.value += 1;
        serverSearchResults.value = [];
        isSearchingClients.value = false;
        return;
    }

    searchTimer.value = setTimeout(async () => {
        await searchClients(trimmed);
    }, 550);
});

    async function searchClients(query) {
        if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
            serverSearchResults.value = [];
            return;
        }

        clientSearchAbortController.value?.abort();
        const ac = new AbortController();
        clientSearchAbortController.value = ac;
        const seq = (clientSearchFetchSeq.value += 1);

        isSearchingClients.value = true;

        try {
            const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=customer&limit=100`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                signal: ac.signal,
            });

            if (!response.ok) {
                throw new Error(`Search failed with status ${response.status}`);
            }

            const data = await response.json();
            if (seq !== clientSearchFetchSeq.value) {
                return;
            }

            serverSearchResults.value = data.contractors || [];
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('Client search error', error);
            if (seq === clientSearchFetchSeq.value) {
                serverSearchResults.value = [];
            }
        } finally {
            if (seq === clientSearchFetchSeq.value) {
                isSearchingClients.value = false;
            }
        }
    }

// Watch for carrier search input changes
watch(carrierSearch, (newSearchValues, oldSearchValues) => {
    // Find changed fields
    for (const [key, value] of Object.entries(newSearchValues)) {
        const oldValue = oldSearchValues[key] || '';
        if (value !== oldValue) {
            // Parse kind and index from key
            const match = key.match(/^(\w+)-(\d+)$/);
            if (match) {
                const [, kind, indexStr] = match;
                const index = parseInt(indexStr, 10);
                queueCarrierSearch(kind, index, value);
            }
        }
    }
}, { deep: true });

function queueCarrierSearch(kind, index, query) {
    const key = carrierSearchKey(kind, index);

    // Clear existing timer
    if (carrierSearchTimers.value[key]) {
        clearTimeout(carrierSearchTimers.value[key]);
    }

    // Clear results for empty query
    if (query.trim().length < MIN_CONTRACTOR_QUERY_LENGTH) {
        carrierSearchAbortControllers.value[key]?.abort();
        carrierSearchFetchSeq.value = {
            ...carrierSearchFetchSeq.value,
            [key]: (carrierSearchFetchSeq.value[key] ?? 0) + 1,
        };
        serverCarrierSearchResults.value = {
            ...serverCarrierSearchResults.value,
            [key]: [],
        };
        isSearchingCarriers.value = {
            ...isSearchingCarriers.value,
            [key]: false,
        };
        return;
    }

    // Set new timer
    carrierSearchTimers.value[key] = setTimeout(async () => {
        await searchCarriers(kind, index, query.trim());
    }, 550);
}

async function searchCarriers(kind, index, query) {
    if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        const keyEmpty = carrierSearchKey(kind, index);
        serverCarrierSearchResults.value = {
            ...serverCarrierSearchResults.value,
            [keyEmpty]: [],
        };
        return;
    }

    const key = carrierSearchKey(kind, index);
    carrierSearchAbortControllers.value[key]?.abort();
    const ac = new AbortController();
    carrierSearchAbortControllers.value = {
        ...carrierSearchAbortControllers.value,
        [key]: ac,
    };
    const seq = (carrierSearchFetchSeq.value[key] ?? 0) + 1;
    carrierSearchFetchSeq.value = {
        ...carrierSearchFetchSeq.value,
        [key]: seq,
    };

    isSearchingCarriers.value = {
        ...isSearchingCarriers.value,
        [key]: true,
    };

    try {
        const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=carrier&limit=100`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
            signal: ac.signal,
        });

        if (!response.ok) {
            throw new Error(`Carrier search failed with status ${response.status}`);
        }

        const data = await response.json();
        if (seq !== carrierSearchFetchSeq.value[key]) {
            return;
        }

        serverCarrierSearchResults.value = {
            ...serverCarrierSearchResults.value,
            [key]: data.contractors || [],
        };
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        console.error('Carrier search error', error);
        if (seq === carrierSearchFetchSeq.value[key]) {
            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [key]: [],
            };
        }
    } finally {
        if (seq === carrierSearchFetchSeq.value[key]) {
            isSearchingCarriers.value = {
                ...isSearchingCarriers.value,
                [key]: false,
            };
        }
    }
}

// Combined results: server search results + local preloaded results
const combinedClientResults = computed(() => {
    const query = clientSearch.value.trim().toLowerCase();
    
    if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        // Use local results for short queries
        return filteredClients.value;
    }
    
    // Combine server results with local results, removing duplicates
    const serverIds = new Set(serverSearchResults.value.map(c => c.id));
    const localResults = filteredClients.value.filter(c => !serverIds.has(c.id));
    
    return [...serverSearchResults.value, ...localResults].slice(0, 50);
});

if (selectedClient.value) {
    clientSearch.value = selectedClient.value.name;
}

function selectClient(contractor) {
    ensureContractorInLocalList(contractor);

    form.client_id = normalizeNullableNumber(contractor.id);
    clientSearch.value = contractor.name;
    showClientResults.value = false;
    applyClientDefaults(contractor);
}

function addPerformer() {
    const stage = `leg_${form.performers.length + 1}`;

    form.performers.push(blankPerformer(stage));
    syncContractorCostsFromPerformers();
    syncRoutePointsFromPerformers();
}

function setPerformerCarrierMode(legIndex, mode) {
    const performer = form.performers[legIndex];

    if (!performer || performer.carrier_mode === mode) {
        return;
    }

    if (mode === CARRIER_MODE_SPLIT) {
        const firstCarrier = performer.contractor_id
            ? {
                contractor_id: performer.contractor_id,
                contractor_name: performer.contractor_name,
                fleet_vehicle_id: performer.fleet_vehicle_id,
                fleet_driver_id: performer.fleet_driver_id,
            }
            : {};

        const stage = performer.stage;
        const singleCostRow = form.financial_term.contractors_costs.find(
            (cost) => costMatchesPerformerSlot(cost, performer, null),
        );

        performer.carrier_mode = CARRIER_MODE_SPLIT;
        const legDates = {
            loading_actual: performer.loading_actual || '',
            unloading_actual: performer.unloading_actual || '',
        };
        performer.split_carriers = [
            { ...blankSplitCarrier(1), ...firstCarrier, ...legDates },
            { ...blankSplitCarrier(2), ...legDates },
        ];
        performer.contractor_id = null;
        performer.contractor_name = null;
        performer.fleet_vehicle_id = null;
        performer.fleet_driver_id = null;
        performer.loading_actual = '';
        performer.unloading_actual = '';

        if (singleCostRow) {
            const sharedPayment = {
                payment_form: singleCostRow.payment_form,
                payment_schedule: JSON.parse(JSON.stringify(singleCostRow.payment_schedule ?? blankPaymentSchedule())),
                payment_terms: singleCostRow.payment_terms,
            };
            form.financial_term.contractors_costs = form.financial_term.contractors_costs.filter(
                (cost) => !stageMatches(cost.stage, stage),
            );
            performer.split_carriers.forEach((slot) => {
                if (!normalizeNullableNumber(slot.contractor_id)) {
                    return;
                }

                form.financial_term.contractors_costs.push(normalizeContractorCost({
                    ...sharedPayment,
                    stage,
                    carrier_slot: Number(slot.slot ?? 1),
                    contractor_id: slot.contractor_id,
                    amount: singleCostRow.amount,
                    currency: singleCostRow.currency ?? 'RUB',
                }));
            });
        }
    } else {
        const firstSlot = performer.split_carriers?.[0] ?? blankSplitCarrier(1);
        performer.carrier_mode = CARRIER_MODE_SINGLE;
        performer.contractor_id = firstSlot.contractor_id ?? null;
        performer.contractor_name = firstSlot.contractor_name ?? null;
        performer.fleet_vehicle_id = firstSlot.fleet_vehicle_id ?? null;
        performer.fleet_driver_id = firstSlot.fleet_driver_id ?? null;
        performer.loading_actual = firstSlot.loading_actual ?? '';
        performer.unloading_actual = firstSlot.unloading_actual ?? '';
        performer.split_carriers = [];
    }

    syncContractorCostsFromPerformers();
}

function addSplitCarrier(legIndex) {
    const performer = form.performers[legIndex];

    if (!performer || !isPerformerSplit(performer) || performer.split_carriers.length >= 4) {
        return;
    }

    performer.split_carriers.push(blankSplitCarrier(performer.split_carriers.length + 1));
    syncContractorCostsFromPerformers();
}

function removeSplitCarrier(legIndex, slotIndex) {
    const performer = form.performers[legIndex];

    if (!performer || !isPerformerSplit(performer) || performer.split_carriers.length <= 2) {
        return;
    }

    performer.split_carriers.splice(slotIndex, 1);
    performer.split_carriers = performer.split_carriers.map((slot, index) => ({
        ...slot,
        slot: index + 1,
    }));
    syncContractorCostsFromPerformers();
}

function parsePerformerCarrierTarget(kind, index) {
    if (kind === 'performer-slot') {
        const [legIndex, slotIndex] = String(index).split('-').map((value) => Number(value));

        return { legIndex, slotIndex, kind };
    }

    return { legIndex: Number(index), slotIndex: null, kind };
}

function splitCarrierAt(legIndex, slotIndex) {
    return form.performers[legIndex]?.split_carriers?.[slotIndex] ?? null;
}

function removePerformer(index) {
    const performer = form.performers[index];

    if (!performer) {
        return;
    }

    const removedStage = performer.stage;

    removeCarrierDocumentsForStage(removedStage);

    form.performers.splice(index, 1);
    form.route_points = form.route_points.filter((point) => !stageMatches(point.stage, removedStage));
    normalizeRoutePointSequences();

    if (form.performers.length > 0) {
        reindexLegStagesAndRemap();
    }

    if (form.performers.length <= 1) {
        form.financial_term.client_request_mode = 'single_request';
    }

    syncContractorCostsFromPerformers();
    syncCargoAllocationMatrixSlots({ pruneOrphans: true });
}

function remapStageReferences(fromStage, toStage) {
    if (stageMatches(fromStage, toStage)) {
        return;
    }

    form.route_points.forEach((point) => {
        if (stageMatches(point.stage, fromStage)) {
            point.stage = toStage;
        }
    });

    form.financial_term.contractors_costs.forEach((row) => {
        if (isAdditionalContractorCost(row)) {
            return;
        }

        if (stageMatches(row.stage, fromStage)) {
            row.stage = toStage;
        }
    });

    form.documents.forEach((doc) => {
        if (doc.party === 'carrier' && doc.stage && stageMatches(doc.stage, fromStage)) {
            doc.stage = toStage;
        }
    });
}

/**
 * После удаления плеча оставшиеся «Плечо 2» и т.д. перенумеровываются в leg_1, leg_2…
 */
function reindexLegStagesAndRemap() {
    const oldStages = form.performers.map((p) => p.stage);

    form.performers = form.performers.map((performer, i) => ({
        ...performer,
        stage: `leg_${i + 1}`,
    }));

    const newStages = form.performers.map((p) => p.stage);

    for (let i = 0; i < form.performers.length; i++) {
        if (!stageMatches(oldStages[i], newStages[i])) {
            remapStageReferences(oldStages[i], newStages[i]);
        }
    }
}

function removeCarrierDocumentsForStage(stage) {
    form.documents = form.documents.filter((doc) => {
        if (doc.party !== 'carrier' || !doc.stage) {
            return true;
        }

        return !stageMatches(doc.stage, stage);
    });
}

/**
 * Убирает плечи, для которых не осталось ни одной точки маршрута (например после удаления этапов).
 */
function pruneEmptyLegPerformers() {
    const stagesWithPoints = new Set(form.route_points.map((p) => toStageKey(p.stage)));
    const before = form.performers.length;

    const filtered = form.performers.filter((p) => stagesWithPoints.has(toStageKey(p.stage)));

    if (filtered.length === before) {
        return;
    }

    const removedStages = form.performers
        .filter((p) => !stagesWithPoints.has(toStageKey(p.stage)))
        .map((p) => p.stage);

    removedStages.forEach((stage) => removeCarrierDocumentsForStage(stage));

    form.performers = filtered;

    if (form.performers.length === 0) {
        form.performers = [blankPerformer('leg_1')];
        syncRoutePointsFromPerformers();
    } else {
        reindexLegStagesAndRemap();
    }

    if (form.performers.length <= 1) {
        form.financial_term.client_request_mode = 'single_request';
    }

    syncContractorCostsFromPerformers();
}

function onRoutePointLegChanged() {
    nextTick(() => {
        pruneEmptyLegPerformers();
    });
}

function getContractorById(contractorId) {
    return contractors.value.find((contractor) => Number(contractor.id) === Number(contractorId)) ?? null;
}

/**
 * Серверный поиск возвращает контрагента, которого может не быть в props.contractors.
 * Без записи в локальный список getContractorById (blur, watch) обнуляет подпись в поле перевозчика.
 */
function ensureContractorInLocalList(contractor) {
    if (!contractor?.id) {
        return;
    }

    const id = Number(contractor.id);
    if (contractors.value.some((c) => Number(c.id) === id)) {
        return;
    }

    contractors.value.unshift({ ...contractor });
}

/**
 * Подпись в поле поиска перевозчика: справочник в props может быть укороченным, а contractor_id при этом валиден.
 */
function performerCarrierSearchLabel(performerIndex, contractorId) {
    const row = form.performers[performerIndex];
    if (isOwnFleetExecutionMode(row?.execution_mode)) {
        return OWN_FLEET_CONTRACTOR_NAME;
    }

    const id = normalizeNullableNumber(contractorId);
    if (id === null) {
        return '';
    }

    const contractor = getContractorById(id);
    const fromLookup = contractor?.name ? String(contractor.name).trim() : '';
    if (fromLookup) {
        return fromLookup;
    }

    const fromRow = row?.contractor_name ? String(row.contractor_name).trim() : '';

    return fromRow || '';
}

function carrierSearchKey(kind, index) {
    return `${kind}-${index}`;
}

function carrierSearchValue(kind, index) {
    return carrierSearch.value[carrierSearchKey(kind, index)] ?? '';
}

function setCarrierSearchValue(kind, index, value) {
    carrierSearch.value = {
        ...carrierSearch.value,
        [carrierSearchKey(kind, index)]: value,
    };
}

function setCarrierResultsVisible(kind, index, visible) {
    showCarrierResults.value = {
        ...showCarrierResults.value,
        [carrierSearchKey(kind, index)]: visible,
    };
}

function isCarrierResultsVisible(kind, index) {
    return Boolean(showCarrierResults.value[carrierSearchKey(kind, index)]);
}

function filteredCarrierResults(kind, index) {
    const query = carrierSearchValue(kind, index).trim().toLowerCase();
    let selectedContractorId = null;

    if (kind === 'performer-slot') {
        const target = parsePerformerCarrierTarget(kind, index);
        selectedContractorId = normalizeNullableNumber(splitCarrierAt(target.legIndex, target.slotIndex)?.contractor_id);
    } else if (kind === 'performer') {
        selectedContractorId = normalizeNullableNumber(form.performers[index]?.contractor_id);
    } else {
        selectedContractorId = normalizeNullableNumber(form.financial_term.contractors_costs[index]?.contractor_id);
    }

    const selectedContractor = getContractorById(selectedContractorId);

    const excludeVirtualOwnFleet = (contractors) => {
        if (!props.ownFleetContractor?.id) {
            return contractors;
        }

        const ownFleetId = Number(props.ownFleetContractor.id);

        return contractors.filter(
            (contractor) => contractor.id !== ownFleetId && !isVirtualOwnFleetContractor(contractor),
        );
    };

    // Get server search results for this specific field
    const serverResults = serverCarrierSearchResults.value[carrierSearchKey(kind, index)] || [];
    const serverIds = new Set(serverResults.map(c => c.id));

    if (query === '' || query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        const visibleContractors = excludeVirtualOwnFleet(carrierOptions.value.slice(0, 50));

        if (!selectedContractor || visibleContractors.some((contractor) => contractor.id === selectedContractor.id)) {
            return visibleContractors;
        }

        if (isVirtualOwnFleetContractor(selectedContractor)) {
            return visibleContractors;
        }

        return [selectedContractor, ...visibleContractors.slice(0, 49)];
    }

    // Combine server results with local results
    const localResults = carrierOptions.value
        .filter((contractor) => [contractor.name, contractor.full_name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .filter(c => !serverIds.has(c.id));

    return excludeVirtualOwnFleet([...serverResults, ...localResults].slice(0, 50));
}

function parsePaymentTermPreset(term) {
    if (!term) {
        return blankPaymentSchedule();
    }

    const normalized = String(term).trim().toUpperCase();
    const prepaymentPercentMatch = normalized.match(/^(\d{1,2})%\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)\s*\/\s*(\d{1,2})%\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)$/u);

    if (prepaymentPercentMatch) {
        return normalizePaymentSchedule({
            has_prepayment: true,
            prepayment_ratio: Number(prepaymentPercentMatch[1]),
            prepayment_days: Number(prepaymentPercentMatch[2]),
            prepayment_mode: prepaymentPercentMatch[3].toLowerCase(),
            postpayment_days: Number(prepaymentPercentMatch[5]),
            postpayment_mode: prepaymentPercentMatch[6].toLowerCase(),
        });
    }

    const prepaymentMatch = normalized.match(/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)\s*\/\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)$/u);

    if (prepaymentMatch) {
        return normalizePaymentSchedule({
            has_prepayment: true,
            prepayment_ratio: Number(prepaymentMatch[1]),
            prepayment_days: Number(prepaymentMatch[3]),
            prepayment_mode: prepaymentMatch[4].toLowerCase(),
            postpayment_days: Number(prepaymentMatch[5]),
            postpayment_mode: prepaymentMatch[6].toLowerCase(),
        });
    }

    const postpaymentMatch = normalized.match(/^(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)$/u);

    if (postpaymentMatch) {
        return normalizePaymentSchedule({
            has_prepayment: false,
            postpayment_days: Number(postpaymentMatch[1]),
            postpayment_mode: postpaymentMatch[2].toLowerCase(),
        });
    }

    return blankPaymentSchedule();
}

function contractorPaymentSchedule(contractor, scheduleField, legacyField) {
    if (contractor?.[scheduleField]) {
        return normalizePaymentSchedule(contractor[scheduleField]);
    }

    if (contractor?.[legacyField]) {
        return parsePaymentTermPreset(contractor[legacyField]);
    }

    return blankPaymentSchedule();
}

function contractorNormsDefaults(contractor, field) {
    if (!contractor?.[field] || !hasNormsPenaltiesContent(contractor[field])) {
        return null;
    }

    return normalizePartyNormsPenalties(contractor[field]);
}

function applyClientDefaults(contractor) {
    if (!contractor) {
        return;
    }

    if (contractor.default_customer_payment_form) {
        form.financial_term.client_payment_form = normalizePaymentFormCode(contractor.default_customer_payment_form, defaultClientPaymentForm());
    }

    form.financial_term.client_payment_schedule = contractorPaymentSchedule(contractor, 'default_customer_payment_schedule', 'default_customer_payment_term');

    const clientNorms = contractorNormsDefaults(contractor, 'default_customer_norms_penalties');
    if (clientNorms) {
        form.financial_term.client_norms_penalties = clientNorms;
    }

    if (contractor.cooperation_terms_notes && !String(form.special_notes || '').trim()) {
        form.special_notes = contractor.cooperation_terms_notes;
    }
}

let syncCarrierNormsByLegFromPerformers = () => {};
let syncContractorCostsFromPerformers = () => {};

function applyCarrierNormsDefaultsByStage(stage, contractorId) {
    const norms = contractorNormsDefaults(getContractorById(contractorId), 'default_carrier_norms_penalties');
    if (!norms) {
        return;
    }

    syncCarrierNormsByLegFromPerformers();

    const idx = form.financial_term.carrier_norms_by_leg.findIndex((row) => stageMatches(row.stage, stage));
    if (idx >= 0) {
        form.financial_term.carrier_norms_by_leg[idx] = normalizePartyNormsPenaltiesWithStage({
            ...norms,
            stage: form.financial_term.carrier_norms_by_leg[idx].stage,
        });
    }
}

function applyCarrierDefaultsByStage(stage, contractorId, carrierSlot = null) {
    const contractor = getContractorById(contractorId);

    if (!contractor) {
        return;
    }

    const costRow = form.financial_term.contractors_costs.find((row) => {
        if (!stageMatches(row.stage, stage)) {
            return false;
        }

        if (carrierSlot == null) {
            return row.carrier_slot == null || row.carrier_slot === '';
        }

        return Number(row.carrier_slot) === Number(carrierSlot);
    });

    if (!costRow) {
        return;
    }

    if (contractor.default_carrier_payment_form) {
        costRow.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
    }

    costRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');

    applyCarrierNormsDefaultsByStage(stage, contractorId);
}

function splitCarrierSearchLabel(legIndex, slotIndex, contractorId) {
    const slot = splitCarrierAt(legIndex, slotIndex);
    if (isOwnFleetExecutionMode(slot?.execution_mode)) {
        return OWN_FLEET_CONTRACTOR_NAME;
    }

    const id = normalizeNullableNumber(contractorId);
    if (id === null) {
        return '';
    }

    const contractor = getContractorById(id);
    const fromLookup = contractor?.name ? String(contractor.name).trim() : '';
    if (fromLookup) {
        return fromLookup;
    }

    const fromRow = slot?.contractor_name ? String(slot.contractor_name).trim() : '';

    return fromRow || '';
}

function selectOwnFleetPerformer(index) {
    const ownFleet = props.ownFleetContractor;
    if (!ownFleet?.id) {
        return;
    }

    ensureContractorInLocalList({
        ...ownFleet,
        type: 'carrier',
    });

    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: Number(ownFleet.id),
        contractor_name: ownFleet.name ? String(ownFleet.name).trim() || null : null,
        execution_mode: EXECUTION_MODE_OWN_FLEET,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, OWN_FLEET_CONTRACTOR_NAME);
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    loadFleetOptionsForLeg(index);
}

function selectOwnFleetSplitSlot(legIndex, slotIndex) {
    const ownFleet = props.ownFleetContractor;
    if (!ownFleet?.id) {
        return;
    }

    ensureContractorInLocalList({
        ...ownFleet,
        type: 'carrier',
    });

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = Number(ownFleet.id);
    slot.contractor_name = ownFleet.name ? String(ownFleet.name).trim() || null : null;
    slot.execution_mode = EXECUTION_MODE_OWN_FLEET;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, OWN_FLEET_CONTRACTOR_NAME);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    loadFleetOptionsForLeg(legIndex, slotIndex);
}

function selectSplitPerformerContractor(legIndex, slotIndex, contractor) {
    if (isVirtualOwnFleetContractor(contractor)) {
        selectOwnFleetSplitSlot(legIndex, slotIndex);

        return;
    }

    ensureContractorInLocalList(contractor);

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = Number(contractor.id);
    slot.contractor_name = contractor.name ? String(contractor.name).trim() || null : null;
    slot.execution_mode = null;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, contractor.name);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[legIndex].stage, contractor.id, slot.slot);
    loadFleetOptionsForLeg(legIndex, slotIndex);
}

function clearSplitPerformerContractor(legIndex, slotIndex) {
    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = null;
    slot.contractor_name = null;
    slot.execution_mode = null;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, '');
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    fleetOptionsCache.value = {
        ...fleetOptionsCache.value,
        [performerFleetCacheKey(legIndex, slotIndex)]: { vehicles: [], drivers: [] },
    };
}

function onSplitPerformerCarrierInput(legIndex, slotIndex, value) {
    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, value);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, true);

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    const typed = String(value ?? '').trim().toLowerCase();
    const selectedContractor = getContractorById(slot.contractor_id);
    const selectedName = String(selectedContractor?.name ?? slot.contractor_name ?? '').trim().toLowerCase();

    if (typed === '') {
        clearSplitPerformerContractor(legIndex, slotIndex);

        return;
    }

    if (normalizeNullableNumber(slot.contractor_id) !== null && selectedName !== '' && selectedName !== typed) {
        slot.contractor_id = null;
        slot.contractor_name = null;
        slot.execution_mode = null;
        slot.fleet_vehicle_id = null;
        slot.fleet_driver_id = null;
        syncContractorCostsFromPerformers();
    }
}

function restoreSplitPerformerCarrierSearch(legIndex, slotIndex) {
    window.setTimeout(() => {
        const slot = splitCarrierAt(legIndex, slotIndex);
        if (!slot) {
            return;
        }

        setCarrierSearchValue(
            'performer-slot',
            `${legIndex}-${slotIndex}`,
            splitCarrierSearchLabel(legIndex, slotIndex, slot.contractor_id),
        );
        setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    }, 120);
}

function selectPerformerContractor(index, contractor) {
    if (isVirtualOwnFleetContractor(contractor)) {
        selectOwnFleetPerformer(index);

        return;
    }

    ensureContractorInLocalList(contractor);

    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: Number(contractor.id),
        contractor_name: contractor.name ? String(contractor.name).trim() || null : null,
        execution_mode: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, contractor.name);
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[index].stage, contractor.id);
    loadFleetOptionsForLeg(index);
}

function clearPerformerContractor(index) {
    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: null,
        contractor_name: null,
        execution_mode: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, '');
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    fleetOptionsCache.value = { ...fleetOptionsCache.value, [index]: { vehicles: [], drivers: [] } };
}

function syncPerformerContractor(stage, contractorId) {
    const performer = form.performers.find((item) => stageMatches(item.stage, stage));

    if (!performer) {
        return;
    }

    performer.contractor_id = contractorId !== null ? Number(contractorId) : null;
    performer.contractor_name = null;
}

function onPerformerCarrierInput(index, value) {
    setCarrierSearchValue('performer', index, value);
    setCarrierResultsVisible('performer', index, true);

    const performer = form.performers[index];
    if (!performer) {
        return;
    }

    const typed = String(value ?? '').trim().toLowerCase();
    const selectedContractor = getContractorById(performer.contractor_id);
    const selectedName = String(selectedContractor?.name ?? performer.contractor_name ?? '').trim().toLowerCase();

    if (typed === '') {
        clearPerformerContractor(index);

        return;
    }

    if (normalizeNullableNumber(performer.contractor_id) !== null && selectedName !== '' && selectedName !== typed) {
        performer.contractor_id = null;
        performer.contractor_name = null;
        performer.execution_mode = null;
        performer.fleet_vehicle_id = null;
        performer.fleet_driver_id = null;
        syncContractorCostsFromPerformers();
    }
}

function restorePerformerCarrierSearch(index) {
    window.setTimeout(() => {
        const performer = form.performers[index];
        if (!performer) {
            return;
        }

        setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, performer.contractor_id));
        setCarrierResultsVisible('performer', index, false);
    }, 120);
}

async function loadFleetOptionsForLeg(legIndex, slotIndex = null) {
    const cacheKey = performerFleetCacheKey(legIndex, slotIndex);
    let contractorId = null;

    if (slotIndex !== null) {
        contractorId = normalizeNullableNumber(splitCarrierAt(legIndex, slotIndex)?.contractor_id);
    } else {
        contractorId = normalizeNullableNumber(form.performers[legIndex]?.contractor_id);
    }

    if (!contractorId) {
        fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles: [], drivers: [] } };

        return;
    }

    const requestOptions = {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'include',
    };

    const loadVehicles = fetch(`${route('fleet.options.vehicles')}?owner_contractor_id=${contractorId}`, requestOptions)
        .then(async (response) => {
            if (!response.ok) {
                return [];
            }

            const payload = await response.json();

            return Array.isArray(payload?.vehicles) ? payload.vehicles : [];
        })
        .catch(() => []);

    const loadDrivers = fetch(`${route('fleet.options.drivers')}?carrier_contractor_id=${contractorId}`, requestOptions)
        .then(async (response) => {
            if (!response.ok) {
                return [];
            }

            const payload = await response.json();

            return Array.isArray(payload?.drivers) ? payload.drivers : [];
        })
        .catch(() => []);

    const [vehicles, drivers] = await Promise.all([loadVehicles, loadDrivers]);
    const existing = fleetOptionsCache.value[cacheKey] ?? { vehicles: [], drivers: [] };
    const mergedVehicles = [...vehicles];
    const mergedDrivers = [...drivers];

    for (const item of existing.vehicles) {
        if (!mergedVehicles.some((entry) => Number(entry.id) === Number(item.id))) {
            mergedVehicles.push(item);
        }
    }

    for (const item of existing.drivers) {
        if (!mergedDrivers.some((entry) => Number(entry.id) === Number(item.id))) {
            mergedDrivers.push(item);
        }
    }

    fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles: mergedVehicles, drivers: mergedDrivers } };
}

function seedFleetOptionsFromPerformer(legIndex, slotIndex = null) {
    const cacheKey = performerFleetCacheKey(legIndex, slotIndex);
    const target = slotIndex !== null ? splitCarrierAt(legIndex, slotIndex) : form.performers[legIndex];

    if (!target) {
        return;
    }

    const vehicleId = normalizeNullableNumber(target.fleet_vehicle_id);
    const driverId = normalizeNullableNumber(target.fleet_driver_id);

    if (vehicleId === null && driverId === null) {
        return;
    }

    const existing = fleetOptionsCache.value[cacheKey] ?? { vehicles: [], drivers: [] };
    const vehicles = [...existing.vehicles];
    const drivers = [...existing.drivers];

    if (vehicleId !== null && target.fleet_vehicle_label && !vehicles.some((entry) => Number(entry.id) === vehicleId)) {
        vehicles.push({ id: vehicleId, label: target.fleet_vehicle_label });
    }

    if (driverId !== null && target.fleet_driver_label && !drivers.some((entry) => Number(entry.id) === driverId)) {
        drivers.push({ id: driverId, label: target.fleet_driver_label });
    }

    fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles, drivers } };
}

function preloadFleetOptionsForPerformers() {
    form.performers.forEach((performer, legIndex) => {
        if (isPerformerSplit(performer)) {
            performer.split_carriers.forEach((slot, slotIndex) => {
                seedFleetOptionsFromPerformer(legIndex, slotIndex);

                if (normalizeNullableNumber(slot.contractor_id) !== null) {
                    loadFleetOptionsForLeg(legIndex, slotIndex);
                }
            });

            return;
        }

        seedFleetOptionsFromPerformer(legIndex);

        if (normalizeNullableNumber(performer.contractor_id) !== null) {
            loadFleetOptionsForLeg(legIndex);
        }
    });
}

function fleetVehicleOptionsForLeg(legIndex, slotIndex = null) {
    return fleetOptionsCache.value[performerFleetCacheKey(legIndex, slotIndex)]?.vehicles ?? [];
}

function fleetDriverOptionsForLeg(legIndex, slotIndex = null) {
    return fleetOptionsCache.value[performerFleetCacheKey(legIndex, slotIndex)]?.drivers ?? [];
}

const routeChainLabel = computed(() => {
    if (form.route_points.length === 0) {
        return 'Маршрут пока не задан';
    }

    return form.route_points
        .slice()
        .sort((left, right) => Number(left.sequence ?? 0) - Number(right.sequence ?? 0))
        .map((point) => {
            if (point.type === 'border_crossing') {
                const parts = [
                    String(form.customs_post_code ?? '').trim(),
                    String(form.svh_name ?? '').trim(),
                    String(form.svh_address ?? '').trim(),
                ].filter((s) => s !== '');
                const label = parts.length > 0 ? parts.join(' · ') : 'СВХ / пост не указаны';

                return `${routePointTypeHeading(point.type)}: ${label}`;
            }

            return `${routePointTypeHeading(point.type)}: ${point.address || 'адрес не указан'}`;
        })
        .join(' → ');
});

const hasBorderCrossingPoint = computed(
    () => Array.isArray(form.route_points) && form.route_points.some((p) => p.type === 'border_crossing'),
);

const financeTab = useOrderWizardFinanceTab({
    form,
    props,
    isEditing,
    contractors,
    getContractorById,
    ensureContractorInLocalList,
    normalizeContractorCost,
    normalizePaymentFormCode,
    contractorPaymentSchedule,
    applyCarrierNormsDefaultsByStage,
    isPerformerSplit,
    isAdditionalContractorCost,
    costMatchesPerformerSlot,
    blankAdditionalCostRow,
    MIN_CONTRACTOR_QUERY_LENGTH,
    additionalExpenseAmountFieldClass,
    stageMatches,
    stageLabel,
    toStageKey,
    orderPs,
    normalizeNullableNumber,
    highlightRequiredField,
    crmFieldFluid,
    paymentFormOptions,
});

const {
    tabContext: financeTabContext,
    canEditFinancialFields,
    legContractorCosts,
    syncContractorCostsFromPerformers: syncContractorCostsFromPerformersImpl,
    syncCarrierNormsByLegFromPerformers: syncCarrierNormsByLegFromPerformersImpl,
} = financeTab;

syncContractorCostsFromPerformers = syncContractorCostsFromPerformersImpl;
syncCarrierNormsByLegFromPerformers = syncCarrierNormsByLegFromPerformersImpl;

/** Ошибки валидации по документам и полю order_payload (вкладка «Документы»). */
const documentTabValidationMessages = computed(() => {
    return Object.entries(form.errors)
        .filter(([key]) => key === 'order_payload' || key.startsWith('documents'))
        .map(([, value]) => (Array.isArray(value) ? value.join(' ') : String(value)));
});

const normsPenaltiesTabValidationMessages = computed(() => {
    return Object.entries(form.errors)
        .filter(([key]) => key.startsWith('financial_term.client_norms_penalties')
            || key.startsWith('financial_term.carrier_norms_by_leg'))
        .map(([, value]) => (Array.isArray(value) ? value.join(' ') : String(value)));
});

const hasUnsavedDocumentFiles = computed(() => form.documents.some((d) => d.file instanceof File));

const financialSummary = computed(() => {
    const clientPrice = Number(form.financial_term.client_price || 0);
    const contractorCosts = legContractorCosts.value.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const additionalExpenses = sumAdditionalCostsAmount(form.financial_term.additional_costs);
    const insurance = Number(form.insurance || 0);
    const rawBonus = Number(form.bonus || 0);
    const kpiPercent = Number(form.financial_term.kpi_percent || 0);
    const serverDelta = Number(calculatedCompensation.value?.delta ?? NaN);
    const hasReliableServerDelta = Number.isFinite(serverDelta) && calculatedCompensation.value?.deal_type !== 'unknown';

    let effectiveBonusCost = rawBonus;
    let margin;

    if (hasReliableServerDelta) {
        const baseAfterKpi = clientPrice * (1 - (kpiPercent / 100));
        const computedBonusCost = baseAfterKpi - contractorCosts - additionalExpenses - insurance - serverDelta;
        effectiveBonusCost = Math.max(0, computedBonusCost);
        margin = serverDelta;
    } else {
        margin = clientPrice * (1 - (kpiPercent / 100)) - (contractorCosts + additionalExpenses + insurance + rawBonus);
    }

    const additionalCosts = additionalExpenses + insurance + effectiveBonusCost;
    const totalCost = contractorCosts + additionalCosts;

    return {
        clientPrice,
        contractorCosts,
        additionalCosts,
        totalCost,
        margin,
    };
});

const orderPaymentSettlement = computed(() => props.order?.payment_settlement ?? null);

const paymentSettlementLines = computed(() => {
    const lines = orderPaymentSettlement.value?.lines;

    return Array.isArray(lines) ? lines : [];
});

const showPaymentSettlementBlock = computed(() => paymentSettlementLines.value.length > 0);

function formatRuDate(isoDate) {
    if (!isoDate) {
        return '';
    }
    const parsed = new Date(`${isoDate}T12:00:00`);
    if (Number.isNaN(parsed.getTime())) {
        return String(isoDate);
    }

    return parsed.toLocaleDateString('ru-RU');
}

function paymentSettlementLineTitle(line) {
    if (!line) {
        return '';
    }

    if (line.party === 'customer') {
        return line.state === 'complete'
            ? 'Клиент рассчитался с нами'
            : 'Оплата от клиента';
    }

    const name = String(line.counterparty_name ?? '').trim();
    const counterpartyLabel = line.party === 'contractor' ? 'подрядчиком' : 'перевозчиком';
    const paymentLabel = line.party === 'contractor' ? 'подрядчику' : 'перевозчику';
    if (line.state === 'complete') {
        return name !== '' ? `Мы рассчитались с ${name}` : `Мы рассчитались с ${counterpartyLabel}`;
    }

    return name !== '' ? `Оплата ${paymentLabel} (${name})` : `Оплата ${paymentLabel}`;
}

function paymentSettlementLineValue(line) {
    if (!line?.has_rows) {
        return '—';
    }

    const dateLabel = line.last_payment_at ? formatRuDate(line.last_payment_at) : '';

    if (line.state === 'complete') {
        return dateLabel || 'да';
    }

    if (line.state === 'partial') {
        const percent = Math.round(Number(line.percent_paid ?? 0));
        if (dateLabel) {
            return `Оплачено ${percent}%. ${dateLabel}`;
        }

        return `Оплачено ${percent}%`;
    }

    return 'не было';
}

const effectiveRequiredDocumentRules = computed(() => buildDocumentRequirementRules(
    form.performers,
    form.financial_term.client_request_mode,
    form.financial_term.additional_costs,
));

const documentChecklist = computed(() => {
    const rules = effectiveRequiredDocumentRules.value;
    const documents = Array.isArray(form.documents) ? form.documents : [];
    const usedIds = new Set();

    return rules.map((rule) => {
        const matchedDocument = documents.find((document) => {
            if (document?.id && usedIds.has(document.id)) {
                return false;
            }

            const status = String(document.status ?? '');

            if (!['sent', 'signed'].includes(status)) {
                return false;
            }

            return documentMatchesRequirementRule(document, rule);
        });

        if (matchedDocument?.id && !rule.allows_multiple) {
            usedIds.add(matchedDocument.id);
        }

        return {
            ...rule,
            completed: matchedDocument !== undefined,
            matched_document_id: matchedDocument?.id ?? null,
        };
    });
});

const customerDocuments = computed(() => {
    return form.documents
        .map((document, index) => ({ document, index }))
        .filter((item) => item.document.party === 'customer');
});

function carrierDocumentsForStage(stage) {
    return form.documents
        .map((document, index) => ({ document, index }))
        .filter((item) => item.document.party === 'carrier' && stageMatches(item.document.stage, stage));
}

function addRoutePoint(type) {
    form.route_points.push(blankRoutePoint(
        type,
        form.route_points.length + 1,
        form.performers[0]?.stage ?? 'leg_1',
    ));
}

function addRoutePointForLeg(stage, type) {
    const stagePoints = form.route_points
        .map((p, i) => ({ p, i }))
        .filter(({ p }) => stageMatches(p.stage, stage));
    let insertAt = form.route_points.length;
    if (type === 'border_crossing') {
        const firstUnload = stagePoints.find(({ p }) => p.type === 'unloading');
        if (firstUnload) {
            insertAt = firstUnload.i;
        } else if (stagePoints.length > 0) {
            insertAt = stagePoints[stagePoints.length - 1].i + 1;
        }
    } else if (stagePoints.length > 0) {
        insertAt = stagePoints[stagePoints.length - 1].i + 1;
    }
    form.route_points.splice(insertAt, 0, blankRoutePoint(type, 0, stage));
    normalizeRoutePointSequences();
}

const routePointInlineBtn =
    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';

function canRemoveRoutePoint(globalIndex) {
    const point = form.route_points[globalIndex];
    if (!point) {
        return false;
    }

    if (point.type === 'border_crossing') {
        return true;
    }

    const sameTypeOnLeg = form.route_points.filter(
        (candidate) => stageMatches(candidate.stage, point.stage) && candidate.type === point.type,
    ).length;

    return sameTypeOnLeg > 1;
}

function addRoutePointAfter(globalIndex) {
    const point = form.route_points[globalIndex];
    if (!point || point.type === 'border_crossing') {
        return;
    }

    form.route_points.splice(globalIndex + 1, 0, blankRoutePoint(point.type, 0, point.stage));
    normalizeRoutePointSequences();
}

function removeRoutePointAt(globalIndex) {
    if (!canRemoveRoutePoint(globalIndex)) {
        return;
    }

    removeItem(form.route_points, globalIndex);
}

function onBorderCrossingLegPickerChange() {
    const raw = borderCrossingLegPicker.value;
    if (raw === '' || raw === null || raw === undefined) {
        return;
    }
    const idx = Number.parseInt(String(raw), 10);
    if (!Number.isFinite(idx) || idx < 0) {
        borderCrossingLegPicker.value = '';

        return;
    }
    const performer = form.performers[idx];
    if (!performer) {
        borderCrossingLegPicker.value = '';

        return;
    }
    addRoutePointForLeg(performer.stage, 'border_crossing');
    borderCrossingLegPicker.value = '';
}

function routePointTypeHeading(type) {
    if (type === 'loading') {
        return 'Погрузка';
    }
    if (type === 'border_crossing') {
        return 'Граница';
    }

    return 'Выгрузка';
}

function routePointTimeBlockHeading(type) {
    if (type === 'loading') {
        return 'Время загрузки';
    }
    if (type === 'border_crossing') {
        return 'Окно (план)';
    }

    return 'Время выгрузки';
}

function routePointAddressHighlightValue(point) {
    if (point.type === 'border_crossing') {
        return String(point.address ?? '').trim() || String(point.planned_date ?? '').trim();
    }

    return point.address;
}

function normalizeRoutePointSequences() {
    form.route_points = form.route_points.map((point, index) => ({
        ...point,
        sequence: index + 1,
    }));
}

function syncRoutePointsFromPerformers() {
    const performerStages = form.performers.map((performer) => performer.stage);

    if (performerStages.length === 0) {
        form.route_points = [];

        return;
    }

    const existingPoints = Array.isArray(form.route_points)
        ? form.route_points.map((point, index) => ({
            ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), point.stage ?? performerStages[0]),
            ...point,
            stage: point.stage ?? performerStages[0],
        }))
        : [];

    const nextPoints = [];

    performerStages.forEach((stage) => {
        const stagePoints = existingPoints.filter((point) => stageMatches(point.stage, stage));
        const normalizedStagePoints = stagePoints.map((point) => ({
            ...point,
            stage,
        }));

        if (!normalizedStagePoints.some((point) => point.type === 'loading')) {
            normalizedStagePoints.unshift(blankRoutePoint('loading', 0, stage));
        }

        if (!normalizedStagePoints.some((point) => point.type === 'unloading')) {
            normalizedStagePoints.push(blankRoutePoint('unloading', 0, stage));
        }

        nextPoints.push(...normalizedStagePoints);
    });

    form.route_points = nextPoints.map((point, index) => ({
        ...point,
        sequence: index + 1,
    }));
}

function routePointOrdinal(index) {
    const currentPoint = form.route_points[index];

    return form.route_points
        .slice(0, index + 1)
        .filter((point) => point.type === currentPoint?.type)
        .length;
}

function routePointTitle(point, index) {
    const ordinal = routePointOrdinal(index);

    if (point.type === 'loading') {
        return `Погрузка ${ordinal}`;
    }
    if (point.type === 'border_crossing') {
        return `Прохождение границы ${ordinal}`;
    }

    return `Выгрузка ${ordinal}`;
}

function routePointCombinedContact(point) {
    if (point.type === 'loading') {
        return point.sender_contact || point.sender_phone || point.contact_person || point.contact_phone || '';
    }

    if (point.type === 'unloading') {
        return point.recipient_contact || point.recipient_phone || point.contact_person || point.contact_phone || '';
    }

    return point.contact_person || point.contact_phone || '';
}

function setRoutePointCombinedContact(point, value) {
    const normalizedValue = String(value ?? '').trim();
    point.contact_person = normalizedValue;
    point.contact_phone = '';

    if (point.type === 'loading') {
        point.sender_contact = normalizedValue;
        point.sender_phone = '';
    }

    if (point.type === 'unloading') {
        point.recipient_contact = normalizedValue;
        point.recipient_phone = '';
    }
}

/**
 * @return {Array<{ point: object, globalIndex: number }>}
 */
function routePointsWithIndicesForLeg(stage) {
    const result = [];

    form.route_points.forEach((point, globalIndex) => {
        if (stageMatches(point.stage, stage)) {
            result.push({ point, globalIndex });
        }
    });

    return result.sort((left, right) => Number(left.point.sequence ?? 0) - Number(right.point.sequence ?? 0));
}

function routePointsDragEnabled() {
    return form.performers.length <= 1;
}

function handleRoutePointDragStart(index, event) {
    if (!routePointsDragEnabled()) {
        return;
    }

    draggedRoutePointIndex.value = index;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
}

function handleRoutePointDragOver(index) {
    if (!routePointsDragEnabled()) {
        return;
    }

    if (draggedRoutePointIndex.value === null || draggedRoutePointIndex.value === index) {
        return;
    }

    dragOverRoutePointIndex.value = index;
}

function handleRoutePointDrop(targetIndex) {
    if (!routePointsDragEnabled()) {
        return;
    }

    const sourceIndex = draggedRoutePointIndex.value;

    if (sourceIndex === null || sourceIndex === targetIndex) {
        return;
    }

    const nextPoints = [...form.route_points];
    const [movedPoint] = nextPoints.splice(sourceIndex, 1);
    nextPoints.splice(targetIndex, 0, movedPoint);
    form.route_points = nextPoints;
    normalizeRoutePointSequences();
    draggedRoutePointIndex.value = null;
    dragOverRoutePointIndex.value = null;
}

function handleRoutePointDragEnd() {
    if (!routePointsDragEnabled()) {
        return;
    }

    draggedRoutePointIndex.value = null;
    dragOverRoutePointIndex.value = null;
}

function addDocumentFor(party, stage = null, overrides = {}) {
    form.documents.push(normalizeDocument({
        party,
        stage,
        ...overrides,
    }));
}

function removeDocumentAt(index) {
    form.documents.splice(index, 1);
}

function removeItem(collection, index) {
    collection.splice(index, 1);

    if (collection === form.route_points) {
        normalizeRoutePointSequences();
        pruneEmptyLegPerformers();
    }
}

const cargoTab = useOrderWizardCargoTab({
    form,
    props,
    getContractorById,
    highlightRequiredField,
    crmFieldFluid,
    removeItem,
    cargoAllocationFieldClass,
    normalizeNullableNumber,
});

const {
    tabContext: cargoTabContext,
    initCargoTabSideEffects,
    serializeCargoItemsForSubmit,
    selectedLoadingTypeCodes,
    syncCargoAllocationMatrixSlots,
    cargoPerformerAllocationColumns,
    needsCargoPerformerAllocationUi,
} = cargoTab;

initCargoTabSideEffects();

const routeTab = useOrderWizardRouteTab({
    form,
    props,
    contractors,
    carrierOptions,
    normalizeNullableNumber,
    blankRoutePoint,
    highlightRequiredField,
    openCounterpartyModal,
    onPerformerActualDateInput,
    onSplitActualDateInput,
    syncContractorCostsFromPerformers,
    syncCargoAllocationMatrixSlots,
    getContractorById,
    ensureContractorInLocalList,
    applyCarrierDefaultsByStage,
    removeCarrierDocumentsForStage,
    normalizeContractorCost,
    normalizePaymentSchedule,
    blankPaymentSchedule,
    costMatchesPerformerSlot,
    MIN_CONTRACTOR_QUERY_LENGTH,
    stageLabel,
    stageMatches,
    toStageKey,
    crmFieldFluid,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
    borderCrossingLegPicker,
    carrierSearch,
    showCarrierResults,
    serverCarrierSearchResults,
    isSearchingCarriers,
    carrierSearchTimers,
    carrierSearchAbortControllers,
    carrierSearchFetchSeq,
    fleetOptionsCache,
    addressSuggestions,
    addressTimers,
    draggedRoutePointIndex,
    dragOverRoutePointIndex,
});

routeTab.initRouteTabSideEffects();

provide(ORDER_WIZARD_ROUTE_TAB_KEY, routeTab);

const {
    intakeLoading,
    intakeSelectedFile,
    intakePreview,
    intakeError,
    activeIntakeDraftId,
    intakeDraftCommitted,
    onIntakeFileSelected,
    extractIntakeDraft,
    applyIntakeDraft,
    loadAndApplyIntakeDraftById,
    discardActiveIntakeLearning,
} = useOrderWizardIntake({
    form,
    isEditing,
    activeTab,
    blankRoutePoint,
    blankOrder,
    normalizeRoutePointSequences,
    normalizeCargoItem,
    normalizeContractorCost,
    normalizePaymentSchedule,
    mergeFinancialTermFromIntake,
    syncContractorCostsFromPerformers,
    getContractorById,
    stageMatches,
    toStageKey,
    setCarrierSearchValue,
});

const mainTabContext = {
    form,
    order: props.order,
    isEditing,
    isOrderFormEditable,
    canAssignResponsible: props.canAssignResponsible,
    ownCompanyOptions,
    showOwnCompanyBankAccountPicker,
    selectableOwnCompanyBankAccounts,
    ownCompanyBankAccountLabel,
    clientSearch,
    showClientResults,
    combinedClientResults,
    isSearchingClients,
    serverSearchResults,
    customerDebtBlocked,
    selectedClient,
    highlightRequiredField,
    openCounterpartyModal,
    selectClient,
    onOrderNumberManualInput,
    suggestedOrderNumberCipher,
    responsibleUsers: props.responsibleUsers,
    onCompensationOwnerPercentInput,
    onCompensationDispatcherPercentInput,
    clientRequestModeOptions,
    financialSummary,
    showPaymentSettlementBlock,
    paymentSettlementLines,
    paymentSettlementLineTitle,
    paymentSettlementLineValue,
};

provide(ORDER_WIZARD_MAIN_TAB_KEY, mainTabContext);

provide(ORDER_WIZARD_CARGO_TAB_KEY, cargoTabContext);

provide(ORDER_WIZARD_FINANCE_TAB_KEY, financeTabContext);

applyCounterpartyContractor = (target, contractor) => {
    if (target.kind === 'performer-slot' && target.index !== null) {
        const parsed = routeTab.parsePerformerCarrierTarget('performer-slot', target.index);
        routeTab.selectSplitPerformerContractor(parsed.legIndex, parsed.slotIndex, contractor);
    } else if (target.kind === 'performer' && target.index !== null) {
        routeTab.selectPerformerContractor(target.index, contractor);
    } else {
        selectClient(contractor);
    }
};

const {
    showOrderDocumentAttachModal,
    orderDocumentAttachPendingFile,
    orderDocumentAttachPresetIndex,
    orderDocumentAttachNewDocType,
    orderDocumentAttachTargetKind,
    orderDocumentAttachStage,
    orderDocumentAttachModalFileInputRef,
    orderDocumentAttachModalTitle,
    orderDocumentAttachPresetSummary,
    openOrderDocumentAttachModal,
    closeOrderDocumentAttachModal,
    confirmOrderDocumentAttach,
    onOrderDocumentAttachModalFileChange,
} = useOrderWizardDocumentAttach({
    form,
    props,
    page,
    isOrderFormEditable,
    stageLabel,
    stageMatches,
    addDocumentFor,
});

const { submit, markOrderDisruption } = useOrderWizardSubmit({
    form,
    props,
    activeTab,
    isEditing,
    isOrderFormEditable,
    saveAttempted,
    coreRequiredFieldsValid,
    hasSelectedCarrier,
    hasClientPrice,
    canShowMarkDisruptionButton,
    syncContractorCostsFromPerformers,
    needsCargoPerformerAllocationUi,
    cargoPerformerAllocationColumns,
    serializeCargoItemsForSubmit,
    selectedLoadingTypeCodes,
    normalizeNullableNumber,
    normalizePaymentFormCode,
    defaultClientPaymentForm,
    toStageKey,
    stageLabel,
    printFormTemplateSelection,
    orderBasicTermsDraft,
    activeIntakeDraftId,
    intakeDraftCommitted,
});

// Watch for changes in contractors_costs to sync back to performers
// Удалено для предотвращения циклической синхронизации при очистке исполнителя
// watch(
//     () => form.financial_term.contractors_costs,
//     (costs) => {
//         costs.forEach((cost) => {
//             const performer = form.performers.find((item) => item.stage === cost.stage);
//             if (performer && performer.contractor_id !== cost.contractor_id) {
//                 performer.contractor_id = cost.contractor_id;
//             }
//         });
//     },
//     { deep: true },
// );

if (Array.isArray(props.order?.performers)) {
    props.order.performers.forEach((p, legIndex) => {
        const registerCarrier = (id, name) => {
            const normalizedId = normalizeNullableNumber(id);
            const normalizedName = name ? String(name).trim() : '';

            if (normalizedId !== null && normalizedName !== '') {
                ensureContractorInLocalList({
                    id: normalizedId,
                    name: normalizedName,
                    type: 'carrier',
                    inn: null,
                    phone: null,
                    email: null,
                    is_own_company: false,
                });
            }
        };

        if (p.carrier_mode === CARRIER_MODE_SPLIT && Array.isArray(p.split_carriers)) {
            p.split_carriers.forEach((slot, slotIndex) => {
                registerCarrier(slot.contractor_id, slot.contractor_name);
                setCarrierSearchValue(
                    'performer-slot',
                    `${legIndex}-${slotIndex}`,
                    splitCarrierSearchLabel(legIndex, slotIndex, slot.contractor_id),
                );
            });

            return;
        }

        registerCarrier(p.contractor_id, p.contractor_name);
    });
}

watch(
    () => form.performers.map((performer) => ({
        stage: performer.stage,
        mode: performer.carrier_mode,
        contractor_id: performer.contractor_id,
        contractor_name: performer.contractor_name,
        split_carriers: (performer.split_carriers ?? []).map((slot) => [
            slot.slot,
            slot.contractor_id,
            slot.contractor_name,
            slot.fleet_vehicle_id,
            slot.fleet_driver_id,
        ]),
    })),
    (performers, prev) => {
        performers.forEach((row, index) => {
            const performer = form.performers[index];
            if (!performer) {
                return;
            }

            if (isPerformerSplit(performer)) {
                performer.split_carriers.forEach((slot, slotIndex) => {
                    setCarrierSearchValue(
                        'performer-slot',
                        `${index}-${slotIndex}`,
                        splitCarrierSearchLabel(index, slotIndex, slot.contractor_id),
                    );

                    const prevSlot = prev?.[index]?.split_carriers?.[slotIndex];
                    const contractorChanged = prevSlot != null && prevSlot[1] !== slot.contractor_id;
                    if (contractorChanged) {
                        slot.fleet_vehicle_id = null;
                        slot.fleet_driver_id = null;
                    }
                    if (prev != null && contractorChanged) {
                        loadFleetOptionsForLeg(index, slotIndex);
                    }
                });

                return;
            }

            setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, row.contractor_id));
            const costIndex = form.financial_term.contractors_costs.findIndex((cost) => stageMatches(cost.stage, row.stage));

            if (costIndex !== -1) {
                setCarrierSearchValue('cost', costIndex, performerCarrierSearchLabel(index, row.contractor_id));
            }

            const prevRow = prev?.[index];
            if (prevRow && prevRow.contractor_id !== row.contractor_id) {
                performer.fleet_vehicle_id = null;
                performer.fleet_driver_id = null;
            }

            if (prev != null && prevRow && prevRow.contractor_id !== row.contractor_id) {
                loadFleetOptionsForLeg(index);
            }
        });
    },
    { deep: true, immediate: true },
);

watch(
    () => form.client_id,
    () => {
        if (selectedClient.value) {
            clientSearch.value = selectedClient.value.name;
        }
    },
    { immediate: true },
);

function documentRequirementLabel(key) {
    return effectiveRequiredDocumentRules.value.find((rule) => rule.key === key)?.label ?? '';
}

function paymentFormLabel(value) {
    return paymentFormOptions.value.find((option) => option.value === value)?.label ?? value;
}

function paymentBasisLabel(value) {
    return orderPs.PAYMENT_BASIS_OPTIONS.find((option) => option.value === value)?.label ?? value;
}

function onRoutePointAddressInput(index) {
    const point = form.route_points[index];
    if (point) {
        syncRoutePointCityFromAddress(point);
    }
    queueAddressLookup(index);
}

function queueAddressLookup(index) {
    clearTimeout(addressTimers[index]);

    if (String(form.route_points[index]?.address ?? '').trim().length < 3) {
        addressSuggestions.value[index] = [];
        return;
    }

    addressTimers[index] = window.setTimeout(() => {
        fetchAddressSuggestions(index);
    }, 300);
}

async function fetchAddressSuggestions(index) {
    const query = form.route_points[index]?.address ?? '';

    try {
        const response = await fetch(`${route('orders.suggest-address')}?query=${encodeURIComponent(query)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        addressSuggestions.value[index] = Array.isArray(data.suggestions) ? data.suggestions : [];
    } catch (error) {
        console.error('Address suggestions error', error);
        addressSuggestions.value[index] = [];
    }
}

function selectAddress(index, suggestion) {
    const point = form.route_points[index];
    const existing = point.normalized_data || {};
    point.address = suggestion.value ?? '';
    const suggestedCity = suggestion.data?.city
        ?? suggestion.data?.settlement
        ?? suggestion.data?.city_with_type
        ?? existing.city
        ?? null;
    point.normalized_data = {
        ...existing,
        city: suggestedCity,
        region: suggestion.data?.region_with_type ?? suggestion.data?.region ?? existing.region ?? null,
        street: suggestion.data?.street_with_type ?? suggestion.data?.street ?? existing.street ?? null,
        house: suggestion.data?.house ?? existing.house ?? null,
        coordinates: {
            lat: suggestion.data?.geo_lat ?? existing.coordinates?.lat ?? null,
            lng: suggestion.data?.geo_lon ?? existing.coordinates?.lng ?? null,
        },
        kladr_id: suggestion.data?.kladr_id ?? existing.kladr_id ?? null,
        fias_id: suggestion.data?.fias_id ?? existing.fias_id ?? null,
    };
    syncRoutePointCityFromAddress(point);
    addressSuggestions.value[index] = [];
}

onBeforeUnmount(() => {
    discardActiveIntakeLearning();
});

function goBack() {
    discardActiveIntakeLearning();
    // Всегда запрашиваем реестр с сервера: history.back() отдаёт старый снимок Inertia без свежих строк.
    router.get(route('orders.index'), {}, { preserveScroll: true, preserveState: false });
}

</script>
