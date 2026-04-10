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
                        Номера счетов и УПД ведутся в карточке заказа (вкладка «Документы»). Здесь — график оплат и
                        зарплатный модуль.
                    </p>
                </div>
            </div>

            <div class="mt-4 grid min-h-0 grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
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
                                v-if="tile.key === 'cashflow'"
                                class="text-sm leading-6 text-zinc-500 dark:text-zinc-400"
                            >
                                Сегодня по плану: поступление {{ formatMoney(todaysCashFlow.incoming) }}, оплата
                                перевозчикам {{ formatMoney(todaysCashFlow.outgoing) }}.
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

        <div v-else-if="activeSubmodule === 'cashflow'" class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <Link
                    href="/finance"
                    class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                    К обзору финансов
                </Link>
            </div>

            <section class="border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">График оплат</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        План и факт по строкам графика заказов. Источник данных — расписание платежей в заказе.
                    </p>
                </div>
                <div v-if="cashFlowJournal.length === 0" class="px-5 py-10 text-sm text-zinc-500 dark:text-zinc-400">
                    График оплат пока не заполнен — задайте платежи в финансовом блоке заказа.
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
                                    <td class="px-3 py-3 font-medium text-zinc-900 dark:text-zinc-50">
                                        <Link
                                            :href="route('orders.edit', row.order_id)"
                                            class="text-zinc-900 underline decoration-zinc-300 underline-offset-2 hover:decoration-zinc-900 dark:text-zinc-50 dark:decoration-zinc-600 dark:hover:decoration-zinc-200"
                                        >
                                            {{ row.order_number || `#${row.order_id}` }}
                                        </Link>
                                    </td>
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

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Ожидаемые движения</h3>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-sky-200/80 bg-gradient-to-br from-sky-50 to-white p-4 dark:border-sky-900/50 dark:from-sky-950/40 dark:to-zinc-900">
                            <div class="text-xs font-medium text-sky-800 dark:text-sky-200">Сегодня — к нам</div>
                            <div class="mt-2 text-xl font-semibold tabular-nums text-sky-950 dark:text-sky-50">{{ formatMoney(cashFlowStats.periods.today.incoming) }}</div>
                        </div>
                        <div class="rounded-2xl border border-violet-200/80 bg-gradient-to-br from-violet-50 to-white p-4 dark:border-violet-900/50 dark:from-violet-950/40 dark:to-zinc-900">
                            <div class="text-xs font-medium text-violet-800 dark:text-violet-200">Неделя — к нам</div>
                            <div class="mt-2 text-xl font-semibold tabular-nums text-violet-950 dark:text-violet-50">{{ formatMoney(cashFlowStats.periods.week.incoming) }}</div>
                        </div>
                        <div class="rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50 to-white p-4 dark:border-indigo-900/50 dark:from-indigo-950/40 dark:to-zinc-900">
                            <div class="text-xs font-medium text-indigo-800 dark:text-indigo-200">Месяц — к нам</div>
                            <div class="mt-2 text-xl font-semibold tabular-nums text-indigo-950 dark:text-indigo-50">{{ formatMoney(cashFlowStats.periods.month.incoming) }}</div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-white p-4 dark:border-amber-900/50 dark:from-amber-950/40 dark:to-zinc-900">
                            <div class="text-xs font-medium text-amber-900 dark:text-amber-200">Сегодня — мы платим</div>
                            <div class="mt-2 text-xl font-semibold tabular-nums text-amber-950 dark:text-amber-50">{{ formatMoney(cashFlowStats.periods.today.outgoing) }}</div>
                        </div>
                        <div class="rounded-2xl border border-orange-200/80 bg-gradient-to-br from-orange-50 to-white p-4 dark:border-orange-900/50 dark:from-orange-950/40 dark:to-zinc-900">
                            <div class="text-xs font-medium text-orange-900 dark:text-orange-200">Неделя — мы платим</div>
                            <div class="mt-2 text-xl font-semibold tabular-nums text-orange-950 dark:text-orange-50">{{ formatMoney(cashFlowStats.periods.week.outgoing) }}</div>
                        </div>
                        <div class="rounded-2xl border border-rose-200/80 bg-gradient-to-br from-rose-50 to-white p-4 dark:border-rose-900/50 dark:from-rose-950/40 dark:to-zinc-900">
                            <div class="text-xs font-medium text-rose-900 dark:text-rose-200">Месяц — мы платим</div>
                            <div class="mt-2 text-xl font-semibold tabular-nums text-rose-950 dark:text-rose-50">{{ formatMoney(cashFlowStats.periods.month.outgoing) }}</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Задолженности по графику</h3>
                    <div
                        class="overflow-hidden rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 via-white to-white shadow-sm dark:border-emerald-900/40 dark:from-emerald-950/30 dark:via-zinc-900 dark:to-zinc-900"
                    >
                        <div class="border-b border-emerald-200/60 bg-emerald-600/5 px-4 py-3 dark:border-emerald-900/50">
                            <div class="flex items-center gap-2 text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                <TrendingUp class="h-4 w-4 shrink-0" />
                                Дебиторка (должны нам клиенты)
                            </div>
                        </div>
                        <div class="space-y-4 p-4">
                            <div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Всего неоплачено по графику</div>
                                <div class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ formatMoney(cashFlowStats.receivables.total) }}</div>
                            </div>
                            <div class="grid gap-3 border-t border-emerald-200/50 pt-4 dark:border-emerald-900/40 sm:grid-cols-2">
                                <div class="flex gap-3 rounded-xl bg-white/80 p-3 dark:bg-zinc-950/50">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">
                                        <Clock class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">В срок по плану</div>
                                        <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(cashFlowStats.receivables.pending) }}</div>
                                    </div>
                                </div>
                                <div class="flex gap-3 rounded-xl border border-rose-200 bg-rose-50/80 p-3 dark:border-rose-900/50 dark:bg-rose-950/30">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                                        <AlertTriangle class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-rose-800 dark:text-rose-200">Просрочено — срочно напомнить</div>
                                        <div class="text-lg font-semibold tabular-nums text-rose-900 dark:text-rose-100">{{ formatMoney(cashFlowStats.receivables.overdue) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-2xl border border-violet-200/90 bg-gradient-to-br from-violet-50/90 via-white to-white shadow-sm dark:border-violet-900/40 dark:from-violet-950/30 dark:via-zinc-900 dark:to-zinc-900"
                    >
                        <div class="border-b border-violet-200/60 bg-violet-600/5 px-4 py-3 dark:border-violet-900/50">
                            <div class="flex items-center gap-2 text-sm font-semibold text-violet-900 dark:text-violet-100">
                                <TrendingDown class="h-4 w-4 shrink-0" />
                                Кредиторка (мы должны перевозчикам)
                            </div>
                        </div>
                        <div class="space-y-4 p-4">
                            <div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Всего неоплачено по графику</div>
                                <div class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ formatMoney(cashFlowStats.payables.total) }}</div>
                            </div>
                            <div class="grid gap-3 border-t border-violet-200/50 pt-4 dark:border-violet-900/40 sm:grid-cols-2">
                                <div class="flex gap-3 rounded-xl bg-white/80 p-3 dark:bg-zinc-950/50">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                                        <Clock class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">В срок по плану</div>
                                        <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(cashFlowStats.payables.pending) }}</div>
                                    </div>
                                </div>
                                <div class="flex gap-3 rounded-xl border border-orange-200 bg-orange-50/80 p-3 dark:border-orange-900/50 dark:bg-orange-950/30">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300">
                                        <AlertTriangle class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-orange-800 dark:text-orange-200">Просрочено — срочно закрыть</div>
                                        <div class="text-lg font-semibold tabular-nums text-orange-900 dark:text-orange-100">{{ formatMoney(cashFlowStats.payables.overdue) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, BarChart3, Clock, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

function defaultCashFlowStats() {
    return {
        periods: {
            today: { incoming: 0.0, outgoing: 0.0 },
            week: { incoming: 0.0, outgoing: 0.0 },
            month: { incoming: 0.0, outgoing: 0.0 },
        },
        receivables: { total: 0.0, pending: 0.0, overdue: 0.0 },
        payables: { total: 0.0, pending: 0.0, overdue: 0.0 },
    };
}

defineOptions({
    layout: (h, page) =>
        h(
            CrmLayout,
            {
                activeKey: 'finance',
                activeSubKey:
                    page.props.active_submodule === 'cashflow'
                        ? 'finance-cashflow'
                        : null,
            },
            () => page,
        ),
});

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
    cashFlowJournal: {
        type: Array,
        default: () => [],
    },
    todays_cash_flow: {
        type: Object,
        default: () => ({ incoming: 0, outgoing: 0 }),
    },
    cash_flow_stats: {
        type: Object,
        default: () => ({}),
    },
    active_submodule: {
        type: String,
        default: 'overview',
    },
    can_access_salary_module: {
        type: Boolean,
        default: false,
    },
});

const submoduleTiles = computed(() => {
    const tiles = [
        {
            key: 'cashflow',
            title: 'График оплат',
            description: 'План, факт, суммы и статусы оплат по заказам.',
            href: route('finance.index', { section: 'cashflow' }),
            group: 'Оплаты',
            accent: 'emerald',
            icon: 'bar-chart-3',
        },
    ];

    if (props.can_access_salary_module) {
        tiles.push({
            key: 'salary',
            title: 'Зарплата',
            description: 'Периоды начислений, суммы к выплате по оплатам заказов и учёт фактических выплат.',
            href: route('finance.salary.index'),
            group: 'Зарплата',
            accent: 'indigo',
            icon: 'wallet',
        });
    }

    return tiles;
});

const cashFlowStats = computed(() => props.cash_flow_stats ?? defaultCashFlowStats());
const todaysCashFlow = computed(() => props.todays_cash_flow ?? cashFlowStats.value.periods.today);

const activeSubmodule = computed(() => {
    if (props.active_submodule === 'cashflow') {
        return 'cashflow';
    }

    return 'overview';
});

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function statusLabel(status) {
    const labels = {
        pending: 'Ожидает',
        paid: 'Оплачено',
        overdue: 'Просрочено',
        cancelled: 'Отменено',
    };

    return labels[status] ?? status ?? '—';
}

function statusClass(status) {
    const classes = {
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
        overdue: 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
        cancelled: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
    };

    return classes[status] ?? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
}

function iconFor(icon) {
    return {
        'bar-chart-3': BarChart3,
        wallet: Wallet,
    }[icon] ?? BarChart3;
}

function iconTone(accent) {
    return {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300',
        indigo: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-300',
    }[accent] ?? 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100';
}
</script>
