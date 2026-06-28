<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Link
                    href="/finance"
                    class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                    К обзору финансов
                </Link>
                <h1 :class="crmPageTitle">Управленческий учёт</h1>
                <p :class="crmPageLead">
                    Факт распоряжения валовой маржой по статьям. Разнос банковских выписок — в графике оплат.
                </p>
            </div>
            <Link
                href="/finance?section=cashflow&cashflow_tab=reconcile"
                class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-300"
            >
                Разнос выписки →
            </Link>
            <ManagementAccountingManualEntryModal
                v-if="can_manage_manual_entries"
                :bank-accounts="bank_accounts"
                :categories="categories"
                :recent-entries="recent_manual_entries"
                :can-manage="can_manage_manual_entries"
            />
        </div>

        <div class="flex flex-wrap gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
            <button
                v-for="tabItem in tabs"
                :key="tabItem.key"
                type="button"
                :class="crmTabButtonClasses(activeTab === tabItem.key)"
                @click="switchTab(tabItem.key)"
            >
                {{ tabItem.label }}
            </button>
        </div>

        <div v-if="activeTab === 'ledger'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div :class="crmSegmented">
                    <button
                        type="button"
                        :class="analytics.period_type === 'month' ? crmSegmentedBtnActive : crmSegmentedBtn"
                        @click="switchPeriodType('month')"
                    >
                        Месяц
                    </button>
                    <button
                        type="button"
                        :class="analytics.period_type === 'quarter' ? crmSegmentedBtnActive : crmSegmentedBtn"
                        @click="switchPeriodType('quarter')"
                    >
                        Квартал
                    </button>
                    <button
                        type="button"
                        :class="analytics.period_type === 'year' ? crmSegmentedBtnActive : crmSegmentedBtn"
                        @click="switchPeriodType('year')"
                    >
                        Год
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="shiftPeriod(-1)">
                        ←
                    </button>
                    <div class="min-w-[10rem] text-center text-sm font-medium capitalize text-zinc-800 dark:text-zinc-100">
                        {{ analytics.period_label }}
                    </div>
                    <button type="button" :class="crmBtnNeutral" @click="shiftPeriod(1)">
                        →
                    </button>
                </div>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ formatDate(analytics.period_start) }} — {{ formatDate(analytics.period_end) }}.
                Доходы минус себестоимость — валовая маржа; далее ФОТ, АУР и налоги из пула маржи.
            </p>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Доход (факт)
                    </div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                        {{ formatMoney(analytics.totals.actual_in) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Расход (факт, себестоимость)
                    </div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                        {{ formatMoney(analytics.totals.actual_out_cost) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Расход (факт, бюджет)
                    </div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                        {{ formatMoney(analytics.totals.actual_out_budget) }}
                    </div>
                    <div
                        v-if="analytics.plan_available && analytics.totals.plan_out > 0"
                        class="mt-1 text-right text-[10px] tabular-nums"
                        :class="(analytics.totals.budget_variance ?? 0) <= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-300'"
                    >
                        план {{ formatMoney(analytics.totals.plan_out) }}
                        · {{ formatSignedMoney(analytics.totals.budget_variance) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Расходы (план)
                    </div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                        {{ formatMoney(analytics.totals.plan_out) }}
                    </div>
                    <div
                        v-if="analytics.plan_available && analytics.totals.budget_execution_percent !== null"
                        class="mt-1 text-right text-[10px] text-zinc-500"
                    >
                        исполнение {{ formatBusinessMarginPercent(analytics.totals.budget_execution_percent) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Чистый поток (факт)
                    </div>
                    <div
                        class="mt-2 text-right text-lg font-semibold tabular-nums"
                        :class="analytics.totals.net >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ formatMoney(analytics.totals.net) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Валовая маржа
                    </div>
                    <div
                        class="mt-2 text-right text-lg font-semibold tabular-nums"
                        :class="(analytics.totals.gross_margin_percent ?? 0) >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ formatBusinessMarginPercent(analytics.totals.gross_margin_percent) }}
                    </div>
                    <div class="mt-1 text-right text-[10px] text-zinc-500">
                        {{ formatMoney(analytics.totals.gross_margin) }} · доход − себестоимость
                    </div>
                </article>
            </div>

            <section v-if="analytics.full_picture" :class="`${crmPanel} overflow-hidden`">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Полная картина за период</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Операционный контур (график оплат) и управленческий (разнесённые строки выписки) без дублей mgmt:*.
                    </p>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-3">
                    <article class="rounded-lg border border-sky-200/80 bg-sky-50/50 p-3 dark:border-sky-900/40 dark:bg-sky-950/20">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Операционный</div>
                        <div class="mt-2 text-sm">Поступления: {{ formatMoney(analytics.full_picture.operational.in) }}</div>
                        <div class="text-sm">Расходы: {{ formatMoney(analytics.full_picture.operational.out) }}</div>
                        <div class="mt-1 font-semibold">Итого: {{ formatMoney(analytics.full_picture.operational.net) }}</div>
                    </article>
                    <article class="rounded-lg border border-violet-200/80 bg-violet-50/50 p-3 dark:border-violet-900/40 dark:bg-violet-950/20">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Управленческий</div>
                        <div class="mt-2 text-sm">Поступления: {{ formatMoney(analytics.full_picture.management.in) }}</div>
                        <div class="text-sm">Расходы: {{ formatMoney(analytics.full_picture.management.out) }}</div>
                        <div class="mt-1 font-semibold">Итого: {{ formatMoney(analytics.full_picture.management.net) }}</div>
                    </article>
                    <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Суммарно</div>
                        <div class="mt-2 text-sm">Поступления: {{ formatMoney(analytics.full_picture.combined.in) }}</div>
                        <div class="text-sm">Расходы: {{ formatMoney(analytics.full_picture.combined.out) }}</div>
                        <div class="mt-1 font-semibold">Итого: {{ formatMoney(analytics.full_picture.combined.net) }}</div>
                    </article>
                </div>
                <div v-if="analytics.full_picture.rows?.length" class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/50">
                            <tr>
                                <th class="px-4 py-2">Контур</th>
                                <th class="px-4 py-2">Статья</th>
                                <th class="px-4 py-2 text-right">Поступления</th>
                                <th class="px-4 py-2 text-right">Расходы</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in analytics.full_picture.rows"
                                :key="`${row.source}-${row.category_id ?? index}`"
                                class="border-t border-zinc-100 dark:border-zinc-800"
                            >
                                <td class="px-4 py-2">{{ row.source_label }}</td>
                                <td class="px-4 py-2">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.in) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.out) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <ManagementAccountingLedgerReport :pivot="analytics.pivot ?? { columns: [], rows: [], time_series: [] }" />

            <ManagementAccountingVarianceTable
                v-if="analytics.plan_available"
                :variance-rows="analytics.variance_rows ?? []"
                :payroll-variance="analytics.payroll_variance"
                :plan-snapshot="analytics.plan_snapshot"
                :plan-source="analytics.plan_source ?? 'none'"
            />

            <p v-if="analytics.plan_source === 'live'" class="text-xs text-amber-700 dark:text-amber-300">
                План не зафиксирован — отклонения считаются по черновику бюджета. Зафиксируйте план в разделе «Бюджетирование».
            </p>
            <p v-if="!analytics.plan_available" class="text-xs text-amber-700 dark:text-amber-300">
                Справочник бюджетирования недоступен — план расходов не рассчитан.
            </p>
        </div>

        <section v-else :class="`${crmPanel} p-5`">
            <ManagementAccountingCategoryTree :tree="category_tree" />
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import ManagementAccountingCategoryTree from '@/Components/Finance/ManagementAccountingCategoryTree.vue';
import ManagementAccountingLedgerReport from '@/Components/Finance/ManagementAccountingLedgerReport.vue';
import ManagementAccountingManualEntryModal from '@/Components/Finance/ManagementAccountingManualEntryModal.vue';
import ManagementAccountingVarianceTable from '@/Components/Finance/ManagementAccountingVarianceTable.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnNeutral,
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
    crmStatCard,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-management-accounting' }, () => page),
});

const props = defineProps({
    filters: { type: Object, default: () => ({ tab: 'ledger', period_type: 'month', period_anchor: '' }) },
    categories: { type: Array, default: () => [] },
    category_tree: { type: Array, default: () => [] },
    bank_accounts: { type: Array, default: () => [] },
    recent_manual_entries: { type: Array, default: () => [] },
    can_manage_manual_entries: { type: Boolean, default: false },
    analytics: { type: Object, required: true },
});

const tabs = [
    { key: 'ledger', label: 'Учёт' },
    { key: 'categories', label: 'Статьи учёта' },
];

const activeTab = computed(() => props.filters.tab ?? 'ledger');

function reloadWithFilters(overrides = {}) {
    router.get('/finance/management-accounting', {
        tab: overrides.tab ?? activeTab.value,
        period_type: overrides.period_type ?? props.filters.period_type ?? 'month',
        period_anchor: overrides.period_anchor ?? props.filters.period_anchor,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function switchTab(tab) {
    reloadWithFilters({ tab });
}

function switchPeriodType(periodType) {
    reloadWithFilters({ tab: 'ledger', period_type: periodType });
}

function shiftPeriod(direction) {
    const anchor = new Date(`${props.filters.period_anchor}T12:00:00`);
    const periodType = props.filters.period_type ?? 'month';

    if (periodType === 'year') {
        anchor.setFullYear(anchor.getFullYear() + direction);
    } else if (periodType === 'quarter') {
        anchor.setMonth(anchor.getMonth() + direction * 3);
    } else {
        anchor.setMonth(anchor.getMonth() + direction);
    }

    const periodAnchor = `${anchor.getFullYear()}-${String(anchor.getMonth() + 1).padStart(2, '0')}-01`;

    reloadWithFilters({ tab: 'ledger', period_anchor: periodAnchor });
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatSignedMoney(value) {
    const amount = Number(value) || 0;
    const prefix = amount > 0 ? '+' : '';

    return `${prefix}${formatMoney(amount)}`;
}

function formatBusinessMarginPercent(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(Number(value))}%`;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ru-RU');
}
</script>
