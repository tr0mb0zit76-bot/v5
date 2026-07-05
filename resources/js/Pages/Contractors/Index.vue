<script setup>
import { computed, nextTick, onMounted, ref, toRaw, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    FileDown,
    FileText,
    History,
    Plus,
    Save,
    Search,
    ShieldCheck,
    Trash2,
    UserCircle,
    Users,
    X,
} from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import ContractorsGrid from '@/Components/Contractors/ContractorsGrid.vue';
import ContractorDocumentsSection from '@/Components/Contractors/ContractorDocumentsSection.vue';
import ContractorPrintFormSection from '@/Components/Contractors/ContractorPrintFormSection.vue';
import ContractorPortraitTab from '@/Components/Contractors/ContractorPortraitTab.vue';
import ContractorInteractionOutcomeModal from '@/Components/Contractors/ContractorInteractionOutcomeModal.vue';
import ContractorDefaultNormsPenaltiesFields from '@/Components/Contractors/ContractorDefaultNormsPenaltiesFields.vue';
import PaymentTermsWizardBlock from '@/Pages/Orders/Components/PaymentTermsWizardBlock.vue';
import * as orderPaymentScheduleUi from '@/support/orderPaymentScheduleUi.js';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { transliteratedFieldValue } from '@/support/cyrillicTransliteration.js';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import {
    crmBtnCreate,
    crmBtnDangerMuted,
    crmBtnNeutral,
    crmCheckbox,
    crmFieldDays,
    crmFieldDisplay,
    crmFieldFluid,
    crmFieldPaymentMode,
    crmGridPanel,
    crmModalEntityShell,
    crmPageTitleSm,
    crmWizardBack,
    crmWizardBody,
    crmWizardHeader,
} from '@/support/crmUi.js';
import { blankPartyNormsPenalties, normalizePartyNormsPenalties } from '@/support/normsPenalties.js';
import {
    applyContractorPartySuggestion,
    ensureContractorPartyAutofill,
    fetchContractorDuplicateCheck,
    fetchContractorPartySuggestion,
    isCompleteContractorInn,
    normalizedContractorInn,
} from '@/support/contractorPartyAutofill.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'contractors', mainFill: true }, () => page),
});

const props = defineProps({
    contractors: {
        type: Array,
        default: () => [],
    },
    activityTypeOptions: {
        type: Array,
        default: () => [],
    },
    selectedContractor: {
        type: Object,
        default: null,
    },
    legalFormOptions: {
        type: Array,
        default: () => [],
    },
    edoProviderOptions: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: () => ({
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 0,
            from: 0,
            to: 0,
            links: [],
        }),
    },
    users: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            type: '',
        }),
    },
    currencyOptions: {
        type: Array,
        default: () => [],
    },
    paymentFormOptions: {
        type: Array,
        default: () => [],
    },
    workStatusOptions: {
        type: Array,
        default: () => [],
    },
    portraitOptions: {
        type: Object,
        default: () => ({}),
    },
    initialTab: {
        type: String,
        default: null,
    },
});

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const availableColumns = computed(() => page.props.contractorColumns ?? []);
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});
const search = ref(props.filters.search || '');
const activeTab = ref(props.initialTab || 'general');
const isCreateModalOpen = ref(false);
const isCreateRouteDismissed = ref(false);
const isDetailsModalDismissed = ref(false);
const showInteractionOutcomeModal = ref(false);
const isInnLookupPending = ref(false);
const submitError = ref('');
const duplicateWarning = ref({
    message: '',
    contractorId: null,
    openUrl: null,
    canOpen: false,
});
let activeInnLookup = null;
const addressSuggestions = ref({
    legal_address: [],
    actual_address: [],
    postal_address: [],
});
const addressTimers = {};
let innLookupTimer = null;
const lastAutoFilledInn = ref('');
const bankLookupTimers = {};
const bankLookupLoading = ref({});
const bankLookupErrors = ref({});

/** Подписи на фронте (UTF-8), чтобы не зависеть от кодировки ответа сервера в списке опций */
const legalFormLabelByValue = {
    ooo: 'ООО',
    zao: 'ЗАО',
    ao: 'АО',
    ip: 'ИП',
    samozanyaty: 'Самозанятый',
    other: 'Другое',
};

const tabs = [
    { key: 'general', label: 'Общие сведения', icon: Building2 },
    { key: 'requisites', label: 'Реквизиты', icon: ShieldCheck },
    { key: 'cooperation', label: 'Условия сотрудничества', icon: FileText },
    { key: 'contacts', label: 'Контакты', icon: Users },
    { key: 'portrait', label: 'Портрет', icon: UserCircle },
    { key: 'communications', label: 'Коммуникации', icon: History },
    { key: 'orders', label: 'Заказы', icon: FileText },
    { key: 'documents', label: 'Документы', icon: FileText },
];

const contractorTypes = [
    { value: 'customer', label: 'Заказчик' },
    { value: 'carrier', label: 'Перевозчик' },
    { value: 'contractor', label: 'Подрядчик' },
    { value: 'both', label: 'Заказчик и перевозчик' },
];

const interactionChannels = [
    { value: 'phone', label: 'Телефон' },
    { value: 'email', label: 'Email' },
    { value: 'messenger', label: 'Мессенджер' },
    { value: 'meeting', label: 'Встреча' },
];

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
        { value: 'cash', label: 'Наличные' },
    ];
});

const paymentBasisOptions = [
    { value: 'fttn', label: 'По сканам' },
    { value: 'fttn_receipt', label: 'По сканам + квиток' },
    { value: 'ottn', label: 'По оригиналам' },
];

function paymentBasisLabel(value) {
    const v = value || 'fttn';
    const fromOptions = paymentBasisOptions.find((option) => option.value === v)?.label;
    if (fromOptions) {
        return fromOptions;
    }
    if (v === 'loading') {
        return 'При погрузке';
    }
    if (v === 'unloading') {
        return 'При выгрузке';
    }

    return v;
}

const defaultCurrencySelectOptions = [
    { value: 'RUB', label: 'RUB' },
    { value: 'USD', label: 'USD' },
    { value: 'CNY', label: 'CNY' },
    { value: 'EUR', label: 'EUR' },
];

const currencySelectOptions = computed(() => {
    const raw = props.currencyOptions;
    if (!Array.isArray(raw) || raw.length === 0) {
        return defaultCurrencySelectOptions;
    }
    const first = raw[0];
    if (typeof first === 'string') {
        return raw.map((code) => ({ value: code, label: code }));
    }
    if (first && typeof first === 'object' && 'value' in first && 'label' in first) {
        return raw;
    }

    return defaultCurrencySelectOptions;
});

function blankPaymentSchedule() {
    return orderPaymentScheduleUi.blankSingleInstallmentSchedule();
}

function normalizePaymentSchedule(schedule = {}) {
    return orderPaymentScheduleUi.normalizePaymentSchedule(schedule);
}

function paymentScheduleSummary(schedule) {
    return orderPaymentScheduleUi.paymentScheduleSummaryHuman(schedule, 0, 'RUB', [], '');
}

/** Как в OrdersGrid: в БД латиница (FTTN/OTTN), в подписи — кириллица. */
function formatPaymentTermsForDisplay(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value)
        .replace(/\bFTTN_RECEIPT\b/gi, 'По сканам + квиток')
        .replace(/\bFTTN\b/gi, 'По сканам')
        .replace(/\bOTTN\b/gi, 'По оригиналам')
        .replace(/\bLOADING\b/gi, 'погрузка')
        .replace(/\bUNLOADING\b/gi, 'выгрузка')
        .replace(/\bfttn_receipt\b/gi, 'По сканам + квиток')
        .replace(/\bfttn\b/gi, 'По сканам')
        .replace(/\bottn\b/gi, 'По оригиналам')
        .replace(/\bloading\b/gi, 'погрузка')
        .replace(/\bunloading\b/gi, 'выгрузка');
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

    const postpaymentMatch = normalized.match(/^(\d+)\s+ДН\s+(FTTN|OTTN|LOADING|UNLOADING)$/u);

    if (postpaymentMatch) {
        return normalizePaymentSchedule({
            has_prepayment: false,
            postpayment_days: Number(postpaymentMatch[1]),
            postpayment_mode: postpaymentMatch[2].toLowerCase(),
        });
    }

    return blankPaymentSchedule();
}

function defaultOwnerId() {
    const currentUserId = Number(page.props.auth?.user?.id);

    return Number.isFinite(currentUserId) && currentUserId > 0 ? currentUserId : null;
}

function blankForm() {
    return {
        type: 'customer',
        name: '',
        full_name: '',
        short_description: '',
        inn: '',
        kpp: '',
        ogrn: '',
        okpo: '',
        legal_form: '',
        legal_address: '',
        actual_address: '',
        postal_address: '',
        phone: '',
        email: '',
        mail_sync_domains: [],
        website: '',
        contact_person: '',
        contact_person_phone: '',
        contact_person_email: '',
        contact_person_position: '',
        signer_name_nominative: '',
        signer_name_prepositional: '',
        signer_position: '',
        signer_authority_basis: '',
        edo_provider: '',
        edo_number: '',
        bank_name: '',
        bik: '',
        account_number: '',
        correspondent_account: '',
        bank_accounts: [blankBankAccount({ is_primary: true })],
        ati_id: '',
        specializations: [],
        activity_types: [],
        transport_requirements: [],
        debt_limit: null,
        debt_limit_currency: 'RUB',
        stop_on_limit: false,
        default_customer_payment_form: '',
        default_customer_payment_term: '',
        default_customer_payment_schedule: blankPaymentSchedule(),
        default_carrier_payment_form: '',
        default_carrier_payment_term: '',
        default_carrier_payment_schedule: blankPaymentSchedule(),
        default_customer_norms_penalties: blankPartyNormsPenalties(),
        default_carrier_norms_penalties: blankPartyNormsPenalties(),
        cooperation_terms_notes: '',
        is_active: true,
        work_status: 'active',
        is_own_company: false,
        is_non_resident: false,
        has_english_requisites: false,
        name_en: '',
        full_name_en: '',
        legal_address_en: '',
        actual_address_en: '',
        postal_address_en: '',
        contact_person_en: '',
        bank_name_en: '',
        signer_name_nominative_en: '',
        signer_name_prepositional_en: '',
        signer_position_en: '',
        signer_authority_basis_en: '',
        non_resident_corr_bank_name: '',
        non_resident_corr_bank_swift: '',
        non_resident_corr_settlement_account: '',
        non_resident_corr_bank_account: '',
        cnaps_code: '',
        owner_id: defaultOwnerId(),
        contacts: [],
        interactions: [],
        documents: [],
    };
}

const PRIMARY_BANK_LABEL = 'Основной';

function blankBankAccount(overrides = {}) {
    const merged = {
        id: `bank-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        label: '',
        country_code: 'RU',
        currency: 'RUB',
        bank_name: '',
        bik: '',
        account_number: '',
        correspondent_account: '',
        swift: '',
        iban: '',
        is_primary: false,
        ...overrides,
    };

    if (merged.is_primary && !String(merged.label ?? '').trim()) {
        merged.label = PRIMARY_BANK_LABEL;
    }

    return merged;
}

function normalizeBankAccount(row, index = 0) {
    const isPrimary = Boolean(row?.is_primary);
    const normalized = blankBankAccount({
        id: row?.id ?? `bank-${Date.now()}-${index}`,
        label: row?.label ?? '',
        country_code: String(row?.country_code ?? 'RU').toUpperCase().slice(0, 2) || 'RU',
        currency: String(row?.currency ?? 'RUB').toUpperCase().slice(0, 3) || 'RUB',
        bank_name: row?.bank_name ?? '',
        bik: String(row?.bik ?? '').replace(/\D/g, ''),
        account_number: String(row?.account_number ?? '').replace(/\D/g, ''),
        correspondent_account: String(row?.correspondent_account ?? '').replace(/\D/g, ''),
        swift: String(row?.swift ?? '').toUpperCase().trim(),
        iban: String(row?.iban ?? '').toUpperCase().replace(/\s+/g, ''),
        is_primary: isPrimary,
    });

    if (normalized.country_code !== 'RU') {
        normalized.bik = '';
    }

    return normalized;
}

function bankAccountHasMeaningfulData(row) {
    return Boolean(
        String(row?.bank_name ?? '').trim()
        || String(row?.bik ?? '').trim()
        || String(row?.account_number ?? '').trim()
        || String(row?.correspondent_account ?? '').trim()
        || String(row?.swift ?? '').trim()
        || String(row?.iban ?? '').trim()
    );
}

function normalizeBankAccounts(rows) {
    const normalized = (Array.isArray(rows) ? rows : [])
        .map((row, index) => normalizeBankAccount(row, index))
        .filter((row) => bankAccountHasMeaningfulData(row));

    if (normalized.length === 0) {
        return [blankBankAccount({ is_primary: true })];
    }

    const hasPrimary = normalized.some((row) => row.is_primary);
    if (!hasPrimary) {
        normalized[0].is_primary = true;
        if (!String(normalized[0].label ?? '').trim()) {
            normalized[0].label = PRIMARY_BANK_LABEL;
        }
    }

    return normalized.map((row, index) => ({
        ...row,
        is_primary: normalized.findIndex((entry) => entry.is_primary) === index,
    }));
}

function contractorToForm(contractor) {
    if (!contractor) {
        return blankForm();
    }

    const legacyBankAccount = {
        bank_name: contractor.bank_name ?? '',
        bik: contractor.bik ?? '',
        account_number: contractor.account_number ?? '',
        correspondent_account: contractor.correspondent_account ?? '',
        country_code: 'RU',
        currency: 'RUB',
        is_primary: true,
    };
    const bankAccountsSource = Array.isArray(contractor.bank_accounts) && contractor.bank_accounts.length > 0
        ? contractor.bank_accounts
        : (bankAccountHasMeaningfulData(legacyBankAccount) ? [legacyBankAccount] : []);

    return {
        type: contractor.type ?? 'customer',
        name: contractor.name ?? '',
        full_name: contractor.full_name ?? '',
        short_description: contractor.short_description ?? '',
        inn: contractor.inn ?? '',
        kpp: contractor.kpp ?? '',
        ogrn: contractor.ogrn ?? '',
        okpo: contractor.okpo ?? '',
        legal_form: contractor.legal_form ?? '',
        legal_address: contractor.legal_address ?? '',
        actual_address: contractor.actual_address ?? '',
        postal_address: contractor.postal_address ?? '',
        phone: contractor.phone ?? '',
        email: contractor.email ?? '',
        mail_sync_domains: Array.isArray(contractor.mail_sync_domains) ? contractor.mail_sync_domains : [],
        website: contractor.website ?? '',
        contact_person: contractor.contact_person ?? '',
        contact_person_phone: contractor.contact_person_phone ?? '',
        contact_person_email: contractor.contact_person_email ?? '',
        contact_person_position: contractor.contact_person_position ?? '',
        signer_name_nominative: contractor.signer_name_nominative ?? '',
        signer_name_prepositional: contractor.signer_name_prepositional ?? '',
        signer_position: contractor.signer_position ?? '',
        signer_authority_basis: contractor.signer_authority_basis ?? '',
        edo_provider: contractor.edo_provider ?? '',
        edo_number: contractor.edo_number ?? '',
        bank_name: contractor.bank_name ?? '',
        bik: contractor.bik ?? '',
        account_number: contractor.account_number ?? '',
        correspondent_account: contractor.correspondent_account ?? '',
        bank_accounts: normalizeBankAccounts(bankAccountsSource),
        ati_id: contractor.ati_id ?? '',
        specializations: Array.isArray(contractor.specializations) ? contractor.specializations : [],
        activity_types: Array.isArray(contractor.activity_types) ? contractor.activity_types : [],
        transport_requirements: Array.isArray(contractor.transport_requirements) ? contractor.transport_requirements : [],
        debt_limit: contractor.debt_limit ?? null,
        debt_limit_currency: contractor.debt_limit_currency ?? 'RUB',
        stop_on_limit: Boolean(contractor.stop_on_limit),
        default_customer_payment_form: contractor.default_customer_payment_form ?? '',
        default_customer_payment_term: contractor.default_customer_payment_term ?? '',
        default_customer_payment_schedule: normalizePaymentSchedule(contractor.default_customer_payment_schedule ?? parsePaymentTermPreset(contractor.default_customer_payment_term)),
        default_carrier_payment_form: contractor.default_carrier_payment_form ?? '',
        default_carrier_payment_term: contractor.default_carrier_payment_term ?? '',
        default_carrier_payment_schedule: normalizePaymentSchedule(contractor.default_carrier_payment_schedule ?? parsePaymentTermPreset(contractor.default_carrier_payment_term)),
        default_customer_norms_penalties: normalizePartyNormsPenalties(contractor.default_customer_norms_penalties),
        default_carrier_norms_penalties: normalizePartyNormsPenalties(contractor.default_carrier_norms_penalties),
        cooperation_terms_notes: contractor.cooperation_terms_notes ?? '',
        is_active: Boolean(contractor.is_active),
        work_status: contractor.work_status ?? 'active',
        is_own_company: Boolean(contractor.is_own_company),
        is_non_resident: Boolean(contractor.is_non_resident),
        has_english_requisites: Boolean(contractor.has_english_requisites),
        name_en: contractor.name_en ?? '',
        full_name_en: contractor.full_name_en ?? '',
        legal_address_en: contractor.legal_address_en ?? '',
        actual_address_en: contractor.actual_address_en ?? '',
        postal_address_en: contractor.postal_address_en ?? '',
        contact_person_en: contractor.contact_person_en ?? '',
        bank_name_en: contractor.bank_name_en ?? '',
        signer_name_nominative_en: contractor.signer_name_nominative_en ?? '',
        signer_name_prepositional_en: contractor.signer_name_prepositional_en ?? '',
        signer_position_en: contractor.signer_position_en ?? '',
        signer_authority_basis_en: contractor.signer_authority_basis_en ?? '',
        non_resident_corr_bank_name: contractor.non_resident_corr_bank_name ?? '',
        non_resident_corr_bank_swift: contractor.non_resident_corr_bank_swift ?? '',
        non_resident_corr_settlement_account: contractor.non_resident_corr_settlement_account ?? '',
        non_resident_corr_bank_account: contractor.non_resident_corr_bank_account ?? '',
        cnaps_code: contractor.cnaps_code ?? '',
        owner_id: contractor.owner_id ?? null,
        contacts: Array.isArray(contractor.contacts)
            ? contractor.contacts.map((contact) => ({
                full_name: contact.full_name ?? '',
                position: contact.position ?? '',
                phone: contact.phone ?? '',
                email: contact.email ?? '',
                is_primary: Boolean(contact.is_primary),
                is_decision_maker: Boolean(contact.is_decision_maker),
                role_in_deal: contact.role_in_deal ?? (contact.is_decision_maker ? 'decision_maker' : 'unknown'),
                communication_notes: contact.communication_notes ?? '',
                notes: contact.notes ?? '',
            }))
            : [],
        interactions: Array.isArray(contractor.interactions)
            ? contractor.interactions.map((interaction) => ({
                contacted_at: interaction.contacted_at ? interaction.contacted_at.slice(0, 16) : '',
                channel: interaction.channel ?? '',
                subject: interaction.subject ?? '',
                summary: interaction.summary ?? '',
                result: interaction.result ?? '',
            }))
            : [],
        documents: Array.isArray(contractor.documents)
            ? contractor.documents.map((document) => ({
                id: document.id ?? null,
                type: document.type ?? '',
                title: document.title ?? '',
                number: document.number ?? '',
                document_date: document.document_date ?? '',
                status: document.status ?? '',
                notes: document.notes ?? '',
                original_name: document.original_name ?? '',
                preview_url: document.preview_url ?? null,
                created_at: document.created_at ?? null,
            }))
            : [],
    };
}

const form = useForm(contractorToForm(props.selectedContractor));

/** Синхронизация адресов на закладке «Реквизиты» (не уходят на сервер отдельно). */
const actualMatchesLegal = ref(false);
const postalMatchesLegal = ref(false);
const postalMatchesActual = ref(false);

function syncAddressLinkTargets() {
    if (actualMatchesLegal.value) {
        form.actual_address = form.legal_address ?? '';
    }

    if (postalMatchesLegal.value) {
        form.postal_address = form.legal_address ?? '';
    } else if (postalMatchesActual.value) {
        form.postal_address = form.actual_address ?? '';
    }
}

function inferAddressLinkFlagsFromForm() {
    const legal = String(form.legal_address ?? '').trim();
    const actual = String(form.actual_address ?? '').trim();
    const postal = String(form.postal_address ?? '').trim();

    actualMatchesLegal.value = legal !== '' && actual === legal;

    if (postal === '') {
        postalMatchesLegal.value = false;
        postalMatchesActual.value = false;

        return;
    }

    if (legal !== '' && postal === legal) {
        postalMatchesLegal.value = true;
        postalMatchesActual.value = false;

        return;
    }

    if (actual !== '' && postal === actual) {
        postalMatchesActual.value = true;
        postalMatchesLegal.value = false;

        return;
    }

    postalMatchesLegal.value = false;
    postalMatchesActual.value = false;
}

function toggleActualMatchesLegal(event) {
    const input = event?.target;
    actualMatchesLegal.value = Boolean(input?.checked);
    if (actualMatchesLegal.value) {
        form.actual_address = form.legal_address ?? '';
        addressSuggestions.value = { ...addressSuggestions.value, actual_address: [] };
    }
    syncAddressLinkTargets();
}

function togglePostalMatchesLegal(event) {
    const input = event?.target;
    const checked = Boolean(input?.checked);
    postalMatchesLegal.value = checked;
    if (checked) {
        postalMatchesActual.value = false;
        addressSuggestions.value = { ...addressSuggestions.value, postal_address: [] };
    }
    syncAddressLinkTargets();
}

function togglePostalMatchesActual(event) {
    const input = event?.target;
    const checked = Boolean(input?.checked);
    postalMatchesActual.value = checked;
    if (checked) {
        postalMatchesLegal.value = false;
        addressSuggestions.value = { ...addressSuggestions.value, postal_address: [] };
    }
    syncAddressLinkTargets();
}

const transportRequirementsText = ref('');
const mailSyncDomainsText = ref('');
const globalActivityTypeOptions = ref(
    [...new Set((props.activityTypeOptions ?? []).map((item) => String(item ?? '').trim()).filter(Boolean))].sort((left, right) => left.localeCompare(right, 'ru'))
);

const availableActivityTypeOptions = computed(() => {
    return [...new Set([
        ...globalActivityTypeOptions.value,
        ...(form.activity_types ?? []),
    ].map((item) => String(item ?? '').trim()).filter(Boolean))].sort((left, right) => left.localeCompare(right, 'ru'));
});

const activityTypeDropdownLabel = computed(() => {
    if (!Array.isArray(form.activity_types) || form.activity_types.length === 0) {
        return 'Выберите виды деятельности';
    }

    if (form.activity_types.length <= 2) {
        return form.activity_types.join(', ');
    }

    return `${form.activity_types.slice(0, 2).join(', ')} +${form.activity_types.length - 2}`;
});

const activityTypeDropdownSummary = computed(() => {
    if (!Array.isArray(form.activity_types) || form.activity_types.length === 0) {
        return 'Выберите виды деятельности';
    }

    if (form.activity_types.length <= 2) {
        return form.activity_types.join(', ');
    }

    return `${form.activity_types.slice(0, 2).join(', ')} +${form.activity_types.length - 2}`;
});

function applyFormState(contractor, options = {}) {
    const resetTab = options.resetTab ?? true;
    const payload = contractorToForm(contractor);
    form.defaults(payload);
    form.reset();
    const normalizedInn = normalizedContractorInn(payload.inn ?? '');
    lastAutoFilledInn.value = String(payload.full_name ?? '').trim() !== '' ? normalizedInn : '';

    for (const [key, value] of Object.entries(payload)) {
        form[key] = value;
    }

    transportRequirementsText.value = payload.transport_requirements.join('\n');
    mailSyncDomainsText.value = formatMailSyncDomainsList(payload.mail_sync_domains);
    if (resetTab) {
        activeTab.value = 'general';
    }
    addressSuggestions.value = {
        legal_address: [],
        actual_address: [],
        postal_address: [],
    };

    inferAddressLinkFlagsFromForm();

    if (shouldLookupPartyByInn(normalizedInn)) {
        void nextTick(() => fetchPartySuggestions());
    }
}

applyFormState(props.selectedContractor);

watch(() => props.selectedContractor, (contractor, previousContractor) => {
    const prevId = previousContractor?.id ?? null;
    const nextId = contractor?.id ?? null;
    const resetTab = prevId !== nextId;

    if (prevId === nextId && form.isDirty && Object.keys(form.errors ?? {}).length > 0) {
        return;
    }

    applyFormState(contractor, { resetTab });
});

watch(() => form.is_non_resident, (isNonResident) => {
    if (!isNonResident) {
        form.non_resident_corr_bank_name = '';
        form.non_resident_corr_bank_swift = '';
        form.non_resident_corr_settlement_account = '';
        form.non_resident_corr_bank_account = '';
        form.cnaps_code = '';
    }
});

watch(() => form.is_own_company, (isOwnCompany) => {
    if (!isOwnCompany) {
        return;
    }

    form.is_active = true;
    form.work_status = 'active';
    form.stop_on_limit = false;
});

watch(() => props.activityTypeOptions, (options) => {
    globalActivityTypeOptions.value = [...new Set((options ?? []).map((item) => String(item ?? '').trim()).filter(Boolean))]
        .sort((left, right) => left.localeCompare(right, 'ru'));
});

watch(() => form.legal_address, () => {
    syncAddressLinkTargets();
});

watch(() => form.actual_address, () => {
    if (postalMatchesActual.value && !postalMatchesLegal.value) {
        form.postal_address = form.actual_address ?? '';
    }
});

function currentPagePath(url) {
    const rawUrl = String(url ?? '');

    try {
        const baseUrl = typeof window === 'undefined' ? 'http://localhost' : window.location.origin;

        return new URL(rawUrl, baseUrl).pathname;
    } catch {
        return rawUrl.split('?')[0].split('#')[0];
    }
}

const isCreateRoute = computed(() => currentPagePath(page.url) === '/contractors/create');
const isCreating = computed(() => isCreateModalOpen.value || (isCreateRoute.value && !isCreateRouteDismissed.value));
const selectedContractorId = computed(() => props.selectedContractor?.id ?? null);

const printFormSectionRef = ref(null);

function openPrintFormSection() {
    activeTab.value = 'cooperation';
    nextTick(() => {
        printFormSectionRef.value?.focusSection?.();
    });
}

const printFormProfileBadgeClass = computed(() => {
    const mode = props.selectedContractor?.print_form_profile?.mode;

    if (mode === 'contractor_external') {
        return 'border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100';
    }

    if (mode === 'internal_customized') {
        return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100';
    }

    return 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200';
});

const visibleTabs = computed(() => tabs.filter((tab) => tab.key !== 'portrait' || selectedContractorId.value));

const portraitForTab = computed(() => props.selectedContractor?.portrait ?? {
    communication_style: 'unknown',
    price_sensitivity: 'unknown',
    preferred_channel: 'unknown',
    decision_cadence: 'unknown',
    relationship_trust: 'unknown',
    success_criteria: '',
    typical_objections: [],
    internal_notes: '',
    coverage_pct: 0,
    missing_slots: [],
});
const canDownloadPartnerCard = computed(
    () =>
        selectedContractorId.value !== null
        && Boolean(form.is_own_company)
        && Boolean(props.selectedContractor?.is_own_company),
);
const isOwnCompanyProfile = computed(() => Boolean(form.is_own_company));
const isContractorModalOpen = computed(() => isCreating.value || (selectedContractorId.value !== null && !isDetailsModalDismissed.value));

const contractorScoring = ref(null);
const contractorScoringLoading = ref(false);
const contractorScoringConfirmLoading = ref(false);
const contractorScoringError = ref('');
const contractorScoringSuccess = ref('');
const limitApprovalSubmitLoading = ref(false);
const limitApprovalState = ref(null);
const verificationOverride = ref(null);
const authUser = computed(() => page.props.auth?.user ?? null);

const canApproveContractorLimit = computed(() => {
    const role = authUser.value?.role;
    if (!role) {
        return false;
    }

    if (role.is_admin) {
        return true;
    }

    return role.name === 'supervisor';
});

const canRequestLimitApproval = computed(() => {
    if (isOwnCompanyProfile.value || selectedContractorId.value === null) {
        return false;
    }

    if (limitApprovalState.value?.assessment_id) {
        return false;
    }

    return Boolean(props.selectedContractor?.can_request_limit_approval);
});

const limitApprovalReasonLabel = computed(() => {
    const reason = props.selectedContractor?.limit_approval_reason;
    const labels = {
        new_card: 'Новая карточка / не проверен',
        verification_expired: 'Проверка истекла',
        limit_reached: 'Лимит достигнут',
        limit_zero: 'Лимит не задан или обнулён',
        limit_insufficient: 'Лимита не хватает',
    };

    return labels[reason] ?? null;
});

const verificationState = computed(() => {
    if (verificationOverride.value) {
        return verificationOverride.value;
    }

    const contractor = props.selectedContractor;

    return {
        is_verified: Boolean(contractor?.is_verified),
        verified_at: contractor?.verified_at ?? null,
        verification_valid_until: contractor?.verification_valid_until ?? null,
    };
});

function contractorStatusBadge(contractor) {
    if (contractor?.is_own_company) {
        return {
            text: contractor?.status_text ?? 'Своя компания',
            class: contractor?.status_badge_class
                ?? 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
        };
    }

    return {
        text: contractor?.status_text ?? (contractor?.is_active ? 'Активен' : 'Архив'),
        class: contractor?.status_badge_class
            ?? (contractor?.is_active
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'),
    };
}

function formatVerificationDate(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString('ru-RU');
}

async function loadContractorScoring(options = { refresh: false }) {
    if (isOwnCompanyProfile.value) {
        contractorScoring.value = null;
        contractorScoringError.value = '';

        return;
    }

    if (selectedContractorId.value === null || !props.selectedContractor?.inn) {
        contractorScoring.value = null;
        contractorScoringError.value = '';

        return;
    }

    contractorScoringLoading.value = true;
    contractorScoringError.value = '';
    contractorScoringSuccess.value = '';

    try {
        const params = new URLSearchParams();
        if (options.refresh) {
            params.set('refresh', '1');
        }

        const qs = params.toString();
        const url = route('contractors.scoring', selectedContractorId.value) + (qs ? `?${qs}` : '');
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.message || 'Ошибка запроса скоринга');
        }

        contractorScoring.value = data;

        if (data.verification) {
            verificationOverride.value = data.verification;
        }

        if (data.ok && data.assessment_id && canApproveContractorLimit.value) {
            applyScoringRecommendations();
        }

        if (!data.ok) {
            contractorScoringError.value = data.error || 'Не удалось рассчитать скоринг';
        }
    } catch (e) {
        contractorScoring.value = null;
        contractorScoringError.value = e.message || 'Не удалось загрузить скоринг';
    } finally {
        contractorScoringLoading.value = false;
    }
}

const scoringScheduleTarget = computed(() => {
    if (form.type === 'carrier') {
        return 'carrier';
    }

    return 'customer';
});

function currentAppliedPostpaymentDays() {
    const schedule = scoringScheduleTarget.value === 'carrier'
        ? form.default_carrier_payment_schedule
        : form.default_customer_payment_schedule;

    return Number(schedule?.postpayment_days ?? 0);
}

const scoringFormDiffersFromDraft = computed(() => {
    if (!contractorScoring.value?.ok) {
        return false;
    }

    const draftLimit = Number(contractorScoring.value.recommended_debt_limit_rub ?? 0);
    const draftDays = Number(contractorScoring.value.recommended_postpayment_days ?? 0);

    return Number(form.debt_limit ?? 0) !== draftLimit
        || currentAppliedPostpaymentDays() !== draftDays;
});

function applyScoringRecommendations() {
    if (!contractorScoring.value?.ok) {
        return;
    }

    form.debt_limit = contractorScoring.value.recommended_debt_limit_rub ?? 0;

    const scheduleField = scoringScheduleTarget.value === 'carrier'
        ? 'default_carrier_payment_schedule'
        : 'default_customer_payment_schedule';

    const schedule = form[scheduleField] && typeof form[scheduleField] === 'object'
        ? { ...form[scheduleField] }
        : { postpayment_days: 0, postpayment_mode: 'ottn', prepayment_days: 0, prepayment_mode: 'ottn' };

    schedule.postpayment_days = contractorScoring.value.recommended_postpayment_days ?? 0;
    form[scheduleField] = schedule;
}

async function applyApprovedScoringLimits() {
    if (!contractorScoring.value?.ok || !contractorScoring.value.assessment_id) {
        return;
    }

    const outcome = scoringFormDiffersFromDraft.value
        ? 'accepted_with_edits'
        : 'accepted_as_is';

    await confirmRiskAssessment(outcome);
}

async function confirmRiskAssessment(outcome) {
    if (!contractorScoring.value?.ok || !contractorScoring.value.assessment_id || selectedContractorId.value === null) {
        return;
    }

    contractorScoringConfirmLoading.value = true;
    contractorScoringError.value = '';

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const url = route('contractors.risk-assessment.confirm', selectedContractorId.value);
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                assessment_id: contractorScoring.value.assessment_id,
                outcome,
                applied_debt_limit: form.debt_limit === '' || form.debt_limit === null
                    ? null
                    : Number(form.debt_limit),
                applied_postpayment_days: scoringScheduleTarget.value === 'carrier'
                    ? Number(form.default_carrier_payment_schedule?.postpayment_days ?? 0)
                    : Number(form.default_customer_payment_schedule?.postpayment_days ?? 0),
                schedule_target: scoringScheduleTarget.value,
            }),
        });
        const data = await res.json();

        if (!res.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null;
            throw new Error(validationMessage || data.message || 'Не удалось подтвердить оценку');
        }

        if (data.verification) {
            verificationOverride.value = data.verification;
        }

        contractorScoringSuccess.value = outcome === 'accepted_with_edits'
            ? 'Условия с вашими правками применены. Контрагент отмечен проверенным.'
            : 'Рекомендации скоринга применены. Контрагент отмечен проверенным.';

        if (contractorScoring.value) {
            contractorScoring.value = {
                ...contractorScoring.value,
                assessment_id: null,
            };
        }
    } catch (e) {
        contractorScoringError.value = e.message || 'Не удалось подтвердить оценку';
    } finally {
        contractorScoringConfirmLoading.value = false;
    }
}

watch([selectedContractorId, () => props.selectedContractor?.inn], () => {
    verificationOverride.value = null;
    limitApprovalState.value = props.selectedContractor?.limit_approval ?? null;
    loadContractorScoring({ refresh: false });
});

watch(
    () => props.selectedContractor?.limit_approval,
    (next) => {
        limitApprovalState.value = next ?? null;
    },
    { deep: true },
);

async function submitLimitApprovalRequest() {
    if (!canRequestLimitApproval.value || selectedContractorId.value === null) {
        return;
    }

    limitApprovalSubmitLoading.value = true;
    contractorScoringError.value = '';

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const url = route('contractors.limit-approval.request', selectedContractorId.value);
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.message || data.errors?.contractor?.[0] || 'Не удалось отправить на согласование');
        }

        limitApprovalState.value = data.limit_approval ?? null;
    } catch (e) {
        contractorScoringError.value = e.message || 'Не удалось отправить на согласование';
    } finally {
        limitApprovalSubmitLoading.value = false;
    }
}

function scoringGradeClass(grade) {
    switch (grade) {
        case 'A':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300';
        case 'B':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300';
        case 'C':
            return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200';
        default:
            return 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300';
    }
}

function scoringEgrStatusLabel(egr) {
    switch (egr) {
        case 'active':
            return 'ЕГРЮЛ: действующая';
        case 'inactive':
            return 'ЕГРЮЛ: ликвидация / исключение';
        default:
            return 'ЕГРЮЛ: не распознан — проверьте на checko.ru';
    }
}

const scoringComponentsLine = computed(() => {
    const components = contractorScoring.value?.components;
    if (!components) {
        return '';
    }

    const parts = [];
    if (components.legal != null) {
        parts.push(`Юр. ${components.legal}`);
    }
    if (components.capacity != null) {
        parts.push(`Фин. ${components.capacity}`);
    }
    if (components.relationship != null) {
        parts.push(`CRM ${components.relationship}`);
    }

    return parts.join(' · ');
});

const scoringDisplayFactors = computed(() => {
    const factors = contractorScoring.value?.factors;
    if (!Array.isArray(factors)) {
        return [];
    }

    return factors.filter((factor) => typeof factor === 'string' && factor.trim() !== '');
});

// Server-side search will be handled by the backend
// The filtered contractors are already in props.contractors

/** Не уходим на сервер с одной буквой — иначе при каждом первом символе перезагружается список. */
function effectiveIndexSearchQuery(raw) {
    const trimmed = String(raw ?? '').trim();

    return trimmed.length < 2 ? '' : trimmed;
}

// Watch for search input changes and trigger server request
let searchTimer = null;
watch(() => search.value, (newSearch) => {
    clearTimeout(searchTimer);

    const trimmed = newSearch.trim();
    if (trimmed.length === 1) {
        return;
    }

    searchTimer = setTimeout(() => {
        router.get(route('contractors.index', {
            search: effectiveIndexSearchQuery(newSearch),
            type: '',
            page: 1, // Reset to first page when searching
        }), {}, { preserveScroll: true });
    }, 700); // Длиннее дебаунс — меньше лишних запросов при медленном наборе
});

const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});

const totalOrdersCount = computed(() => props.selectedContractor?.orders?.length ?? 0);
const relatedOrderDocumentsCount = computed(() => props.selectedContractor?.order_documents?.length ?? 0);

function openCreateForm() {
    isCreateRouteDismissed.value = false;
    isDetailsModalDismissed.value = false;
    submitError.value = '';
    clearDuplicateWarning();
    applyFormState(null);
    activeTab.value = 'general';
    isCreateModalOpen.value = true;
}

function openContractor(contractorId) {
    isCreateModalOpen.value = false;
    isCreateRouteDismissed.value = false;
    isDetailsModalDismissed.value = false;

    router.get(route('contractors.show', {
        contractor: contractorId,
        search: effectiveIndexSearchQuery(search.value),
        type: '',
        page: props.pagination.current_page,
    }), {}, { preserveScroll: true });
}

function closeContractorModal() {
    if (isCreateModalOpen.value && !isCreateRoute.value && selectedContractorId.value === null) {
        isCreateModalOpen.value = false;

        return;
    }

    isCreateModalOpen.value = false;
    isCreateRouteDismissed.value = true;
    isDetailsModalDismissed.value = true;

    router.get(route('contractors.index', {
        search: effectiveIndexSearchQuery(search.value),
        type: '',
        page: props.pagination.current_page,
    }), {}, { preserveScroll: true, replace: true });
}

function resetToSelected() {
    applyFormState(props.selectedContractor, { resetTab: false });
}

function parseMultilineList(value) {
    return value
        .split('\n')
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function formatMailSyncDomainsList(domains) {
    if (!Array.isArray(domains)) {
        return '';
    }

    return domains.map((item) => String(item).trim()).filter(Boolean).join('\n');
}

function parseMailSyncDomainsList(value) {
    return String(value ?? '')
        .split(/[\n,;]+/)
        .map((item) => item.trim().replace(/^@+/, '').toLowerCase())
        .filter((item) => item !== '');
}

function buildContractorSubmitPayload() {
    const documents = (form.documents ?? []).map(({ file: _file, preview_url: _previewUrl, ...meta }) => meta);

    return JSON.parse(JSON.stringify({
        ...toRaw(form.data()),
        documents,
    }));
}

function filterEmptyNestedRowsForSubmit() {
    form.contacts = (form.contacts ?? []).filter((contact) => String(contact?.full_name ?? '').trim() !== '');
    form.documents = (form.documents ?? []).filter((document) => {
        if (document?.file instanceof File) {
            return true;
        }

        return String(document?.title ?? '').trim() !== '';
    });
    form.interactions = (form.interactions ?? []).filter((interaction) => {
        const contactedAt = String(interaction?.contacted_at ?? '').trim();
        const subject = String(interaction?.subject ?? '').trim();
        const summary = String(interaction?.summary ?? '').trim();
        const result = String(interaction?.result ?? '').trim();

        return contactedAt !== '' || subject !== '' || summary !== '' || result !== '';
    });
}

async function submit() {
    submitError.value = '';
    form.clearErrors();

    try {
        clearTimeout(innLookupTimer);
        await waitForActiveInnLookup();

        if (isCompleteContractorInn(form.inn) && contractorNameForSubmit() === '') {
            const filled = await ensureContractorPartyAutofill(form);
            if (!filled) {
                submitError.value = 'Не удалось получить данные по ИНН. Укажите краткое название вручную.';
                focusContractorSubmitIssue('name');

                return;
            }
        }

        if (contractorNameForSubmit() === '') {
            submitError.value = 'Укажите краткое название контрагента или корректный ИНН.';
            focusContractorSubmitIssue('name');

            return;
        }

        sanitizeOwnerIdForSubmit();

        if (await refreshDuplicateWarning()) {
            submitError.value = duplicateWarning.value.message;
            focusContractorSubmitIssue('inn');

            return;
        }
    } catch (error) {
        console.error('Contractor submit preparation error', error);
        submitError.value = 'Не удалось подготовить данные к сохранению. Попробуйте ещё раз.';

        return;
    }

    filterEmptyNestedRowsForSubmit();
    form.transport_requirements = parseMultilineList(transportRequirementsText.value);
    form.mail_sync_domains = parseMailSyncDomainsList(mailSyncDomainsText.value);
    form.activity_types = [...new Set((form.activity_types ?? []).map((item) => String(item).trim()).filter(Boolean))];
    form.default_customer_payment_schedule = normalizePaymentSchedule(form.default_customer_payment_schedule);
    form.default_carrier_payment_schedule = normalizePaymentSchedule(form.default_carrier_payment_schedule);
    form.default_customer_payment_term = paymentScheduleSummary(form.default_customer_payment_schedule) || '';
    form.default_carrier_payment_term = paymentScheduleSummary(form.default_carrier_payment_schedule) || '';
    form.bank_accounts = normalizeBankAccounts(form.bank_accounts);
    form.is_non_resident = Boolean(form.is_non_resident);
    form.has_english_requisites = Boolean(form.has_english_requisites);
    form.contacts = (form.contacts ?? []).map((contact) => ({
        ...contact,
        is_primary: Boolean(contact.is_primary),
        is_decision_maker: Boolean(contact.is_decision_maker),
    }));

    const primaryBankAccount = form.bank_accounts.find((account) => account.is_primary) ?? form.bank_accounts[0] ?? null;
    const existingHadLegacy = Boolean(
        props.selectedContractor?.bank_name
        || props.selectedContractor?.bik
        || props.selectedContractor?.account_number
        || props.selectedContractor?.correspondent_account
    );
    if (existingHadLegacy && primaryBankAccount) {
        form.bank_name = primaryBankAccount.bank_name ?? '';
        form.bik = primaryBankAccount.bik ?? '';
        form.account_number = primaryBankAccount.account_number ?? '';
        form.correspondent_account = primaryBankAccount.correspondent_account ?? '';
    } else {
        form.bank_name = '';
        form.bik = '';
        form.account_number = '';
        form.correspondent_account = '';
    }

    if (form.inn != null && form.inn !== '') {
        form.inn = String(form.inn).replace(/\D/g, '');
    }

    const hasNewDocumentFiles = (form.documents ?? []).some((document) => document.file instanceof File);

    if (hasNewDocumentFiles) {
        const jsonBody = buildContractorSubmitPayload();
        const formData = new FormData();
        formData.append('contractor_payload', JSON.stringify(jsonBody));
        (form.documents ?? []).forEach((document, index) => {
            if (document.file instanceof File) {
                formData.append(`contractor_document_file_${index}`, document.file);
            }
        });

        const url = selectedContractorId.value === null
            ? route('contractors.store')
            : route('contractors.update', selectedContractorId.value);
        const opts = {
            preserveScroll: true,
            forceFormData: true,
            onBefore: () => {
                form.processing = true;
            },
            onFinish: () => {
                form.processing = false;
            },
            onSuccess: () => {
                submitError.value = '';
                if (selectedContractorId.value === null) {
                    isCreateModalOpen.value = false;
                }
            },
            onError: handleContractorSubmitErrors,
        };

        if (selectedContractorId.value !== null) {
            formData.append('_method', 'patch');
        }

        router.post(url, formData, opts);

        return;
    }

    if (selectedContractorId.value === null) {
        form.post(route('contractors.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitError.value = '';
                isCreateModalOpen.value = false;
            },
            onError: handleContractorSubmitErrors,
        });

        return;
    }

    form.patch(route('contractors.update', selectedContractorId.value), {
        preserveScroll: true,
        onError: handleContractorSubmitErrors,
    });
}

function openOrder(orderId) {
    router.get(route('orders.edit', orderId), {}, { preserveScroll: true });
}

function removeContractor() {
    if (selectedContractorId.value === null) {
        return;
    }

    if (!window.confirm('Удалить контрагента?')) {
        return;
    }

    router.delete(route('contractors.destroy', selectedContractorId.value), {
        preserveScroll: true,
    });
}

function addContact() {
    form.contacts.push({
        full_name: '',
        position: '',
        phone: '',
        email: '',
        is_primary: form.contacts.length === 0,
        is_decision_maker: false,
        role_in_deal: 'unknown',
        communication_notes: '',
        notes: '',
    });
}

function addInteraction() {
    form.interactions.push({
        contacted_at: '',
        channel: 'phone',
        subject: '',
        summary: '',
        result: '',
    });
}

function removeItem(collection, index) {
    collection.splice(index, 1);
}

function addBankAccount() {
    const hasPrimary = Array.isArray(form.bank_accounts) && form.bank_accounts.some((row) => row.is_primary);
    form.bank_accounts = [
        ...(Array.isArray(form.bank_accounts) ? form.bank_accounts : []),
        blankBankAccount({ is_primary: !hasPrimary }),
    ];
}

function removeBankAccount(index) {
    if (!Array.isArray(form.bank_accounts) || form.bank_accounts.length <= 1) {
        return;
    }

    const wasPrimary = Boolean(form.bank_accounts[index]?.is_primary);
    form.bank_accounts.splice(index, 1);

    if (wasPrimary && form.bank_accounts.length > 0) {
        form.bank_accounts[0].is_primary = true;
        if (!String(form.bank_accounts[0].label ?? '').trim()) {
            form.bank_accounts[0].label = PRIMARY_BANK_LABEL;
        }
    }
}

function setPrimaryBankAccount(index) {
    if (!Array.isArray(form.bank_accounts)) {
        return;
    }

    form.bank_accounts = form.bank_accounts.map((row, rowIndex) => {
        const nextPrimary = rowIndex === index;
        let label = row.label;

        if (row.is_primary && !nextPrimary && String(label ?? '').trim() === PRIMARY_BANK_LABEL) {
            label = '';
        }

        if (nextPrimary) {
            label = PRIMARY_BANK_LABEL;
        }

        return {
            ...row,
            is_primary: nextPrimary,
            label,
        };
    });
}

function scheduleBankLookup(index) {
    clearTimeout(bankLookupTimers[index]);
    bankLookupTimers[index] = window.setTimeout(() => {
        fetchBankSuggestionByBik(index);
    }, 350);
}

async function fetchBankSuggestionByBik(index) {
    const account = form.bank_accounts?.[index];
    if (!account) {
        return;
    }

    const bik = String(account.bik ?? '').replace(/\D/g, '');
    form.bank_accounts[index].bik = bik;

    if (account.country_code !== 'RU' || bik.length !== 9) {
        return;
    }

    bankLookupLoading.value[index] = true;
    bankLookupErrors.value[index] = '';

    try {
        const response = await fetch(`${route('contractors.suggest-bank')}?bik=${encodeURIComponent(bik)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        const suggestion = Array.isArray(data.suggestions) ? data.suggestions[0] : null;
        const payload = suggestion?.data ?? {};

        form.bank_accounts[index].bank_name = suggestion?.value ?? form.bank_accounts[index].bank_name ?? '';
        if (!form.bank_accounts[index].correspondent_account && payload.correspondent_account) {
            form.bank_accounts[index].correspondent_account = String(payload.correspondent_account).replace(/\D/g, '');
        }
        if (!form.bank_accounts[index].swift && payload.swift) {
            form.bank_accounts[index].swift = String(payload.swift).toUpperCase();
        }
    } catch (error) {
        console.error('DaData bank suggestion error', error);
        bankLookupErrors.value[index] = 'Не удалось получить данные банка';
    } finally {
        bankLookupLoading.value[index] = false;
    }
}

const englishTransliterationHint = ref('');

function primaryBankName() {
    const accounts = Array.isArray(form.bank_accounts) ? form.bank_accounts : [];
    const primary = accounts.find((row) => row?.is_primary) ?? accounts[0];

    return String(primary?.bank_name ?? form.bank_name ?? '').trim();
}

function fillEnglishRequisitesFromRussian(overwrite = false) {
    const mapping = [
        ['name_en', () => form.name],
        ['full_name_en', () => form.full_name],
        ['legal_address_en', () => form.legal_address],
        ['actual_address_en', () => form.actual_address],
        ['postal_address_en', () => form.postal_address],
        ['contact_person_en', () => form.contact_person],
        ['bank_name_en', () => primaryBankName()],
        ['signer_name_nominative_en', () => form.signer_name_nominative],
        ['signer_name_prepositional_en', () => form.signer_name_prepositional],
        ['signer_position_en', () => form.signer_position],
        ['signer_authority_basis_en', () => form.signer_authority_basis],
    ];

    let filled = 0;

    mapping.forEach(([targetKey, sourceGetter]) => {
        const before = String(form[targetKey] ?? '').trim();
        const next = transliteratedFieldValue(before, sourceGetter(), overwrite);

        if (next !== before) {
            form[targetKey] = next;
            filled += 1;
        }
    });

    englishTransliterationHint.value = overwrite
        ? 'Латиница обновлена по русским полям.'
        : (filled > 0
            ? `Заполнено полей: ${filled}. Уже заполненные EN-поля не изменялись.`
            : 'Нечего заполнять: все EN-поля уже заполнены или нет русских данных.');
}

function toggleActivityType(activityType) {
    if (!Array.isArray(form.activity_types)) {
        form.activity_types = [];
    }

    if (form.activity_types.includes(activityType)) {
        form.activity_types = form.activity_types.filter((item) => item !== activityType);

        return;
    }

    form.activity_types.push(activityType);
}

function shouldLookupPartyByInn(normalizedInn) {
    if (!isCompleteContractorInn(normalizedInn)) {
        return false;
    }

    if (normalizedInn !== lastAutoFilledInn.value) {
        return true;
    }

    return String(form.full_name ?? '').trim() === '';
}

async function fetchPartySuggestions() {
    const query = form.inn.trim() || form.name.trim();

    if (query.length < 2) {
        return;
    }

    const lookup = (async () => {
        isInnLookupPending.value = true;

        try {
            const suggestion = await fetchContractorPartySuggestion(query);

            if (suggestion) {
                applyPartySuggestion(suggestion);
                lastAutoFilledInn.value = normalizedContractorInn(form.inn) || query;
            }

            await refreshDuplicateWarning();
        } catch (error) {
            console.error('DaData party suggestion error', error);
        } finally {
            isInnLookupPending.value = false;
        }
    })();

    activeInnLookup = lookup;

    try {
        await lookup;
    } finally {
        if (activeInnLookup === lookup) {
            activeInnLookup = null;
        }
    }
}

async function waitForActiveInnLookup() {
    if (activeInnLookup) {
        await activeInnLookup;
    }
}

function contractorNameForSubmit() {
    return String(form.name ?? '').trim();
}

function focusContractorSubmitIssue(field = 'name') {
    activeTab.value = field === 'name' || field === 'inn' || field === 'type' ? 'general' : activeTab.value;
}

function sanitizeOwnerIdForSubmit() {
    const ownerId = Number(form.owner_id);

    if (Number.isFinite(ownerId) && ownerId > 0) {
        form.owner_id = ownerId;

        return;
    }

    form.owner_id = defaultOwnerId();
}

function clearDuplicateWarning() {
    duplicateWarning.value = {
        message: '',
        contractorId: null,
        openUrl: null,
        canOpen: false,
    };
}

async function refreshDuplicateWarning() {
    if (!isCreating.value && selectedContractorId.value !== null) {
        const result = await fetchContractorDuplicateCheck({
            inn: form.inn,
            name: form.name,
            ignoreId: selectedContractorId.value,
        });

        if (result.duplicate) {
            duplicateWarning.value = {
                message: result.message ?? 'Контрагент с такими данными уже существует.',
                contractorId: result.contractor_id,
                openUrl: result.open_url,
                canOpen: Boolean(result.can_open),
            };

            return true;
        }

        clearDuplicateWarning();

        return false;
    }

    if (!isCompleteContractorInn(form.inn) && contractorNameForSubmit() === '') {
        clearDuplicateWarning();

        return false;
    }

    const result = await fetchContractorDuplicateCheck({
        inn: form.inn,
        name: form.name,
    });

    if (!result.duplicate) {
        clearDuplicateWarning();

        return false;
    }

    duplicateWarning.value = {
        message: result.message ?? 'Контрагент с такими данными уже существует.',
        contractorId: result.contractor_id,
        openUrl: result.open_url,
        canOpen: Boolean(result.can_open),
    };

    return true;
}

function handleContractorSubmitErrors(errors) {
    const duplicateMessage = errors?.inn || errors?.name;
    const firstError = duplicateMessage || Object.values(errors ?? {}).find((message) => String(message ?? '').trim() !== '');
    submitError.value = firstError ? String(firstError) : 'Не удалось сохранить контрагента.';

    if (duplicateMessage) {
        duplicateWarning.value = {
            message: String(duplicateMessage),
            contractorId: null,
            openUrl: null,
            canOpen: false,
        };
    }

    if (errors?.name || errors?.inn || errors?.type || errors?.owner_id) {
        focusContractorSubmitIssue('name');
    }
}

function applyPartySuggestion(suggestion) {
    applyContractorPartySuggestion(form, suggestion);
    inferAddressLinkFlagsFromForm();
    syncAddressLinkTargets();
}

async function fetchAddressSuggestions(field, value) {
    if (value.trim().length < 3) {
        addressSuggestions.value[field] = [];
        return;
    }

    try {
        const response = await fetch(`${route('contractors.suggest-address')}?query=${encodeURIComponent(value)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        addressSuggestions.value[field] = Array.isArray(data.suggestions) ? data.suggestions : [];
    } catch (error) {
        console.error('DaData address suggestion error', error);
        addressSuggestions.value[field] = [];
    }
}

function queueAddressLookup(field) {
    clearTimeout(addressTimers[field]);

    addressTimers[field] = window.setTimeout(() => {
        fetchAddressSuggestions(field, form[field] ?? '');
    }, 300);
}

function selectAddress(field, suggestion) {
    form[field] = suggestion.value ?? '';
    addressSuggestions.value[field] = [];
    syncAddressLinkTargets();
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function interactionChannelLabel(value) {
    return interactionChannels.find((channel) => channel.value === value)?.label ?? (value || '—');
}

function scrollToInteractionCard(index) {
    const element = document.getElementById(`contractor-interaction-${index}`);
    element?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function formatMoney(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value))} ${currency}`;
}

function contractorTypeLabel(value) {
    return contractorTypes.find((item) => item.value === value)?.label ?? value;
}

function paymentFormLabel(value) {
    return paymentFormOptions.value.find((item) => item.value === value)?.label ?? 'Не задано';
}

watch(() => form.inn, (inn) => {
    clearTimeout(innLookupTimer);
    clearDuplicateWarning();

    const normalizedInn = normalizedContractorInn(inn);

    if (isCompleteContractorInn(normalizedInn) && form.inn !== normalizedInn) {
        form.inn = normalizedInn;
    }

    if (!isCompleteContractorInn(normalizedInn)) {
        lastAutoFilledInn.value = '';

        return;
    }

    if (!shouldLookupPartyByInn(normalizedInn)) {
        return;
    }

    innLookupTimer = window.setTimeout(() => {
        fetchPartySuggestions();
    }, 500);
});

watch(() => form.name, () => {
    if (isCreating.value) {
        clearDuplicateWarning();
    }
});

function goToPage(pageNumber) {
    if (pageNumber < 1 || pageNumber > props.pagination.last_page) {
        return;
    }
    
    router.get(route('contractors.index', {
        page: pageNumber,
        search: effectiveIndexSearchQuery(search.value),
        type: '',
    }), {}, { preserveScroll: true });
}

</script>

<template>
    <div v-if="isMobileStandalone" class="space-y-4 pb-24">
        <section class="rounded-[28px] bg-zinc-900 px-5 py-6 text-white shadow-sm dark:bg-zinc-50 dark:text-zinc-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs uppercase tracking-[0.22em] text-white/60 dark:text-zinc-500">Мобильная база</div>
                    <h1 class="mt-3 text-2xl font-semibold">Контрагенты</h1>
                    <p class="mt-2 text-sm text-white/70 dark:text-zinc-600">
                        Быстрый поиск по базе клиентов и перевозчиков без desktop-карточки.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-2xl bg-white px-4 text-sm font-medium text-zinc-900 transition hover:bg-zinc-200 dark:bg-zinc-900 dark:text-zinc-50 dark:hover:bg-zinc-800"
                    @click="openCreateForm"
                >
                    <Plus class="h-4 w-4" />
                    Новый
                </button>
            </div>
        </section>

        <section
            v-if="selectedContractor"
            class="space-y-3 rounded-[24px] border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ selectedContractor.name }}</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ contractorTypeLabel(selectedContractor.type) }}
                    </div>
                </div>

                <span
                    class="shrink-0 rounded-full px-2 py-1 text-[11px] font-medium"
                    :class="contractorStatusBadge(selectedContractor).class"
                >
                    {{ contractorStatusBadge(selectedContractor).text }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs text-zinc-600 dark:text-zinc-300">
                <div>
                    <div class="text-zinc-400 dark:text-zinc-500">ИНН</div>
                    <div class="mt-1">{{ selectedContractor.inn || '—' }}</div>
                </div>
                <div>
                    <div class="text-zinc-400 dark:text-zinc-500">Телефон</div>
                    <div class="mt-1">{{ selectedContractor.phone || '—' }}</div>
                </div>
                <div>
                    <div class="text-zinc-400 dark:text-zinc-500">Email</div>
                    <div class="mt-1 break-all">{{ selectedContractor.email || '—' }}</div>
                </div>
                <div>
                    <div class="text-zinc-400 dark:text-zinc-500">Заказы</div>
                    <div class="mt-1">{{ totalOrdersCount }}</div>
                </div>
            </div>
        </section>

        <section class="space-y-3 rounded-[24px] border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Поиск по названию, ИНН, телефону"
                    class="w-full rounded-2xl border border-zinc-300 bg-white py-3 pl-10 pr-4 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                />
            </div>

            <div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                <span>Найдено: {{ pagination.total }}</span>
                <span>Всего: {{ contractors.length }}</span>
            </div>
        </section>

        <section class="space-y-3">
            <button
                v-for="contractor in contractors"
                :key="contractor.id"
                type="button"
                class="w-full rounded-[24px] border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                @click="openContractor(contractor.id)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ contractor.name }}
                        </div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ contractorTypeLabel(contractor.type) }}
                        </div>
                    </div>

                    <span
                        class="shrink-0 rounded-full px-2 py-1 text-[11px] font-medium"
                        :class="contractorStatusBadge(contractor).class"
                    >
                        {{ contractorStatusBadge(contractor).text }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-zinc-600 dark:text-zinc-300">
                    <div>
                        <div class="text-zinc-400 dark:text-zinc-500">ИНН</div>
                        <div class="mt-1">{{ contractor.inn || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-400 dark:text-zinc-500">Телефон</div>
                        <div class="mt-1">{{ contractor.phone || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-400 dark:text-zinc-500">Контакты</div>
                        <div class="mt-1">{{ contractor.contacts_count }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-400 dark:text-zinc-500">Заказы</div>
                        <div class="mt-1">{{ contractor.orders_count }}</div>
                    </div>
                </div>
            </button>

            <div
                v-if="contractors.length === 0"
                class="rounded-[24px] border border-dashed border-zinc-300 bg-white px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400"
            >
                По текущему запросу контрагенты не найдены.
            </div>
        </section>
    </div>

    <div v-else class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Реестр контрагентов на всю ширину экрана. Карточка открывается поверх таблицы."
            title="Контрагенты"
            :title-class="crmPageTitleSm"
        >
            <template #actions>
                <button
                    type="button"
                    :class="crmBtnCreate"
                    @click="openCreateForm"
                >
                    <Plus class="h-4 w-4" />
                    Добавить
                </button>
            </template>
        </CrmPageHeader>

        <div :class="crmGridPanel">
            <ContractorsGrid
                :rows="contractors"
                :available-columns="availableColumns"
                :role-columns-config="roleColumnsConfig"
                :user-id="userId"
                :users="users"
                @row-select="openContractor"
                @create-request="openCreateForm"
            />
        </div>

            <Modal :show="isContractorModalOpen" max-width="7xl" @close="closeContractorModal">
                <section :class="`${crmModalEntityShell} gap-3`">
                <div :class="crmWizardHeader">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            :class="crmWizardBack"
                            title="К реестру"
                            @click="closeContractorModal"
                        >
                            <X class="h-5 w-5" />
                            <span class="sr-only">К реестру</span>
                        </button>

                        <div class="min-w-0">
                            <h1 class="truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                                {{ isCreating ? 'Новый контрагент' : (selectedContractor?.name || 'Карточка контрагента') }}
                            </h1>
                            <div class="mt-1 flex flex-wrap gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                <span v-if="selectedContractor?.inn">ИНН {{ selectedContractor.inn }}</span>
                                <span v-if="selectedContractor?.phone">{{ selectedContractor.phone }}</span>
                                <span v-if="selectedContractor?.email">{{ selectedContractor.email }}</span>
                                <span v-if="selectedContractorId !== null">Заказы: {{ totalOrdersCount }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                        <p
                            v-if="submitError"
                            class="w-full text-right text-sm text-rose-600 dark:text-rose-400"
                        >
                            {{ submitError }}
                        </p>
                        <button
                            type="button"
                            :class="crmBtnNeutral"
                            @click="resetToSelected"
                        >
                            Сбросить
                        </button>
                        <button
                            v-if="selectedContractorId !== null"
                            type="button"
                            :class="crmBtnDangerMuted"
                            @click="removeContractor"
                        >
                            <Trash2 class="h-4 w-4" />
                            Удалить
                        </button>
                        <button
                            type="button"
                            :class="crmBtnCreate"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-b border-zinc-200 bg-white px-5 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in visibleTabs"
                            :key="tab.key"
                            type="button"
                            class="inline-flex items-center gap-2 text-sm transition-colors"
                            :class="crmTabButtonClasses(activeTab === tab.key)"
                            @click="activeTab = tab.key"
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div :class="crmWizardBody">
                    <div v-if="false" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Краткое название</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            :class="crmFieldFluid"
                                        />
                                        <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Полное название</label>
                                        <input
                                            v-model="form.full_name"
                                            type="text"
                                            :class="crmFieldFluid"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Профиль контрагента</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Роль компании в работе и внутренние признаки карточки.
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Тип контрагента</label>
                                            <select
                                                v-model="form.type"
                                                :class="crmFieldFluid"
                                            >
                                                <option v-for="type in contractorTypes" :key="type.value" :value="type.value">
                                                    {{ type.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="space-y-3">
                                            <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-100">
                                                <input v-model="form.is_own_company" type="checkbox" :class="crmCheckbox" />
                                                Своя компания
                                            </label>
                                            <a
                                                v-if="canDownloadPartnerCard"
                                                :href="route('contractors.partner-card', selectedContractorId)"
                                                class="inline-flex w-full items-center justify-center gap-2 border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-900 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100 dark:hover:bg-sky-900/60"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <FileDown class="h-4 w-4 shrink-0" />
                                                Карта партнёра
                                            </a>
                                            <p v-if="isOwnCompanyProfile" class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Для своей компании не используются статус работы, архив, проверка и лимиты задолженности.
                                            </p>
                                            <template v-if="!isOwnCompanyProfile">
                                                <div class="space-y-1">
                                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Статус работы</label>
                                                    <select
                                                        v-model="form.work_status"
                                                        :class="crmFieldFluid"
                                                        :disabled="!form.is_active"
                                                    >
                                                        <option
                                                            v-if="form.work_status === 'work_pause' && !workStatusOptions.some((o) => o.value === 'work_pause')"
                                                            value="work_pause"
                                                        >
                                                            Пауза в работе (авто)
                                                        </option>
                                                        <option v-for="option in workStatusOptions" :key="option.value" :value="option.value">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                    <p v-if="form.work_status === 'work_pause'" class="text-xs text-amber-700 dark:text-amber-300">
                                                        Пауза назначается автоматически, если заказов не было более 3 месяцев.
                                                    </p>
                                                </div>
                                                <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-100">
                                                    <input
                                                        type="checkbox"
                                                        :class="crmCheckbox"
                                                        :checked="!form.is_active"
                                                        @change="form.is_active = !$event.target.checked"
                                                    />
                                                    В архиве
                                                </label>
                                                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ verificationState.is_verified ? 'Проверен' : 'Не проверен' }}
                                                    </div>
                                                    <p v-if="verificationState.is_verified && verificationState.verification_valid_until" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                        Действует до {{ formatVerificationDate(verificationState.verification_valid_until) }}
                                                    </p>
                                                    <p v-else class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                        Проверка сбрасывается, если прошло более 3 месяцев. Обновите скоринг на вкладке «Условия сотрудничества».
                                                    </p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ИНН</label>
                                            <input
                                                v-model="form.inn"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                После ввода корректного ИНН DaData подставит реквизиты автоматически.
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Основной телефон</label>
                                            <input
                                                v-model="form.phone"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                    </div>
                                    <div v-if="isInnLookupPending" class="inline-flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        <Search class="h-4 w-4 animate-pulse" />
                                        Идёт поиск реквизитов в DaData...
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Email</label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            :class="crmFieldFluid"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Сайт</label>
                                        <input
                                            v-model="form.website"
                                            type="text"
                                            :class="crmFieldFluid"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Домены для синхронизации почты</label>
                                    <textarea
                                        v-model="mailSyncDomainsText"
                                        rows="3"
                                        placeholder="exwill.ru&#10;logistics.exwill.ru"
                                        :class="crmFieldFluid"
                                    />
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        Письма с этих доменов попадут в CRM. Для Gmail и других публичных почт укажите полный адрес в Email или контактах — домен gmail.com сюда не добавляйте.
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Краткое описание контрагента</label>
                                    <textarea
                                        v-model="form.short_description"
                                        rows="4"
                                        placeholder="Коротко: чем занимается компания, сильные стороны, профиль работы"
                                        :class="crmFieldFluid"
                                    />
                                </div>
                            </div>

                            <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Основной контакт</div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Контактное лицо</label>
                                    <input
                                        v-model="form.contact_person"
                                        type="text"
                                        :class="crmFieldFluid"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Должность</label>
                                    <input
                                        v-model="form.contact_person_position"
                                        type="text"
                                        :class="crmFieldFluid"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Телефон</label>
                                    <input
                                        v-model="form.contact_person_phone"
                                        type="text"
                                        :class="crmFieldFluid"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Email</label>
                                    <input
                                        v-model="form.contact_person_email"
                                        type="email"
                                        :class="crmFieldFluid"
                                    />
                                </div>
                                </div>
                    </div>
                </div>
            </div>
        </div>

                    <div v-if="activeTab === 'general'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Краткое название</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            :class="crmFieldFluid"
                                        />
                                        <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Полное название</label>
                                        <input
                                            v-model="form.full_name"
                                            type="text"
                                            :class="crmFieldFluid"
                                        />
                                    </div>
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Карточка компании</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Основные данные контрагента для повседневной работы менеджера.
                                            </div>
                                        </div>
                                        <button
                                            v-if="selectedContractor?.print_form_profile?.label"
                                            type="button"
                                            class="rounded-full border px-3 py-1 text-xs font-medium"
                                            :class="printFormProfileBadgeClass"
                                            :title="selectedContractor.print_form_profile.summary"
                                            @click="openPrintFormSection"
                                        >
                                            {{ selectedContractor.print_form_profile.label }}
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ИНН</label>
                                            <input
                                                v-model="form.inn"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                После ввода корректного ИНН DaData попробует заполнить реквизиты автоматически.
                                            </div>
                                            <div v-if="form.errors.inn" class="text-sm text-rose-600">{{ form.errors.inn }}</div>
                                            <div
                                                v-else-if="duplicateWarning.message"
                                                class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
                                            >
                                                <p>{{ duplicateWarning.message }}</p>
                                                <button
                                                    v-if="duplicateWarning.canOpen && duplicateWarning.contractorId"
                                                    type="button"
                                                    class="mt-2 text-sm font-medium text-sky-700 underline underline-offset-2 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100"
                                                    @click="openContractor(duplicateWarning.contractorId)"
                                                >
                                                    Открыть существующую карточку
                                                </button>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Основной телефон</label>
                                            <input
                                                v-model="form.phone"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Email</label>
                                            <input
                                                v-model="form.email"
                                                type="email"
                                                :class="crmFieldFluid"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Сайт</label>
                                            <input
                                                v-model="form.website"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                    </div>

                                    <div v-if="isInnLookupPending" class="mt-4 inline-flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        <Search class="h-4 w-4 animate-pulse" />
                                        Идёт поиск реквизитов в DaData...
                                    </div>
                                </div>

                                <div class="space-y-2 border border-zinc-200 p-4 dark:border-zinc-800">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Краткое описание</label>
                                    <textarea
                                        v-model="form.short_description"
                                        rows="4"
                                        placeholder="Коротко: чем занимается компания, ключевой профиль, сильные стороны и особенности работы."
                                        :class="crmFieldFluid"
                                    />
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Профиль контрагента</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Роль компании в работе и внутренние признаки карточки.
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Тип контрагента</label>
                                        <select
                                            v-model="form.type"
                                            :class="crmFieldFluid"
                                        >
                                            <option v-for="type in contractorTypes" :key="type.value" :value="type.value">
                                                {{ type.label }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.type" class="text-sm text-rose-600">{{ form.errors.type }}</div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ATI ID</label>
                                        <input
                                            v-model="form.ati_id"
                                            type="text"
                                            :class="crmFieldFluid"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Владелец</label>
                                        <select
                                            v-model.number="form.owner_id"
                                            :class="crmFieldFluid"
                                        >
                                            <option :value="null">Не назначен</option>
                                            <option v-for="user in users" :key="user.id" :value="user.id">
                                                {{ user.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-3">
                                        <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-100">
                                            <input v-model="form.is_own_company" type="checkbox" :class="crmCheckbox" />
                                            Своя компания
                                        </label>
                                        <a
                                            v-if="canDownloadPartnerCard"
                                            :href="route('contractors.partner-card', selectedContractorId)"
                                            class="inline-flex w-full items-center justify-center gap-2 border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-900 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100 dark:hover:bg-sky-900/60"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <FileDown class="h-4 w-4 shrink-0" />
                                            Карта партнёра
                                        </a>
                                        <p v-if="isOwnCompanyProfile" class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Для своей компании не используются статус работы, архив, проверка и лимиты задолженности.
                                        </p>
                                        <template v-if="!isOwnCompanyProfile">
                                            <div class="space-y-1">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Статус работы</label>
                                                <select
                                                    v-model="form.work_status"
                                                    :class="crmFieldFluid"
                                                    :disabled="!form.is_active"
                                                >
                                                    <option
                                                        v-if="form.work_status === 'work_pause' && !workStatusOptions.some((o) => o.value === 'work_pause')"
                                                        value="work_pause"
                                                    >
                                                        Пауза в работе (авто)
                                                    </option>
                                                    <option v-for="option in workStatusOptions" :key="`desktop-${option.value}`" :value="option.value">
                                                        {{ option.label }}
                                                    </option>
                                                </select>
                                                <p v-if="form.work_status === 'work_pause'" class="text-xs text-amber-700 dark:text-amber-300">
                                                    Пауза назначается автоматически, если заказов не было более 3 месяцев.
                                                </p>
                                                <div v-if="form.errors.work_status" class="text-sm text-rose-600">{{ form.errors.work_status }}</div>
                                            </div>
                                            <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-100">
                                                <input
                                                    type="checkbox"
                                                    :class="crmCheckbox"
                                                    :checked="!form.is_active"
                                                    @change="form.is_active = !$event.target.checked"
                                                />
                                                В архиве
                                            </label>
                                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ verificationState.is_verified ? 'Проверен' : 'Не проверен' }}
                                                </div>
                                                <p v-if="verificationState.is_verified && verificationState.verification_valid_until" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                    Действует до {{ formatVerificationDate(verificationState.verification_valid_until) }}
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Виды деятельности</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Выбор из глобального справочника для сегментации и отчётности.
                                        </div>
                                    </div>

                                    <details class="group border border-zinc-200 bg-zinc-50/70 dark:border-zinc-700 dark:bg-zinc-950/40">
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-sm text-zinc-700 dark:text-zinc-200">
                                            <span class="truncate">{{ activityTypeDropdownSummary }}</span>
                                            <span class="text-xs text-zinc-400 transition group-open:rotate-180">⌄</span>
                                        </summary>

                                        <div class="border-t border-zinc-200 p-3 dark:border-zinc-700">
                                            <div v-if="availableActivityTypeOptions.length > 0" class="grid grid-cols-1 gap-2">
                                                <label
                                                    v-for="activityType in availableActivityTypeOptions"
                                                    :key="activityType"
                                                    class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200"
                                                >
                                                    <input
                                                        :checked="form.activity_types.includes(activityType)"
                                                        type="checkbox"
                                                        class="rounded border-zinc-300"
                                                        @change="toggleActivityType(activityType)"
                                                    />
                                                    <span>{{ activityType }}</span>
                                                </label>
                                            </div>
                                            <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                                                В справочнике пока нет видов деятельности.
                                            </div>
                                        </div>
                                    </details>

                                    <div v-if="form.errors.activity_types" class="text-sm text-rose-600">{{ form.errors.activity_types }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'cooperation'" class="space-y-4">
                        <ContractorPrintFormSection
                            v-if="selectedContractorId && selectedContractor?.print_form_editor?.enabled"
                            ref="printFormSectionRef"
                            :contractor-id="selectedContractorId"
                            :editor="selectedContractor.print_form_editor"
                        />

                        <div
                            class="grid grid-cols-1 gap-4"
                            :class="isOwnCompanyProfile ? '' : 'lg:grid-cols-[minmax(0,1fr)_minmax(260px,18rem)]'"
                        >
                            <div class="min-w-0 space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Финансовые условия по умолчанию</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Эти значения подставляются в заказ при выборе контрагента.
                                        </div>
                                    </div>
                                    <label v-if="!isOwnCompanyProfile" class="flex items-center gap-2 text-sm text-zinc-900 dark:text-zinc-100">
                                        <input v-model="form.stop_on_limit" type="checkbox" :class="crmCheckbox" />
                                        Стоп-работа по лимиту
                                    </label>
                                </div>

                                <div v-if="!isOwnCompanyProfile" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="space-y-2 md:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Лимит задолженности</label>
                                        <input v-model="form.debt_limit" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Валюта</label>
                                        <select v-model="form.debt_limit_currency" :class="crmFieldFluid">
                                            <option v-for="option in currencySelectOptions" :key="option.value" :value="option.value">{{ option.value }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid w-full min-w-0 grid-cols-1 gap-4">
                                    <div
                                        v-if="form.type === 'customer' || form.type === 'both'"
                                        class="min-w-0 w-full space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40"
                                    >
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Форма оплаты заказчика</label>
                                            <select v-model="form.default_customer_payment_form" :class="crmFieldFluid">
                                                <option value="">Не задана</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <PaymentTermsWizardBlock
                                            v-model:summary-text="form.default_customer_payment_term"
                                            :schedule="form.default_customer_payment_schedule"
                                            :editable-summary="true"
                                        />
                                        <ContractorDefaultNormsPenaltiesFields
                                            v-model="form.default_customer_norms_penalties"
                                            :currency-options="currencySelectOptions"
                                            description="Подставляются в заказ на вкладке «Нормативы / штрафы» (блок заказчика)."
                                        />
                                    </div>

                                    <div
                                        v-if="form.type === 'carrier' || form.type === 'both'"
                                        class="min-w-0 w-full space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40"
                                    >
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Форма оплаты перевозчика</label>
                                            <select v-model="form.default_carrier_payment_form" :class="crmFieldFluid">
                                                <option value="">Не задана</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <PaymentTermsWizardBlock
                                            v-model:summary-text="form.default_carrier_payment_term"
                                            :schedule="form.default_carrier_payment_schedule"
                                            :editable-summary="true"
                                        />
                                        <ContractorDefaultNormsPenaltiesFields
                                            v-model="form.default_carrier_norms_penalties"
                                            :currency-options="currencySelectOptions"
                                            description="Подставляются в заказ на вкладке «Нормативы / штрафы» (по каждому плечу перевозчика)."
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Условия сотрудничества</label>
                                    <textarea v-model="form.cooperation_terms_notes" rows="4" :class="crmFieldFluid"></textarea>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Требования к перевозке</label>
                                    <textarea
                                        v-model="transportRequirementsText"
                                        rows="6"
                                        placeholder="По одному требованию на строку"
                                        :class="crmFieldFluid"
                                    />
                                </div>
                            </div>

                            <div v-if="!isOwnCompanyProfile" class="min-w-0 space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Кредитный статус</div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Текущая задолженность</span>
                                        <span class="font-medium">{{ formatMoney(selectedContractor?.current_debt, selectedContractor?.debt_limit_currency || form.debt_limit_currency) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Лимит</span>
                                        <span class="font-medium">{{ formatMoney(form.debt_limit, form.debt_limit_currency) }}</span>
                                    </div>
                                    <div v-if="form.type === 'customer' || form.type === 'both'" class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Форма оплаты заказчика</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_customer_payment_form) }}</span>
                                    </div>
                                    <div v-if="form.type === 'carrier' || form.type === 'both'" class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Форма оплаты перевозчика</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_carrier_payment_form) }}</span>
                                    </div>
                                </div>

                                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Согласование лимита</div>
                                    </div>

                                    <div
                                        v-if="limitApprovalState?.assessment_id"
                                        class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                                    >
                                        На согласовании у руководителя
                                        <span v-if="limitApprovalState.submission_reason_label"> — {{ limitApprovalState.submission_reason_label }}</span>
                                        <span v-if="limitApprovalState.submitted_at"> ({{ formatVerificationDate(limitApprovalState.submitted_at) }})</span>
                                    </div>

                                    <div v-else-if="canRequestLimitApproval" class="mb-3 space-y-2">
                                        <p v-if="limitApprovalReasonLabel" class="text-xs text-zinc-600 dark:text-zinc-400">
                                            Основание: {{ limitApprovalReasonLabel }}
                                        </p>
                                        <button
                                            type="button"
                                            class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-700 disabled:opacity-50"
                                            :disabled="limitApprovalSubmitLoading || selectedContractorId === null"
                                            @click="submitLimitApprovalRequest"
                                        >
                                            {{ limitApprovalSubmitLoading ? 'Отправка…' : 'Отправить на согласование' }}
                                        </button>
                                    </div>

                                    <div v-else class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        Отправка на согласование нужна для новых карточек, после истечения проверки или при нехватке лимита.
                                    </div>
                                </div>

                                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Скоринг контрагента (Checko)</div>
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-zinc-600 underline underline-offset-2 hover:text-zinc-900 disabled:opacity-50 dark:text-zinc-400 dark:hover:text-zinc-100"
                                            :disabled="contractorScoringLoading || selectedContractorId === null"
                                            @click="loadContractorScoring({ refresh: true })"
                                        >
                                            Обновить данные
                                        </button>
                                    </div>

                                    <div v-if="!selectedContractor?.inn" class="text-xs text-zinc-500 dark:text-zinc-400">Укажите ИНН в реквизитах — без него скоринг недоступен.</div>

                                    <div v-else-if="contractorScoringLoading" class="text-xs text-zinc-500 dark:text-zinc-400">Загрузка данных...</div>

                                    <div v-else-if="contractorScoringError" class="whitespace-pre-wrap text-xs text-rose-600 dark:text-rose-400">{{ contractorScoringError }}</div>

                                    <div v-else-if="contractorScoring?.ok" class="space-y-3 text-xs">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold" :class="scoringGradeClass(contractorScoring.grade)">
                                                Класс {{ contractorScoring.grade }}
                                            </span>
                                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ contractorScoring.score }} / 100</span>
                                            <span v-if="contractorScoring.tier_label" class="rounded-full bg-zinc-100 px-2 py-0.5 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                {{ contractorScoring.tier_label }}
                                            </span>
                                            <span v-if="contractorScoring.checko_from_cache" class="text-zinc-500 dark:text-zinc-400">кэш Checko</span>
                                        </div>

                                        <p v-if="scoringComponentsLine" class="text-zinc-600 dark:text-zinc-300">
                                            {{ scoringComponentsLine }}
                                        </p>

                                        <div v-if="contractorScoring.company_name" class="text-zinc-600 dark:text-zinc-300">
                                            {{ contractorScoring.company_name }}
                                        </div>

                                        <div v-if="contractorScoring.egr_status" class="text-zinc-500 dark:text-zinc-400">
                                            {{ scoringEgrStatusLabel(contractorScoring.egr_status) }}
                                            <span v-if="contractorScoring.status_text"> — «{{ contractorScoring.status_text }}»</span>
                                        </div>

                                        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900/50">
                                            <dt class="text-zinc-500 dark:text-zinc-400">Отсрочка</dt>
                                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">
                                                до {{ contractorScoring.recommended_postpayment_days }} дн.
                                            </dd>
                                            <dt class="text-zinc-500 dark:text-zinc-400">Лимит</dt>
                                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">
                                                {{ formatMoney(contractorScoring.recommended_debt_limit_rub ?? 0, 'RUB') }}
                                            </dd>
                                        </dl>

                                        <p v-if="contractorScoring.summary" class="text-[10px] leading-snug text-zinc-400 dark:text-zinc-500">
                                            {{ contractorScoring.summary }}
                                        </p>

                                        <div v-if="contractorScoring.assessment_id && canApproveContractorLimit" class="space-y-2">
                                            <p v-if="contractorScoringSuccess" class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                {{ contractorScoringSuccess }}
                                            </p>
                                            <button
                                                type="button"
                                                class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                                :disabled="contractorScoringConfirmLoading"
                                                @click="applyApprovedScoringLimits"
                                            >
                                                {{ contractorScoringConfirmLoading ? 'Сохранение…' : 'Применить условия' }}
                                            </button>
                                            <p v-if="scoringFormDiffersFromDraft" class="text-[11px] leading-snug text-zinc-500 dark:text-zinc-400">
                                                Лимит или отсрочка изменены относительно рекомендации — сохранятся ваши значения.
                                            </p>
                                        </div>

                                        <ul
                                            v-if="scoringDisplayFactors.length"
                                            class="list-disc space-y-1 border-t border-zinc-200 pt-2 pl-4 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300"
                                        >
                                            <li v-for="(factor, idx) in scoringDisplayFactors" :key="`factor-${idx}`">{{ factor }}</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="rounded-xl border px-3 py-3 text-sm" :class="selectedContractor?.debt_limit_reached ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'">
                                    {{ selectedContractor?.debt_limit_reached ? 'Лимит достигнут. Новые заказы должны блокироваться.' : 'По текущим данным лимит не достигнут.' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'requisites'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2 xl:items-start">
                            <div class="space-y-4">
                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">Регистрация</div>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">КПП</label>
                                            <input
                                                v-model="form.kpp"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Орг.-правовая форма</label>
                                            <select
                                                v-model="form.legal_form"
                                                :class="crmFieldFluid"
                                            >
                                                <option value="">Не указана</option>
                                                <option v-for="option in legalFormOptions" :key="option.value" :value="option.value">
                                                    {{ legalFormLabelByValue[option.value] ?? option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ОГРН</label>
                                            <input
                                                v-model="form.ogrn"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ОКПО</label>
                                            <input
                                                v-model="form.okpo"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">ЭДО</div>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Провайдер ЭДО</label>
                                            <select
                                                v-model="form.edo_provider"
                                                :class="crmFieldFluid"
                                            >
                                                <option value="">Не указан</option>
                                                <option
                                                    v-for="option in edoProviderOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <p v-if="form.errors.edo_provider" class="text-xs text-rose-600">{{ form.errors.edo_provider }}</p>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Номер ЭДО</label>
                                            <input
                                                v-model="form.edo_number"
                                                type="text"
                                                :class="crmFieldFluid"
                                                placeholder="Идентификатор участника ЭДО"
                                            />
                                            <p v-if="form.errors.edo_number" class="text-xs text-rose-600">{{ form.errors.edo_number }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">Адреса</div>
                                    <div class="min-w-0 space-y-3">
                                        <div class="relative space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Юридический адрес</label>
                                            <textarea v-model="form.legal_address" rows="2" :class="crmFieldFluid" @input="queueAddressLookup('legal_address')"></textarea>
                                            <div v-if="addressSuggestions.legal_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                                <button v-for="suggestion in addressSuggestions.legal_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('legal_address', suggestion)">
                                                    {{ suggestion.value }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="relative space-y-2">
                                            <div class="flex flex-wrap items-end justify-between gap-x-3 gap-y-1">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Фактический адрес</label>
                                                <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                                                    <input
                                                        type="checkbox"
                                                        class="rounded border-zinc-300 dark:border-zinc-600"
                                                        :checked="actualMatchesLegal"
                                                        @change="toggleActualMatchesLegal"
                                                    />
                                                    Совпадает с юридическим
                                                </label>
                                            </div>
                                            <template v-if="!actualMatchesLegal">
                                                <textarea
                                                    v-model="form.actual_address"
                                                    rows="2"
                                                    :class="crmFieldFluid"
                                                    @input="queueAddressLookup('actual_address')"
                                                ></textarea>
                                                <div v-if="addressSuggestions.actual_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                                    <button v-for="suggestion in addressSuggestions.actual_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('actual_address', suggestion)">
                                                        {{ suggestion.value }}
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="relative space-y-2">
                                            <div class="flex flex-wrap items-end justify-between gap-x-3 gap-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Почтовый адрес</label>
                                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                                            :checked="postalMatchesLegal"
                                                            @change="togglePostalMatchesLegal"
                                                        />
                                                        Совпадает с юридическим
                                                    </label>
                                                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                                            :checked="postalMatchesActual"
                                                            @change="togglePostalMatchesActual"
                                                        />
                                                        Совпадает с фактическим
                                                    </label>
                                                </div>
                                            </div>
                                            <template v-if="!postalMatchesLegal && !postalMatchesActual">
                                                <textarea
                                                    v-model="form.postal_address"
                                                    rows="2"
                                                    :class="crmFieldFluid"
                                                    @input="queueAddressLookup('postal_address')"
                                                ></textarea>
                                                <div v-if="addressSuggestions.postal_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                                    <button v-for="suggestion in addressSuggestions.postal_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('postal_address', suggestion)">
                                                        {{ suggestion.value }}
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">Подписант</div>
                                    <div class="max-w-xl space-y-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ФИО, именительный падеж</label>
                                            <input
                                                v-model="form.signer_name_nominative"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ФИО, родительный падеж</label>
                                            <input
                                                v-model="form.signer_name_prepositional"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Должность</label>
                                            <input
                                                v-model="form.signer_position"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Право подписи на основании</label>
                                            <input
                                                v-model="form.signer_authority_basis"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">Банковские реквизиты</div>
                                <div class="mb-3 flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                                        <input v-model="form.is_non_resident" type="checkbox" :class="crmCheckbox" />
                                        Нерезидент
                                    </label>
                                    <button type="button" class="inline-flex items-center gap-1 border border-zinc-300 px-2 py-1 text-xs text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-800" @click="addBankAccount">
                                        <Plus class="h-3.5 w-3.5" />
                                        Добавить счёт
                                    </button>
                                </div>
                                <div
                                    v-if="form.is_non_resident"
                                    class="mb-4 space-y-3 rounded border border-zinc-200 bg-zinc-50/80 p-3 dark:border-zinc-700 dark:bg-zinc-900/50"
                                >
                                    <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Банк-корреспондент</div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Наименование банка-корреспондента</label>
                                        <input
                                            v-model="form.non_resident_corr_bank_name"
                                            type="text"
                                            :class="crmFieldFluid"
                                        />
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">SWIFT / BIC</label>
                                            <input
                                                v-model="form.non_resident_corr_bank_swift"
                                                type="text"
                                                maxlength="11"
                                                class="w-full uppercase border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                                @input="form.non_resident_corr_bank_swift = String(form.non_resident_corr_bank_swift || '').toUpperCase().replace(/\s+/g, '')"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">CNAPS CODE</label>
                                            <input
                                                v-model="form.cnaps_code"
                                                type="text"
                                                maxlength="20"
                                                inputmode="numeric"
                                                autocomplete="off"
                                                :class="crmFieldFluid"
                                                @input="form.cnaps_code = String(form.cnaps_code || '').replace(/\D/g, '')"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Расчётный счёт</label>
                                            <input
                                                v-model="form.non_resident_corr_settlement_account"
                                                type="text"
                                                maxlength="34"
                                                inputmode="numeric"
                                                autocomplete="off"
                                                :class="crmFieldFluid"
                                                @input="form.non_resident_corr_settlement_account = String(form.non_resident_corr_settlement_account || '').replace(/\D/g, '')"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Счёт в банке-корреспонденте</label>
                                            <input
                                                v-model="form.non_resident_corr_bank_account"
                                                type="text"
                                                :class="crmFieldFluid"
                                            />
                                        </div>
                                    </div>
                                    <p v-if="form.errors.non_resident_corr_bank_name" class="text-xs text-rose-600">{{ form.errors.non_resident_corr_bank_name }}</p>
                                    <p v-if="form.errors.non_resident_corr_bank_swift" class="text-xs text-rose-600">{{ form.errors.non_resident_corr_bank_swift }}</p>
                                    <p v-if="form.errors.non_resident_corr_settlement_account" class="text-xs text-rose-600">{{ form.errors.non_resident_corr_settlement_account }}</p>
                                    <p v-if="form.errors.non_resident_corr_bank_account" class="text-xs text-rose-600">{{ form.errors.non_resident_corr_bank_account }}</p>
                                    <p v-if="form.errors.cnaps_code" class="text-xs text-rose-600">{{ form.errors.cnaps_code }}</p>
                                </div>
                                <div class="space-y-4">
                                    <div
                                        v-for="(account, bankIndex) in form.bank_accounts"
                                        :key="account.id || bankIndex"
                                        class="space-y-3 border border-zinc-200 p-3 dark:border-zinc-700"
                                    >
                                        <div class="flex flex-wrap items-center gap-3">
                                            <label class="inline-flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                                                <input :checked="account.is_primary" type="radio" name="primary_bank_account" class="border-zinc-300" @change="setPrimaryBankAccount(bankIndex)" />
                                                Основной
                                            </label>
                                            <button type="button" class="text-xs text-rose-600 disabled:text-zinc-400" :disabled="form.bank_accounts.length <= 1" @click="removeBankAccount(bankIndex)">
                                                Удалить
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Метка счёта</label>
                                                <input v-model="account.label" type="text" :class="crmFieldFluid" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Страна банка</label>
                                                <input v-model="account.country_code" type="text" maxlength="2" class="w-full uppercase border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="account.country_code = String(account.country_code || '').toUpperCase().slice(0, 2)" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Валюта</label>
                                                <select v-model="account.currency" :class="crmFieldFluid">
                                                    <option v-for="option in currencySelectOptions" :key="option.value" :value="option.value">{{ option.value }}</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">SWIFT</label>
                                                <input v-model="account.swift" type="text" maxlength="11" class="w-full uppercase border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Банк</label>
                                            <input v-model="account.bank_name" type="text" :class="crmFieldFluid" />
                                        </div>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">БИК</label>
                                                <input v-model="account.bik" type="text" maxlength="9" :class="crmFieldFluid" @input="account.bik = String(account.bik || '').replace(/\\D/g, ''); scheduleBankLookup(bankIndex)" />
                                                <div v-if="bankLookupLoading[bankIndex]" class="text-xs text-zinc-500 dark:text-zinc-400">Поиск банка по БИК...</div>
                                                <div v-if="bankLookupErrors[bankIndex]" class="text-xs text-rose-600">{{ bankLookupErrors[bankIndex] }}</div>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">IBAN</label>
                                                <input v-model="account.iban" type="text" class="w-full uppercase border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="account.iban = String(account.iban || '').toUpperCase().replace(/\\s+/g, '')" />
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Расчётный счёт / Account</label>
                                                <input v-model="account.account_number" type="text" :class="crmFieldFluid" @input="account.account_number = String(account.account_number || '').replace(/\\s+/g, '')" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Корр. счёт</label>
                                                <input v-model="account.correspondent_account" type="text" :class="crmFieldFluid" @input="account.correspondent_account = String(account.correspondent_account || '').replace(/\\D/g, '')" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                <input v-model="form.has_english_requisites" type="checkbox" :class="crmCheckbox" />
                                Реквизиты на английском
                            </label>
                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                Для печатных форм: переменные с суффиксом <span class="font-mono">_en</span> (например, <span class="font-mono">${customer.full_name_en}</span>).
                            </p>
                            <div v-if="form.has_english_requisites" class="mt-4 space-y-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 border border-zinc-300 px-3 py-1.5 text-xs text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-800"
                                        @click="fillEnglishRequisitesFromRussian(false)"
                                    >
                                        Заполнить латиницей (пустые поля)
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 border border-zinc-300 px-3 py-1.5 text-xs text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-800"
                                        @click="fillEnglishRequisitesFromRussian(true)"
                                    >
                                        Перезаписать все EN-поля
                                    </button>
                                </div>
                                <p v-if="englishTransliterationHint" class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ englishTransliterationHint }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Автозаполнение — транслитерация по ГОСТ (паспортная схема). Для официальных наименований в договорах при необходимости поправьте вручную.
                                </p>
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Краткое наименование (EN)</label>
                                        <input v-model="form.name_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Полное наименование (EN)</label>
                                        <input v-model="form.full_name_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2 lg:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Юридический адрес (EN)</label>
                                        <textarea v-model="form.legal_address_en" rows="2" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2 lg:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Фактический адрес (EN)</label>
                                        <textarea v-model="form.actual_address_en" rows="2" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2 lg:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Почтовый адрес (EN)</label>
                                        <textarea v-model="form.postal_address_en" rows="2" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Контактное лицо (EN)</label>
                                        <input v-model="form.contact_person_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Банк (EN)</label>
                                        <input v-model="form.bank_name_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Подписант, именительный (EN)</label>
                                        <input v-model="form.signer_name_nominative_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Подписант, родительный (EN)</label>
                                        <input v-model="form.signer_name_prepositional_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Должность подписанта (EN)</label>
                                        <input v-model="form.signer_position_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2 lg:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Право подписи на основании (EN)</label>
                                        <input v-model="form.signer_authority_basis_en" type="text" :class="crmFieldFluid" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="activeTab === 'contacts'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Отдельные контакты удобно хранить отдельно от основной карточки компании. Основной контакт подставляется в заявку и печатные формы, если в заказе не указан другой.
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-800" @click="addContact">
                                <Plus class="h-4 w-4" />
                                Добавить контакт
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(contact, index) in form.contacts" :key="`contact-${index}`" class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Контакт #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.contacts, index)">
                                        Удалить
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <div class="space-y-2 xl:col-start-1 xl:row-start-1">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">ФИО</label>
                                        <input v-model="contact.full_name" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2 xl:col-start-2 xl:row-start-1">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Должность</label>
                                        <input v-model="contact.position" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="flex flex-col gap-3 md:col-span-2 xl:col-span-1 xl:col-start-3 xl:row-start-1 xl:row-span-2">
                                        <label class="flex items-center gap-2 text-sm text-zinc-900 dark:text-zinc-100">
                                            <input v-model="contact.is_primary" type="checkbox" :class="crmCheckbox" />
                                            Основной контакт (для заявок и печати)
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-zinc-900 dark:text-zinc-100">
                                            <input
                                                v-model="contact.is_decision_maker"
                                                type="checkbox"
                                                :class="crmCheckbox"
                                                @change="contact.role_in_deal = contact.is_decision_maker ? 'decision_maker' : (contact.role_in_deal === 'decision_maker' ? 'unknown' : contact.role_in_deal)"
                                            />
                                            ЛПР
                                        </label>
                                    </div>
                                    <div class="space-y-2 xl:col-start-1 xl:row-start-3 md:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Роль в сделке</label>
                                        <select v-model="contact.role_in_deal" :class="crmFieldFluid">
                                            <option v-for="option in portraitOptions.role_in_deal ?? []" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2 xl:col-start-2 xl:row-start-3 md:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Заметки по общению</label>
                                        <input v-model="contact.communication_notes" type="text" :class="crmFieldFluid" placeholder="Когда звонить, стиль, табу-темы" />
                                    </div>
                                    <div class="space-y-2 xl:col-start-1 xl:row-start-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Телефон</label>
                                        <input v-model="contact.phone" type="text" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2 xl:col-start-2 xl:row-start-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Email</label>
                                        <input v-model="contact.email" type="email" :class="crmFieldFluid" />
                                    </div>
                                    <div class="col-span-full space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Комментарий</label>
                                        <input v-model="contact.notes" type="text" :class="crmFieldFluid" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.contacts.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Отдельные контакты пока не добавлены.
                            </div>
                        </div>
                    </div>

                    <ContractorPortraitTab
                        v-else-if="activeTab === 'portrait' && selectedContractorId"
                        :contractor-id="selectedContractorId"
                        :contractor-type="props.selectedContractor?.type ?? null"
                        :portrait="portraitForTab"
                        :contacts="props.selectedContractor?.contacts ?? []"
                        :interactions="props.selectedContractor?.interactions ?? []"
                        :insight-drafts="props.selectedContractor?.insight_drafts ?? []"
                        :portrait-options="portraitOptions"
                        @open-communications="activeTab = 'communications'"
                        @record-interaction="showInteractionOutcomeModal = true"
                    />

                    <div v-else-if="activeTab === 'communications'" class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Журнал общения: источник контекста для ассистента. Ниже — редактирование записей перед сохранением карточки.
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-if="selectedContractorId"
                                    type="button"
                                    class="inline-flex items-center gap-2 border border-sky-200 px-3 py-2 text-sm text-sky-800 hover:bg-sky-50 dark:border-sky-900 dark:text-sky-200 dark:hover:bg-sky-950/40"
                                    @click="showInteractionOutcomeModal = true"
                                >
                                    <Plus class="h-4 w-4" />
                                    Зафиксировать итог
                                </button>
                                <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-800" @click="addInteraction">
                                    <Plus class="h-4 w-4" />
                                    Добавить запись
                                </button>
                            </div>
                        </div>

                        <div v-if="form.interactions.length > 0" class="overflow-auto border border-zinc-200 dark:border-zinc-800">
                            <table class="min-w-full border-collapse text-sm">
                                <thead class="bg-zinc-100 dark:bg-zinc-800">
                                    <tr class="text-left">
                                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Дата</th>
                                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Канал</th>
                                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Тема</th>
                                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Содержание</th>
                                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Результат</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(interaction, index) in form.interactions"
                                        :key="`interaction-row-${index}`"
                                        class="cursor-pointer border-b border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60"
                                        @click="scrollToInteractionCard(index)"
                                    >
                                        <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ formatDateTime(interaction.contacted_at) }}</td>
                                        <td class="px-3 py-2">{{ interactionChannelLabel(interaction.channel) }}</td>
                                        <td class="px-3 py-2">{{ interaction.subject || '—' }}</td>
                                        <td class="max-w-xs truncate px-3 py-2" :title="interaction.summary">{{ interaction.summary || '—' }}</td>
                                        <td class="px-3 py-2">{{ interaction.result || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(interaction, index) in form.interactions"
                                :id="`contractor-interaction-${index}`"
                                :key="`interaction-${index}`"
                                class="border border-zinc-200 p-4 dark:border-zinc-800"
                            >
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Событие #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.interactions, index)">
                                        Удалить
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Дата и время</label>
                                        <input v-model="interaction.contacted_at" type="datetime-local" :class="crmFieldFluid" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Канал</label>
                                        <select v-model="interaction.channel" :class="crmFieldFluid">
                                            <option value="">Не указан</option>
                                            <option v-for="channel in interactionChannels" :key="channel.value" :value="channel.value">
                                                {{ channel.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2 md:col-span-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Тема</label>
                                        <input v-model="interaction.subject" type="text" :class="crmFieldFluid" />
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Краткое содержание</label>
                                        <textarea v-model="interaction.summary" rows="4" :class="crmFieldFluid"></textarea>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Результат</label>
                                        <input v-model="interaction.result" type="text" :class="crmFieldFluid" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.interactions.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Коммуникации пока не заполнены.
                            </div>
                        </div>
                    </div>
                    <div v-else-if="activeTab === 'orders'" class="space-y-4">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            Последние связанные заказы. Таблица пока read-only, без редактирования из карточки контрагента.
                        </div>

                        <div class="overflow-auto border border-zinc-200 dark:border-zinc-800">
                            <table class="min-w-full border-collapse text-sm">
                                <thead class="bg-zinc-100 dark:bg-zinc-800">
                                    <tr class="text-left">
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Заказ</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Роль</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Статус</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Дата</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Ставка клиента</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Ставка перевозчика</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="order in selectedContractor?.orders || []" :key="order.id" class="border-b border-zinc-100 dark:border-zinc-800">
                                        <td class="px-3 py-3 font-medium">{{ order.order_number || `#${order.id}` }}</td>
                                        <td class="px-3 py-3">{{ order.relation === 'customer' ? 'Заказчик' : 'Перевозчик' }}</td>
                                        <td class="px-3 py-3">{{ order.status || '—' }}</td>
                                        <td class="px-3 py-3">{{ formatDate(order.order_date) }}</td>
                                        <td class="px-3 py-3">{{ order.customer_rate ?? '—' }}</td>
                                        <td class="px-3 py-3">{{ order.carrier_rate ?? '—' }}</td>
                                    </tr>
                                    <tr v-if="(selectedContractor?.orders || []).length === 0">
                                        <td colspan="6" class="px-3 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                            У контрагента пока нет связанных заказов.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <ContractorDocumentsSection v-else-if="activeTab === 'documents'" v-model="form.documents" />
                </div>
            </section>
        </Modal>

        <ContractorInteractionOutcomeModal
            v-if="selectedContractorId"
            :show="showInteractionOutcomeModal"
            :contractor-id="selectedContractorId"
            :contacts="props.selectedContractor?.contacts ?? []"
            :portrait-options="portraitOptions"
            :interaction-channels="interactionChannels"
            @close="showInteractionOutcomeModal = false"
        />
    </div>
</template>
