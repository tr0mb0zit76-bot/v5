<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            :lead="filters.can_view_all
                ? 'Результаты прохождения тестов по всем сотрудникам.'
                : 'Ваши результаты прохождения тестов в Книге продаж.'"
            title="Статистика тестов"
        />

        <section :class="`${crmPanel} space-y-3 p-6`">
            <p class="text-sm">
                <Link
                    :href="route('sales-assistant.book')"
                    class="font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    ← К книге продаж
                </Link>
            </p>
        </section>

        <section :class="`${crmPanel} p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Фильтры</h2>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Период</span>
                    <select v-model.number="localDays" :class="`${crmField} min-w-[10rem]`">
                        <option :value="7">7 дней</option>
                        <option :value="30">30 дней</option>
                        <option :value="90">90 дней</option>
                        <option :value="180">180 дней</option>
                    </select>
                </label>
                <label
                    v-if="filters.can_view_all"
                    class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400"
                >
                    <span class="font-medium">Сотрудник</span>
                    <select v-model="localUserId" :class="`${crmField} min-w-[14rem]`">
                        <option value="">Все</option>
                        <option v-for="user in filterUsers" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Страница с тестом</span>
                    <select v-model="localArticleId" :class="`${crmField} min-w-[14rem] max-w-[20rem]`">
                        <option value="">Все</option>
                        <option v-for="article in filterArticles" :key="article.id" :value="String(article.id)">{{ article.title }}</option>
                    </select>
                </label>
                <button
                    type="button"
                    class="rounded-xl border border-sky-800 bg-sky-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-800 dark:border-sky-500 dark:bg-sky-600 dark:hover:bg-sky-500"
                    @click="applyFilters"
                >
                    Применить
                </button>
            </div>
        </section>

        <section
            class="grid gap-3 md:grid-cols-2"
            :class="filters.can_view_all ? 'xl:grid-cols-5' : 'xl:grid-cols-4'"
        >
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                    Попытки ({{ summary.window_days }}д)
                </div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.attempts }}</div>
            </article>
            <article
                v-if="filters.can_view_all"
                class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
            >
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Сотрудников</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.unique_users }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Тестов</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.unique_articles }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Средний балл</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ formatNullable(summary.avg_score) }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Средний %</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ formatPercent(summary.avg_percent) }}</div>
            </article>
        </section>

        <section v-if="filters.can_view_all && insights.by_user.length" :class="`${crmPanel} overflow-x-auto p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По сотрудникам</h2>
            <table class="mt-4 min-w-[40rem] w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                        <th class="pb-2 pr-2 font-medium">Сотрудник</th>
                        <th class="pb-2 pr-2 font-medium">Попыток</th>
                        <th class="pb-2 pr-2 font-medium">Ср. %</th>
                        <th class="pb-2 pr-2 font-medium">Лучший результат</th>
                        <th class="pb-2 font-medium">Последняя попытка</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in insights.by_user"
                        :key="`user-${row.user_id}`"
                        class="border-b border-zinc-100 dark:border-zinc-800/80"
                    >
                        <td class="py-2 pr-2 font-medium text-zinc-900 dark:text-zinc-100">{{ row.name }}</td>
                        <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.attempts }}</td>
                        <td class="py-2 pr-2">
                            <span :class="percentBadgeClass(row.avg_percent)">{{ row.avg_percent }}%</span>
                        </td>
                        <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.best_score }}/{{ row.best_total }}</td>
                        <td class="whitespace-nowrap py-2 text-zinc-600 dark:text-zinc-300">{{ formatDate(row.last_attempt_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="insights.by_article.length" :class="`${crmPanel} overflow-x-auto p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По тестам</h2>
            <table class="mt-4 min-w-[40rem] w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                        <th class="pb-2 pr-2 font-medium">Страница</th>
                        <th class="pb-2 pr-2 font-medium">Попыток</th>
                        <th v-if="filters.can_view_all" class="pb-2 pr-2 font-medium">Людей</th>
                        <th class="pb-2 pr-2 font-medium">Ср. %</th>
                        <th class="pb-2 font-medium">Последняя попытка</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in insights.by_article"
                        :key="`article-${row.article_id}`"
                        class="border-b border-zinc-100 dark:border-zinc-800/80"
                    >
                        <td class="max-w-[16rem] py-2 pr-2">
                            <Link
                                :href="route('sales-assistant.book', { article_id: row.article_id })"
                                class="font-medium text-sky-800 underline-offset-4 hover:underline dark:text-sky-300"
                            >
                                {{ row.title }}
                            </Link>
                        </td>
                        <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.attempts }}</td>
                        <td v-if="filters.can_view_all" class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.unique_users }}</td>
                        <td class="py-2 pr-2">
                            <span :class="percentBadgeClass(row.avg_percent)">{{ row.avg_percent }}%</span>
                        </td>
                        <td class="whitespace-nowrap py-2 text-zinc-600 dark:text-zinc-300">{{ formatDate(row.last_attempt_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="insights.recent_attempts.length" :class="`${crmPanel} overflow-x-auto p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Последние попытки</h2>
            <table class="mt-4 min-w-[48rem] w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                        <th class="pb-2 pr-2 font-medium">Дата</th>
                        <th v-if="filters.can_view_all" class="pb-2 pr-2 font-medium">Сотрудник</th>
                        <th class="pb-2 pr-2 font-medium">Тест</th>
                        <th class="pb-2 pr-2 font-medium">Результат</th>
                        <th class="pb-2 font-medium">%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in insights.recent_attempts"
                        :key="`attempt-${row.id}`"
                        class="border-b border-zinc-100 dark:border-zinc-800/80"
                    >
                        <td class="whitespace-nowrap py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ formatDate(row.completed_at) }}</td>
                        <td v-if="filters.can_view_all" class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">{{ row.user_name }}</td>
                        <td class="max-w-[14rem] truncate py-2 pr-2">
                            <Link
                                :href="route('sales-assistant.book', { article_id: row.article_id })"
                                class="text-sky-800 underline-offset-4 hover:underline dark:text-sky-300"
                            >
                                {{ row.article_title }}
                            </Link>
                        </td>
                        <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.score }}/{{ row.total_questions }}</td>
                        <td class="py-2">
                            <span :class="percentBadgeClass(row.percent)">{{ row.percent }}%</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            v-if="summary.attempts === 0"
            :class="`${crmPanel} p-8 text-center text-sm text-zinc-500 dark:text-zinc-400`"
        >
            За выбранный период попыток пока нет.
        </section>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import { crmField, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'reports', activeSubKey: 'sales-assistant-book-quiz-analytics' }, () => page),
});

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    filterUsers: {
        type: Array,
        default: () => [],
    },
    filterArticles: {
        type: Array,
        default: () => [],
    },
    insights: {
        type: Object,
        required: true,
    },
});

const localDays = ref(props.filters.days);
const localUserId = ref(props.filters.user_id != null ? String(props.filters.user_id) : '');
const localArticleId = ref(props.filters.article_id != null ? String(props.filters.article_id) : '');

watch(
    () => props.filters,
    (filters) => {
        localDays.value = filters.days;
        localUserId.value = filters.user_id != null ? String(filters.user_id) : '';
        localArticleId.value = filters.article_id != null ? String(filters.article_id) : '';
    },
    { deep: true },
);

const summary = computed(() => props.insights.summary ?? {});

function applyFilters() {
    const query = { days: localDays.value };

    if (localUserId.value) {
        query.user_id = localUserId.value;
    }

    if (localArticleId.value) {
        query.article_id = localArticleId.value;
    }

    router.get(route('sales-assistant.book.quiz-analytics'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatNullable(value) {
    return value === null || value === undefined ? '—' : value;
}

function formatPercent(value) {
    return value === null || value === undefined ? '—' : `${value}%`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function percentBadgeClass(percent) {
    const value = Number(percent);

    if (value >= 83) {
        return 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100';
    }

    if (value >= 58) {
        return 'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950/50 dark:text-amber-100';
    }

    return 'rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900 dark:bg-rose-950/50 dark:text-rose-100';
}
</script>
