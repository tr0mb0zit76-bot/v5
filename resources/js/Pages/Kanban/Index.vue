<template>
    <div class="space-y-6">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Визуальный Канбан</div>
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Распределение потоков задач</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Быстрый взгляд на статус по всей воронке: новые тикеты, те, что в работе, и готовые к закрытию.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Link
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-white transition hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :href="route('tasks.index')"
                    >
                        Открыть задачи
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-100 dark:border-zinc-50 dark:text-zinc-50 dark:hover:bg-zinc-800"
                    >
                        Настроить колонки
                    </button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <div
                v-for="column in columns"
                :key="column.title"
                class="flex flex-col border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
            >
                <div class="flex items-center justify-between text-sm font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">
                    <span>{{ column.title }}</span>
                    <span class="text-xs text-zinc-400">{{ column.cards.length }} шт.</span>
                </div>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="card in column.cards"
                        :key="card.id"
                        class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            {{ card.order }}
                        </div>

                        <h2 class="mt-2 text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ card.title }}</h2>

                        <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ card.owner }} · {{ card.due }}
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs font-semibold uppercase tracking-[0.2em]">
                            <span class="text-emerald-600 dark:text-emerald-300">{{ card.priority }}</span>
                            <span class="text-sky-500 dark:text-sky-300">{{ card.target }}</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const columns = [
    {
        title: 'Новые',
        cards: [
            { id: 1, title: 'Подтвердить дату отгрузки по #5023', order: '#5023', owner: 'Олег', due: '05 апреля', priority: 'Срочно', target: 'Документы' },
            { id: 2, title: 'Прописать доп. условия оплаты', order: '#5101', owner: 'Светлана', due: '06 апреля', priority: 'Высокий', target: 'Финансы' },
        ],
    },
    {
        title: 'В работе',
        cards: [
            { id: 3, title: 'Передать заказ на контроль клиента', order: '#4984', owner: 'Кирилл', due: '07 апреля', priority: 'Средний', target: 'УПД' },
            { id: 4, title: 'Уточнить статус УПД "СеверТранс"', order: '#4892', owner: 'Маша', due: '08 апреля', priority: 'Средний', target: 'Документы' },
        ],
    },
    {
        title: 'Готово к закрытию',
        cards: [
            { id: 5, title: 'Отправить отчёт клиенту', order: '#4721', owner: 'Алена', due: '09 апреля', priority: 'Низкий', target: 'Клиент' },
            { id: 6, title: 'Подтвердить оплату по #4578', order: '#4578', owner: 'Виталий', due: '10 апреля', priority: 'Низкий', target: 'Финансы' },
        ],
    },
];

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'kanban' }, () => page),
});
</script>
