<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            :lead="filters.can_view_all ? 'Сводка по всем менеджерам. Фильтры по сотруднику, периоду и сценарию.' : 'Ваши тренировки за выбранный период.'"
            title="Аналитика тренажёра"
        />
        <section :class="`${crmPanel} space-y-3 p-6`">
            <p class="mt-4 text-sm">
                <Link
                    :href="route('sales-assistant.trainer')"
                    class="font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    ← К тренажёру
                </Link>
            </p>
        </section>

        <section :class="`${crmPanel} p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Фильтры</h2>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Период</span>
                    <select
                        v-model.number="localDays"
                        :class="`${crmField} min-w-[10rem]`"
                    >
                        <option :value="7">7 дней</option>
                        <option :value="30">30 дней</option>
                        <option :value="90">90 дней</option>
                        <option :value="180">180 дней</option>
                    </select>
                </label>
                <label v-if="filters.can_view_all" class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Менеджер</span>
                    <select
                        v-model="localUserId"
                        :class="`${crmField} min-w-[14rem]`"
                    >
                        <option value="">Все</option>
                        <option v-for="u in filterUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Профиль клиента</span>
                    <select
                        v-model="localProfileKey"
                        :class="`${crmField} min-w-[12rem]`"
                    >
                        <option value="">Все</option>
                        <option v-for="p in profile_options" :key="p.key" :value="p.key">{{ p.title }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Сценарий</span>
                    <select
                        v-model="localVersionId"
                        :class="`${crmField} min-w-[14rem] max-w-[20rem]`"
                    >
                        <option value="">Все</option>
                        <option v-for="v in version_options" :key="v.id" :value="String(v.id)">{{ v.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Исход</span>
                    <select
                        v-model="localOutcome"
                        :class="`${crmField} min-w-[11rem]`"
                    >
                        <option value="">Любой</option>
                        <option v-for="o in outcomeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Оценка тренировки</span>
                    <select
                        v-model="localDialogQuality"
                        :class="`${crmField} min-w-[12rem]`"
                    >
                        <option value="">Любая</option>
                        <option v-for="q in trainerDialogQualityOptions" :key="q.value" :value="q.value">{{ q.label }}</option>
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

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                    Сессии ({{ summary.window_days }}д)
                </div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.total_sessions }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Завершено</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.completed_sessions }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Средний score</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.avg_score }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Успех / КП / потеря</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ summary.won_sessions }} / {{ summary.quote_sessions }} / {{ summary.lost_sessions }}
                </div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:col-span-2 xl:col-span-1">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Оценки тренировки (успех / неудача / тупик)</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ summary.trainer_dialog_success }} / {{ summary.trainer_dialog_failure }} / {{ summary.trainer_dialog_stuck }}
                </div>
            </article>
        </section>

        <section v-if="daily.length" class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:p-6">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По дням</h2>
            <div class="mt-6 flex h-40 items-end gap-0.5 overflow-x-auto pb-2">
                <div
                    v-for="row in daily"
                    :key="row.date"
                    class="flex min-w-[10px] flex-1 flex-col items-center justify-end gap-1"
                    :title="`${row.date}: ${row.total} сессий, score ${row.avg_score}`"
                >
                    <div
                        class="w-full rounded-t bg-sky-500/90 dark:bg-sky-400/80"
                        :style="{ height: `${Math.max(4, (row.total / maxDailyTotal) * 100)}%` }"
                    />
                </div>
            </div>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Высота столбца — число сессий за день.</p>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:p-6">
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По профилю клиента</h2>
                <div v-if="by_profile.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Нет данных.</div>
                <table v-else class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="pb-2 pr-2 font-medium">Профиль</th>
                            <th class="pb-2 pr-2 font-medium">Сессий</th>
                            <th class="pb-2 font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in by_profile"
                            :key="row.profile_key ?? row.profile_title"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">{{ row.profile_title }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.total }}</td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ row.avg_score }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="filters.can_view_all"
                class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:p-6"
            >
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По менеджерам</h2>
                <div v-if="by_user.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Нет данных.</div>
                <table v-else class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="pb-2 pr-2 font-medium">Менеджер</th>
                            <th class="pb-2 pr-2 font-medium">Сессий</th>
                            <th class="pb-2 pr-2 font-medium">Завершено</th>
                            <th class="pb-2 font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in by_user"
                            :key="row.user_id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">{{ row.name }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.total }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.completed }}</td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ row.avg_score }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section :class="`${crmPanel} p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Последние сессии</h2>
            <div v-if="recent_sessions.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Нет данных.</div>
            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-[48rem] w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th v-if="filters.can_view_all" class="pb-2 pr-2 font-medium">Менеджер</th>
                            <th class="pb-2 pr-2 font-medium">Дата</th>
                            <th class="pb-2 pr-2 font-medium">Профиль</th>
                            <th class="pb-2 pr-2 font-medium">Сценарий</th>
                            <th class="pb-2 pr-2 font-medium">Исход</th>
                            <th class="pb-2 pr-2 font-medium">Тренировка</th>
                            <th class="pb-2 font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in recent_sessions"
                            :key="s.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td v-if="filters.can_view_all" class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">
                                {{ s.user_name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ formatDate(s.created_at) }}
                            </td>
                            <td class="max-w-[12rem] truncate py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ s.trainer_profile_title ?? '—' }}
                            </td>
                            <td class="max-w-[14rem] truncate py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ s.script_label }}
                            </td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ outcomeLabel(s.outcome) }}
                            </td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ trainerDialogQualityLabel(s.trainer_dialog_quality) }}
                            </td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ s.trainer_score ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmField, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-trainer-analytics' }, () => page),
});

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    outcomeOptions: {
        type: Array,
        default: () => [],
    },
    trainerDialogQualityOptions: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
    daily: {
        type: Array,
        default: () => [],
    },
    by_profile: {
        type: Array,
        default: () => [],
    },
    by_user: {
        type: Array,
        default: () => [],
    },
    recent_sessions: {
        type: Array,
        default: () => [],
    },
    filterUsers: {
        type: Array,
        default: () => [],
    },
    profile_options: {
        type: Array,
        default: () => [],
    },
    version_options: {
        type: Array,
        default: () => [],
    },
});

const localDays = ref(props.filters.days);
const localUserId = ref(props.filters.user_id != null ? String(props.filters.user_id) : '');
const localProfileKey = ref(props.filters.trainer_profile_key ?? '');
const localVersionId = ref(
    props.filters.sales_script_version_id != null ? String(props.filters.sales_script_version_id) : '',
);
const localOutcome = ref(props.filters.outcome ?? '');
const localDialogQuality = ref(props.filters.trainer_dialog_quality ?? '');

watch(
    () => props.filters,
    (f) => {
        localDays.value = f.days;
        localUserId.value = f.user_id != null ? String(f.user_id) : '';
        localProfileKey.value = f.trainer_profile_key ?? '';
        localVersionId.value = f.sales_script_version_id != null ? String(f.sales_script_version_id) : '';
        localOutcome.value = f.outcome ?? '';
        localDialogQuality.value = f.trainer_dialog_quality ?? '';
    },
    { deep: true },
);

const maxDailyTotal = computed(() => Math.max(1, ...props.daily.map((d) => d.total)));

function outcomeLabel(value) {
    if (value == null || value === '') {
        return '—';
    }
    const row = props.outcomeOptions.find((o) => o.value === value);

    return row?.label ?? value;
}

function trainerDialogQualityLabel(value) {
    if (value == null || value === '') {
        return '—';
    }
    const row = props.trainerDialogQualityOptions.find((o) => o.value === value);

    return row?.label ?? value;
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function applyFilters() {
    const q = { days: localDays.value };
    if (props.filters.can_view_all && localUserId.value) {
        q.user_id = localUserId.value;
    }
    if (localProfileKey.value) {
        q.trainer_profile_key = localProfileKey.value;
    }
    if (localVersionId.value) {
        q.sales_script_version_id = localVersionId.value;
    }
    if (localOutcome.value) {
        q.outcome = localOutcome.value;
    }
    if (localDialogQuality.value) {
        q.trainer_dialog_quality = localDialogQuality.value;
    }

    router.get(route('sales-assistant.trainer.analytics'), q, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>
