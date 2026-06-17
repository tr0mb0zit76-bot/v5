<template>
    <div
        class="min-h-0 flex-1"
        :class="activeSubmodule === 'cashflow' ? 'flex flex-col overflow-hidden' : 'overflow-y-auto'"
    >
    <section
        v-if="activeSubmodule === 'overview'"
        :class="`${crmPanel} space-y-3 p-5`"
    >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 :class="crmPageTitle">Финансы</h1>
                    <p :class="crmPageLead">
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
                    :class="`${crmModuleCard} flex min-h-[190px] flex-col justify-between`"
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
            <div class="flex shrink-0 flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <Link
                        href="/finance"
                        class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        К обзору финансов
                    </Link>
                    <h1 :class="crmPageTitle">График оплат</h1>
                    <p :class="crmPageLead">
                        План и факт по строкам графика заказов; разнос банковских выписок — отдельной вкладкой.
                    </p>
                </div>
            </div>

            <div v-if="can_access_payment_reconcile" class="flex shrink-0 flex-wrap gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
                <Link
                    href="/finance?section=cashflow&cashflow_tab=schedule"
                    :class="cashflowTab === 'schedule' ? cashflowTabActiveClass : cashflowTabClass"
                >
                    График
                </Link>
                <Link
                    href="/finance?section=cashflow&cashflow_tab=reconcile"
                    :class="cashflowTab === 'reconcile' ? cashflowTabActiveClass : cashflowTabClass"
                >
                    Разнос выписки
                </Link>
            </div>

            <ManagementAccountingStatementImports
                v-if="cashflowTab === 'reconcile' && can_access_payment_reconcile"
                :imports="statement_imports"
                :bank_accounts="bank_accounts"
                :default_bank_account_id="default_bank_account_id"
            />

            <template v-else>

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
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Всего</div>
                                <div class="text-sm font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ formatMoney(cashFlowStats.receivables.total) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">По плану</div>
                                <div class="text-sm font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ formatMoney(cashFlowStats.receivables.pending) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-medium text-rose-800 dark:text-rose-200">Просрочено</div>
                                <div class="text-sm font-bold tabular-nums text-rose-900 dark:text-rose-100">{{ formatMoney(cashFlowStats.receivables.overdue) }}</div>
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
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Всего</div>
                                <div class="text-sm font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ formatMoney(cashFlowStats.payables.total) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">По плану</div>
                                <div class="text-sm font-bold tabular-nums text-violet-700 dark:text-violet-300">{{ formatMoney(cashFlowStats.payables.pending) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-medium text-orange-800 dark:text-orange-200">Просрочено</div>
                                <div class="text-sm font-bold tabular-nums text-orange-900 dark:text-orange-100">{{ formatMoney(cashFlowStats.payables.overdue) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p
                v-if="cashFlowPresetLabel"
                class="shrink-0 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200"
            >
                {{ cashFlowPresetLabel }}
            </p>

            <section class="flex min-h-0 flex-1 flex-col overflow-hidden border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <CashFlowGrid
                    :rows="cashFlowRows"
                    :initial-preset="cashFlowGridPreset"
                    :user-id="cashFlowGridUserId"
                    :available-columns="paymentScheduleColumns"
                    :role-columns-config="roleColumnsConfig"
                    :can-manage-actions="canManagePaymentSchedule"
                    :can-show-actions-column="canShowPaymentScheduleActions"
                    :can-record-payment="canPaymentScheduleRecordPayment"
                    :can-cancel-payment-row="canPaymentScheduleCancelRow"
                />
            </section>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, BarChart3, Clock, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmModuleCard, crmPageLead, crmPageTitle, crmPanel } from '@/support/crmUi.js';
import CashFlowGrid from '@/Components/Finance/CashFlowGrid.vue';
import ManagementAccountingStatementImports from '@/Components/Finance/ManagementAccountingStatementImports.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import { summarizeCashFlowJournal } from '@/support/cashFlowJournalStats.js';

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
                mainFill: page.props.active_submodule === 'cashflow',
            },
            () => page,
        ),
});

const page = usePage();
const cashFlowGridUserId = computed(() => page.props.auth?.user?.id ?? 'guest');
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});

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
    cashflow_tab: {
        type: String,
        default: 'schedule',
    },
    statement_imports: {
        type: Array,
        default: () => [],
    },
    bank_accounts: {
        type: Array,
        default: () => [],
    },
    default_bank_account_id: {
        type: [Number, String],
        default: null,
    },
    can_access_salary_module: {
        type: Boolean,
        default: false,
    },
    can_access_management_accounting: {
        type: Boolean,
        default: false,
    },
    can_access_payment_reconcile: {
        type: Boolean,
        default: false,
    },
    can_access_payment_schedules: {
        type: Boolean,
        default: false,
    },
    can_manage_payment_schedule: {
        type: Boolean,
        default: false,
    },
    can_show_payment_schedule_actions: {
        type: Boolean,
        default: false,
    },
    can_payment_schedule_record_payment: {
        type: Boolean,
        default: false,
    },
    can_payment_schedule_cancel_row: {
        type: Boolean,
        default: false,
    },
    paymentScheduleColumns: {
        type: Array,
        default: () => [],
    },
});

const activeSubmodule = computed(() => props.active_submodule);
const cashflowTab = computed(() => props.cashflow_tab ?? 'schedule');
const cashflowTabClass = 'rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800';
const cashflowTabActiveClass = crmTabButtonClasses(true);

const cashFlowGridPreset = ref(null);

function isCustomerOverdueRow(row) {
    if (row?.direction !== 'Нам') {
        return false;
    }
    if (row.status === 'overdue') {
        return true;
    }
    if (row.status !== 'pending' || !row.planned_date) {
        return false;
    }
    const planned = String(row.planned_date).slice(0, 10);
    const today = new Date().toISOString().slice(0, 10);

    return planned < today;
}

const cashFlowRows = computed(() => {
    if (cashFlowGridPreset.value !== 'customer_overdue') {
        return props.cashFlowJournal;
    }

    return props.cashFlowJournal.filter(isCustomerOverdueRow);
});

const cashFlowPresetLabel = computed(() => {
    if (cashFlowGridPreset.value === 'customer_overdue') {
        return 'Показана просроченная дебиторка от клиентов (как на дашборде).';
    }

    return null;
});

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    const preset = url.searchParams.get('preset');
    if (!preset) {
        return;
    }

    cashFlowGridPreset.value = preset;
    url.searchParams.delete('preset');
    window.history.replaceState({}, '', url.pathname + url.search);
});

const canManagePaymentSchedule = computed(() => props.can_manage_payment_schedule);
const canShowPaymentScheduleActions = computed(() => props.can_show_payment_schedule_actions);
const canPaymentScheduleRecordPayment = computed(() => props.can_payment_schedule_record_payment);
const canPaymentScheduleCancelRow = computed(() => props.can_payment_schedule_cancel_row);

const cashFlowStats = computed(() => summarizeCashFlowJournal(props.cashFlowJournal));

const todaysCashFlow = computed(() => cashFlowStats.value.periods.today);

const submoduleTiles = computed(() => {
    const tiles = [];

    if (props.can_access_payment_schedules) {
        tiles.push({
            key: 'cashflow',
            title: 'График оплат',
            description: 'План и факт по строкам графика заказов',
            icon: 'Wallet',
            accent: 'sky',
            group: 'Платежи',
            href: '/finance?section=cashflow',
        });
        tiles.push({
            key: 'reconciliation',
            title: 'Акты сверок',
            description: 'Сводка услуг по заказам и фактических оплат с контрагентом',
            icon: 'Clock',
            accent: 'amber',
            group: 'Платежи',
            href: '/finance/reconciliation',
        });
    }

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

    if (props.can_access_management_accounting) {
        tiles.push({
            key: 'management-accounting',
            title: 'Управленческий учёт',
            description: 'Валовая маржа, факт по статьям и план из бюджетирования',
            icon: 'Wallet',
            accent: 'violet',
            group: 'Учёт',
            href: '/finance/management-accounting',
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
        violet: 'border-violet-200 bg-violet-50 text-violet-600 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-400',
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
