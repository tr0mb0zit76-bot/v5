<template>
    <div class="space-y-4">
        <section :class="`${crmPanel} p-5`">
            <h2 :class="crmSectionTitle">Динамика за период</h2>
            <div class="mt-4 overflow-x-auto">
                <svg
                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                    class="mx-auto h-auto w-full min-w-[40rem]"
                    role="img"
                    aria-label="График доходов, расходов и прибыли"
                >
                    <line
                        :x1="chartPad.left"
                        :x2="chartWidth - chartPad.right"
                        :y1="chartHeight - chartPad.bottom"
                        :y2="chartHeight - chartPad.bottom"
                        class="stroke-zinc-300 dark:stroke-zinc-600"
                        stroke-width="1"
                    />
                    <polyline
                        :points="linePoints('revenue')"
                        fill="none"
                        class="stroke-emerald-600 dark:stroke-emerald-400"
                        stroke-width="2"
                    />
                    <polyline
                        :points="linePoints('expense')"
                        fill="none"
                        class="stroke-rose-600 dark:stroke-rose-400"
                        stroke-width="2"
                    />
                    <polyline
                        :points="linePoints('profit')"
                        fill="none"
                        class="stroke-sky-600 dark:stroke-sky-400"
                        stroke-width="2"
                        stroke-dasharray="4 3"
                    />
                    <g v-for="(point, index) in timeSeries" :key="point.key">
                        <text
                            :x="pointX(index)"
                            :y="chartHeight - chartPad.bottom + 16"
                            text-anchor="middle"
                            class="fill-zinc-500 text-[10px]"
                        >
                            {{ point.label }}
                        </text>
                    </g>
                </svg>
            </div>
            <div class="mt-3 flex flex-wrap justify-center gap-4 text-xs text-zinc-500">
                <span class="inline-flex items-center gap-2"><span class="h-0.5 w-6 bg-emerald-600" /> Доходы</span>
                <span class="inline-flex items-center gap-2"><span class="h-0.5 w-6 bg-rose-600" /> Расходы</span>
                <span class="inline-flex items-center gap-2"><span class="h-0.5 w-6 border-t-2 border-dashed border-sky-600" /> Прибыль</span>
            </div>
        </section>

        <section :class="`${crmPanel} p-5`">
            <h2 :class="crmSectionTitle">Отчёт по статьям</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                В ячейке расходов: сумма и доля в доходе периода. Доходы и сводные строки — только сумма.
            </p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500 dark:border-zinc-700">
                            <th class="sticky left-0 z-10 min-w-[14rem] bg-white px-2 py-2 dark:bg-zinc-900">Статья</th>
                            <th
                                v-for="column in pivot.columns"
                                :key="column.key"
                                class="min-w-[5.5rem] px-2 py-2 text-right"
                            >
                                {{ column.label }}
                            </th>
                            <th class="min-w-[6rem] px-2 py-2 text-right">Итого</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in visibleRows" :key="rowKey(row)">
                            <tr
                                class="border-b border-zinc-100 dark:border-zinc-800"
                                :class="row.is_summary ? 'bg-zinc-50 font-semibold dark:bg-zinc-950/50' : ''"
                            >
                                <td class="sticky left-0 z-10 bg-white px-2 py-2 dark:bg-zinc-900">
                                    <div class="flex items-center gap-1" :style="{ paddingLeft: `${row.depth * 0.75}rem` }">
                                        <button
                                            v-if="row.has_children"
                                            type="button"
                                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center border border-zinc-200 text-[10px] dark:border-zinc-700"
                                            @click="toggleExpand(row.category_id)"
                                        >
                                            {{ isExpanded(row.category_id) ? '−' : '+' }}
                                        </button>
                                        <span v-else class="inline-block w-5 shrink-0" />
                                        <span>{{ row.name }}</span>
                                    </div>
                                </td>
                                <td
                                    v-for="cell in row.cells"
                                    :key="`${rowKey(row)}-${cell.key}`"
                                    class="px-2 py-2 text-right tabular-nums"
                                >
                                    <div>{{ formatMoney(cell.amount) }}</div>
                                    <div v-if="showPercent(row, cell)" class="text-[10px] text-zinc-500">
                                        {{ cell.percent }}%
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums font-medium">
                                    {{ formatMoney(rowTotal(row)) }}
                                </td>
                            </tr>
                            <tr v-if="isExpanded(row.category_id) && row.breakdown?.length">
                                <td :colspan="pivot.columns.length + 2" class="bg-zinc-50 px-2 py-2 dark:bg-zinc-950/40">
                                    <table class="min-w-full text-xs">
                                        <thead>
                                            <tr class="text-zinc-500">
                                                <th class="py-1 pl-8 text-left font-medium">
                                                    {{ breakdownColumnLabel(row.breakdown_label) }}
                                                </th>
                                                <th class="py-1 text-right font-medium">Сумма</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in row.breakdown" :key="item.id" class="border-b border-zinc-100 dark:border-zinc-800">
                                                <td class="py-1 pl-8">{{ item.name }}</td>
                                                <td class="py-1 text-right tabular-nums">{{ formatMoney(breakdownAmount(item, row.breakdown_label)) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { crmPanel, crmSectionTitle } from '@/support/crmUi.js';

const props = defineProps({
    pivot: {
        type: Object,
        default: () => ({ columns: [], rows: [], time_series: [] }),
    },
});

const chartWidth = 900;
const chartHeight = 260;
const chartPad = { top: 20, right: 20, bottom: 36, left: 48 };

const expandedIds = ref([]);

const timeSeries = computed(() => props.pivot.time_series ?? []);

watch(
    () => props.pivot.rows,
    (rows) => {
        expandedIds.value = rows
            .filter((row) => row.has_children && (row.depth ?? 0) === 0)
            .map((row) => row.category_id)
            .filter((id) => id !== null && id !== undefined);
    },
    { immediate: true },
);

const parentById = computed(() => {
    const map = {};

    (props.pivot.rows ?? []).forEach((row) => {
        if (row.category_id !== null && row.category_id !== undefined) {
            map[row.category_id] = row.parent_id ?? null;
        }
    });

    return map;
});

const visibleRows = computed(() => (props.pivot.rows ?? []).filter((row) => {
    if (row.is_summary) {
        return true;
    }

    if ((row.depth ?? 0) === 0) {
        return true;
    }

    let parentId = row.parent_id;

    while (parentId !== null && parentId !== undefined) {
        if (!expandedIds.value.includes(parentId)) {
            return false;
        }

        parentId = parentById.value[parentId] ?? null;
    }

    return true;
}));

const chartMax = computed(() => {
    const values = timeSeries.value.flatMap((point) => [
        Math.abs(point.revenue),
        Math.abs(point.expense),
        Math.abs(point.profit),
    ]);

    return Math.max(...values, 1) * 1.1;
});

function isExpanded(categoryId) {
    return expandedIds.value.includes(categoryId);
}

function toggleExpand(categoryId) {
    if (isExpanded(categoryId)) {
        expandedIds.value = expandedIds.value.filter((id) => id !== categoryId);
    } else {
        expandedIds.value = [...expandedIds.value, categoryId];
    }
}

function rowKey(row) {
    return `${row.category_id ?? 'summary'}-${row.code ?? row.name}`;
}

function rowTotal(row) {
    if (row.is_summary) {
        return row.summary_profit ?? 0;
    }

    if ((row.flow ?? 'out') === 'in') {
        return row.actual_in ?? 0;
    }

    return row.actual_out ?? 0;
}

function pointX(index) {
    const count = Math.max(timeSeries.value.length, 1);
    const inner = chartWidth - chartPad.left - chartPad.right;

    return chartPad.left + (inner * index) / Math.max(count - 1, 1);
}

function pointY(value) {
    const inner = chartHeight - chartPad.top - chartPad.bottom;
    const ratio = Math.abs(Number(value)) / chartMax.value;

    return chartHeight - chartPad.bottom - inner * ratio;
}

function linePoints(field) {
    return timeSeries.value
        .map((point, index) => `${pointX(index)},${pointY(point[field])}`)
        .join(' ');
}

function breakdownColumnLabel(label) {
    if (label === 'order') {
        return 'Заказ';
    }

    if (label === 'employee') {
        return 'Сотрудник';
    }

    return 'Детализация';
}

function breakdownAmount(item, label) {
    if (label === 'employee') {
        return item.actual_out || item.actual_in;
    }

    return item.actual_out || item.actual_in;
}

function showPercent(row, cell) {
    if (cell.percent === null || cell.percent === undefined) {
        return false;
    }

    if (row.is_summary) {
        return false;
    }

    return (row.flow ?? 'out') === 'out';
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}
</script>
