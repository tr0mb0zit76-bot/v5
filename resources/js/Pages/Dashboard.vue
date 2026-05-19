<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <div v-if="isMobileStandalone" class="space-y-5">
            <section class="rounded-[28px] bg-zinc-900 px-5 py-6 text-white shadow-sm dark:bg-zinc-50 dark:text-zinc-900">
                <div class="text-xs uppercase tracking-[0.22em] text-white/60 dark:text-zinc-500">Мобильное приложение</div>
                <h1 class="mt-3 text-2xl font-semibold">Главный экран CRM</h1>
                <p class="mt-2 max-w-sm text-sm text-white/70 dark:text-zinc-600">
                    Быстрый доступ к заказам, базе контрагентов, отчётам и рабочим действиям без desktop-интерфейса.
                </p>
            </section>

            <section class="grid grid-cols-2 gap-3">
                <Link
                    href="/orders/create"
                    class="rounded-[24px] border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900">
                        <SquarePen class="h-5 w-5" />
                    </div>
                    <div class="mt-4 text-sm font-semibold">Новый заказ</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Запустить мастер и оформить заявку в дороге.</div>
                </Link>

                <Link
                    href="/contractors/create"
                    class="rounded-[24px] border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-50">
                        <Building2 class="h-5 w-5" />
                    </div>
                    <div class="mt-4 text-sm font-semibold">Новый контрагент</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Быстро завести карточку клиента или перевозчика.</div>
                </Link>
            </section>

            <section class="space-y-3">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Что можно делать в приложении</div>

                <div class="space-y-3">
                    <Link
                        v-for="item in mobileSections"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-start gap-4 rounded-[24px] border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-50">
                            <component :is="item.icon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ item.title }}</div>
                            <div class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ item.description }}</div>
                        </div>
                    </Link>
                </div>
            </section>

            <section class="rounded-[24px] border border-dashed border-zinc-300 bg-white px-4 py-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                        <Bot class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">AI-строка</div>
                        <div class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                            Когда рабочий AI-контур будет подключён, сюда добавим быстрый сценарий общения и постановки задач прямо из приложения.
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-else class="space-y-6">
            <section class="rounded-none border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Дашборд</div>
                        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Ключевые показатели</h1>
                    </div>

                    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]" @submit.prevent="applyFilters">
                        <label class="space-y-2">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">С даты</span>
                            <input
                                v-model="filterForm.date_from"
                                type="date"
                                class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                            />
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">По дату</span>
                            <input
                                v-model="filterForm.date_to"
                                type="date"
                                class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                            />
                        </label>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        >
                            Применить
                        </button>
                    </form>
                </div>
            </section>

            <section
                v-if="showFinanceFlowSection"
                class="rounded-none border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Денежный поток</h2>
                    </div>
                    <div
                        v-if="financeFlowMode === 'full'"
                        class="flex flex-wrap gap-1 rounded-2xl border border-zinc-200 bg-zinc-50 p-1 dark:border-zinc-700 dark:bg-zinc-950"
                    >
                        <button
                            v-for="opt in chartMetricOptions"
                            :key="opt.key"
                            type="button"
                            class="rounded-xl px-3 py-1.5 text-sm font-medium transition"
                            :class="chartMetric === opt.key
                                ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800'"
                            @click="chartMetric = opt.key"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <div class="mt-6 flex h-56 items-end gap-1.5 sm:gap-2">
                    <div
                        v-for="p in financeChart"
                        :key="p.ym"
                        class="flex min-h-0 min-w-0 flex-1 flex-col items-stretch justify-end gap-2"
                    >
                        <div class="flex flex-1 flex-col justify-end px-0.5">
                            <div
                                class="mx-auto w-full max-w-[40px] rounded-t-md sm:max-w-[52px]"
                                :class="barToneClass(p)"
                                :style="{ height: barHeightPercent(p) }"
                                :title="formatCurrency(pointValue(p))"
                            />
                        </div>
                        <div class="truncate text-center text-[10px] leading-tight text-zinc-500 dark:text-zinc-400 sm:text-xs">
                            {{ p.label }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Сделок за период</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ primaryScopeLabel }}</div>
                    <Link
                        :href="ordersPeriodUrl"
                        class="mt-2 block text-3xl font-semibold text-zinc-900 underline-offset-4 transition hover:underline dark:text-zinc-50"
                    >
                        {{ metrics.total_orders }}
                    </Link>
                    <p v-if="showDualMetrics" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        Мои: {{ metricsOwn.total_orders }}
                    </p>
                </article>

                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Задач на сегодня</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ primaryScopeLabel }}</div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ metrics.tasks_today }}</div>
                    <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>Открытых просроченных (по сроку или SLA):</span>
                        <Link
                            v-if="Number(metrics.tasks_overdue) > 0"
                            :href="tasksOverdueUrl"
                            :class="dashboardTaskCountLinkClass"
                            :aria-label="`Открыть просроченные задачи: ${metrics.tasks_overdue}`"
                        >
                            {{ metrics.tasks_overdue }}
                        </Link>
                        <span v-else class="font-medium text-zinc-700 dark:text-zinc-300">{{ metrics.tasks_overdue }}</span>
                    </p>
                    <p
                        v-if="Number(metrics.tasks_sla_breached_open || 0) > 0"
                        class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-rose-600 dark:text-rose-400"
                    >
                        <span>С просроченным SLA:</span>
                        <Link
                            :href="tasksSlaOverdueUrl"
                            :class="dashboardTaskCountLinkClass"
                            :aria-label="`Открыть задачи с просроченным SLA: ${metrics.tasks_sla_breached_open}`"
                        >
                            {{ metrics.tasks_sla_breached_open }}
                        </Link>
                    </p>
                    <p v-if="showDualMetrics" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        Мои: сегодня {{ metricsOwn.tasks_today }}, просрочено {{ metricsOwn.tasks_overdue }}
                    </p>
                </article>

                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">План выполнен на</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ primaryScopeLabel }}</div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ formatPercent(metrics.plan_completion_percent) }}</div>
                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">В срок (закрытые за период): {{ formatPercent(metrics.tasks_on_time_percent) }}</div>
                    <p v-if="showDualMetrics" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        Мои: {{ formatPercent(metricsOwn.plan_completion_percent) }}
                    </p>
                </article>

                <article class="rounded-none border border-rose-200 bg-rose-50/70 p-5 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/20">
                    <div class="text-sm font-medium text-rose-800 dark:text-rose-200">На этой неделе надо вернуть от клиентов</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-rose-600/80 dark:text-rose-400/80">{{ primaryScopeLabel }}</div>
                    <div class="mt-2 text-3xl font-semibold text-rose-900 dark:text-rose-100">{{ formatCurrency(metrics.weekly_client_returns) }}</div>
                    <p class="mt-2 text-xs text-rose-700 dark:text-rose-300">
                        из них просрочено:
                        <Link
                            v-if="Number(metrics.weekly_client_returns_overdue) > 0"
                            :href="financeCustomerOverdueUrl"
                            class="font-semibold underline-offset-2 hover:underline"
                        >
                            {{ formatCurrency(metrics.weekly_client_returns_overdue) }}
                        </Link>
                        <span v-else>{{ formatCurrency(metrics.weekly_client_returns_overdue) }}</span>
                    </p>
                    <p v-if="showDualMetrics" class="mt-2 text-xs text-rose-700/90 dark:text-rose-300/90">
                        Мои: {{ formatCurrency(metricsOwn.weekly_client_returns) }}
                        (просрочено {{ formatCurrency(metricsOwn.weekly_client_returns_overdue) }})
                    </p>
                </article>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { BarChart3, Bot, Building2, FileText, Package, SquarePen } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            date_from: '',
            date_to: '',
        }),
    },
    metrics: {
        type: Object,
        default: () => ({
            total_orders: 0,
            period_delta: 0,
            weekly_client_returns: 0,
            weekly_client_returns_overdue: 0,
            tasks_today: 0,
            tasks_overdue: 0,
            plan_completion_percent: 0,
            tasks_on_time_percent: 0,
            tasks_sla_breached_open: 0,
            margin_rank: '—',
            finance_chart: [],
            finance_flow_mode: 'hidden',
            show_dual_metrics: false,
            metrics_scope: 'own',
            metrics_own: null,
        }),
    },
});

const emptyTileMetrics = {
    total_orders: 0,
    period_delta: 0,
    weekly_client_returns: 0,
    weekly_client_returns_overdue: 0,
    tasks_today: 0,
    tasks_overdue: 0,
    plan_completion_percent: 0,
    tasks_on_time_percent: 0,
    tasks_sla_breached_open: 0,
    margin_rank: '—',
};

const showDualMetrics = computed(() => props.metrics?.show_dual_metrics === true);
const metricsOwn = computed(() => ({ ...emptyTileMetrics, ...(props.metrics?.metrics_own ?? {}) }));
const primaryScopeLabel = computed(() => (props.metrics?.metrics_scope === 'company' ? 'По компании' : 'Мои'));

const ordersPeriodUrl = computed(() => route('orders.index', {
    order_date_from: filterForm.date_from,
    order_date_to: filterForm.date_to,
}));

const tasksOverdueUrl = computed(() => route('tasks.index', { filter: 'overdue' }));
const tasksSlaOverdueUrl = computed(() => route('tasks.index', { filter: 'sla_overdue' }));

const dashboardTaskCountLinkClass =
    'inline-flex min-w-[2.25rem] items-center justify-center rounded-lg border border-rose-200/90 bg-rose-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-rose-900 shadow-sm transition hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-500 dark:border-rose-800/70 dark:bg-rose-950/45 dark:text-rose-50 dark:hover:bg-rose-900/40';
const financeCustomerOverdueUrl = computed(() => route('finance.index', {
    section: 'cashflow',
    preset: 'customer_overdue',
}));

const financeFlowMode = computed(() => props.metrics?.finance_flow_mode ?? 'hidden');

const financeChart = computed(() => props.metrics?.finance_chart ?? []);

const showFinanceFlowSection = computed(() => financeFlowMode.value !== 'hidden' && financeChart.value.length > 0);

const chartMetric = ref('income');

watch(
    financeFlowMode,
    (mode) => {
        if (mode === 'margin_own') {
            chartMetric.value = 'margin';
        }
    },
    { immediate: true },
);

const chartMetricOptions = computed(() => {
    if (financeFlowMode.value !== 'full') {
        return [];
    }

    return [
        { key: 'income', label: 'Доход' },
        { key: 'expense', label: 'Расход' },
        { key: 'margin', label: 'Маржа' },
    ];
});

const chartMaxAbs = computed(() => {
    const key = chartMetric.value;
    let m = 0;
    for (const p of financeChart.value) {
        m = Math.max(m, Math.abs(Number(p[key] ?? 0)));
    }

    return m > 0 ? m : 1;
});

function pointValue(point) {
    return Number(point[chartMetric.value] ?? 0);
}

function barHeightPercent(point) {
    const ratio = Math.abs(pointValue(point)) / chartMaxAbs.value;

    return `${Math.max(6, ratio * 100)}%`;
}

function barToneClass(point) {
    const v = pointValue(point);
    if (chartMetric.value === 'margin') {
        if (v < 0) {
            return 'bg-rose-500/90 dark:bg-rose-400/90';
        }

        return 'bg-emerald-500/90 dark:bg-emerald-400/90';
    }
    if (chartMetric.value === 'expense') {
        return 'bg-amber-500/90 dark:bg-amber-400/90';
    }

    return 'bg-sky-500/90 dark:bg-sky-400/90';
}

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'dashboard' }, () => page),
});

const filterForm = reactive({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});

function applyFilters() {
    router.get(route('dashboard'), {
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatPercent(value) {
    return `${Number(value || 0).toFixed(2)}%`;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

const mobileSections = [
    {
        href: '/orders',
        title: 'Заказы',
        description: 'Открыть мобильный реестр заказов и перейти к текущим сделкам.',
        icon: Package,
    },
    {
        href: '/contractors',
        title: 'Контрагенты',
        description: 'Поиск по базе, открытие карточек и быстрый доступ к реквизитам.',
        icon: Building2,
    },
    {
        href: '/reports',
        title: 'Отчёты и статистика',
        description: 'Ключевые показатели и сводки без перегруженных desktop-таблиц.',
        icon: BarChart3,
    },
      {
          href: '/finance?section=cashflow',
          title: 'Финансы',
          description: 'График оплат и движение денег по заказам.',
          icon: FileText,
      },
  ];
</script>
