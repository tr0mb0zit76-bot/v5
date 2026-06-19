<template>
    <section :class="`${crmPanel} p-4`">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Отклонение от плана
                </h2>
                <p v-if="planSnapshot" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Снимок «{{ planSnapshot.period_label }}» от {{ formatDateTime(planSnapshot.approved_at) }}
                </p>
                <p v-else-if="planSource === 'live'" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                    Снимок плана не зафиксирован — показан черновой план из бюджетирования.
                </p>
            </div>
        </div>

        <div v-if="varianceRows.length > 0" class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700">
                        <th class="px-2 py-2 font-medium">Статья</th>
                        <th class="px-2 py-2 text-right font-medium">План</th>
                        <th class="px-2 py-2 text-right font-medium">Факт</th>
                        <th class="px-2 py-2 text-right font-medium">Δ</th>
                        <th class="px-2 py-2 text-right font-medium">Δ %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in varianceRows"
                        :key="`${row.category_id ?? 'none'}-${row.name}`"
                        class="border-b border-zinc-100 dark:border-zinc-800"
                    >
                        <td class="px-2 py-2 text-zinc-800 dark:text-zinc-100">{{ row.name }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-sky-700 dark:text-sky-300">{{ formatMoney(row.planned) }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ formatMoney(row.actual) }}</td>
                        <td
                            class="px-2 py-2 text-right tabular-nums"
                            :class="varianceClass(row.variance)"
                        >
                            {{ formatSignedMoney(row.variance) }}
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums text-zinc-500">
                            {{ formatPercent(row.variance_percent) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-else class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
            Нет данных для сравнения план/факт за выбранный период.
        </p>

        <div v-if="payrollVariance" class="mt-4 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">ФОТ продавцов</h3>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="text-[10px] uppercase text-zinc-500">План</div>
                    <div class="text-sm font-semibold tabular-nums">{{ formatMoney(payrollVariance.planned) }}</div>
                </div>
                <div>
                    <div class="text-[10px] uppercase text-zinc-500">Начислено</div>
                    <div class="text-sm font-semibold tabular-nums">{{ formatMoney(payrollVariance.actual_accrued) }}</div>
                </div>
                <div>
                    <div class="text-[10px] uppercase text-zinc-500">Выплачено</div>
                    <div class="text-sm font-semibold tabular-nums">{{ formatMoney(payrollVariance.actual_paid) }}</div>
                </div>
                <div>
                    <div class="text-[10px] uppercase text-zinc-500">Δ выплата</div>
                    <div class="text-sm font-semibold tabular-nums" :class="varianceClass(payrollVariance.variance_paid)">
                        {{ formatSignedMoney(payrollVariance.variance_paid) }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { crmPanel } from '@/support/crmUi.js';

defineProps({
    varianceRows: { type: Array, default: () => [] },
    payrollVariance: { type: Object, default: null },
    planSnapshot: { type: Object, default: null },
    planSource: { type: String, default: 'none' },
});

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

function formatPercent(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(Number(value))}%`;
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ru-RU');
}

function varianceClass(value) {
    const amount = Number(value) || 0;

    if (amount > 0) {
        return 'text-amber-700 dark:text-amber-300';
    }

    if (amount < 0) {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    return 'text-zinc-600 dark:text-zinc-300';
}
</script>
