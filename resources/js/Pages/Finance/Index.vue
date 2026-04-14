<template>
    <div
        class="min-h-0 flex-1"
        :class="activeSubmodule === 'cashflow' ? 'flex flex-col overflow-hidden' : 'overflow-y-auto'"
    >
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
                            <div class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ tile.title }}</div>
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

        <div v-else-if="activeSubmodule === 'cashflow'" class="flex min-h-0 flex-1 flex-col gap-6 overflow-hidden">
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                <Link
                    href="/finance"
                    class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                    К обзору финансов
                </Link>
            </div>

            <!-- Блоки статистики ПЕРЕД таблицей - ФИНАЛЬНАЯ ОПТИМИЗАЦИЯ -->
            <div class="grid shrink-0 gap-4 lg:grid-cols-3">
                <!-- Блок "Ожидаемые движения" с заголовком внутри -->
                <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-950/30">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                            <BarChart3 class="h-3 w-3 shrink-0" />
                            Ожидаемые движения
                        </div>
                    </div>
                    <div class="p-3">
                        <!-- Таблица с правильной структурой -->
                        <div class="grid grid-cols-3 gap-1">
                            <!-- Заголовки -->
                            <div class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400">Период</div>
                            <div class="text-[10px] font-medium text-sky-600 dark:text-sky-400 text-center">К нам</div>
                            <div class="text-[10px] font-medium text-amber-600 dark:text-amber-400 text-center">Мы платим</div>
                            
                            <!-- Строки -->
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Сегодня</div>
                            <div class="text-xs font-semibold tabular-nums text-sky-700 dark:text-sky-300 text-center">{{ formatMoney(cashFlowStats.periods.today.incoming) }}</div>
                            <div class="text-xs font-semibold tabular-nums text-amber-700 dark:text-amber-300 text-center">{{ formatMoney(cashFlowStats.periods.today.outgoing) }}</div>
                            
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Неделя</div>
                            <div class="text-xs font-semibold tabular-nums text-violet-700 dark:text-violet-300 text-center">{{ formatMoney(cashFlowStats.periods.week.incoming) }}</div>
                            <div class="text-xs font-semibold tabular-nums text-orange-700 dark:text-orange-300 text-center">{{ formatMoney(cashFlowStats.periods.week.outgoing) }}</div>
                            
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Месяц</div>
                            <div class="text-xs font-semibold tabular-nums text-indigo-700 dark:text-indigo-300 text-center">{{ formatMoney(cashFlowStats.periods.month.incoming) }}</div>
                            <div class="text-xs font-semibold tabular-nums text-rose-700 dark:text-rose-300 text-center">{{ formatMoney(cashFlowStats.periods.month.outgoing) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Блок "Дебиторка" -->
                <div class="overflow-hidden rounded-lg border border-emerald-200 bg-white dark:border-emerald-800 dark:bg-zinc-900">
                    <div class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-800 dark:bg-emerald-950/30">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-900 dark:text-emerald-100">
                            <TrendingUp class="h-3 w-3 shrink-0" />
                            Дебиторка
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Всего</div>
                                <div class="text-sm font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ formatMoney(cashFlowStats.receivables.total) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] font-medium text-rose-800 dark:text-rose-200">Просрочено</div>
                                <div class="text-lg font-bold tabular-nums text-rose-900 dark:text-rose-100">{{ formatMoney(cashFlowStats.receivables.overdue) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Блок "Кредиторка" -->
                <div class="overflow-hidden rounded-lg border border-violet-200 bg-white dark:border-violet-800 dark:bg-zinc-900">
                    <div class="border-b border-violet-200 bg-violet-50 px-3 py-2 dark:border-violet-800 dark:bg-violet-950/30">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-violet-900 dark:text-violet-100">
                            <TrendingDown class="h-3 w-3 shrink-0" />
                            Кредиторка
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Всего</div>
                                <div class="text-sm font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ formatMoney(cashFlowStats.payables.total) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] font-medium text-orange-800 dark:text-orange-200">Просрочено</div>
                                <div class="text-lg font-bold tabular-nums text-orange-900 dark:text-orange-100">{{ formatMoney(cashFlowStats.payables.overdue) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="flex min-h-0 flex-1 flex-col overflow-hidden border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <CashFlowGrid :rows="cashFlowJournal" :user-id="cashFlowGridUserId" />
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, BarChart3, Clock, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CashFlowGrid from '@/Components/Finance/CashFlowGrid.vue';

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

const page = usePage();
const cashFlowGridUserId = computed(() => page.props.auth?.user?.id ?? 'guest');

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

const activeSubmodule = computed(() => props.active_submodule);

const cashFlowStats = computed(() => {
    if (Object.keys(props.cash_flow_stats).length === 0) {
        return defaultCashFlowStats();
    }
    return props.cash_flow_stats;
});

const todaysCashFlow = computed(() => props.todays_cash_flow);

const submoduleTiles = computed(() => {
    const tiles = [
        {
            key: 'cashflow',
            title: 'График оплат',
            description: 'План и факт по строкам графика заказов',
            icon: 'Wallet',
            accent: 'sky',
            group: 'Платежи',
            href: '/finance?section=cashflow',
        },
    ];

    if (props.can_access_salary_module) {
        tiles.push({
            key: 'salary',
            title: 'Зарплатный модуль',
            description: 'Расчёт зарплаты водителей и экспедиторов',
            icon: 'Wallet',
            accent: 'emerald',
            group: 'Зарплата',
            href: '/finance/salary',
        });
    }

    return tiles;
});

function iconFor(name) {
    const icons = {
        Wallet,
        Clock,
        AlertTriangle,
    };
    return icons[name] || Wallet;
}

function iconTone(accent) {
    const tones = {
        sky: 'border-sky-200 bg-sky-50 text-sky-600 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-400',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400',
        amber: 'border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400',
    };
    return tones[accent] || tones.sky;
}

function formatMoney(value) {
    if (typeof value !== 'number') return '0 ₽';
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

</script>
