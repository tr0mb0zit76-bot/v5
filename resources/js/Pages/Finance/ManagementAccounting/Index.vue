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
                    Разнос банковских выписок и аналитика по статьям с планом из бюджетирования.
                </p>
            </div>
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

        <div v-if="activeTab === 'payments'" class="space-y-4">
            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 :class="crmSectionTitle">Загрузка выписки</h2>
                <form class="space-y-3" @submit.prevent="submitImport">
                    <label class="block space-y-1 text-sm">
                        <span :class="crmLabel">Счёт</span>
                        <select
                            v-model="importForm.bank_account_id"
                            :class="crmFieldFluid"
                            required
                        >
                            <option value="" disabled>Выберите счёт</option>
                            <option v-for="account in bank_accounts" :key="account.id" :value="Number(account.id)">
                                {{ account.bank_name }} · {{ account.account_mask }} ({{ account.currency }})
                            </option>
                        </select>
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span :class="crmLabel">Файл XLSX (Сбер «Реестр банковских документов»)</span>
                        <input
                            type="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            class="w-full text-sm"
                            @change="onFileChange"
                        >
                    </label>
                    <button
                        type="submit"
                        :disabled="importForm.processing"
                        :class="crmBtnPrimary"
                    >
                        Загрузить и разнести
                    </button>
                </form>
            </section>

            <section :class="`${crmPanel} p-5`">
                <h2 :class="crmSectionTitle">Импорты</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500 dark:border-zinc-700">
                                <th class="px-2 py-2">Файл</th>
                                <th class="px-2 py-2">Счёт</th>
                                <th class="px-2 py-2">Период</th>
                                <th class="px-2 py-2">Строки</th>
                                <th class="px-2 py-2">Суммы</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in imports"
                                :key="item.id"
                                class="border-b border-zinc-100 dark:border-zinc-800"
                            >
                                <td class="px-2 py-2">{{ item.file_name }}</td>
                                <td class="px-2 py-2">{{ item.bank_account?.bank_name }} {{ item.bank_account?.account_mask }}</td>
                                <td class="px-2 py-2">{{ formatDate(item.period_from) }} — {{ formatDate(item.period_to) }}</td>
                                <td class="px-2 py-2 tabular-nums">{{ item.lines_allocated }} / {{ item.lines_count }}</td>
                                <td class="px-2 py-2 tabular-nums">
                                    +{{ formatMoney(item.total_in) }} / −{{ formatMoney(item.total_out) }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <Link
                                        :href="`/finance/management-accounting/imports/${item.id}`"
                                        class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                                    >
                                        Разнести
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="imports.length === 0">
                                <td colspan="6" class="px-2 py-6 text-center text-zinc-500">Импортов пока нет</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section :class="`${crmPanel} p-5`">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 :class="crmSectionTitle">Справочник статей</h2>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            10 системных статей + статьи из «Бюджетирования» (синхронизация при открытии страницы).
                            Свои статьи можно добавить вручную.
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="crmBtnSecondary"
                        :disabled="categorySyncForm.processing"
                        @click="syncCategories"
                    >
                        Синхронизировать с бюджетом
                    </button>
                </div>

                <form class="mt-4 flex flex-wrap items-end gap-2" @submit.prevent="submitNewCategory">
                    <label class="min-w-[16rem] flex-1 space-y-1 text-sm">
                        <span :class="crmLabel">Новая статья</span>
                        <input
                            v-model="categoryForm.name"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Например: Аренда склада"
                            required
                        >
                    </label>
                    <button type="submit" :class="crmBtnPrimary" :disabled="categoryForm.processing">
                        Добавить
                    </button>
                </form>
                <p v-if="categoryForm.errors.name" class="mt-1 text-sm text-rose-600">{{ categoryForm.errors.name }}</p>

                <div class="mt-3 space-y-2">
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="font-medium">{{ category.name }}</div>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide"
                                    :class="categorySourceClass(category.source)"
                                >
                                    {{ categorySourceLabel(category.source) }}
                                </span>
                            </div>
                            <div class="text-xs text-zinc-500">{{ category.code }}</div>
                        </div>
                        <input
                            v-if="category.source !== 'system'"
                            :value="category.name"
                            type="text"
                            :class="crmField"
                            @change="updateCategory(category, $event.target.value)"
                        >
                    </div>
                    <p v-if="categories.length === 0" class="py-4 text-center text-sm text-zinc-500">
                        Статей пока нет. Запустите сидер или добавьте статьи в бюджетировании.
                    </p>
                </div>
            </section>
        </div>

        <div v-else class="space-y-4">
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
                Факт — разнесённые операции; план расходов — фиксированные статьи из бюджетирования.
            </p>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div :class="crmStatCard">
                    <div class="text-xs uppercase text-zinc-500">Поступления (факт)</div>
                    <div class="mt-1 text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                        {{ formatMoney(analytics.totals.actual_in) }}
                    </div>
                </div>
                <div :class="crmStatCard">
                    <div class="text-xs uppercase text-zinc-500">Расходы (факт)</div>
                    <div class="mt-1 text-lg font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                        {{ formatMoney(analytics.totals.actual_out) }}
                    </div>
                </div>
                <div :class="crmStatCard">
                    <div class="text-xs uppercase text-zinc-500">Расходы (план)</div>
                    <div class="mt-1 text-lg font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                        {{ formatMoney(analytics.totals.plan_out) }}
                    </div>
                </div>
                <div :class="crmStatCard">
                    <div class="text-xs uppercase text-zinc-500">Чистый поток (факт)</div>
                    <div
                        class="mt-1 text-lg font-semibold tabular-nums"
                        :class="analytics.totals.net >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ formatMoney(analytics.totals.net) }}
                    </div>
                </div>
            </div>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 :class="crmSectionTitle">ФОТ (полупериод {{ current_payroll_half.half === 1 ? '1–15' : '16–конец' }})</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Период {{ formatDate(current_payroll_half.period_start) }} — {{ formatDate(current_payroll_half.period_end) }},
                    выплата {{ formatDate(current_payroll_half.payment_date) }}
                </p>
                <div class="grid grid-cols-2 gap-3 text-sm sm:max-w-md">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs uppercase text-zinc-500">Начислено</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ formatMoney(current_payroll_half.accrued_total) }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs uppercase text-zinc-500">Выплачено</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ formatMoney(current_payroll_half.paid_total) }}</div>
                    </div>
                </div>
            </section>

            <section :class="`${crmPanel} p-5`">
                <h2 :class="crmSectionTitle">План / факт по статьям</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500 dark:border-zinc-700">
                                <th class="px-2 py-2">Статья</th>
                                <th class="px-2 py-2 text-right">Поступления</th>
                                <th class="px-2 py-2 text-right">Расходы (факт)</th>
                                <th class="px-2 py-2 text-right">План</th>
                                <th class="px-2 py-2 text-right">Δ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in analytics.rows"
                                :key="`${row.category_id ?? 'none'}-${row.name}`"
                                class="border-b border-zinc-100 dark:border-zinc-800"
                            >
                                <td class="px-2 py-2">
                                    <div class="font-medium">{{ row.name }}</div>
                                    <div v-if="row.code" class="text-xs text-zinc-500">{{ row.code }}</div>
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {{ row.actual_in > 0 ? formatMoney(row.actual_in) : '—' }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums text-rose-700 dark:text-rose-300">
                                    {{ row.actual_out > 0 ? formatMoney(row.actual_out) : '—' }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums text-zinc-500">
                                    {{ row.plan_amount !== null ? formatMoney(row.plan_amount) : '—' }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ row.variance_amount !== null ? formatMoney(row.variance_amount) : '—' }}
                                </td>
                            </tr>
                            <tr v-if="analytics.rows.length === 0">
                                <td colspan="5" class="px-2 py-8 text-center text-zinc-500">
                                    За выбранный период разнесённых операций пока нет.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="analytics.rows.length > 0">
                            <tr class="border-t border-zinc-200 font-medium dark:border-zinc-700">
                                <td class="px-2 py-2">Итого</td>
                                <td class="px-2 py-2 text-right tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {{ formatMoney(analytics.totals.actual_in) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums text-rose-700 dark:text-rose-300">
                                    {{ formatMoney(analytics.totals.actual_out) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums text-sky-700 dark:text-sky-300">
                                    {{ formatMoney(analytics.totals.plan_out) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ formatMoney(analytics.totals.actual_out - analytics.totals.plan_out) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p v-if="!analytics.plan_available" class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                    Справочник бюджетирования недоступен — план расходов не рассчитан.
                </p>
            </section>

            <section :class="`${crmPanel} p-5`">
                <h2 :class="crmSectionTitle">План / факт</h2>
                <div class="mt-4 overflow-x-auto">
                    <svg
                        :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                        class="mx-auto h-auto w-full max-w-3xl"
                        role="img"
                        aria-label="График план и факт"
                    >
                        <line
                            :x1="chartPad.left"
                            :x2="chartWidth - chartPad.right"
                            :y1="chartHeight - chartPad.bottom"
                            :y2="chartHeight - chartPad.bottom"
                            class="stroke-zinc-300 dark:stroke-zinc-600"
                            stroke-width="1"
                        />
                        <g v-for="(point, index) in analytics.chart" :key="point.key">
                            <text
                                :x="barGroupX(index)"
                                :y="chartHeight - chartPad.bottom + 18"
                                text-anchor="middle"
                                class="fill-zinc-500 text-[11px]"
                            >
                                {{ point.label }}
                            </text>
                            <rect
                                :x="barX(index, 'plan')"
                                :y="barY(point.plan)"
                                :width="barWidth"
                                :height="barHeight(point.plan)"
                                class="fill-sky-200 dark:fill-sky-900/60"
                                rx="3"
                            />
                            <rect
                                :x="barX(index, 'fact')"
                                :y="barY(point.fact)"
                                :width="barWidth"
                                :height="barHeight(point.fact)"
                                class="fill-sky-600 dark:fill-sky-400"
                                rx="3"
                            />
                        </g>
                    </svg>
                </div>
                <div class="mt-3 flex flex-wrap justify-center gap-4 text-xs text-zinc-500">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-3 w-3 rounded-sm bg-sky-200 dark:bg-sky-900/60" /> План
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="h-3 w-3 rounded-sm bg-sky-600 dark:bg-sky-400" /> Факт
                    </span>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnNeutral,
    crmBtnPrimary,
    crmBtnSecondary,
    crmField,
    crmFieldFluid,
    crmLabel,
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSectionTitle,
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
    filters: { type: Object, default: () => ({ tab: 'payments', period_type: 'month', period_anchor: '' }) },
    bank_accounts: { type: Array, default: () => [] },
    default_bank_account_id: { type: [Number, String], default: null },
    categories: { type: Array, default: () => [] },
    imports: { type: Array, default: () => [] },
    payroll_halves: { type: Array, default: () => [] },
    current_payroll_half: { type: Object, default: () => ({}) },
    analytics: { type: Object, required: true },
});

const tabs = [
    { key: 'payments', label: 'Разнос платежей' },
    { key: 'ledger', label: 'Учёт' },
];

const activeTab = computed(() => props.filters.tab ?? 'payments');

const chartWidth = 720;
const chartHeight = 240;
const chartPad = { top: 16, right: 16, bottom: 36, left: 16 };
const barWidth = 28;
const groupGap = 120;

const chartMaxValue = computed(() => {
    const values = props.analytics.chart.flatMap((point) => [Math.abs(point.plan), Math.abs(point.fact)]);

    return Math.max(...values, 1);
});

function resolveDefaultBankAccountId() {
    if (props.default_bank_account_id !== null && props.default_bank_account_id !== '') {
        return Number(props.default_bank_account_id);
    }

    const firstAccountId = props.bank_accounts[0]?.id;

    return firstAccountId !== null && firstAccountId !== undefined ? Number(firstAccountId) : '';
}

const importForm = useForm({
    bank_account_id: resolveDefaultBankAccountId(),
    statement_file: null,
});

const categoryForm = useForm({
    name: '',
});

const categorySyncForm = useForm({});

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

function barGroupX(index) {
    return chartPad.left + groupGap * index + groupGap / 2;
}

function barX(index, kind) {
    const center = barGroupX(index);
    const offset = kind === 'plan' ? -barWidth - 4 : 4;

    return center + offset;
}

function barY(value) {
    const max = chartMaxValue.value * 1.1;
    const inner = chartHeight - chartPad.top - chartPad.bottom;
    const ratio = Math.abs(Number(value)) / max;

    return chartHeight - chartPad.bottom - inner * ratio;
}

function barHeight(value) {
    const max = chartMaxValue.value * 1.1;
    const inner = chartHeight - chartPad.top - chartPad.bottom;
    const ratio = Math.abs(Number(value)) / max;

    return Math.max(inner * ratio, 0);
}

function onFileChange(event) {
    importForm.statement_file = event.target.files?.[0] ?? null;
}

function submitImport() {
    importForm.post('/finance/management-accounting/imports', {
        forceFormData: true,
    });
}

function submitNewCategory() {
    categoryForm.post('/finance/management-accounting/categories', {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset('name');
        },
    });
}

function syncCategories() {
    categorySyncForm.post('/finance/management-accounting/categories/sync', {
        preserveScroll: true,
    });
}

function updateCategory(category, name) {
    router.patch(`/finance/management-accounting/categories/${category.id}`, { name }, { preserveScroll: true });
}

function categorySourceLabel(source) {
    const labels = {
        system: 'системная',
        budget: 'бюджет',
        custom: 'своя',
    };

    return labels[source] ?? source;
}

function categorySourceClass(source) {
    const classes = {
        system: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        budget: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        custom: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    };

    return classes[source] ?? classes.system;
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ru-RU');
}
</script>
