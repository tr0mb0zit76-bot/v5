<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Бюджетирование</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                План маржи сверху вниз: безубыточность и целевые дивиденды. Меняйте допущения — график пересчитывается сразу.
            </p>
        </div>

        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(280px,360px)_1fr]">
            <aside class="space-y-4">
                <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Накладные расходы</h2>
                    <div class="mt-3 space-y-3">
                        <label v-for="field in opexFields" :key="field.key" class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ field.label }}</span>
                            <input
                                v-model.number="localInputs[field.key]"
                                type="number"
                                min="0"
                                step="1000"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Менеджеры и старт</h2>
                    <div class="mt-3 space-y-3">
                        <label v-for="field in teamFields" :key="field.key" class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ field.label }}</span>
                            <input
                                v-model.number="localInputs[field.key]"
                                type="number"
                                :min="field.min"
                                :max="field.max"
                                :step="field.step"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Цели</h2>
                    <div class="mt-3 space-y-3">
                        <label v-for="field in goalFields" :key="field.key" class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ field.label }}</span>
                            <input
                                v-model.number="localInputs[field.key]"
                                type="number"
                                :min="field.min"
                                :max="field.max"
                                :step="field.step"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                    </div>
                </section>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :disabled="saveForm.processing"
                        @click="saveScenario"
                    >
                        {{ saveForm.processing ? 'Сохранение…' : 'Сохранить сценарий' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        @click="resetToDefaults"
                    >
                        Сбросить
                    </button>
                </div>
            </aside>

            <div class="flex min-h-0 flex-col gap-4">
                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ card.label }}</div>
                        <div class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">{{ card.value }}</div>
                        <p v-if="card.hint" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ card.hint }}</p>
                    </article>
                </section>

                <section
                    v-if="plan.summary.min_cumulative < 0"
                    class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    Внимание: в модели кассовый остаток уходит в минус ({{ formatMoney(plan.summary.min_cumulative) }}).
                    Увеличьте вливание, срок выхода на безубыточность или плановую маржу.
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Траектория маржи и кассы</h2>
                        <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-sm bg-sky-500" /> Маржа (план)
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-sm bg-rose-400" /> OPEX
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-0.5 w-4 bg-emerald-500" /> Накопленный остаток
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 h-64">
                        <svg
                            class="h-full w-full"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            preserveAspectRatio="none"
                            role="img"
                            aria-label="График плана маржи"
                        >
                            <line
                                v-for="tick in yTicks"
                                :key="`grid-${tick}`"
                                :x1="chartPad.left"
                                :x2="chartWidth - chartPad.right"
                                :y1="yScale(tick)"
                                :y2="yScale(tick)"
                                class="stroke-zinc-200 dark:stroke-zinc-700"
                                stroke-width="1"
                                stroke-dasharray="4 4"
                            />

                            <g v-for="point in plan.months" :key="`bar-${point.month}`">
                                <rect
                                    :x="barX(point.month) - barWidth / 2"
                                    :y="yScale(point.margin)"
                                    :width="barWidth * 0.42"
                                    :height="Math.max(0, chartHeight - chartPad.bottom - yScale(point.margin))"
                                    class="fill-sky-500/80"
                                    rx="2"
                                />
                                <rect
                                    :x="barX(point.month) + barWidth * 0.08"
                                    :y="yScale(point.opex)"
                                    :width="barWidth * 0.42"
                                    :height="Math.max(0, chartHeight - chartPad.bottom - yScale(point.opex))"
                                    class="fill-rose-400/80"
                                    rx="2"
                                />
                            </g>

                            <polyline
                                :points="cumulativePolyline"
                                fill="none"
                                class="stroke-emerald-500"
                                stroke-width="2.5"
                                stroke-linejoin="round"
                            />

                            <circle
                                v-for="point in plan.months"
                                :key="`cum-${point.month}`"
                                :cx="barX(point.month)"
                                :cy="yScale(point.cumulative)"
                                r="3"
                                class="fill-emerald-500"
                            />
                        </svg>
                    </div>

                    <div class="mt-2 flex justify-between gap-1 text-[10px] text-zinc-500 dark:text-zinc-400 sm:text-xs">
                        <span v-for="point in plan.months" :key="`lbl-${point.month}`" class="flex-1 text-center">
                            М{{ point.month }}
                        </span>
                    </div>
                </section>

                <section class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Месяц</th>
                                <th class="px-4 py-3 text-right">Маржа</th>
                                <th class="px-4 py-3 text-right">OPEX</th>
                                <th class="px-4 py-3 text-right">Чистый поток</th>
                                <th class="px-4 py-3 text-right">Накоплено</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr
                                v-for="row in plan.months"
                                :key="`row-${row.month}`"
                                :class="highlightRowClass(row.month)"
                            >
                                <td class="px-4 py-2 font-medium">М{{ row.month }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.margin) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.opex) }}</td>
                                <td
                                    class="px-4 py-2 text-right tabular-nums"
                                    :class="row.net >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                                >
                                    {{ formatMoney(row.net) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.cumulative) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    buildBudgetPlan,
    defaultBudgetInputs,
    formatBudgetMoney,
    normalizeBudgetInputs,
} from '@/support/budgetTopDownPlanner';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-budgeting' }, () => page),
});

const props = defineProps({
    inputs: { type: Object, required: true },
    plan: { type: Object, required: true },
});

const localInputs = reactive(normalizeBudgetInputs(props.inputs));

watch(
    () => props.inputs,
    (next) => {
        Object.assign(localInputs, normalizeBudgetInputs(next));
    },
    { deep: true },
);

const plan = computed(() => buildBudgetPlan(localInputs));

const opexFields = [
    { key: 'office_monthly', label: 'Офис, ₽/мес' },
    { key: 'accounting_monthly', label: 'Бухгалтерия, ₽/мес' },
    { key: 'manager_payroll_monthly', label: 'Оклады менеджеров, ₽/мес' },
    { key: 'manager_payroll_months', label: 'Месяцев с окладами' },
];

const teamFields = [
    { key: 'manager_count', label: 'Число менеджеров', min: 1, max: 100, step: 1 },
    { key: 'owner_investment', label: 'Вливание собственника, ₽', min: 0, max: null, step: 10000 },
];

const goalFields = [
    { key: 'horizon_months', label: 'Горизонт, мес', min: 6, max: 36, step: 1 },
    { key: 'breakeven_month', label: 'Безубыточность, мес', min: 1, max: 36, step: 1 },
    { key: 'target_dividends_month', label: 'Дивиденды с мес', min: 1, max: 36, step: 1 },
    { key: 'target_dividends_amount', label: 'Дивиденды, ₽/мес', min: 0, max: null, step: 10000 },
];

const summaryCards = computed(() => {
    const s = plan.value.summary;

    return [
        {
            key: 'margin-be',
            label: `Маржа компании (мес ${s.breakeven_month})`,
            value: formatMoney(s.required_margin_breakeven),
            hint: 'Покрывает накладные — точка безубыточности',
        },
        {
            key: 'margin-target',
            label: `Маржа компании (мес ${s.target_dividends_month})`,
            value: formatMoney(s.required_margin_target),
            hint: 'OPEX + целевые дивиденды',
        },
        {
            key: 'x',
            label: 'X — цель на менеджера',
            value: formatMoney(s.manager_target_x),
            hint: `Средняя маржа при ${s.manager_count} менеджерах`,
        },
        {
            key: 'y',
            label: 'Y — нижний порог',
            value: formatMoney(s.manager_floor_y),
            hint: 'Минимум на безубыточности',
        },
    ];
});

const saveForm = useForm({
    inputs: {},
});

function formatMoney(value) {
    return formatBudgetMoney(value);
}

function saveScenario() {
    saveForm.inputs = normalizeBudgetInputs(localInputs);
    saveForm.patch(route('budgeting.scenario.update'), {
        preserveScroll: true,
    });
}

function resetToDefaults() {
    Object.assign(localInputs, normalizeBudgetInputs(defaultBudgetInputs()));
}

function highlightRowClass(month) {
    const s = plan.value.summary;

    if (month === s.breakeven_month || month === s.target_dividends_month) {
        return 'bg-sky-50/80 dark:bg-sky-950/20';
    }

    return '';
}

const chartWidth = 720;
const chartHeight = 256;
const chartPad = { top: 12, right: 12, bottom: 28, left: 12 };
const barWidth = computed(() => {
    const count = plan.value.months.length || 1;
    const inner = chartWidth - chartPad.left - chartPad.right;

    return Math.max(8, inner / count - 4);
});

const chartMaxValue = computed(() => {
    const values = plan.value.months.flatMap((p) => [p.margin, p.opex, p.cumulative]);

    return Math.max(1, ...values.map((v) => Math.abs(Number(v) || 0)));
});

const yTicks = computed(() => {
    const max = chartMaxValue.value;
    const step = Math.ceil(max / 4 / 50000) * 50000 || 50000;

    return [0, step, step * 2, step * 3, step * 4].filter((t) => t <= max * 1.1);
});

function yScale(value) {
    const max = chartMaxValue.value * 1.1;
    const inner = chartHeight - chartPad.top - chartPad.bottom;
    const ratio = Math.min(1, Math.max(0, Number(value) / max));

    return chartHeight - chartPad.bottom - inner * ratio;
}

function barX(month) {
    const count = plan.value.months.length || 1;
    const inner = chartWidth - chartPad.left - chartPad.right;
    const slot = inner / count;

    return chartPad.left + slot * (month - 0.5);
}

const cumulativePolyline = computed(() => plan.value.months
    .map((p) => `${barX(p.month)},${yScale(p.cumulative)}`)
    .join(' '));
</script>
