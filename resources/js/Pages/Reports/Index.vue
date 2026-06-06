<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto">
        <section :class="`${crmPanel} p-5`">
            <div>
                <h1 :class="crmPageTitle">Отчёты</h1>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    :class="crmTabButtonClasses(tab === t.key)"
                    @click="switchTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <div
                class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                        {{ glossaryBlock }}
                    </p>
                    <div v-if="usesPartyFilter" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="filterForm.party === 'customer' ? crmPillActive : crmPill"
                            @click="switchParty('customer')"
                        >
                            Заказчики
                        </button>
                        <button
                            type="button"
                            :class="filterForm.party === 'carrier' ? crmPillActive : crmPill"
                            @click="switchParty('carrier')"
                        >
                            Перевозчики
                        </button>
                    </div>
                </div>

                <form
                    v-if="usesDateRange"
                    class="crm-filter-bar shrink-0"
                    @submit.prevent="applyFilters"
                >
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">С</span>
                        <input
                            v-model="filterForm.date_from"
                            type="date"
                            :class="crmField"
                        >
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">По</span>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            :class="crmField"
                        >
                    </label>
                    <button type="submit" :class="crmBtnPrimary">
                        Применить
                    </button>
                </form>

                <form
                    v-else-if="tab === 'lead-process'"
                    class="crm-filter-bar shrink-0"
                    @submit.prevent="applyLeadProcessFilters"
                >
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Порог «долго на этапе», дн.</span>
                        <input
                            v-model.number="filterForm.stuck_days"
                            type="number"
                            min="1"
                            max="365"
                            class="w-28"
                            :class="crmField"
                        >
                    </label>
                    <button type="submit" :class="crmBtnPrimary">
                        Применить
                    </button>
                </form>
            </div>
        </section>

        <section v-if="tab === 'abc'" :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Класс</th>
                        <th class="px-4 py-3">{{ partyContractorLabel }}</th>
                        <th class="px-4 py-3 text-right">Заказов</th>
                        <th class="px-4 py-3 text-right">{{ partyAmountLabel }}</th>
                        <th class="px-4 py-3 text-right">Доля, %</th>
                        <th class="px-4 py-3 text-right">Накопл., %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in abc.rows" :key="row.contractor_id" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-2">
                            <span
                                class="inline-flex min-w-[1.5rem] items-center justify-center rounded border px-1.5 py-0.5 text-xs font-bold"
                                :class="abcBadgeClass(row.abc_class)"
                            >{{ row.abc_class }}</span>
                        </td>
                        <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ row.contractor_name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ row.orders_count }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(row.revenue) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ row.share_percent.toFixed(2) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ row.cumulative_share_percent.toFixed(2) }}</td>
                    </tr>
                    <tr v-if="!abc.rows.length" class="text-zinc-500 dark:text-zinc-400">
                        <td colspan="6" class="px-4 py-6 text-center text-sm">Нет данных за период.</td>
                    </tr>
                </tbody>
                <tfoot v-if="abc.rows.length" class="border-t border-zinc-200 bg-zinc-50 font-semibold dark:border-zinc-700 dark:bg-zinc-950/40">
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-zinc-700 dark:text-zinc-300">Итого</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(abc.total_revenue) }}</td>
                        <td colspan="2" class="px-4 py-2 text-right text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ abc.total_orders }} заказов</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section v-else-if="tab === 'xyz'" :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="sticky left-0 z-10 bg-zinc-50 px-4 py-3 dark:bg-zinc-950/50">XYZ</th>
                        <th class="sticky left-12 z-10 bg-zinc-50 px-4 py-3 dark:bg-zinc-950/50">{{ partyContractorLabel }}</th>
                        <th v-for="m in xyz.months" :key="m" class="px-2 py-3 text-right">{{ m }}</th>
                        <th class="px-4 py-3 text-right">μ</th>
                        <th class="px-4 py-3 text-right">σ</th>
                        <th class="px-4 py-3 text-right">CV</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in xyz.rows" :key="row.contractor_id" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2 dark:bg-zinc-900">
                            <span
                                class="inline-flex min-w-[1.5rem] items-center justify-center rounded border px-1.5 py-0.5 text-xs font-bold"
                                :class="xyzBadgeClass(row.xyz_class)"
                            >{{ row.xyz_class }}</span>
                        </td>
                        <td class="sticky left-12 z-10 bg-white px-4 py-2 font-medium text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">{{ row.contractor_name }}</td>
                        <td v-for="(v, idx) in row.monthly_revenues" :key="idx" class="px-2 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ formatMoney(v) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.mean) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.std_dev) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ row.cv === null ? '—' : row.cv.toFixed(3) }}</td>
                    </tr>
                    <tr v-if="!xyz.rows.length" class="text-zinc-500 dark:text-zinc-400">
                        <td :colspan="4 + (xyz.months?.length || 0)" class="px-4 py-6 text-center text-sm">Нет данных за выбранные месяцы.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-else-if="tab === 'lead-process'" :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Лид</th>
                        <th class="px-4 py-3">Причина</th>
                        <th class="px-4 py-3">Процесс</th>
                        <th class="px-4 py-3">Этап</th>
                        <th class="px-4 py-3">Ответственный</th>
                        <th class="px-4 py-3 text-right">На этапе с</th>
                        <th class="px-4 py-3 text-right">Дней на этапе</th>
                        <th class="px-4 py-3 text-right">Срок этапа</th>
                        <th class="px-4 py-3 text-right">Просрочка, дн.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in lead_process.rows"
                        :key="row.lead_id"
                        class="cursor-pointer border-b border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"
                        @click="openLead(row.lead_id)"
                    >
                        <td class="px-4 py-2">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.lead_number }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.lead_title }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="label in row.issue_labels"
                                    :key="label"
                                    class="inline-flex rounded border px-1.5 py-0.5 text-[10px] font-medium"
                                    :class="issueLabelClass(label)"
                                >{{ label }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2">{{ row.process_name || '—' }}</td>
                        <td class="px-4 py-2">{{ row.stage_name || '—' }}</td>
                        <td class="px-4 py-2">{{ row.responsible_name || '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatDateTime(row.stage_entered_at) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ row.days_on_stage ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatDateTime(row.stage_due_at) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-rose-600 dark:text-rose-400">{{ row.days_overdue ?? '—' }}</td>
                    </tr>
                    <tr v-if="!lead_process.rows.length" class="text-zinc-500 dark:text-zinc-400">
                        <td colspan="9" class="px-4 py-6 text-center text-sm">
                            {{ has_leads_access ? `Нет лидов с проблемой на этапе (порог «долго на этапе»: ${lead_process.stuck_days ?? filterForm.stuck_days} дн.).` : 'Нет доступа к разделу «Лиды».' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-else :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Менеджер</th>
                        <th class="px-4 py-3 text-right">Заказов</th>
                        <th class="px-4 py-3 text-right">Маржа</th>
                        <th class="px-4 py-3 text-right">Средний чек</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in managers" :key="row.manager_id" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ row.manager_name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ row.orders_count }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(row.margin) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ formatMoney(row.avg_check) }}</td>
                    </tr>
                    <tr v-if="!managers.length" class="text-zinc-500 dark:text-zinc-400">
                        <td colspan="4" class="px-4 py-6 text-center text-sm">Нет закрытых заказов за период.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnPrimary,
    crmField,
    crmFilterBar,
    crmFilterField,
    crmLabelCompact,
    crmPageTitle,
    crmPanel,
    crmPill,
    crmPillActive,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'reports', activeSubKey: 'reports-overview' }, () => page),
});

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    tab: { type: String, default: 'abc' },
    order_scope: { type: String, default: 'own' },
    leads_scope: { type: String, default: 'own' },
    has_leads_access: { type: Boolean, default: false },
    lead_process: { type: Object, default: () => ({ rows: [], stuck_days: 3 }) },
    abc: { type: Object, default: () => ({ rows: [], total_revenue: 0, total_orders: 0 }) },
    xyz: { type: Object, default: () => ({ rows: [], months: [] }) },
    managers: { type: Array, default: () => [] },
    glossary: { type: Object, default: () => ({}) },
});

const DEFAULT_STUCK_DAYS = 3;

const filterForm = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    party: props.filters.party === 'carrier' ? 'carrier' : 'customer',
    stuck_days: props.filters.stuck_days ?? props.lead_process?.stuck_days ?? DEFAULT_STUCK_DAYS,
});

const tabs = [
    { key: 'abc', label: 'ABC' },
    { key: 'xyz', label: 'XYZ' },
    { key: 'managers', label: 'Менеджеры' },
    { key: 'lead-process', label: 'Этапы лидов' },
];

const usesDateRange = computed(() => ['abc', 'xyz', 'managers'].includes(props.tab));

const usesPartyFilter = computed(() => ['abc', 'xyz'].includes(props.tab));

const isCarrierParty = computed(() => filterForm.party === 'carrier');

const partyContractorLabel = computed(() => (isCarrierParty.value ? 'Перевозчик' : 'Клиент'));

const partyAmountLabel = computed(() => (isCarrierParty.value ? 'Сумма ставок' : 'Выручка'));

const glossaryBlock = computed(() => {
    if (props.tab === 'xyz') {
        return isCarrierParty.value ? props.glossary.xyz_carrier : props.glossary.xyz_customer;
    }
    if (props.tab === 'managers') {
        return props.glossary.managers;
    }
    if (props.tab === 'lead-process') {
        return props.glossary.lead_process;
    }

    return isCarrierParty.value ? props.glossary.abc_carrier : props.glossary.abc_customer;
});

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function abcBadgeClass(cls) {
    if (cls === 'A') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    }
    if (cls === 'B') {
        return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
    }

    return 'border-zinc-300 bg-zinc-100 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200';
}

function xyzBadgeClass(cls) {
    if (cls === 'X') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    }
    if (cls === 'Y') {
        return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
    }
    if (cls === 'Z') {
        return 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200';
    }

    return 'border-zinc-300 bg-zinc-100 text-zinc-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300';
}

function issueLabelClass(label) {
    if (label === 'Срок этапа') {
        return 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200';
    }

    return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
}

function normalizeStuckDays(value) {
    const days = Number.parseInt(String(value ?? DEFAULT_STUCK_DAYS), 10);

    if (Number.isNaN(days)) {
        return DEFAULT_STUCK_DAYS;
    }

    return Math.min(365, Math.max(1, days));
}

function reportQueryParams(tab) {
    const params = {
        tab,
    };

    if (['abc', 'xyz', 'managers'].includes(tab)) {
        params.date_from = filterForm.date_from;
        params.date_to = filterForm.date_to;
    }

    if (['abc', 'xyz'].includes(tab)) {
        params.party = filterForm.party === 'carrier' ? 'carrier' : 'customer';
    }

    if (tab === 'lead-process') {
        params.stuck_days = normalizeStuckDays(filterForm.stuck_days);
    }

    return params;
}

function applyFilters() {
    router.get(route('reports.index'), reportQueryParams(props.tab), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function applyLeadProcessFilters() {
    filterForm.stuck_days = normalizeStuckDays(filterForm.stuck_days);

    router.get(route('reports.index'), reportQueryParams('lead-process'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function switchTab(key) {
    router.get(route('reports.index'), reportQueryParams(key), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function switchParty(party) {
    if (filterForm.party === party) {
        return;
    }

    filterForm.party = party;

    router.get(route('reports.index'), reportQueryParams(props.tab), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function openLead(leadId) {
    if (!leadId) {
        return;
    }

    router.get(route('leads.show', leadId));
}
</script>
