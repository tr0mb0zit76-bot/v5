<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    FileText,
    History,
    Plus,
    Save,
    Search,
    ShieldCheck,
    Trash2,
    Users,
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
});

const page = usePage();
const search = ref('');
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

/** РџРѕРґРїРёСЃРё РЅР° С„СЂРѕРЅС‚Рµ (UTF-8), С‡С‚РѕР±С‹ РЅРµ Р·Р°РІРёСЃРµС‚СЊ РѕС‚ РєРѕРґРёСЂРѕРІРєРё РѕС‚РІРµС‚Р° СЃРµСЂРІРµСЂР° РІ СЃРїРёСЃРєРµ РѕРїС†РёР№ */
const legalFormLabelByValue = {
    ooo: 'РћРћРћ',
    zao: 'Р—РђРћ',
    ao: 'РђРћ',
    ip: 'РРџ',
    samozanyaty: 'РЎР°РјРѕР·Р°РЅСЏС‚С‹Р№',
    other: 'Р”СЂСѓРіРѕРµ',
};

const tabs = [
    { key: 'general', label: 'РћР±С‰РёРµ СЃРІРµРґРµРЅРёСЏ', icon: Building2 },
    { key: 'cooperation', label: 'РЈСЃР»РѕРІРёСЏ СЃРѕС‚СЂСѓРґРЅРёС‡РµСЃС‚РІР°', icon: FileText },
    { key: 'requisites', label: 'Р РµРєРІРёР·РёС‚С‹', icon: ShieldCheck },
    { key: 'contacts', label: 'РљРѕРЅС‚Р°РєС‚С‹', icon: Users },
    { key: 'history', label: 'РСЃС‚РѕСЂРёСЏ РѕР±С‰РµРЅРёСЏ', icon: History },
    { key: 'orders', label: 'Р—Р°РєР°Р·С‹', icon: FileText },
    { key: 'documents', label: 'Р”РѕРєСѓРјРµРЅС‚С‹', icon: FileText },
];

const contractorTypes = [
    { value: 'customer', label: 'Р—Р°РєР°Р·С‡РёРє' },
    { value: 'carrier', label: 'РџРµСЂРµРІРѕР·С‡РёРє' },
    { value: 'both', label: 'Р—Р°РєР°Р·С‡РёРє Рё РїРµСЂРµРІРѕР·С‡РёРє' },
];

const interactionChannels = [
    { value: 'phone', label: 'РўРµР»РµС„РѕРЅ' },
    { value: 'email', label: 'Email' },
    { value: 'messenger', label: 'РњРµСЃСЃРµРЅРґР¶РµСЂ' },
    { value: 'meeting', label: 'Р’СЃС‚СЂРµС‡Р°' },
];

const paymentFormOptions = [
    { value: 'vat', label: 'РЎ РќР”РЎ' },
    { value: 'no_vat', label: 'Р‘РµР· РќР”РЎ' },
    { value: 'cash', label: 'РќР°Р»РёС‡РЅС‹Рµ' },
];

const paymentBasisOptions = [
    { value: 'fttn', label: 'Р¤РўРўРќ' },
    { value: 'ottn', label: 'РћРўРўРќ' },
    { value: 'fttn', label: 'РќР° Р·Р°РіСЂСѓР·РєРµ' },
    { value: 'ottn', label: 'РќР° РІС‹РіСЂСѓР·РєРµ' },
];

const currencyOptions = ['RUB', 'USD', 'CNY', 'EUR'];

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

        return `${prepaymentRatio}/${postpaymentRatio}, ${Number(normalized.prepayment_days || 0)} РґРЅ ${String(normalized.prepayment_mode || 'fttn').toUpperCase()} / ${Number(normalized.postpayment_days || 0)} РґРЅ ${String(normalized.postpayment_mode || 'ottn').toUpperCase()}`;
    }

    return `${Number(normalized.postpayment_days || 0)} РґРЅ ${String(normalized.postpayment_mode || 'ottn').toUpperCase()}`;
}

function parsePaymentTermPreset(term) {
    if (!term) {
        return blankPaymentSchedule();
    }

    const normalized = String(term).trim().toUpperCase();
    const prepaymentMatch = normalized.match(/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+Р”Рќ\s+(FTTN|OTTN)\s*\/\s*(\d+)\s+Р”Рќ\s+(FTTN|OTTN)$/u);

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

    const postpaymentMatch = normalized.match(/^(\d+)\s+Р”Рќ\s+(FTTN|OTTN)$/u);

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
const specializationsText = ref('');
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

function applyFormState(contractor) {
    const payload = contractorToForm(contractor);
    form.defaults(payload);
    form.reset();
    lastAutoFilledInn.value = payload.inn;

    for (const [key, value] of Object.entries(payload)) {
        form[key] = value;
    }

    specializationsText.value = payload.specializations.join('\n');
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

const filteredContractors = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return props.contractors;
    }

    return props.contractors.filter((contractor) => {
        return [contractor.name, contractor.inn, contractor.phone, contractor.email]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query));
    });
});

const totalOrdersCount = computed(() => props.selectedContractor?.orders?.length ?? 0);
const relatedOrderDocumentsCount = computed(() => props.selectedContractor?.order_documents?.length ?? 0);

function openCreateForm() {
    router.get(route('contractors.create'), {}, { preserveScroll: true });
}

function openContractor(contractorId) {
    router.get(route('contractors.show', contractorId), {}, { preserveScroll: true });
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
    form.specializations = parseMultilineList(specializationsText.value);
    form.transport_requirements = parseMultilineList(transportRequirementsText.value);
    form.activity_types = [...new Set((form.activity_types ?? []).map((item) => String(item).trim()).filter(Boolean))];
    form.default_customer_payment_schedule = normalizePaymentSchedule(form.default_customer_payment_schedule);
    form.default_carrier_payment_schedule = normalizePaymentSchedule(form.default_carrier_payment_schedule);
    form.default_customer_payment_term = paymentScheduleSummary(form.default_customer_payment_schedule) || '';
    form.default_carrier_payment_term = paymentScheduleSummary(form.default_carrier_payment_schedule) || '';

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

    if (!window.confirm('РЈРґР°Р»РёС‚СЊ РєРѕРЅС‚СЂР°РіРµРЅС‚Р°?')) {
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
    form.inn = party.inn ?? form.inn;
    form.kpp = party.kpp ?? form.kpp;
    form.ogrn = party.ogrn ?? form.ogrn;
    form.okpo = party.okpo ?? form.okpo;
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
        return 'вЂ”';
    }

    return new Date(value).toLocaleDateString('ru-RU');
}

function formatMoney(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') {
        return 'вЂ”';
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
    return paymentFormOptions.find((item) => item.value === value)?.label ?? 'РќРµ Р·Р°РґР°РЅРѕ';
}

watch(() => form.inn, (inn) => {
    clearTimeout(innLookupTimer);

    const normalizedInn = String(inn ?? '').replace(/\D/g, '');

    if (![10, 12].includes(normalizedInn.length) || normalizedInn === lastAutoFilledInn.value) {
        return;
    }

    innLookupTimer = window.setTimeout(() => {
        form.inn = normalizedInn;
        fetchPartySuggestions();
    }, 500);
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">РљРѕРЅС‚СЂР°РіРµРЅС‚С‹</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Р•РґРёРЅР°СЏ РєР°СЂС‚РѕС‡РєР° РєРѕРЅС‚СЂР°РіРµРЅС‚Р° СЃ СЂРµРєРІРёР·РёС‚Р°РјРё, РєРѕРЅС‚Р°РєС‚Р°РјРё, РёСЃС‚РѕСЂРёРµР№ РєРѕРјРјСѓРЅРёРєР°С†РёР№ Рё СЃРІСЏР·Р°РЅРЅС‹РјРё Р·Р°РєР°Р·Р°РјРё.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                @click="openCreateForm"
            >
                <Plus class="h-4 w-4" />
                РќРѕРІС‹Р№ РєРѕРЅС‚СЂР°РіРµРЅС‚
            </button>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="flex min-h-0 flex-col border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 p-3 dark:border-zinc-800">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="РџРѕРёСЃРє РїРѕ РЅР°Р·РІР°РЅРёСЋ, РРќРќ, С‚РµР»РµС„РѕРЅСѓ"
                            class="w-full border border-zinc-300 bg-white py-2 pl-9 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                        />
                    </div>
                </div>

                <div class="border-b border-zinc-200 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    Р’СЃРµРіРѕ РєРѕРЅС‚СЂР°РіРµРЅС‚РѕРІ: {{ contractors.length }}
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <button
                        v-for="contractor in filteredContractors"
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
                                    РЎРІРѕСЏ РєРѕРјРїР°РЅРёСЏ
                                </div>
                            </div>
                            <span
                                class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="contractor.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200'"
                            >
                                {{ contractor.is_active ? 'РђРєС‚РёРІРµРЅ' : 'РђСЂС…РёРІ' }}
                            </span>
                        </div>

                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ contractorTypeLabel(contractor.type) }}<span v-if="contractor.inn"> В· РРќРќ {{ contractor.inn }}</span>
                        </div>

                        <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>РљРѕРЅС‚Р°РєС‚С‹: {{ contractor.contacts_count }}</span>
                            <span>Р—Р°РєР°Р·С‹: {{ contractor.orders_count }}</span>
                        </div>
                    </button>

                    <div v-if="filteredContractors.length === 0" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        РљРѕРЅС‚СЂР°РіРµРЅС‚С‹ РЅРµ РЅР°Р№РґРµРЅС‹.
                    </div>
                </div>
            </aside>

            <section class="flex min-h-0 flex-col border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div class="space-y-1">
                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ isCreating ? 'РќРѕРІС‹Р№ РєРѕРЅС‚СЂР°РіРµРЅС‚' : (selectedContractor?.name || 'РљР°СЂС‚РѕС‡РєР° РєРѕРЅС‚СЂР°РіРµРЅС‚Р°') }}
                        </div>
                        <div class="flex flex-wrap gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                            <span v-if="selectedContractor?.inn">РРќРќ {{ selectedContractor.inn }}</span>
                            <span v-if="selectedContractor?.phone">{{ selectedContractor.phone }}</span>
                            <span v-if="selectedContractor?.email">{{ selectedContractor.email }}</span>
                            <span v-if="selectedContractorId !== null">Р—Р°РєР°Р·С‹: {{ totalOrdersCount }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            @click="resetToSelected"
                        >
                            РЎР±СЂРѕСЃРёС‚СЊ
                        </button>
                        <button
                            v-if="selectedContractorId !== null"
                            type="button"
                            class="inline-flex items-center gap-2 border border-rose-200 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            @click="removeContractor"
                        >
                            <Trash2 class="h-4 w-4" />
                            РЈРґР°Р»РёС‚СЊ
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 bg-zinc-900 px-3 py-2 text-sm text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'РЎРѕС…СЂР°РЅРµРЅРёРµ...' : 'РЎРѕС…СЂР°РЅРёС‚СЊ' }}
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
                    <div v-if="activeTab === 'general'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                            <div class="space-y-4">
                                <div v-if="false" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РўРёРї РєРѕРЅС‚СЂР°РіРµРЅС‚Р°</label>
                                        <select
                                            v-model="form.type"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        >
                                            <option v-for="type in contractorTypes" :key="type.value" :value="type.value">
                                                {{ type.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="flex items-end gap-3">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300" />
                                            РђРєС‚РёРІРµРЅ
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input v-model="form.is_verified" type="checkbox" class="rounded border-zinc-300" />
                                            РџСЂРѕРІРµСЂРµРЅ
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input v-model="form.is_own_company" type="checkbox" class="rounded border-zinc-300" />
                                            РЎРІРѕСЏ РєРѕРјРїР°РЅРёСЏ
                                        </label>
                                    </div>
                                </div>

                                <div v-if="false" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                        <div v-if="false" class="space-y-2">
                                            <label class="text-sm font-medium">РћРїР»Р°С‚Р° РїРѕ</label>
                                            <select v-model="form.default_customer_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                <option value="">РќРµ Р·Р°РґР°РЅР°</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <div v-if="false" class="space-y-2">
                                            <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ РѕРїР»Р°С‚С‹ Р·Р°РєР°Р·С‡РёРєР°</label>
                                            <p class="border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                                                {{ paymentScheduleSummary(form.default_customer_payment_schedule) || 'РќРµ Р·Р°РґР°РЅС‹' }}
                                            </p>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє, РґРЅРµР№</label>
                                                <input v-model="form.default_customer_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РўРёРї РґРѕРєСѓРјРµРЅС‚РѕРІ</label>
                                                <select v-model="form.default_customer_payment_schedule.postpayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <label class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <input v-model="form.default_customer_payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                                РџСЂРµРґРѕРїР»Р°С‚Р°
                                            </label>
                                        </div>
                                        <div v-if="form.default_customer_payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџСЂРµРґРѕРїР»Р°С‚Р°, %</label>
                                                <input v-model="form.default_customer_payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє РїСЂРµРґРѕРїР»Р°С‚С‹, РґРЅРµР№</label>
                                                <input v-model="form.default_customer_payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РўРёРї РґРѕРєСѓРјРµРЅС‚РѕРІ РґР»СЏ РїСЂРµРґРѕРїР»Р°С‚С‹</label>
                                                <select v-model="form.default_customer_payment_schedule.prepayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџРѕСЃС‚РѕРїР»Р°С‚Р°, %</label>
                                                <input :value="100 - Number(form.default_customer_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                        <div v-if="false" class="space-y-2">
                                            <label class="text-sm font-medium">РћРїР»Р°С‚Р° РїРѕ</label>
                                            <select v-model="form.default_carrier_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                <option value="">РќРµ Р·Р°РґР°РЅР°</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ РѕРїР»Р°С‚С‹ РїРµСЂРµРІРѕР·С‡РёРєР°</label>
                                            <p class="border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                                                {{ paymentScheduleSummary(form.default_carrier_payment_schedule) || 'РќРµ Р·Р°РґР°РЅС‹' }}
                                            </p>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє, РґРЅРµР№</label>
                                                <input v-model="form.default_carrier_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РўРёРї РґРѕРєСѓРјРµРЅС‚РѕРІ</label>
                                                <select v-model="form.default_carrier_payment_schedule.postpayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <label class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <input v-model="form.default_carrier_payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                                РџСЂРµРґРѕРїР»Р°С‚Р°
                                            </label>
                                        </div>
                                        <div v-if="form.default_carrier_payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџСЂРµРґРѕРїР»Р°С‚Р°, %</label>
                                                <input v-model="form.default_carrier_payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє РїСЂРµРґРѕРїР»Р°С‚С‹, РґРЅРµР№</label>
                                                <input v-model="form.default_carrier_payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РўРёРї РґРѕРєСѓРјРµРЅС‚РѕРІ РґР»СЏ РїСЂРµРґРѕРїР»Р°С‚С‹</label>
                                                <select v-model="form.default_carrier_payment_schedule.prepayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџРѕСЃС‚РѕРїР»Р°С‚Р°, %</label>
                                                <input :value="100 - Number(form.default_carrier_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РљСЂР°С‚РєРѕРµ РЅР°Р·РІР°РЅРёРµ</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                        <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РџРѕР»РЅРѕРµ РЅР°Р·РІР°РЅРёРµ</label>
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
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">РџСЂРѕС„РёР»СЊ РєРѕРЅС‚СЂР°РіРµРЅС‚Р°</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Р РѕР»СЊ РєРѕРјРїР°РЅРёРё РІ СЂР°Р±РѕС‚Рµ Рё РІРЅСѓС‚СЂРµРЅРЅРёРµ РїСЂРёР·РЅР°РєРё РєР°СЂС‚РѕС‡РєРё.
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">РўРёРї РєРѕРЅС‚СЂР°РіРµРЅС‚Р°</label>
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
                                                РђРєС‚РёРІРµРЅ
                                            </label>
                                            <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                                                <input v-model="form.is_verified" type="checkbox" class="rounded border-zinc-300" />
                                                РџСЂРѕРІРµСЂРµРЅ
                                            </label>
                                            <label class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/60 sm:col-span-2">
                                                <input v-model="form.is_own_company" type="checkbox" class="rounded border-zinc-300" />
                                                РЎРІРѕСЏ РєРѕРјРїР°РЅРёСЏ
                                            </label>
                                        </div>
                                    </div>

                                <div class="grid grid-cols-1 gap-4">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">РРќРќ</label>
                                            <input
                                                v-model="form.inn"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            />
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                РџРѕСЃР»Рµ РІРІРѕРґР° РєРѕСЂСЂРµРєС‚РЅРѕРіРѕ РРќРќ DaData РїРѕРґСЃС‚Р°РІРёС‚ СЂРµРєРІРёР·РёС‚С‹ Р°РІС‚РѕРјР°С‚РёС‡РµСЃРєРё.
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">РћСЃРЅРѕРІРЅРѕР№ С‚РµР»РµС„РѕРЅ</label>
                                            <input
                                                v-model="form.phone"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            />
                                        </div>
                                    </div>
                                    <div v-if="isInnLookupPending" class="inline-flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        <Search class="h-4 w-4 animate-pulse" />
                                        РРґС‘С‚ РїРѕРёСЃРє СЂРµРєРІРёР·РёС‚РѕРІ РІ DaData...
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
                                        <label class="text-sm font-medium">РЎР°Р№С‚</label>
                                        <input
                                            v-model="form.website"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">РљСЂР°С‚РєРѕРµ РѕРїРёСЃР°РЅРёРµ РєРѕРЅС‚СЂР°РіРµРЅС‚Р°</label>
                                    <textarea
                                        v-model="form.short_description"
                                        rows="4"
                                        placeholder="РљРѕСЂРѕС‚РєРѕ: С‡РµРј Р·Р°РЅРёРјР°РµС‚СЃСЏ РєРѕРјРїР°РЅРёСЏ, СЃРёР»СЊРЅС‹Рµ СЃС‚РѕСЂРѕРЅС‹, РїСЂРѕС„РёР»СЊ СЂР°Р±РѕС‚С‹"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                            </div>

                            <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">РћСЃРЅРѕРІРЅРѕР№ РєРѕРЅС‚Р°РєС‚</div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">РљРѕРЅС‚Р°РєС‚РЅРѕРµ Р»РёС†Рѕ</label>
                                    <input
                                        v-model="form.contact_person"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Р”РѕР»Р¶РЅРѕСЃС‚СЊ</label>
                                    <input
                                        v-model="form.contact_person_position"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">РўРµР»РµС„РѕРЅ</label>
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
                        <div v-if="false" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                            <div class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">РљРѕРјРјРµСЂС‡РµСЃРєРёРµ СѓСЃР»РѕРІРёСЏ</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Р­С‚Рё Р·РЅР°С‡РµРЅРёСЏ РїРѕРґСЃС‚Р°РІР»СЏСЋС‚СЃСЏ РІ Р·Р°РєР°Р· РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ Рё СѓС‡Р°СЃС‚РІСѓСЋС‚ РІ РїСЂРѕРІРµСЂРєРµ Р»РёРјРёС‚Р°.
                                        </div>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="form.stop_on_limit" type="checkbox" class="rounded border-zinc-300" />
                                        РЎС‚РѕРї-СЂР°Р±РѕС‚Р° РїРѕ Р»РёРјРёС‚Сѓ
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="space-y-2 md:col-span-2">
                                        <label class="text-sm font-medium">Р›РёРјРёС‚ Р·Р°РґРѕР»Р¶РµРЅРЅРѕСЃС‚Рё</label>
                                        <input v-model="form.debt_limit" type="number" min="0" step="0.01" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р’Р°Р»СЋС‚Р°</label>
                                        <select v-model="form.debt_limit_currency" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                            <option v-for="currency in currencyOptions" :key="currency" :value="currency">{{ currency }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹ Р·Р°РєР°Р·С‡РёРєР° РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</label>
                                        <select v-model="form.default_customer_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                            <option value="">РќРµ Р·Р°РґР°РЅР°</option>
                                            <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ РѕРїР»Р°С‚С‹ Р·Р°РєР°Р·С‡РёРєР° РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</label>
                                        <input v-model="form.default_customer_payment_term" type="text" placeholder="РќР°РїСЂРёРјРµСЂ: 7 РґРЅ OTTN" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹ РїРµСЂРµРІРѕР·С‡РёРєР° РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</label>
                                        <select v-model="form.default_carrier_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                            <option value="">РќРµ Р·Р°РґР°РЅР°</option>
                                            <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ РѕРїР»Р°С‚С‹ РїРµСЂРµРІРѕР·С‡РёРєР° РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</label>
                                        <input v-model="form.default_carrier_payment_term" type="text" placeholder="РќР°РїСЂРёРјРµСЂ: 50/50, 1 РґРЅ FTTN / 5 РґРЅ OTTN" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ СЃРѕС‚СЂСѓРґРЅРёС‡РµСЃС‚РІР° РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</label>
                                    <textarea v-model="form.cooperation_terms_notes" rows="3" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                </div>
                            </div>

                            <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">РљСЂРµРґРёС‚РЅС‹Р№ СЃС‚Р°С‚СѓСЃ</div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">РўРµРєСѓС‰Р°СЏ Р·Р°РґРѕР»Р¶РµРЅРЅРѕСЃС‚СЊ</span>
                                        <span class="font-medium">{{ formatMoney(selectedContractor?.current_debt, selectedContractor?.debt_limit_currency || form.debt_limit_currency) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Р›РёРјРёС‚</span>
                                        <span class="font-medium">{{ formatMoney(form.debt_limit, form.debt_limit_currency) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹ Р·Р°РєР°Р·С‡РёРєР°</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_customer_payment_form) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹ РїРµСЂРµРІРѕР·С‡РёРєР°</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_carrier_payment_form) }}</span>
                                    </div>
                                </div>

                                <div class="rounded-xl border px-3 py-3 text-sm" :class="selectedContractor?.debt_limit_reached ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'">
                                    {{ selectedContractor?.debt_limit_reached ? 'Р›РёРјРёС‚ РґРѕСЃС‚РёРіРЅСѓС‚. РќРѕРІС‹Рµ Р·Р°РєР°Р·С‹ РґРѕР»Р¶РЅС‹ Р±Р»РѕРєРёСЂРѕРІР°С‚СЊСЃСЏ.' : 'РџРѕ С‚РµРєСѓС‰РёРј РґР°РЅРЅС‹Рј Р»РёРјРёС‚ РЅРµ РґРѕСЃС‚РёРіРЅСѓС‚.' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'cooperation'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                            <div class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Р¤РёРЅР°РЅСЃРѕРІС‹Рµ СѓСЃР»РѕРІРёСЏ РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Р­С‚Рё Р·РЅР°С‡РµРЅРёСЏ РїРѕРґСЃС‚Р°РІР»СЏСЋС‚СЃСЏ РІ Р·Р°РєР°Р· РїСЂРё РІС‹Р±РѕСЂРµ РєРѕРЅС‚СЂР°РіРµРЅС‚Р°.
                                        </div>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="form.stop_on_limit" type="checkbox" class="rounded border-zinc-300" />
                                        РЎС‚РѕРї-СЂР°Р±РѕС‚Р° РїРѕ Р»РёРјРёС‚Сѓ
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="space-y-2 md:col-span-2">
                                        <label class="text-sm font-medium">Р›РёРјРёС‚ Р·Р°РґРѕР»Р¶РµРЅРЅРѕСЃС‚Рё</label>
                                        <input v-model="form.debt_limit" type="number" min="0" step="0.01" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р’Р°Р»СЋС‚Р°</label>
                                        <select v-model="form.debt_limit_currency" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                            <option v-for="currency in currencyOptions" :key="currency" :value="currency">{{ currency }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹</label>
                                            <select v-model="form.default_customer_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                <option value="">РќРµ Р·Р°РґР°РЅР°</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ РѕРїР»Р°С‚С‹ Р·Р°РєР°Р·С‡РёРєР°</label>
                                            <p class="border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                                                {{ paymentScheduleSummary(form.default_customer_payment_schedule) || 'РќРµ Р·Р°РґР°РЅС‹' }}
                                            </p>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє, РґРЅРµР№</label>
                                                <input v-model="form.default_customer_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РћРїР»Р°С‚Р° РїРѕ</label>
                                                <select v-model="form.default_customer_payment_schedule.postpayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <label class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <input v-model="form.default_customer_payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                                РџСЂРµРґРѕРїР»Р°С‚Р°
                                            </label>
                                        </div>
                                        <div v-if="form.default_customer_payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџСЂРµРґРѕРїР»Р°С‚Р°, %</label>
                                                <input v-model="form.default_customer_payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє РїСЂРµРґРѕРїР»Р°С‚С‹, РґРЅРµР№</label>
                                                <input v-model="form.default_customer_payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РћРїР»Р°С‚Р° РїРѕ</label>
                                                <select v-model="form.default_customer_payment_schedule.prepayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџРѕСЃС‚РѕРїР»Р°С‚Р°, %</label>
                                                <input :value="100 - Number(form.default_customer_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹</label>
                                            <select v-model="form.default_carrier_payment_form" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                <option value="">РќРµ Р·Р°РґР°РЅР°</option>
                                                <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ РѕРїР»Р°С‚С‹ РїРµСЂРµРІРѕР·С‡РёРєР°</label>
                                            <p class="border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                                                {{ paymentScheduleSummary(form.default_carrier_payment_schedule) || 'РќРµ Р·Р°РґР°РЅС‹' }}
                                            </p>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє, РґРЅРµР№</label>
                                                <input v-model="form.default_carrier_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РћРїР»Р°С‚Р° РїРѕ</label>
                                                <select v-model="form.default_carrier_payment_schedule.postpayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <label class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <input v-model="form.default_carrier_payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                                РџСЂРµРґРѕРїР»Р°С‚Р°
                                            </label>
                                        </div>
                                        <div v-if="form.default_carrier_payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџСЂРµРґРѕРїР»Р°С‚Р°, %</label>
                                                <input v-model="form.default_carrier_payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РЎСЂРѕРє РїСЂРµРґРѕРїР»Р°С‚С‹, РґРЅРµР№</label>
                                                <input v-model="form.default_carrier_payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РћРїР»Р°С‚Р° РїРѕ</label>
                                                <select v-model="form.default_carrier_payment_schedule.prepayment_mode" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                                    <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">РџРѕСЃС‚РѕРїР»Р°С‚Р°, %</label>
                                                <input :value="100 - Number(form.default_carrier_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">РЈСЃР»РѕРІРёСЏ СЃРѕС‚СЂСѓРґРЅРёС‡РµСЃС‚РІР°</label>
                                    <textarea v-model="form.cooperation_terms_notes" rows="4" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                </div>
                            </div>

                            <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">РљСЂРµРґРёС‚РЅС‹Р№ СЃС‚Р°С‚СѓСЃ</div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">РўРµРєСѓС‰Р°СЏ Р·Р°РґРѕР»Р¶РµРЅРЅРѕСЃС‚СЊ</span>
                                        <span class="font-medium">{{ formatMoney(selectedContractor?.current_debt, selectedContractor?.debt_limit_currency || form.debt_limit_currency) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Р›РёРјРёС‚</span>
                                        <span class="font-medium">{{ formatMoney(form.debt_limit, form.debt_limit_currency) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹ Р·Р°РєР°Р·С‡РёРєР°</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_customer_payment_form) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-zinc-500 dark:text-zinc-400">Р¤РѕСЂРјР° РѕРїР»Р°С‚С‹ РїРµСЂРµРІРѕР·С‡РёРєР°</span>
                                        <span class="font-medium">{{ paymentFormLabel(form.default_carrier_payment_form) }}</span>
                                    </div>
                                </div>

                                <div class="rounded-xl border px-3 py-3 text-sm" :class="selectedContractor?.debt_limit_reached ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'">
                                    {{ selectedContractor?.debt_limit_reached ? 'Р›РёРјРёС‚ РґРѕСЃС‚РёРіРЅСѓС‚. РќРѕРІС‹Рµ Р·Р°РєР°Р·С‹ РґРѕР»Р¶РЅС‹ Р±Р»РѕРєРёСЂРѕРІР°С‚СЊСЃСЏ.' : 'РџРѕ С‚РµРєСѓС‰РёРј РґР°РЅРЅС‹Рј Р»РёРјРёС‚ РЅРµ РґРѕСЃС‚РёРіРЅСѓС‚.' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'requisites'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РћСЂРі.-РїСЂР°РІРѕРІР°СЏ С„РѕСЂРјР°</label>
                                        <select
                                            v-model="form.legal_form"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        >
                                            <option value="">РќРµ СѓРєР°Р·Р°РЅР°</option>
                                            <option v-for="option in legalFormOptions" :key="option.value" :value="option.value">
                                                {{ legalFormLabelByValue[option.value] ?? option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РљРџРџ</label>
                                        <input
                                            v-model="form.kpp"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РћР“Р Рќ</label>
                                        <input
                                            v-model="form.ogrn"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РћРљРџРћ</label>
                                        <input
                                            v-model="form.okpo"
                                            type="text"
                                            class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 text-sm font-medium">Р‘Р°РЅРєРѕРІСЃРєРёРµ СЂРµРєРІРёР·РёС‚С‹</div>
                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р‘Р°РЅРє</label>
                                        <input v-model="form.bank_name" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Р‘РРљ</label>
                                            <input v-model="form.bik" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Р Р°СЃС‡С‘С‚РЅС‹Р№ СЃС‡С‘С‚</label>
                                            <input v-model="form.account_number" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РљРѕСЂСЂРµСЃРїРѕРЅРґРµРЅС‚СЃРєРёР№ СЃС‡С‘С‚</label>
                                        <input v-model="form.correspondent_account" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">ATI ID</label>
                                        <input v-model="form.ati_id" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="space-y-4">
                                <div class="relative space-y-2">
                                    <label class="text-sm font-medium">Р®СЂРёРґРёС‡РµСЃРєРёР№ Р°РґСЂРµСЃ</label>
                                    <textarea v-model="form.legal_address" rows="2" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="queueAddressLookup('legal_address')" />
                                    <div v-if="addressSuggestions.legal_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                        <button v-for="suggestion in addressSuggestions.legal_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('legal_address', suggestion)">
                                            {{ suggestion.value }}
                                        </button>
                                    </div>
                                </div>

                                <div class="relative space-y-2">
                                    <label class="text-sm font-medium">Р¤Р°РєС‚РёС‡РµСЃРєРёР№ Р°РґСЂРµСЃ</label>
                                    <textarea v-model="form.actual_address" rows="2" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="queueAddressLookup('actual_address')" />
                                    <div v-if="addressSuggestions.actual_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                        <button v-for="suggestion in addressSuggestions.actual_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('actual_address', suggestion)">
                                            {{ suggestion.value }}
                                        </button>
                                    </div>
                                </div>

                                <div class="relative space-y-2">
                                    <label class="text-sm font-medium">РџРѕС‡С‚РѕРІС‹Р№ Р°РґСЂРµСЃ</label>
                                    <textarea v-model="form.postal_address" rows="2" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" @input="queueAddressLookup('postal_address')" />
                                    <div v-if="addressSuggestions.postal_address.length > 0" class="absolute z-20 w-full border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                                        <button v-for="suggestion in addressSuggestions.postal_address" :key="suggestion.value" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60" @click="selectAddress('postal_address', suggestion)">
                                            {{ suggestion.value }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium">РЎРїРµС†РёР°Р»РёР·Р°С†РёРё</div>
                                    <textarea
                                        v-model="specializationsText"
                                        rows="6"
                                        placeholder="РџРѕ РѕРґРЅРѕР№ СЃРїРµС†РёР°Р»РёР·Р°С†РёРё РЅР° СЃС‚СЂРѕРєСѓ"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium">РўСЂРµР±РѕРІР°РЅРёСЏ Рє РїРµСЂРµРІРѕР·РєРµ</div>
                                    <textarea
                                        v-model="transportRequirementsText"
                                        rows="6"
                                        placeholder="РџРѕ РѕРґРЅРѕРјСѓ С‚СЂРµР±РѕРІР°РЅРёСЋ РЅР° СЃС‚СЂРѕРєСѓ"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="activeTab === 'contacts'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                РћС‚РґРµР»СЊРЅС‹Рµ РєРѕРЅС‚Р°РєС‚С‹ СѓРґРѕР±РЅРѕ С…СЂР°РЅРёС‚СЊ РѕС‚РґРµР»СЊРЅРѕ РѕС‚ РѕСЃРЅРѕРІРЅРѕР№ РєР°СЂС‚РѕС‡РєРё РєРѕРјРїР°РЅРёРё.
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addContact">
                                <Plus class="h-4 w-4" />
                                Р”РѕР±Р°РІРёС‚СЊ РєРѕРЅС‚Р°РєС‚
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(contact, index) in form.contacts" :key="`contact-${index}`" class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium">РљРѕРЅС‚Р°РєС‚ #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.contacts, index)">
                                        РЈРґР°Р»РёС‚СЊ
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р¤РРћ</label>
                                        <input v-model="contact.full_name" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р”РѕР»Р¶РЅРѕСЃС‚СЊ</label>
                                        <input v-model="contact.position" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <label class="flex items-center gap-2 pt-8 text-sm">
                                        <input v-model="contact.is_primary" type="checkbox" class="rounded border-zinc-300" />
                                        РћСЃРЅРѕРІРЅРѕР№ РєРѕРЅС‚Р°РєС‚
                                    </label>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РўРµР»РµС„РѕРЅ</label>
                                        <input v-model="contact.phone" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Email</label>
                                        <input v-model="contact.email" type="email" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2 md:col-span-2 xl:col-span-1">
                                        <label class="text-sm font-medium">РљРѕРјРјРµРЅС‚Р°СЂРёР№</label>
                                        <input v-model="contact.notes" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.contacts.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                РћС‚РґРµР»СЊРЅС‹Рµ РєРѕРЅС‚Р°РєС‚С‹ РїРѕРєР° РЅРµ РґРѕР±Р°РІР»РµРЅС‹.
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'history'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                РСЃС‚РѕСЂРёСЏ Р·РІРѕРЅРєРѕРІ, РїРёСЃРµРј, РІСЃС‚СЂРµС‡ Рё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ РєРѕРјРјСѓРЅРёРєР°С†РёРё.
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addInteraction">
                                <Plus class="h-4 w-4" />
                                Р”РѕР±Р°РІРёС‚СЊ Р·Р°РїРёСЃСЊ
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(interaction, index) in form.interactions" :key="`interaction-${index}`" class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium">РЎРѕР±С‹С‚РёРµ #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.interactions, index)">
                                        РЈРґР°Р»РёС‚СЊ
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р”Р°С‚Р° Рё РІСЂРµРјСЏ</label>
                                        <input v-model="interaction.contacted_at" type="datetime-local" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РљР°РЅР°Р»</label>
                                        <select v-model="interaction.channel" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50">
                                            <option value="">РќРµ СѓРєР°Р·Р°РЅ</option>
                                            <option v-for="channel in interactionChannels" :key="channel.value" :value="channel.value">
                                                {{ channel.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2 md:col-span-2">
                                        <label class="text-sm font-medium">РўРµРјР°</label>
                                        <input v-model="interaction.subject" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РљСЂР°С‚РєРѕРµ СЃРѕРґРµСЂР¶Р°РЅРёРµ</label>
                                        <textarea v-model="interaction.summary" rows="4" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р РµР·СѓР»СЊС‚Р°С‚</label>
                                        <input v-model="interaction.result" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.interactions.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                РСЃС‚РѕСЂРёСЏ РѕР±С‰РµРЅРёСЏ РїРѕРєР° РЅРµ Р·Р°РїРѕР»РЅРµРЅР°.
                            </div>
                        </div>
                    </div>
                    <div v-else-if="activeTab === 'orders'" class="space-y-4">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            РџРѕСЃР»РµРґРЅРёРµ СЃРІСЏР·Р°РЅРЅС‹Рµ Р·Р°РєР°Р·С‹. РўР°Р±Р»РёС†Р° РїРѕРєР° read-only, Р±РµР· СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ РёР· РєР°СЂС‚РѕС‡РєРё РєРѕРЅС‚СЂР°РіРµРЅС‚Р°.
                        </div>

                        <div class="overflow-auto border border-zinc-200 dark:border-zinc-800">
                            <table class="min-w-full border-collapse text-sm">
                                <thead class="bg-zinc-100 dark:bg-zinc-800">
                                    <tr class="text-left">
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Р—Р°РєР°Р·</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Р РѕР»СЊ</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">РЎС‚Р°С‚СѓСЃ</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Р”Р°С‚Р°</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">РЎС‚Р°РІРєР° РєР»РёРµРЅС‚Р°</th>
                                        <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">РЎС‚Р°РІРєР° РїРµСЂРµРІРѕР·С‡РёРєР°</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="order in selectedContractor?.orders || []" :key="order.id" class="border-b border-zinc-100 dark:border-zinc-800">
                                        <td class="px-3 py-3 font-medium">{{ order.order_number || `#${order.id}` }}</td>
                                        <td class="px-3 py-3">{{ order.relation === 'customer' ? 'Р—Р°РєР°Р·С‡РёРє' : 'РџРµСЂРµРІРѕР·С‡РёРє' }}</td>
                                        <td class="px-3 py-3">{{ order.status || 'вЂ”' }}</td>
                                        <td class="px-3 py-3">{{ formatDate(order.order_date) }}</td>
                                        <td class="px-3 py-3">{{ order.customer_rate ?? 'вЂ”' }}</td>
                                        <td class="px-3 py-3">{{ order.carrier_rate ?? 'вЂ”' }}</td>
                                    </tr>
                                    <tr v-if="(selectedContractor?.orders || []).length === 0">
                                        <td colspan="6" class="px-3 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                            РЈ РєРѕРЅС‚СЂР°РіРµРЅС‚Р° РїРѕРєР° РЅРµС‚ СЃРІСЏР·Р°РЅРЅС‹С… Р·Р°РєР°Р·РѕРІ.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'documents'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                РљР°СЂС‚РѕС‡РєР° С…СЂР°РЅРёС‚ РјРµС‚Р°РґР°РЅРЅС‹Рµ РїРѕ РґРѕРєСѓРјРµРЅС‚Р°Рј РєРѕРЅС‚СЂР°РіРµРЅС‚Р°. Р¤Р°Р№Р»РѕРІРѕРµ С…СЂР°РЅРёР»РёС‰Рµ РјРѕР¶РЅРѕ РїРѕРґРєР»СЋС‡РёС‚СЊ РѕС‚РґРµР»СЊРЅС‹Рј С€Р°РіРѕРј.
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addDocument">
                                <Plus class="h-4 w-4" />
                                Р”РѕР±Р°РІРёС‚СЊ РґРѕРєСѓРјРµРЅС‚
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(document, index) in form.documents" :key="`document-${index}`" class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium">Р”РѕРєСѓРјРµРЅС‚ #{{ index + 1 }}</div>
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-300" @click="removeItem(form.documents, index)">
                                        РЈРґР°Р»РёС‚СЊ
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РўРёРї</label>
                                        <input v-model="document.type" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РќР°РёРјРµРЅРѕРІР°РЅРёРµ</label>
                                        <input v-model="document.title" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РќРѕРјРµСЂ</label>
                                        <input v-model="document.number" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Р”Р°С‚Р° РґРѕРєСѓРјРµРЅС‚Р°</label>
                                        <input v-model="document.document_date" type="date" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">РЎС‚Р°С‚СѓСЃ</label>
                                        <input v-model="document.status" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                    <div class="space-y-2 md:col-span-2 xl:col-span-1">
                                        <label class="text-sm font-medium">РљРѕРјРјРµРЅС‚Р°СЂРёР№</label>
                                        <input v-model="document.notes" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.documents.length === 0" class="border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Р”РѕРєСѓРјРµРЅС‚С‹ РїРѕРєР° РЅРµ РґРѕР±Р°РІР»РµРЅС‹.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
