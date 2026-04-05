<template>
    <div class="space-y-6">
        <section
            v-if="activeSubmodule === 'overview'"
            class="space-y-3 border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Финансы</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Выберите нужный подмодуль, чтобы перейти к реестру документов, журналу ДДС или контролировать
                        счета и УПД.
                    </p>
                </div>
                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Выберите</span>
            </div>

            <div class="mt-4 grid min-h-0 grid-cols-1 gap-4 md:grid-cols-2">
                <Link
                    v-for="tile in submoduleTiles"
                    :key="tile.key"
                    :href="tile.href"
                    class="group flex min-h-[190px] flex-col justify-between border border-zinc-200 bg-white p-5 transition hover:border-zinc-900 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-500 dark:hover:bg-zinc-800"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-none border"
                                :class="iconTone(tile.accent)"
                            >
                                <component :is="iconFor(tile.icon)" class="h-5 w-5" />
                            </div>
                            <div class="border border-zinc-200 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                {{ tile.group }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ tile.title }}</div>
                            <p
                                v-if="tile.key === 'dds'"
                                class="text-sm leading-6 text-zinc-500 dark:text-zinc-400"
                            >
                                Сегодня ожидаем поступление {{ formatMoney(todaysCashFlow.incoming) }} рублей.
                                Должны оплатить {{ formatMoney(todaysCashFlow.outgoing) }} рублей.
                            </p>
                            <p
                                v-else
                                class="text-sm leading-6 text-zinc-500 dark:text-zinc-400"
                            >
                                {{ tile.description }}
                            </p>
                        </div>
                    </div>

                    <div class="text-sm font-medium text-zinc-900 transition-transform group-hover:translate-x-1 dark:text-zinc-100">
                        Открыть →
                    </div>
                </Link>
            </div>
        </section>

        <div v-else class="space-y-4">
            <section v-if="activeSubmodule === 'documents'">
                <section class="border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Документы</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Черновики, готовые версии и история статусов.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-none border border-zinc-300 bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            @click="openCreateModal"
                        >
                            Создать документ
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="overflow-x-auto">
                            <div class="max-h-[60vh] min-w-full overflow-y-auto">
                                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                                    <thead class="bg-zinc-50 dark:bg-zinc-950/40">
                                        <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                            <th class="px-3 py-3">Тип</th>
                                            <th class="px-3 py-3">Заказ</th>
                                            <th class="px-3 py-3">Номер</th>
                                            <th class="px-3 py-3">Сумма</th>
                                            <th class="px-3 py-3">Статус</th>
                                            <th class="px-3 py-3">Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        <tr v-for="doc in documents" :key="`doc-${doc.id}`">
                                            <td class="px-3 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ documentLabel(doc.document_type) }}</td>
                                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ doc.order_number || `#${doc.order_id}` }}</td>
                                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ doc.number || '—' }}</td>
                                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ formatMoney(doc.amount) }}</td>
                                            <td class="px-3 py-3">
                                                <span class="inline-flex rounded-none bg-zinc-100 px-2 py-0.5 text-xs font-medium dark:bg-zinc-800 dark:text-zinc-200">
                                                    {{ statusLabel(doc.status) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-3">
                                                <button
                                                    type="button"
                                                    class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                                                    @click="openEditModal(doc)"
                                                >
                                                    Редактировать
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="documents.length === 0">
                                            <td colspan="6" class="px-3 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">Документы ещё не созданы.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="inline-flex items-center border px-4 py-2 text-sm transition-colors"
                            :class="activeTab === tab.key
                                ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                                : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </section>

                <section v-if="activeTab === 'invoices'" class="space-y-4">
                    <div class="border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Счета</h2>
                        </div>
                        <div v-if="invoices.length === 0" class="px-5 py-10 text-sm text-zinc-500 dark:text-zinc-400">Нет данных для реестра счетов.</div>
                        <div v-else class="overflow-x-auto">
                            <div class="max-h-[60vh] overflow-y-auto">
                                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                    <thead class="bg-zinc-50 dark:bg-zinc-950/40">
                                        <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                            <th class="px-4 py-3">Заказ</th>
                                            <th class="px-4 py-3">Дата</th>
                                            <th class="px-4 py-3">Контрагент</th>
                                            <th class="px-4 py-3">Менеджер</th>
                                            <th class="px-4 py-3">Сумма</th>
                                            <th class="px-4 py-3">Счёт</th>
                                            <th class="px-4 py-3">Статус</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                                        <tr v-for="row in invoices" :key="`invoice-${row.id}`">
                                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ row.order_number || `#${row.id}` }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.order_date || '—' }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.customer_name || '—' }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.manager_name || '—' }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ formatMoney(row.amount) }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.invoice_number || 'Не выставлен' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-1 text-xs font-medium" :class="row.is_issued ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'">
                                                    {{ row.is_issued ? 'Выставлен' : 'Черновик' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'upds'" class="space-y-4">
                    <div class="border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">УПД</h2>
                        </div>
                        <div v-if="upds.length === 0" class="px-5 py-10 text-sm text-zinc-500 dark:text-zinc-400">Нет данных по УПД.</div>
                        <div v-else class="overflow-x-auto">
                            <div class="max-h-[60vh] overflow-y-auto">
                                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                    <thead class="bg-zinc-50 dark:bg-zinc-950/40">
                                        <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                            <th class="px-4 py-3">Заказ</th>
                                            <th class="px-4 py-3">Дата</th>
                                            <th class="px-4 py-3">Клиент</th>
                                            <th class="px-4 py-3">Перевозчик</th>
                                            <th class="px-4 py-3">УПД клиента</th>
                                            <th class="px-4 py-3">УПД перевозчика</th>
                                            <th class="px-4 py-3">Статус</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                                        <tr v-for="row in upds" :key="`upd-${row.id}`">
                                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ row.order_number || `#${row.id}` }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.order_date || '—' }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.customer_name || '—' }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.carrier_name || '—' }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.customer_upd_number || '—' }}</td>
                                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ row.carrier_upd_number || '—' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-1 text-xs font-medium" :class="row.has_any_upd ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'">
                                                    {{ row.has_any_upd ? 'В работе' : 'Не заполнен' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            <section v-else>
                <section class="border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Журнал ДДС</h2>
                    </div>
                    <div v-if="cashFlowJournal.length === 0" class="px-5 py-10 text-sm text-zinc-500 dark:text-zinc-400">
                        График оплат пока не заполнен.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <div class="max-h-[60vh] overflow-y-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50 dark:bg-zinc-950/40">
                                    <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        <th class="px-3 py-3">Заказ</th>
                                        <th class="px-3 py-3">Направление</th>
                                        <th class="px-3 py-3">Контрагент</th>
                                        <th class="px-3 py-3">Тип</th>
                                        <th class="px-3 py-3">План</th>
                                        <th class="px-3 py-3">Факт</th>
                                        <th class="px-3 py-3">Сумма</th>
                                        <th class="px-3 py-3">Статус</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                                    <tr v-for="row in cashFlowJournal" :key="`cash-${row.id}`">
                                        <td class="px-3 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ row.order_number || `#${row.order_id}` }}</td>
                                        <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ row.direction }}</td>
                                        <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ row.counterparty_name || '—' }}</td>
                                        <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ row.payment_type }}</td>
                                        <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ row.planned_date || '—' }}</td>
                                        <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ row.actual_date || '—' }}</td>
                                        <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ formatMoney(row.amount) }}</td>
                                        <td class="px-3 py-3">
                                            <span class="px-2.5 py-1 text-xs font-medium" :class="statusClass(row.status)">
                                                {{ statusLabel(row.status) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </section>
        </div>

        <transition name="modal">
            <div
                v-if="showDocumentModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 dark:bg-black/60"
            >
                <div class="w-full max-w-lg rounded-none bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ modalMode === 'create' ? 'Создать документ' : 'Редактировать документ' }}
                        </h3>
                        <button type="button" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-50" @click="closeDocumentModal">
                            ×
                        </button>
                    </div>
                    <form class="mt-4 space-y-4" @submit.prevent="submitDocument">
                        <div>
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Заказ</label>
                            <select v-model="documentForm.order_id" required class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option :value="null" disabled>Выберите заказ</option>
                                <option v-for="order in orders" :key="order.id" :value="order.id">
                                    {{ orderLabel(order) }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Тип документа</label>
                                <select v-model="documentForm.document_type" required class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Статус</label>
                                <select v-model="documentForm.status" class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <option v-for="status in statusOptions" :key="status" :value="status">{{ statusLabel(status) }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Сумма</label>
                                <input v-model="documentForm.amount" type="number" min="0" step="0.01" required class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Дата</label>
                                <input v-model="documentForm.issue_date" type="date" class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Срок оплаты</label>
                                <input v-model="documentForm.due_date" type="date" class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Номер</label>
                            <input v-model="documentForm.number" type="text" class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Основание оплаты</label>
                            <input v-model="documentForm.payment_basis" type="text" class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Примечания</label>
                            <textarea v-model="documentForm.notes" rows="3" class="mt-1 w-full rounded-none border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" class="rounded-none border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800" @click="closeDocumentModal">
                                Отмена
                            </button>
                            <button type="submit" class="rounded-none bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200" :disabled="documentForm.processing">
                                {{ modalMode === 'create' ? 'Создать' : 'Сохранить' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { BarChart3, FileText } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) =>
        h(
            CrmLayout,
            {
                activeKey: 'finance',
                activeSubKey: page.props.active_submodule === 'dds' ? 'finance-dds' : 'finance-documents',
            },
            () => page,
        ),
});

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
    invoices: {
        type: Array,
        default: () => [],
    },
    upds: {
        type: Array,
        default: () => [],
    },
    cashFlowJournal: {
        type: Array,
        default: () => [],
    },
    orders: {
        type: Array,
        default: () => [],
    },
    documents: {
        type: Array,
        default: () => [],
    },
    todays_cash_flow: {
        type: Object,
        default: () => ({ incoming: 0, outgoing: 0 }),
    },
    active_submodule: {
        type: String,
        default: 'overview',
    },
});

const submoduleTiles = [
    {
        key: 'documents',
        title: 'Документы',
        description: 'Управляйте статусами счётов, УПД и актов по заказам.',
        href: route('finance.index', { section: 'documents' }),
        group: 'Документы',
        accent: 'slate',
        icon: 'file-text',
    },
    {
        key: 'dds',
        title: 'Журнал ДДС',
        description: 'План, факт, суммы и статусы оплат в одном месте.',
        href: route('finance.index', { section: 'dds' }),
        group: 'ДДС',
        accent: 'emerald',
        icon: 'bar-chart-3',
    },
];

const activeSubmodule = computed(() => {
    if (props.active_submodule === 'dds') {
        return 'dds';
    }
    if (props.active_submodule === 'documents') {
        return 'documents';
    }

    return 'overview';
});
const tabs = [
    { key: 'invoices', label: 'Счета' },
    { key: 'upds', label: 'УПД' },
];

const activeTab = ref('invoices');
const showDocumentModal = ref(false);
const modalMode = ref('create');
const editingDocument = ref(null);
const documentTypes = [
    { value: 'invoice', label: 'Счёт' },
    { value: 'upd', label: 'УПД' },
];
const statusOptions = ['draft', 'issued', 'sent', 'signed'];

const documentForm = useForm({
    order_id: null,
    document_type: 'invoice',
    amount: '',
    number: '',
    issue_date: '',
    due_date: '',
    payment_basis: '',
    notes: '',
    status: 'draft',
});

const todaysCashFlow = computed(() => props.todays_cash_flow ?? { incoming: 0, outgoing: 0 });

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function documentLabel(type) {
    return documentTypes.find((item) => item.value === type)?.label ?? type.toUpperCase();
}

function statusLabel(status) {
    const labels = {
        draft: 'Черновик',
        issued: 'Оформлен',
        sent: 'Отправлен',
        signed: 'Подписан',
    };

    return labels[status] ?? '—';
}

function statusClass(status) {
    const classes = {
        draft: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        issued: 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
        sent: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        signed: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
    };

    return classes[status] ?? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
}

function openCreateModal() {
    modalMode.value = 'create';
    editingDocument.value = null;
    documentForm.reset();
    showDocumentModal.value = true;
}

function openEditModal(doc) {
    modalMode.value = 'edit';
    editingDocument.value = doc;
    documentForm.reset({
        order_id: doc.order_id,
        document_type: doc.document_type,
        amount: doc.amount,
        number: doc.number,
        issue_date: doc.issue_date,
        due_date: doc.due_date,
        payment_basis: doc.payment_basis,
        notes: doc.notes,
        status: doc.status,
    });
    showDocumentModal.value = true;
}

function closeDocumentModal() {
    showDocumentModal.value = false;
    documentForm.reset();
}

function orderLabel(order) {
    if (!order) {
        return '—';
    }
    return order.order_number ? `${order.order_number} (${order.customer_name || '—'})` : `#${order.id}`;
}

function submitDocument() {
    documentForm.order_id = Number(documentForm.order_id);

    const routeName = modalMode.value === 'create'
        ? route('finance.documents.store')
        : route('finance.documents.update', editingDocument.value.id);

    const handler = modalMode.value === 'create' ? documentForm.post : documentForm.patch;

    handler(routeName, {
        preserveScroll: true,
        onSuccess: () => {
            closeDocumentModal();
            router.reload();
        },
    });
}

function iconFor(icon) {
    return {
        'file-text': FileText,
        'bar-chart-3': BarChart3,
    }[icon] ?? FileText;
}

function iconTone(accent) {
    return {
        slate: 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300',
    }[accent] ?? 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100';
}
</script>
