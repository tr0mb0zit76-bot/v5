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

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Поступления (факт)
                    </div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                        {{ formatMoney(analytics.totals.actual_in) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Расходы (факт)
                    </div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                        {{ formatMoney(analytics.totals.actual_out) }}
                    </div>
                </article>
                <article :class="`${crmStatCard} min-w-0 p-4`">
                    <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500">
                        Расходы (план)
                    </div>
                    <div class="mt-2 text-right text-lg font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                        {{ formatMoney(analytics.totals.plan_out) }}
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
                        Маржинальность бизнеса
                    </div>
                    <div
                        class="mt-2 text-right text-lg font-semibold tabular-nums"
                        :class="(analytics.totals.business_margin_percent ?? 0) >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ formatBusinessMarginPercent(analytics.totals.business_margin_percent) }}
                    </div>
                    <div class="mt-1 text-right text-[10px] text-zinc-500">
                        чистый поток / поступления
                    </div>
                </article>
            </div>

            <ManagementAccountingLedgerReport :pivot="analytics.pivot ?? { columns: [], rows: [], time_series: [] }" />

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
