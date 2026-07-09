<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden pb-6">
        <CrmPageHeader
            :lead="`Управленческие инициативы компании: цели, этапы, сроки и бюджет. Всего: ${initiatives.length}`"
            title="План компании"
        >
            <template #actions>
                <button type="button" :class="crmBtnCreate" @click="createOpen = !createOpen">
                    {{ createOpen ? 'Скрыть форму' : 'Новая инициатива' }}
                </button>
            </template>
        </CrmPageHeader>

        <section v-if="createOpen" :class="`${crmPanel} shrink-0 space-y-4 p-5`">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Новая инициатива</h2>
            <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitCreate">
                <label :class="crmFilterField" class="md:col-span-2">
                    <span :class="crmLabelCompact">Название</span>
                    <input v-model="createForm.title" :class="crmFieldFluid" placeholder="Например: Запуск направления импорта" />
                    <InputError :message="createForm.errors.title" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Направление</span>
                    <select v-model="createForm.direction" :class="crmFieldFluid">
                        <option :value="null">Не указано</option>
                        <option v-for="(label, value) in directionLabels" :key="value" :value="value">{{ label }}</option>
                    </select>
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Владелец</span>
                    <select v-model="createForm.owner_id" :class="crmFieldFluid">
                        <option :value="null">Не назначен</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Старт</span>
                    <input v-model="createForm.starts_on" type="date" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Дедлайн</span>
                    <input v-model="createForm.ends_on" type="date" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField" class="md:col-span-2">
                    <span :class="crmLabelCompact">Цель</span>
                    <textarea v-model="createForm.goal" rows="2" :class="crmFieldFluid" />
                </label>
                <div class="md:col-span-2 flex justify-end gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="createOpen = false">Отмена</button>
                    <button type="submit" :class="crmBtnPrimary" :disabled="createForm.processing">Создать</button>
                </div>
            </form>
        </section>

        <section class="grid shrink-0 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <button
                v-for="card in summaryCards"
                :key="card.key"
                type="button"
                :class="`${crmPanel} px-4 py-3 text-left transition hover:border-zinc-300 hover:bg-zinc-50 dark:hover:border-zinc-600 dark:hover:bg-zinc-900/60`"
                @click="applySummaryFilter(card)"
            >
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    {{ card.label }}
                </div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                    {{ card.value }}
                </div>
            </button>
        </section>

        <section :class="`${crmPanel} shrink-0 space-y-3 p-4`">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in viewFilterOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                    :class="viewFilter === option.value
                        ? 'border-sky-600 bg-sky-600 text-white dark:border-sky-400 dark:bg-sky-500'
                        : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300'"
                    @click="applyViewFilter(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in statusFilterOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                    :class="statusFilter === option.value
                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                        : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300'"
                    @click="applyStatusFilter(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </section>

        <section
            v-if="viewFilter === 'timeline'"
            :class="`${crmPanel} min-h-0 flex-1 space-y-4 overflow-y-auto p-5`"
        >
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Портфельная дорожная карта</h2>
                    <p v-if="timelineStart && timelineEnd" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ formatDate(timelineStart) }} — {{ formatDate(timelineEnd) }}
                    </p>
                </div>
            </div>

            <div
                v-if="timelineRows.length === 0"
                class="rounded-2xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
            >
                Нет этапов с датами у активных инициатив.
            </div>

            <div v-else class="overflow-x-auto">
                <div class="min-w-[720px] space-y-3">
                    <button
                        v-for="row in timelineRows"
                        :key="row.key"
                        type="button"
                        class="grid w-full grid-cols-[minmax(180px,280px)_minmax(0,1fr)] items-center gap-3 rounded-xl px-2 py-1 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-900/60"
                        @click="openTimelineRow(row)"
                    >
                        <div class="truncate text-sm text-zinc-700 dark:text-zinc-300" :title="row.label">{{ row.label }}</div>
                        <div class="relative h-8 rounded-xl bg-zinc-100 dark:bg-zinc-900">
                            <div
                                class="absolute top-1/2 h-4 -translate-y-1/2 rounded-full"
                                :class="row.tone"
                                :style="barStyle(row)"
                            />
                        </div>
                    </button>
                </div>
            </div>
        </section>

        <div v-else-if="initiatives.length === 0" :class="`${crmPanel} shrink-0 p-4`">
            <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                Инициатив пока нет. Создайте первую — это верхний уровень плана, не задачи сотрудников.
            </div>
        </div>

        <div v-else :class="crmGridPanel">
            <CompanyPlanningGrid
                :rows="initiatives"
                :user-id="userId"
                :status-labels="statusLabels"
                :risk-labels="riskLabels"
                @row-click="openInitiative"
                @row-dblclick="openInitiative"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CompanyPlanningGrid from '@/Components/CompanyPlanning/CompanyPlanningGrid.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmBtnPrimary,
    crmFieldFluid,
    crmFilterField,
    crmGridPanel,
    crmLabelCompact,
    crmPanel,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'company-planning', mainFill: true }, () => page),
});

const props = defineProps({
    initiatives: { type: Array, default: () => [] },
    summary: {
        type: Object,
        default: () => ({
            active_count: 0,
            overdue_initiatives_count: 0,
            overdue_milestones_count: 0,
            high_risk_count: 0,
            upcoming_milestones_count: 0,
        }),
    },
    status_filter: { type: String, default: '' },
    view_filter: { type: String, default: 'list' },
    timeline_rows: { type: Array, default: () => [] },
    status_labels: { type: Object, default: () => ({}) },
    direction_labels: { type: Object, default: () => ({}) },
    risk_labels: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
});

const page = usePage();
const createOpen = ref(false);
const statusFilter = computed(() => props.status_filter ?? '');
const viewFilter = computed(() => props.view_filter ?? 'list');
const statusLabels = computed(() => props.status_labels);
const directionLabels = computed(() => props.direction_labels);
const riskLabels = computed(() => props.risk_labels);
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');

const createForm = useForm({
    title: '',
    direction: null,
    owner_id: null,
    starts_on: null,
    ends_on: null,
    goal: null,
    status: 'draft',
});

const viewFilterOptions = [
    { value: 'list', label: 'Список' },
    { value: 'budget', label: 'Бюджет' },
    { value: 'risk', label: 'Риски' },
    { value: 'upcoming', label: 'На 7 дней' },
    { value: 'timeline', label: 'Дорожная карта' },
];

const summaryCards = computed(() => [
    { key: 'active', label: 'Активные', value: props.summary.active_count ?? 0, query: { status: 'active' } },
    { key: 'overdue_initiatives', label: 'Просроч. инициативы', value: props.summary.overdue_initiatives_count ?? 0, query: { view: 'risk' } },
    { key: 'overdue_milestones', label: 'Проср. этапы', value: props.summary.overdue_milestones_count ?? 0, query: { view: 'risk' } },
    { key: 'high_risk', label: 'Высокий риск', value: props.summary.high_risk_count ?? 0, query: { view: 'risk' } },
    { key: 'upcoming', label: 'Этапы на 7 дней', value: props.summary.upcoming_milestones_count ?? 0, query: { view: 'upcoming' } },
]);

const statusFilterOptions = computed(() => [
    { value: '', label: 'Все' },
    ...Object.entries(props.status_labels).map(([value, label]) => ({ value, label })),
]);

function applySummaryFilter(card) {
    if (!card?.query) {
        return;
    }

    router.get(route('company-planning.index'), card.query, {
        preserveScroll: true,
        preserveState: true,
    });
}

const timelineRows = computed(() => props.timeline_rows ?? []);

const timelineStart = computed(() => {
    const dates = collectTimelineDates();

    return dates.length ? dates[0] : null;
});

const timelineEnd = computed(() => {
    const dates = collectTimelineDates();

    return dates.length ? dates[dates.length - 1] : null;
});

function indexQuery(overrides = {}) {
    const query = {};

    const status = overrides.status ?? statusFilter.value;
    const view = overrides.view ?? viewFilter.value;

    if (status) {
        query.status = status;
    }
    if (view && view !== 'list') {
        query.view = view;
    }

    return query;
}

function applyStatusFilter(value) {
    router.get(route('company-planning.index'), indexQuery({ status: value }), {
        preserveScroll: true,
        preserveState: true,
    });
}

function applyViewFilter(value) {
    router.get(route('company-planning.index'), indexQuery({ view: value }), {
        preserveScroll: true,
        preserveState: true,
    });
}

function submitCreate() {
    createForm.post(route('company-planning.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createOpen.value = false;
        },
    });
}

function openInitiative(row) {
    if (!row?.id) {
        return;
    }

    router.get(route('company-planning.show', row.id));
}

function openTimelineRow(row) {
    if (row?.show_url) {
        router.get(row.show_url);

        return;
    }

    if (row?.initiative_id) {
        router.get(route('company-planning.show', row.initiative_id));
    }
}

function collectTimelineDates() {
    const values = [];

    for (const row of timelineRows.value) {
        if (row.starts_on) {
            values.push(row.starts_on);
        }
        if (row.ends_on) {
            values.push(row.ends_on);
        }
    }

    return values.sort();
}

function barStyle(row) {
    const start = timelineStart.value;
    const end = timelineEnd.value;
    const rowStart = row.starts_on ?? row.ends_on;
    const rowEnd = row.ends_on ?? row.starts_on;

    if (!start || !end || !rowStart || !rowEnd) {
        return { left: '0%', width: '0%' };
    }

    const totalMs = dateToMs(end) - dateToMs(start) || 1;
    const leftMs = Math.max(0, dateToMs(rowStart) - dateToMs(start));
    const widthMs = Math.max(1, dateToMs(rowEnd) - dateToMs(rowStart));

    return {
        left: `${(leftMs / totalMs) * 100}%`,
        width: `${Math.max(2, (widthMs / totalMs) * 100)}%`,
    };
}

function dateToMs(value) {
    return new Date(`${value}T00:00:00`).getTime();
}

function formatDate(value) {
    if (!value) {
        return '…';
    }

    const parts = String(value).split('-');
    if (parts.length !== 3) {
        return value;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}
</script>
