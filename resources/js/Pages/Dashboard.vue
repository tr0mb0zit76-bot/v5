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
            <section class="rounded-[28px] border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Личный дашборд</div>
                        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Показатели менеджера</h1>
                        <p class="max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
                            Доля прямых сделок и суммарная дельта считаются по произвольному периоду.
                        </p>
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

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Сделок за период</div>
                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ metrics.total_orders }}</div>
                </article>

                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Прямых сделок</div>
                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ metrics.direct_orders }}</div>
                </article>

                <article class="rounded-none border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/20">
                    <div class="text-sm text-emerald-700 dark:text-emerald-300">Доля прямых сделок</div>
                    <div class="mt-3 text-3xl font-semibold text-emerald-900 dark:text-emerald-100">{{ formatPercent(metrics.direct_share_percent) }}</div>
                </article>

                <article class="rounded-none border border-sky-200 bg-sky-50/70 p-5 shadow-sm dark:border-sky-900/60 dark:bg-sky-950/20">
                    <div class="text-sm text-sky-700 dark:text-sky-300">Дельта за период</div>
                    <div class="mt-3 text-3xl font-semibold text-sky-900 dark:text-sky-100">{{ formatCurrency(metrics.period_delta) }}</div>
                </article>

                <article class="rounded-none border border-rose-200 bg-rose-50/70 p-5 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/20">
                    <div class="text-sm text-rose-700 dark:text-rose-300">Ожидаемые поступления от клиентов на этой неделе</div>
                    <div class="mt-2 text-sm text-rose-600 dark:text-rose-300">по графику оплат в заказах (не оплачено)</div>
                    <div class="mt-3 text-3xl font-semibold text-rose-900 dark:text-rose-100">{{ formatCurrency(metrics.weekly_client_returns) }}</div>
                </article>

                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Задач на сегодня</div>
                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ metrics.tasks_today }}</div>
                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Открытых просроченных (по сроку или SLA): {{ metrics.tasks_overdue }}</div>
                    <div v-if="Number(metrics.tasks_sla_breached_open || 0) > 0" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                        С просроченным SLA: {{ metrics.tasks_sla_breached_open }}
                    </div>
                </article>

                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">План выполнен на</div>
                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ formatPercent(metrics.plan_completion_percent) }}</div>
                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">В срок (закрытые за период): {{ formatPercent(metrics.tasks_on_time_percent) }}</div>
                </article>

                <article class="rounded-none border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Ты на месте по марже</div>
                    <div class="mt-3 text-3xl font-semibold text-zinc-900 dark:text-zinc-50">{{ metrics.margin_rank }}</div>
                </article>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
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
            direct_orders: 0,
            direct_share_percent: 0,
            period_delta: 0,
            weekly_client_returns: 0,
            tasks_today: 0,
            tasks_overdue: 0,
            plan_completion_percent: 0,
            tasks_on_time_percent: 0,
            tasks_sla_breached_open: 0,
        }),
    },
});

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
