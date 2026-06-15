<template>
    <section :class="`${crmPanel} overflow-auto p-5`">
        <div class="mb-3 space-y-1">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ section.title }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ section.description }}</p>
        </div>

        <table v-if="section.rows.length > 0" class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                <tr>
                    <th class="w-8 px-2 py-2" />
                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Заказ</th>
                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Дата</th>
                    <th class="px-3 py-2 text-right font-medium text-zinc-600 dark:text-zinc-300">Начислено</th>
                    <th class="px-3 py-2 text-right font-medium text-zinc-600 dark:text-zinc-300">Оплачено</th>
                    <th class="px-3 py-2 text-right font-medium text-zinc-600 dark:text-zinc-300">Остаток</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                <template v-for="row in section.rows" :key="`${section.title}-${row.order_id}`">
                    <tr>
                        <td class="px-2 py-2 text-center">
                            <button
                                v-if="row.tranches?.length"
                                type="button"
                                class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                :title="isExpanded(row.order_id) ? 'Скрыть транши' : 'Показать транши'"
                                @click="toggleRow(row.order_id)"
                            >
                                <ChevronDown
                                    class="h-4 w-4 transition-transform"
                                    :class="isExpanded(row.order_id) ? 'rotate-180' : ''"
                                />
                            </button>
                        </td>
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
                    <tr v-if="isExpanded(row.order_id) && row.tranches?.length">
                        <td colspan="6" class="bg-zinc-50/80 px-4 py-3 dark:bg-zinc-950/40">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-zinc-500 dark:text-zinc-400">
                                        <th class="pb-2 pr-3 font-medium">Транш</th>
                                        <th class="pb-2 pr-3 font-medium">План</th>
                                        <th class="pb-2 pr-3 text-right font-medium">Начислено</th>
                                        <th class="pb-2 pr-3 text-right font-medium">Оплачено</th>
                                        <th class="pb-2 pr-3 text-right font-medium">Остаток</th>
                                        <th class="pb-2 font-medium">Оплаты</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    <tr v-for="tranche in row.tranches" :key="`${row.order_id}-${tranche.id}`">
                                        <td class="py-2 pr-3 tabular-nums text-zinc-700 dark:text-zinc-200">
                                            #{{ tranche.id }}
                                            <span v-if="tranche.invoice_number" class="text-zinc-500 dark:text-zinc-400">
                                                · счёт {{ tranche.invoice_number }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-3 tabular-nums">{{ formatDate(tranche.planned_date) }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">{{ formatMoney(tranche.accrued) }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">{{ formatMoney(tranche.paid) }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums" :class="balanceClass(tranche.balance)">
                                            {{ formatMoney(tranche.balance) }}
                                        </td>
                                        <td class="py-2">
                                            <ul v-if="tranche.payments?.length" class="space-y-1">
                                                <li
                                                    v-for="(payment, index) in tranche.payments"
                                                    :key="`${tranche.id}-payment-${index}`"
                                                    class="text-zinc-600 dark:text-zinc-300"
                                                >
                                                    {{ formatDate(payment.date) }} — {{ formatMoney(payment.amount) }}
                                                    <span v-if="payment.reference" class="text-zinc-500 dark:text-zinc-400">
                                                        ({{ payment.reference }})
                                                    </span>
                                                </li>
                                            </ul>
                                            <span v-else class="text-zinc-500 dark:text-zinc-400">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot class="bg-zinc-50 font-medium dark:bg-zinc-950/60">
                <tr>
                    <td class="px-3 py-2" colspan="3">Итого</td>
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
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
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

const expandedRows = ref(new Set());

function toggleRow(orderId) {
    const next = new Set(expandedRows.value);

    if (next.has(orderId)) {
        next.delete(orderId);
    } else {
        next.add(orderId);
    }

    expandedRows.value = next;
}

function isExpanded(orderId) {
    return expandedRows.value.has(orderId);
}

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
