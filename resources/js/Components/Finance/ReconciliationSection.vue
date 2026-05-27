<template>
    <section :class="`${crmPanel} overflow-auto p-5`">
        <div class="mb-3 space-y-1">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ section.title }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ section.description }}</p>
        </div>

        <table v-if="section.rows.length > 0" class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Заказ</th>
                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Дата</th>
                    <th class="px-3 py-2 text-right font-medium text-zinc-600 dark:text-zinc-300">Начислено</th>
                    <th class="px-3 py-2 text-right font-medium text-zinc-600 dark:text-zinc-300">Оплачено</th>
                    <th class="px-3 py-2 text-right font-medium text-zinc-600 dark:text-zinc-300">Остаток</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                <tr v-for="row in section.rows" :key="`${section.title}-${row.order_id}`">
                    <td class="px-3 py-2">
                        <Link
                            :href="route('orders.edit', row.order_id)"
                            class="font-medium text-zinc-900 underline decoration-zinc-300 underline-offset-2 hover:decoration-zinc-900 dark:text-zinc-100 dark:decoration-zinc-600"
                        >
                            {{ row.order_number }}
                        </Link>
                    </td>
                    <td class="px-3 py-2 tabular-nums text-zinc-600 dark:text-zinc-300">{{ formatDate(row.order_date) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.accrued) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.paid) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums" :class="balanceClass(row.balance)">
                        {{ formatMoney(row.balance) }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="bg-zinc-50 font-medium dark:bg-zinc-950/60">
                <tr>
                    <td class="px-3 py-2" colspan="2">Итого</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(section.totals.accrued) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(section.totals.paid) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums" :class="balanceClass(section.totals.balance)">
                        {{ formatMoney(section.totals.balance) }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">{{ emptyText }}</p>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { crmPanel } from '@/support/crmUi.js';

defineProps({
    section: {
        type: Object,
        required: true,
    },
    emptyText: {
        type: String,
        default: 'Нет данных за выбранный период.',
    },
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const parts = String(value).slice(0, 10).split('-');

    if (parts.length !== 3) {
        return String(value);
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function balanceClass(balance) {
    const value = Number(balance || 0);

    if (Math.abs(value) < 0.005) {
        return 'text-zinc-700 dark:text-zinc-200';
    }

    return value > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300';
}
</script>
