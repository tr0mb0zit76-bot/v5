<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    ClipboardList,
    FileText,
    History,
    House,
    Kanban,
    Package,
    Plus,
    Save,
    Search,
    ShieldCheck,
    SquarePen,
    Trash2,
    Users,
    Wallet,
} from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'contractors' }, () => page),
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
});

const page = usePage();
const search = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');
const activeTab = ref('general');
const isInnLookupPending = ref(false);
const addressSuggestions = ref({
    legal_address: [],
    actual_address: [],
    postal_address: [],
});
const addressTimers = {};
let innLookupTimer = null;
const lastAutoFilledInn = ref('');

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
    { key: 'history', label: 'История общения', icon: History },
    { key: 'orders', label: 'Заказы', icon: FileText },
    { key: 'documents', label: 'Документы', icon: FileText },
];

const contractorTypes = [
    { value: 'customer', label: 'Заказчик' },
    { value: 'carrier', label: 'Перевозчик' },
    { value: 'both', label: 'Заказчик и перевозчик' },
];

const interactionChannels = [
    { value: 'phone', label: 'Телефон' },
    { value: 'email', label: 'Email' },
    { value: 'messenger', label: 'Мессенджер' },
    { value: 'meeting', label: 'Встреча' },
];

const paymentFormOptions = [
    { value: 'vat', label: 'С НДС' },
    { value: 'no_vat', label: 'Без НДС' },
    { value: 'cash', label: 'Наличные' },
];

const paymentBasisOptions = [
    { value: 'fttn', label: 'ФТТН' },
    { value: 'ottn', label: 'ОТТН' },
    { value: 'loading', label: 'На загрузке' },
    { value: 'unloading', label: 'На выгрузке' },
];

const currencyOptions = ['RUB', 'USD', 'CNY', 'EUR'];

const mobileNavItems = [
    { key: 'dashboard', label: 'Главная', icon: House },
    { key: 'orders', label: 'Заказы', icon: Package },
    { key: 'tasks', label: 'Задачи', icon: ClipboardList },
    { key: 'kanban', label: 'Канбан', icon: Kanban },
    { key: 'finance', label: 'Финансы', icon: Wallet },
    { key: 'orders-create', label: 'Новый', icon: SquarePen },
    { key: 'contractors', label: 'База', icon: Users },
    { key: 'reports', label: 'Отчёты', icon: BarChart3 },
];

function blankPaymentSchedule() {
    return {
        has_prepayment: false,
        prepayment_ratio: 50,
        prepayment_days: 0,
        prepayment_mode: 'fttn',
        postpayment_days: 0,
        postpayment_mode: 'ottn',
    };
}

function normalizePaymentSchedule(schedule = {}) {
    const raw = schedule?.has_prepayment;

    return {
        ...blankPaymentSchedule(),
        ...schedule,
        has_prepayment: raw === true || raw === 1 || raw === '1',
    };
}

function hasMeaningfulPaymentSchedule(schedule) {
    if (!schedule) {
        return false;
    }

    if (schedule.has_prepayment) {
        return true;
    }

    return Number(schedule.postpayment_days || 0) > 0;
}

function paymentScheduleSummary(schedule) {
    const normalized = normalizePaymentSchedule(schedule);

    if (!hasMeaningfulPaymentSchedule(normalized)) {
        return '';
    }

    if (normalized.has_prepayment) {
        const prepaymentRatio = Number(normalized.prepayment_ratio || 0);
        const postpaymentRatio = Math.max(0, 100 - prepaymentRatio);

        return `${prepaymentRatio}/${postpaymentRatio}, ${Number(normalized.prepayment_days || 0)} дн ${String(normalized.prepayment_mode || 'fttn').toUpperCase()} / ${Number(normalized.postpayment_days || 0)} дн ${String(normalized.postpayment_mode || 'ottn').toUpperCase()}`;
    }

    return `${Number(normalized.postpayment_days || 0)} дн ${String(normalized.postpayment_mode || 'ottn').toUpperCase()}`;
}

/** Как в OrdersGrid: в БД латиница (FTTN/OTTN), в подписи — кириллица. */
function formatPaymentTermsForDisplay(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value)
        .replace(/\bFTTN\b/gi, 'ФТТН')
        .replace(/\bOTTN\b/gi, 'ОТТН')
        .replace(/\bLOADING\b/gi, 'погрузка')
        .replace(/\bUNLOADING\b/gi, 'выгрузка')
        .replace(/\bfttn\b/gi, 'ФТТН')
        .replace(/\bottn\b/gi, 'ОТТН')
        .replace(/\bloading\b/gi, 'погрузка')
        .replace(/\bunloading\b/gi, 'выгрузка');
}

function parsePaymentTermPreset(term) {
    if (!term) {
        return blankPaymentSchedule();
    }

    const normalized = String(term).trim().toUpperCase();
    const prepaymentMatch = normalized.match(/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+ДН\s+(FTTN|OTTN|LOADING|UNLOADING)\s*\/\s*(\d+)\s+ДН\s+(FTTN|OTTN|LOADING|UNLOADING)$/u);

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
        website: '',
        contact_person: '',
        contact_person_phone: '',
        contact_person_email: '',
        contact_person_position: '',
        signer_name_nominative: '',
        signer_name_prepositional: '',
        signer_authority_basis: '',
        bank_name: '',
        bik: '',
        account_number: '',
        correspondent_account: '',
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
        cooperation_terms_notes: '',
        is_active: true,
        is_verified: false,
        is_own_company: false,
        owner_id: null,
        contacts: [],
        interactions: [],
        documents: [],
    };
}

function contractorToForm(contractor) {
    if (!contractor) {
        return blankForm();
    }

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
        website: contractor.website ?? '',
        contact_person: contractor.contact_person ?? '',
        contact_person_phone: contractor.contact_person_phone ?? '',
        contact_person_email: contractor.contact_person_email ?? '',
        contact_person_position: contractor.contact_person_position ?? '',
        signer_name_nominative: contractor.signer_name_nominative ?? '',
        signer_name_prepositional: contractor.signer_name_prepositional ?? '',
        signer_authority_basis: contractor.signer_authority_basis ?? '',
        bank_name: contractor.bank_name ?? '',
        bik: contractor.bik ?? '',
        account_number: contractor.account_number ?? '',
        correspondent_account: contractor.correspondent_account ?? '',
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
        cooperation_terms_notes: contractor.cooperation_terms_notes ?? '',
        is_active: Boolean(contractor.is_active),
        is_verified: Boolean(contractor.is_verified),
        is_own_company: Boolean(contractor.is_own_company),
        owner_id: contractor.owner_id ?? null,
        contacts: Array.isArray(contractor.contacts)
            ? contractor.contacts.map((contact) => ({
                full_name: contact.full_name ?? '',
                position: contact.position ?? '',
                phone: contact.phone ?? '',
                email: contact.email ?? '',
                is_primary: Boolean(contact.is_primary),
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
                type: document.type ?? '',
                title: document.title ?? '',
                number: document.number ?? '',
                document_date: document.document_date ?? '',
                status: document.status ?? '',
                notes: document.notes ?? '',
            }))
            : [],
    };
}

const form = useForm(contractorToForm(props.selectedContractor));
const transportRequirementsText = ref('');
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

function applyFormState(contractor) {
    const payload = contractorToForm(contractor);
    form.defaults(payload);
    form.reset();
    lastAutoFilledInn.value = payload.inn;

    for (const [key, value] of Object.entries(payload)) {
        form[key] = value;
    }

    transportRequirementsText.value = payload.transport_requirements.join('\n');
    activeTab.value = 'general';
    addressSuggestions.value = {
        legal_address: [],
        actual_address: [],
        postal_address: [],
    };
}

applyFormState(props.selectedContractor);

watch(() => props.selectedContractor, (contractor) => {
    applyFormState(contractor);
});

watch(() => props.activityTypeOptions, (options) => {
    globalActivityTypeOptions.value = [...new Set((options ?? []).map((item) => String(item ?? '').trim()).filter(Boolean))]
        .sort((left, right) => left.localeCompare(right, 'ru'));
});

const isCreating = computed(() => page.url.endsWith('/contractors/create'));
const selectedContractorId = computed(() => props.selectedContractor?.id ?? null);

const contractorScoring = ref(null);
const contractorScoringLoading = ref(false);
const contractorScoringError = ref('');

async function loadContractorScoring(options = { refresh: false }) {
    if (selectedContractorId.value === null || !props.selectedContractor?.inn) {
        contractorScoring.value = null;
        contractorScoringError.value = '';

        return;
    }

    contractorScoringLoading.value = true;
    contractorScoringError.value = '';

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

watch([selectedContractorId, () => props.selectedContractor?.inn], () => {
    loadContractorScoring({ refresh: false });
});

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
            return 'Статус ЕГРЮЛ (авто): действующая';
        case 'inactive':
            return 'Статус ЕГРЮЛ (авто): ликвидация / исключение / иные блокирующие признаки';
        default:
            return 'Статус ЕГРЮЛ (авто): не распознан однозначно из ответа API';
    }
}

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
            type: typeFilter.value,
            page: 1, // Reset to first page when searching
        }), {}, { preserveScroll: true });
    }, 700); // Длиннее дебаунс — меньше лишних запросов при медленном наборе
});

// Watch for type filter changes
let typeFilterTimer = null;
watch(() => typeFilter.value, (newType) => {
    clearTimeout(typeFilterTimer);
    clearTimeout(searchTimer); // Also clear search timer to avoid conflicts

    typeFilterTimer = setTimeout(() => {
        router.get(route('contractors.index', {
            search: effectiveIndexSearchQuery(search.value),
            type: newType,
            page: 1, // Reset to first page when filtering
        }), {}, { preserveScroll: true });
    }, 300); // Debounce 300ms
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
    router.get(route('contractors.create', {
        search: effectiveIndexSearchQuery(search.value),
        type: typeFilter.value,
    }), {}, { preserveScroll: true });
}

function openContractor(contractorId) {
    router.get(route('contractors.show', {
        contractor: contractorId,
        search: effectiveIndexSearchQuery(search.value),
        type: typeFilter.value,
        page: props.pagination.current_page,
    }), {}, { preserveScroll: true });
}

function resetToSelected() {
    applyFormState(props.selectedContractor);
}

function parseMultilineList(value) {
    return value
        .split('\n')
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function submit() {
    form.transport_requirements = parseMultilineList(transportRequirementsText.value);
    form.activity_types = [...new Set((form.activity_types ?? []).map((item) => String(item).trim()).filter(Boolean))];
    form.default_customer_payment_schedule = normalizePaymentSchedule(form.default_customer_payment_schedule);
    form.default_carrier_payment_schedule = normalizePaymentSchedule(form.default_carrier_payment_schedule);
    form.default_customer_payment_term = paymentScheduleSummary(form.default_customer_payment_schedule) || '';
    form.default_carrier_payment_term = paymentScheduleSummary(form.default_carrier_payment_schedule) || '';

    if (form.inn != null && form.inn !== '') {
        form.inn = String(form.inn).replace(/\D/g, '');
    }

    if (selectedContractorId.value === null) {
        form.post(route('contractors.store'), {
            preserveScroll: true,
        });

        return;
    }

    form.patch(route('contractors.update', selectedContractorId.value), {
        preserveScroll: true,
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

function addDocument() {
    form.documents.push({
        type: '',
        title: '',
        number: '',
        document_date: '',
        status: '',
        notes: '',
    });
}

function removeItem(collection, index) {
    collection.splice(index, 1);
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

async function fetchPartySuggestions() {
    const query = form.inn.trim() || form.name.trim();

    if (query.length < 2) {
        return;
    }

    isInnLookupPending.value = true;

    try {
        const response = await fetch(`${route('contractors.suggest-party')}?query=${encodeURIComponent(query)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];

        if (suggestions.length > 0) {
            applyPartySuggestion(suggestions[0]);
            lastAutoFilledInn.value = query;
        }
    } catch (error) {
        console.error('DaData party suggestion error', error);
    } finally {
        isInnLookupPending.value = false;
    }
}

function applyPartySuggestion(suggestion) {
    const party = suggestion?.data ?? {};

    form.name = suggestion.value ?? form.name;
    form.full_name = party.name?.full_with_opf ?? form.full_name;
    form.inn = party.inn != null && party.inn !== '' ? String(party.inn) : form.inn;
    form.kpp = party.kpp != null && party.kpp !== '' ? String(party.kpp) : form.kpp;
    form.ogrn = party.ogrn != null && party.ogrn !== '' ? String(party.ogrn) : form.ogrn;
    form.okpo = party.okpo != null && party.okpo !== '' ? String(party.okpo) : form.okpo;
    form.legal_address = party.address?.value ?? form.legal_address;
    form.actual_address = party.address?.value ?? form.actual_address;
    form.postal_address = party.address?.value ?? form.postal_address;

    if (party.type === 'INDIVIDUAL') {
        form.legal_form = 'ip';
    }
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
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
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
    return paymentFormOptions.find((item) => item.value === value)?.label ?? 'Не задано';
}

watch(() => form.inn, (inn) => {
    clearTimeout(innLookupTimer);

    const normalizedInn = String(inn ?? '').replace(/\D/g, '');

    if ([10, 12].includes(normalizedInn.length) && form.inn !== normalizedInn) {
        form.inn = normalizedInn;
    }

    if (![10, 12].includes(normalizedInn.length) || normalizedInn === lastAutoFilledInn.value) {
        return;
    }

    innLookupTimer = window.setTimeout(() => {
        form.inn = normalizedInn;
        fetchPartySuggestions();
    }, 500);
});

function goToPage(pageNumber) {
    if (pageNumber < 1 || pageNumber > props.pagination.last_page) {
        return;
    }
    
    router.get(route('contractors.index', {
        page: pageNumber,
        search: effectiveIndexSearchQuery(search.value),
        type: typeFilter.value,
    }), {}, { preserveScroll: true });
}

function handleMobileNavSelect(key) {
    const routes = {
        dashboard: '/dashboard',
        orders: '/orders',
        tasks: '/tasks',
        kanban: '/kanban',
        'orders-create': '/orders/create',
        contractors: '/contractors',
        finance: '/finance',
        reports: '/reports',
    };

    if (routes[key]) {
        router.visit(routes[key]);
    }
}
</script>

<template>
    <div v-if="isMobileStandalone" class="space-y-4">
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
                    :class="selectedContractor.is_active
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                        : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'"
                >
                    {{ selectedContractor.is_active ? 'Активен' : 'Архив' }}
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
                        :class="contractor.is_active
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'"
                    >
                        {{ contractor.is_active ? 'Активен' : 'Архив' }}
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

        <nav class="fixed bottom-0 left-0 right-0 z-50 shrink-0 border-t border-zinc-200 bg-white/95 px-2 py-2 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
            <div class="grid grid-cols-6 gap-2">
                <button
                    v-for="item in mobileNavItems"
                    :key="item.key"
                    type="button"
                    class="flex flex-col items-center justify-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-medium transition-colors"
                    :class="item.key === 'contractors'
                        ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                        : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                    @click="handleMobileNavSelect(item.key)"
                >
                    <component :is="item.icon" class="h-4 w-4" />
                    <span class="truncate">{{ item.label }}</span>
                </button>
            </div>
        </nav>
    </div>

    <div v-else class="flex h-full min-h-0 flex-col gap-3 xl:h-[calc(100dvh-9.5rem)] xl:overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Контрагенты</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Единая карточка контрагента с реквизитами, контактами, историей коммуникаций и связанными заказами.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                @click="openCreateForm"
            >
                <Plus class="h-4 w-4" />
                Новый контрагент
            </button>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 overflow-hidden xl:h-full xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="flex min-h-0 flex-col overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 p-3 dark:border-zinc-800">
                    <div class="relative mb-2">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Поиск по названию, ИНН, телефону"
                            class="w-full border border-zinc-300 bg-white py-2 pl-9 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Тип контрагента</label>
                        <select
                            v-model="typeFilter"
                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                        >
                            <option value="">Все типы</option>
                            <option v-for="type in contractorTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="border-b border-zinc-200 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    <div class="flex items-center justify-between">
                        <div>
                            Всего контрагентов: {{ pagination.total }}
                            <span v-if="pagination.total > pagination.per_page" class="ml-2">
                                (показано {{ pagination.from }}-{{ pagination.to }})
                            </span>
                        </div>
                        <div v-if="pagination.last_page > 1" class="flex items-center gap-1">
                            <button
                                type="button"
                                class="inline-flex h-6 w-6 items-center justify-center rounded border border-zinc-300 bg-white text-xs hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                :disabled="pagination.current_page === 1"
                                @click="goToPage(pagination.current_page - 1)"
                            >
                                ←
                            </button>
                            <span class="px-2 text-xs">
                                {{ pagination.current_page }} / {{ pagination.last_page }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex h-6 w-6 items-center justify-center rounded border border-zinc-300 bg-white text-xs hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                :disabled="pagination.current_page === pagination.last_page"
                                @click="goToPage(pagination.current_page + 1)"
                            >
                                →
                            </button>
                        </div>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain" scroll-region>
                    <button
                        v-for="contractor in contractors"
                        :key="contractor.id"
                        type="button"
                        class="flex w-full flex-col gap-1 border-b border-zinc-100 px-3 py-3 text-left transition-colors dark:border-zinc-800"
                        :class="selectedContractorId === contractor.id
                            ? 'bg-zinc-100 dark:bg-zinc-800'
                            : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/60'"
                        @click="openContractor(contractor.id)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="space-y-1">
                                <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ contractor.name }}</div>
                                <div v-if="contractor.is_own_company" class="text-[11px] font-medium text-indigo-600 dark:text-indigo-300">
                                    Своя компания
                                </div>
                            </div>
                            <span
                                class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="contractor.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200'"
                            >
                                {{ contractor.is_active ? 'Активен' : 'Архив' }}
                            </span>
                        </div>

                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ contractorTypeLabel(contractor.type) }}<span v-if="contractor.inn"> · ИНН {{ contractor.inn }}</span>
                        </div>

                        <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>Контакты: {{ contractor.contacts_count }}</span>
                            <span>Заказы: {{ contractor.orders_count }}</span>
                        </div>
                    </button>

                    <div v-if="contractors.length === 0" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        Контрагенты не найдены.
                    </div>
                </div>
            </aside>

            <section class="flex min-h-0 flex-col overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div class="space-y-1">
                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ isCreating ? 'Новый контрагент' : (selectedContractor?.name || 'Карточка контрагента') }}
                        </div>
                        <div class="flex flex-wrap gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                            <span v-if="selectedContractor?.inn">ИНН {{ selectedContractor.inn }}</span>
                            <span v-if="selectedContractor?.phone">{{ selectedContractor.phone }}</span>
                            <span v-if="selectedContractor?.email">{{ selectedContractor.email }}</span>
                            <span v-if="selectedContractorId !== null">Заказы: {{ totalOrdersCount }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            @click="resetToSelected"
                        >
                            Сбросить
                        </button>
                        <button
                            v-if="selectedContractorId !== null"
                            type="button"
                            class="inline-flex items-center gap-2 border border-rose-200 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            @click="removeContractor"
                        >
                            <Trash2 class="h-4 w-4" />
                            Удалить
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 bg-zinc-900 px-3 py-2 text-sm text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </div>

                <div class="border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="inline-flex items-center gap-2 border px-3 py-2 text-sm transition-colors"
                            :class="activeTab === tab.key
                                ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                                : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                            @click="activeTab = tab.key"
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-auto px-4 py-3">
                    <div v-if="false" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Краткое название</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                        <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Полное название</label>
                                        <input
                                            v-model="form.full_name"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Профиль контрагента</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Роль компании в работе и внутренние признаки карточки.
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Тип контрагента</label>
                                            <select
                                                v-model="form.type"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            >
                                                <option v-for="type in contractorTypes" :key="type.value" :value="type.value">
                                                    {{ type.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                                <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300" />
                                                Активен
                                            </label>
                                            <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                                <input v-model="form.is_verified" type="checkbox" class="rounded border-zinc-300" />
                                                Проверен
                                            </label>
                                            <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60 sm:col-span-2">
                                                <input v-model="form.is_own_company" type="checkbox" class="rounded border-zinc-300" />
                                                Своя компания
                                            </label>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">ИНН</label>
                                            <input
                                                v-model="form.inn"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            />
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                После ввода корректного ИНН DaData подставит реквизиты автоматически.
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Основной телефон</label>
                                            <input
                                                v-model="form.phone"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
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
                                        <label class="text-sm font-medium">Email</label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Сайт</label>
                                        <input
                                            v-model="form.website"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Краткое описание контрагента</label>
                                    <textarea
                                        v-model="form.short_description"
                                        rows="4"
                                        placeholder="Коротко: чем занимается компания, сильные стороны, профиль работы"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                            </div>

                            <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Основной контакт</div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контактное лицо</label>
                                    <input
                                        v-model="form.contact_person"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Должность</label>
                                    <input
                                        v-model="form.contact_person_position"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Телефон</label>
                                    <input
                                        v-model="form.contact_person_phone"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Email</label>
                                    <input
                                        v-model="form.contact_person_email"
                                        type="email"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
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
                                        <label class="text-sm font-medium">Краткое название</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                        <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Полное название</label>
                                        <input
                                            v-model="form.full_name"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-4">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Карточка компании</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Основные данные контрагента для повседневной работы менеджера.
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">ИНН</label>
                                            <input
                                                v-model="form.inn"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            />
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                После ввода корректного ИНН DaData попробует заполнить реквизиты автоматически.
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Основной телефон</label>
                                            <input
                                                v-model="form.phone"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Email</label>
                                            <input
                                                v-model="form.email"
                                                type="email"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Сайт</label>
                                            <input
                                                v-model="form.website"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            />
                                        </div>
                                    </div>

                                    <div v-if="isInnLookupPending" class="mt-4 inline-flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        <Search class="h-4 w-4 animate-pulse" />
                                        Идёт поиск реквизитов в DaData...
                                    </div>
                                </div>

                                <div class="space-y-2 border border-zinc-200 p-4 dark:border-zinc-800">
                                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Краткое описание</label>
                                    <textarea
                                        v-model="form.short_description"
                                        rows="4"
                                        placeholder="Коротко: чем занимается компания, ключевой профиль, сильные стороны и особенности работы."
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Профиль контрагента</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Роль компании в работе и внутренние признаки карточки.
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Тип контрагента</label>
                                        <select
                                            v-model="form.type"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        >
                                            <option v-for="type in contractorTypes" :key="type.value" :value="type.value">
                                                {{ type.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Владелец</label>
                                        <select
                                            v-model="form.owner_id"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        >
                                            <option :value="null">Не назначен</option>
                                            <option v-for="user in users" :key="user.id" :value="user.id">
                                                {{ user.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3">
                                        <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                            <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300" />
                                            Активен
                                        </label>
                                        <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                            <input v-model="form.is_verified" type="checkbox" class="rounded border-zinc-300" />
                                            Проверен
                                        </label>
                                        <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                            <input v-model="form.is_own_company" type="checkbox" class="rounded border-zinc-300" />
                                            Своя компания
                                        </label>
                                    </div>
                                </div>

                                <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Виды деятельности</div>
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
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                            <div class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Финансовые условия по умолчанию</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Эти значения подставляются в заказ при выборе контрагента.
                                        </div>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="form.stop_on_limit" type="checkbox" class="rounded border-zinc-300" />
                                        Стоп-работа по лимиту
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="space-y-2 md:col-span-2">
                                        <label class="text-sm font-medium">Лимит задолженности</label>
                                        <input v-model="form.debt_limit" type="number" min="0" step="0.01" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Валюта</label>
                                        <select v-model="form.debt_limit_currency" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                            <option v-for="currency in currencyOptions" :key="currency" :value="currency">{{ currency }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Форма оплаты</label>
                                            <select v-model="form.default_customer_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                <option value="">Не задана</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Условия оплаты заказчика</label>
                                            <p class="border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                                                {{ formatPaymentTermsForDisplay(paymentScheduleSummary(form.default_customer_payment_schedule)) || 'Не заданы' }}
                                            </p>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Срок, дней</label>
                                                <input v-model="form.default_customer_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Оплата по</label>
                                                <select v-model="form.default_customer_payment_schedule.postpayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <label class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <input v-model="form.default_customer_payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                                Предоплата
                                            </label>
                                        </div>
                                        <div v-if="form.default_customer_payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Предоплата, %</label>
                                                <input v-model="form.default_customer_payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Срок предоплаты, дней</label>
                                                <input v-model="form.default_customer_payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Оплата по</label>
                                                <select v-model="form.default_customer_payment_schedule.prepayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Постоплата, %</label>
                                                <input :value="100 - Number(form.default_customer_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Форма оплаты</label>
                                            <select v-model="form.default_carrier_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                <option value="">Не задана</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Условия оплаты перевозчика</label>
                                            <p class="border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                                                {{ formatPaymentTermsForDisplay(paymentScheduleSummary(form.default_carrier_payment_schedule)) || 'Не заданы' }}
                                            </p>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Срок, дней</label>
                                                <input v-model="form.default_carrier_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Оплата по</label>
                                                <select v-model="form.default_carrier_payment_schedule.postpayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <label class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <input v-model="form.default_carrier_payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                                Предоплата
                                            </label>
                                        </div>
                                        <div v-if="form.default_carrier_payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Предоплата, %</label>
                                                <input v-model="form.default_carrier_payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Срок предоплаты, дней</label>
                                                <input v-model="form.default_carrier_payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Оплата по</label>
                                                <select v-model="form.default_carrier_payment_schedule.prepayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Постоплата, %</label>
                                                <input :value="100 - Number(form.default_carrier_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Условия сотрудничества</label>
                                    <textarea v-model="form.cooperation_terms_notes" rows="4" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"></textarea>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Требования к перевозке</label>
                                    <textarea
                                        v-model="transportRequirementsText"
                                        rows="6"
                                        placeholder="По одному требованию на строку"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                            </div>

                            <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Кредитный статус</div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Текущая задолженность</span>
                                        <span class="font-medium">{{ formatMoney(selectedContractor?.current_debt, selectedContractor?.debt_limit_currency || form.debt_limit_currency) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Лимит</span>
                                        <span class="font-medium">{{ formatMoney(form.debt_limit, form.debt_limit_currency) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Форма оплаты заказчика</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_customer_payment_form) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Форма оплаты перевозчика</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_carrier_payment_form) }}</span>
                                    </div>
                                </div>

                                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Скоринг контрагента (Checko)</div>
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

                                    <div v-else-if="contractorScoring?.ok" class="space-y-2 text-xs">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold" :class="scoringGradeClass(contractorScoring.grade)">
                                                Класс {{ contractorScoring.grade }}
                                            </span>
                                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ contractorScoring.score }} / 100</span>
                                            <span v-if="contractorScoring.checko_from_cache" class="text-zinc-500 dark:text-zinc-400">(кэш Checko)</span>
                                        </div>
                                        <div v-if="contractorScoring.company_name" class="text-zinc-600 dark:text-zinc-300">{{ contractorScoring.company_name }}</div>
                                        <div v-if="contractorScoring.egr_status" class="text-zinc-500 dark:text-zinc-400">
                                            {{ scoringEgrStatusLabel(contractorScoring.egr_status) }}
                                            <span v-if="contractorScoring.status_text"> — «{{ contractorScoring.status_text }}»</span>
                                        </div>
                                        <div class="space-y-1">
                                            <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                                Рекомендуемая отсрочка: до {{ contractorScoring.recommended_postpayment_days }} дн.
                                                <span class="font-normal text-zinc-500 dark:text-zinc-400">(макс. 10 для «сильных» профилей)</span>
                                            </div>
                                            <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                                Рекомендуемый лимит задолженности:
                                                {{ formatMoney(contractorScoring.recommended_debt_limit_rub ?? 0, 'RUB') }}
                                                <span class="block font-normal text-zinc-500 dark:text-zinc-400">Ориентир по оценке и данным Checko; при известной выручке не выше ~8% от неё.</span>
                                            </div>
                                            <p class="text-zinc-600 dark:text-zinc-300">{{ contractorScoring.summary }}</p>
                                        </div>
                                        <ul class="list-disc space-y-1 pl-4 text-zinc-600 dark:text-zinc-300">
                                            <li v-for="(factor, idx) in contractorScoring.factors" :key="`factor-${idx}`">{{ factor }}</li>
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
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Орг.-правовая форма</label>
                                        <select
                                            v-model="form.legal_form"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        >
                                            <option value="">Не указана</option>
                                            <option v-for="option in legalFormOptions" :key="option.value" :value="option.value">
                                                {{ legalFormLabelByValue[option.value] ?? option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">КПП</label>
                                        <input
                                            v-model="form.kpp"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">ОГРН</label>
                                        <input
                                            v-model="form.ogrn"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">ОКПО</label>
                                        <input
                                            v-model="form.okpo"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 text-sm font-medium">Банковские реквизиты</div>
                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Банк</label>
                                        <input v-model="form.bank_name" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">БИК</label>
                                            <input v-model="form.bik" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Расчётный счёт</label>
                                            <input v-model="form.account_number" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Корреспондентский счёт</label>
                                        <input v-model="form.correspondent_account" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">ATI ID</label>
                                        <input v-model="form.ati_id" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="mb-3 text-sm font-medium">Подписант</div>
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">ФИО, именительный падеж</label>
                                    <input
                                        v-model="form.signer_name_nominative"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">ФИО, родительный падеж</label>
                                    <input
                                        v-model="form.signer_name_prepositional"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Основание права подписи</label>
                                    <input
                                        v-model="form.signer_authority_basis"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="relative space-y-2">
                                <label class="text-sm font-medium">Юридический адрес</label>
                                <textarea v-model="form.legal_address" rows="2" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="queueAddressLookup('legal_address')"></textarea>
                                <div v-if="addressSuggestions.legal_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                    <button v-for="suggestion in addressSuggestions.legal_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('legal_address', suggestion)">
                                        {{ suggestion.value }}
                                    </button>
                                </div>
                            </div>

                            <div class="relative space-y-2">
                                <label class="text-sm font-medium">Фактический адрес</label>
                                <textarea v-model="form.actual_address" rows="2" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="queueAddressLookup('actual_address')"></textarea>
                                <div v-if="addressSuggestions.actual_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                    <button v-for="suggestion in addressSuggestions.actual_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('actual_address', suggestion)">
                                        {{ suggestion.value }}
                                    </button>
                                </div>
                            </div>

                            <div class="relative space-y-2">
                                <label class="text-sm font-medium">Почтовый адрес</label>
                                <textarea v-model="form.postal_address" rows="2" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="queueAddressLookup('postal_address')"></textarea>
                                <div v-if="addressSuggestions.postal_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                    <button v-for="suggestion in addressSuggestions.postal_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('postal_address', suggestion)">
                                        {{ suggestion.value }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="activeTab === 'contacts'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Отдельные контакты удобно хранить отдельно от основной карточки компании.
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addContact">
                                <Plus class="h-4 w-4" />
                                Добавить контакт
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(contact, index) in form.contacts" :key="`contact-${index}`" class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium">Контакт #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.contacts, index)">
                                        Удалить
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">ФИО</label>
                                        <input v-model="contact.full_name" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Должность</label>
                                        <input v-model="contact.position" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <label class="flex items-center gap-2 pt-8 text-sm">
                                        <input v-model="contact.is_primary" type="checkbox" class="rounded border-zinc-300" />
                                        Основной контакт
                                    </label>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Телефон</label>
                                        <input v-model="contact.phone" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Email</label>
                                        <input v-model="contact.email" type="email" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2 md:col-span-2 xl:col-span-1">
                                        <label class="text-sm font-medium">Комментарий</label>
                                        <input v-model="contact.notes" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.contacts.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Отдельные контакты пока не добавлены.
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'history'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                История звонков, писем, встреч и результатов коммуникации.
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addInteraction">
                                <Plus class="h-4 w-4" />
                                Добавить запись
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(interaction, index) in form.interactions" :key="`interaction-${index}`" class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium">Событие #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.interactions, index)">
                                        Удалить
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Дата и время</label>
                                        <input v-model="interaction.contacted_at" type="datetime-local" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Канал</label>
                                        <select v-model="interaction.channel" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                            <option value="">Не указан</option>
                                            <option v-for="channel in interactionChannels" :key="channel.value" :value="channel.value">
                                                {{ channel.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2 md:col-span-2">
                                        <label class="text-sm font-medium">Тема</label>
                                        <input v-model="interaction.subject" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Краткое содержание</label>
                                        <textarea v-model="interaction.summary" rows="4" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"></textarea>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Результат</label>
                                        <input v-model="interaction.result" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.interactions.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                История общения пока не заполнена.
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

                    <div v-else-if="activeTab === 'documents'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Карточка хранит метаданные по документам контрагента. Файловое хранилище можно подключить отдельным шагом.
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addDocument">
                                <Plus class="h-4 w-4" />
                                Добавить документ
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(document, index) in form.documents" :key="`document-${index}`" class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium">Документ #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.documents, index)">
                                        Удалить
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Тип</label>
                                        <input v-model="document.type" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Наименование</label>
                                        <input v-model="document.title" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Номер</label>
                                        <input v-model="document.number" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Дата документа</label>
                                        <input v-model="document.document_date" type="date" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Статус</label>
                                        <input v-model="document.status" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2 md:col-span-2 xl:col-span-1">
                                        <label class="text-sm font-medium">Комментарий</label>
                                        <input v-model="document.notes" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.documents.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Документы пока не добавлены.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
