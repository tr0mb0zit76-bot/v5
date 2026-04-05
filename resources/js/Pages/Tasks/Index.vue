<template>
    <div class="space-y-6">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm transition dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Модуль задач</div>
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Контроль задач менеджеров</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Сразу видно, какие задачи горят, кто отвечает и к каким заказам относятся.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Link
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-white transition hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :href="route('kanban.index')"
                    >
                        Перейти в Канбан
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-100 dark:border-zinc-50 dark:text-zinc-50 dark:hover:bg-zinc-800"
                    >
                        Создать задачу
                    </button>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <span
                    v-for="filter in quickFilters"
                    :key="filter.label"
                    class="rounded-xl border border-zinc-200 px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:border-zinc-700 dark:text-zinc-300"
                >
                    {{ filter.label }} · {{ filter.count }}
                </span>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <article
                v-for="task in tasks"
                :key="task.id"
                class="border border-zinc-200 bg-white p-5 shadow-sm transition dark:border-zinc-800 dark:bg-zinc-950"
            >
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                    {{ task.category }}
                    <span :class="statusClasses(task.status)">{{ task.status }}</span>
                </div>

                <h2 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ task.title }}</h2>

                <div class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                    Ответственный: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ task.assignee }}</span>
                    · Дата: {{ task.due }}
                </div>

                <div class="mt-4 flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400">
                    <div>Связанный заказ: {{ task.order }}</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-50">{{ formatCurrency(task.amount) }}</div>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full bg-emerald-500" :style="{ width: `${task.progress}%` }"></div>
                </div>
            </article>
        </section>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const quickFilters = [
    { label: 'Все', count: 18 },
    { label: 'Срочные', count: 5 },
    { label: 'В работе', count: 8 },
    { label: 'На проверке', count: 3 },
];

const tasks = [
    {
        id: 1,
        title: 'Уточнить оплату по заказу #5128',
        assignee: 'Алина',
        due: '05 апреля',
        status: 'В работе',
        category: 'Финансы',
        order: '#5128',
        amount: 29100,
        progress: 70,
    },
    {
        id: 2,
        title: 'Отправить инструкции по УПД клиенту "Норс Транс"',
        assignee: 'Алексей',
        due: '06 апреля',
        status: 'На проверке',
        category: 'Документы',
        order: '#4792',
        amount: 128000,
        progress: 40,
    },
    {
        id: 3,
        title: 'Сформировать доп. расходы по заказу #5012',
        assignee: 'Инна',
        due: '08 апреля',
        status: 'Новая',
        category: 'Операции',
        order: '#5012',
        amount: 8800,
        progress: 10,
    },
    {
        id: 4,
        title: 'Согласовать контракт с клиентом "РосКом"',
        assignee: 'Даниил',
        due: '10 апреля',
        status: 'Завершено',
        category: 'Продажи',
        order: '#4887',
        amount: 336000,
        progress: 100,
    },
];

function statusClasses(status) {
    const map = {
        'В работе': 'text-emerald-600',
        'На проверке': 'text-amber-500',
        Новая: 'text-sky-600',
        Завершено: 'text-zinc-400',
    };

    return map[status] ?? 'text-zinc-500';
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 0 }).format(value) + ' ₽';
}

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'tasks' }, () => page),
});
</script>
