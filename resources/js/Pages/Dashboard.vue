<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <div v-if="isMobileStandalone" class="space-y-5">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Главная</h1>

            <section class="grid grid-cols-2 gap-3">
                <Link
                    href="/orders/create"
                    :class="crmMobileTile"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900">
                        <SquarePen class="h-5 w-5" />
                    </div>
                    <div class="mt-4 text-sm font-semibold">Новый заказ</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Запустить мастер и оформить заявку в дороге.</div>
                </Link>

                <Link
                    href="/contractors/create"
                    :class="crmMobileTile"
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
                        <div class="min-w-0 text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ item.title }}</div>
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
            <section class="crm-panel p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-2">
                        <div :class="crmPageEyebrow">Дашборд</div>
                        <h1 :class="crmPageTitle">Ключевые показатели</h1>
                    </div>

                    <form class="crm-filter-bar" @submit.prevent="applyFilters">
                        <label :class="crmFilterField">
                            <span :class="crmLabelCompact">С даты</span>
                            <input
                                v-model="filterForm.date_from"
                                type="date"
                                :class="crmField"
                            />
                        </label>
                        <label :class="crmFilterField">
                            <span :class="crmLabelCompact">По дату</span>
                            <input
                                v-model="filterForm.date_to"
                                type="date"
                                :class="crmField"
                            />
                        </label>
                        <button type="submit" :class="crmBtnPrimary">
                            Применить
                        </button>
                    </form>
                </div>
            </section>

            <LeadAttentionPanel
                v-if="leadAttentionQueue?.available && leadAttentionQueue.total > 0"
                :queue="leadAttentionQueue"
                variant="summary"
                show-all-link
            />

            <section
                v-if="companyPlanningPortfolio?.available && companyPlanningPortfolio.items.length > 0"
                class="crm-panel p-6"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 :class="crmSectionTitle">План компании</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Активных инициатив: {{ companyPlanningPortfolio.total_active }}
                        </p>
                    </div>
                    <Link
                        v-if="companyPlanningPortfolio.index_url"
                        :href="companyPlanningPortfolio.index_url"
                        :class="crmBtnNeutral"
                    >
                        Все инициативы
                    </Link>
                </div>

                <div class="mt-4 space-y-3">
                    <Link
                        v-for="item in companyPlanningPortfolio.items"
                        :key="item.id"
                        :href="item.show_url"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 px-4 py-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:border-zinc-700 dark:hover:bg-zinc-900/60"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-900 dark:text-zinc-50">{{ item.title }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ item.owner_name || 'Владелец не назначен' }}
                                · {{ item.status_label }}
                                <span v-if="item.ends_on"> · до {{ formatDashboardDate(item.ends_on) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="item.is_overdue || item.overdue_milestones_count > 0"
                                class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-200"
                            >
                                {{ item.overdue_milestones_count > 0 ? `Просрочено этапов: ${item.overdue_milestones_count}` : 'Просрочена' }}
                            </span>
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ item.progress_percent }}%
                            </span>
                            <span
                                v-if="item.risk_level === 'high' || item.risk_level === 'critical'"
                                class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                            >
                                {{ item.risk_label }}
                            </span>
                        </div>
                    </Link>
                </div>
            </section>

            <section
                v-if="showFinanceFlowSection"
                class="crm-panel p-6"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-1">
                        <h2 :class="crmSectionTitle">Денежный поток</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Завершённые заказы за выбранный период
                            <span v-if="financeFlowMode === 'margin_own'"> · только ваша маржа</span>
                        </p>
                    </div>
                    <div
                        v-if="financeFlowMode === 'full'"
                        :class="crmSegmented"
                    >
                        <button
                            v-for="opt in chartMetricOptions"
                            :key="opt.key"
                            type="button"
                            :class="chartMetric === opt.key ? crmSegmentedBtnActive : crmSegmentedBtn"
                            @click="chartMetric = opt.key"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="financeTotals.hasData"
                    class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3"
                >
                    <article v-if="financeFlowMode === 'full'" class="crm-stat-card p-4">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Доход за период</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                            {{ formatCurrency(financeTotals.income) }}
                        </div>
                    </article>
                    <article v-if="financeFlowMode === 'full'" class="crm-stat-card p-4">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Расход за период</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                            {{ formatCurrency(financeTotals.expense) }}
                        </div>
                    </article>
                    <article class="crm-stat-card p-4">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Маржа за период</div>
                        <div
                            class="mt-1 text-2xl font-semibold tabular-nums"
                            :class="financeTotals.margin >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                        >
                            {{ formatCurrency(financeTotals.margin) }}
                        </div>
                    </article>
                </div>

                <p
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                >
                    За выбранный период нет завершённых заказов — график пуст. Проверьте фильтр дат или статусы заказов.
                </p>

                <div v-if="financeChart.length > 0" class="mt-6 flex h-56 items-end gap-1.5 sm:gap-2">
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
                <article class="crm-stat-card p-5">
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

                <article class="crm-stat-card p-5">
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

                <article class="crm-stat-card p-5">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">План выполнен на</div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ primaryScopeLabel }}</div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ formatPercent(metrics.plan_completion_percent) }}</div>
                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">В срок (закрытые за период): {{ formatPercent(metrics.tasks_on_time_percent) }}</div>
                    <p v-if="showDualMetrics" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        Мои: {{ formatPercent(metricsOwn.plan_completion_percent) }}
                    </p>
                </article>

                <article :class="`${crmStatCardRose} p-5`">
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

            <section
                v-if="dispositionKpi"
                class="crm-panel p-6"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="space-y-1">
                        <h2 :class="crmSectionTitle">Диспозиция</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            За {{ dispositionKpiDateLabel }} · заказов «в пути»: {{ dispositionKpi.orders_in_progress }}
                        </p>
                    </div>
                    <Link
                        :href="route('disposition.index')"
                        class="text-sm font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-200"
                    >
                        Открыть грид
                    </Link>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <article class="crm-stat-card p-5">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Оба слота заполнены</div>
                        <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ primaryScopeLabel }}</div>
                        <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ formatPercent(dispositionKpi.both_slots_fill_percent) }}
                        </div>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ dispositionKpi.both_slots_filled_count }} из {{ dispositionKpi.orders_in_progress }}
                            (утро {{ formatPercent(dispositionKpi.morning_fill_percent) }},
                            вечер {{ formatPercent(dispositionKpi.evening_fill_percent) }})
                        </p>
                        <p v-if="dispositionKpiOwn" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            Мои: {{ formatPercent(dispositionKpiOwn.both_slots_fill_percent) }}
                            ({{ dispositionKpiOwn.both_slots_filled_count }}/{{ dispositionKpiOwn.orders_in_progress }})
                        </p>
                    </article>

                    <article class="crm-stat-card p-5">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Средняя задержка внесения</div>
                        <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ primaryScopeLabel }}</div>
                        <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ formatDelayMinutes(dispositionKpi.avg_delay_minutes) }}
                        </div>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            После дедлайна слота (10:00 / 16:00 МСК).
                            С опозданием: {{ dispositionKpi.delayed_entries_count }}
                            из {{ dispositionKpi.filled_entries_count }} заполненных ячеек.
                        </p>
                        <p v-if="dispositionKpiOwn" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            Мои: {{ formatDelayMinutes(dispositionKpiOwn.avg_delay_minutes) }}
                        </p>
                    </article>

                    <article class="crm-stat-card p-5">
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Незаполнено сегодня</div>
                        <div class="mt-1 text-[11px] uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ primaryScopeLabel }}</div>
                        <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ dispositionKpi.orders_in_progress - dispositionKpi.both_slots_filled_count }}
                        </div>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            Заказов без утра и вечера с местоположением.
                        </p>
                        <p v-if="dispositionKpiOwn" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            Мои: {{ dispositionKpiOwn.orders_in_progress - dispositionKpiOwn.both_slots_filled_count }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { BarChart3, Bot, Building2, FileText, Package, SquarePen, Target } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import LeadAttentionPanel from '@/Components/Leads/LeadAttentionPanel.vue';
import {
    crmBtnNeutral,
    crmBtnPrimary,
    crmField,
    crmFilterBar,
    crmFilterField,
    crmLabelCompact,
    crmSectionTitle,
    crmMobileTile,
    crmPageEyebrow,
    crmPageTitle,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
    crmStatCardRose,
} from '@/support/crmUi.js';

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
            disposition_kpi: null,
            disposition_kpi_own: null,
        }),
    },
    leadAttentionQueue: {
        type: Object,
        default: null,
    },
    company_planning_portfolio: {
        type: Object,
        default: null,
    },
});

const companyPlanningPortfolio = computed(() => props.company_planning_portfolio);
const dispositionKpi = computed(() => props.metrics?.disposition_kpi ?? null);
const dispositionKpiOwn = computed(() => props.metrics?.disposition_kpi_own ?? null);

const dispositionKpiDateLabel = computed(() => {
    const raw = dispositionKpi.value?.date;
    if (!raw) {
        return 'сегодня';
    }

    const parts = String(raw).split('-');
    if (parts.length !== 3) {
        return raw;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
});

function formatDelayMinutes(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    const minutes = Number(value);
    if (Number.isNaN(minutes)) {
        return '—';
    }

    if (minutes < 60) {
        return `${Math.round(minutes)} мин`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = Math.round(minutes % 60);

    return rest > 0 ? `${hours} ч ${rest} мин` : `${hours} ч`;
}

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
const primaryScopeLabel = computed(() => {
    if (props.metrics?.metrics_scope === 'company') {
        return 'По компании';
    }

    if (props.metrics?.metrics_scope === 'department') {
        return 'По подразделению';
    }

    return 'Мои';
});

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

const showFinanceFlowSection = computed(() => financeFlowMode.value !== 'hidden');

const financeTotals = computed(() => {
    const totals = { income: 0, expense: 0, margin: 0, hasData: false };

    for (const point of financeChart.value) {
        totals.income += Number(point.income ?? 0);
        totals.expense += Number(point.expense ?? 0);
        totals.margin += Number(point.margin ?? 0);

        if (
            Math.abs(Number(point.income ?? 0)) > 0
            || Math.abs(Number(point.expense ?? 0)) > 0
            || Math.abs(Number(point.margin ?? 0)) > 0
        ) {
            totals.hasData = true;
        }
    }

    return totals;
});

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

function formatDashboardDate(value) {
    if (!value) {
        return '';
    }

    const parts = String(value).split('-');
    if (parts.length !== 3) {
        return value;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

const mobileSections = [
    { href: '/orders', title: 'Заказы', icon: Package },
    { href: '/leads', title: 'Лиды', icon: Target },
    { href: '/contractors', title: 'Контрагенты', icon: Building2 },
    { href: '/reports', title: 'Отчёты', icon: BarChart3 },
    { href: '/finance?section=cashflow', title: 'Финансы', icon: FileText },
];
</script>
