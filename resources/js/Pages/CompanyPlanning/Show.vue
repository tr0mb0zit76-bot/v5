<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto pb-8">
        <CrmPageHeader :title="initiative.title">
            <template #lead>
                {{ initiative.direction_label || 'Инициатива' }}
                <span v-if="initiative.owner_name"> · {{ initiative.owner_name }}</span>
                · {{ initiative.status_label }}
            </template>
            <template #actions>
                <Link :href="route('company-planning.index')" :class="crmBtnNeutral">К списку</Link>
                <button type="button" :class="crmBtnDangerMuted" @click="destroyInitiative">Удалить</button>
            </template>
        </CrmPageHeader>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Карточка инициативы</h2>
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveInitiative">
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Название</span>
                        <input v-model="initiativeForm.title" :class="crmFieldFluid" />
                        <InputError :message="initiativeForm.errors.title" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Статус</span>
                        <select v-model="initiativeForm.status" :class="crmFieldFluid">
                            <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Приоритет</span>
                        <select v-model="initiativeForm.priority" :class="crmFieldFluid">
                            <option v-for="(label, value) in priorityLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Направление</span>
                        <select v-model="initiativeForm.direction" :class="crmFieldFluid">
                            <option :value="null">Не указано</option>
                            <option v-for="(label, value) in directionLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Владелец</span>
                        <select v-model="initiativeForm.owner_id" :class="crmFieldFluid">
                            <option :value="null">Не назначен</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Старт</span>
                        <input v-model="initiativeForm.starts_on" type="date" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Дедлайн</span>
                        <input v-model="initiativeForm.ends_on" type="date" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Плановый бюджет</span>
                        <input v-model="initiativeForm.planned_budget_amount" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Статья управленки</span>
                        <select v-model="initiativeForm.management_expense_category_id" :class="crmFieldFluid">
                            <option :value="null">Не привязана</option>
                            <option v-for="category in expenseCategories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Риск</span>
                        <select v-model="initiativeForm.risk_level" :class="crmFieldFluid">
                            <option v-for="(label, value) in riskLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Прогресс, %</span>
                        <input v-model="initiativeForm.progress_percent" type="number" min="0" max="100" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Цель</span>
                        <textarea v-model="initiativeForm.goal" rows="2" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Ожидаемый результат</span>
                        <textarea v-model="initiativeForm.expected_result" rows="2" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Описание</span>
                        <textarea v-model="initiativeForm.description" rows="3" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Комментарий к бюджету</span>
                        <textarea v-model="initiativeForm.budget_notes" rows="2" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Риски</span>
                        <textarea v-model="initiativeForm.risk_summary" rows="2" :class="crmFieldFluid" />
                    </label>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" :class="crmBtnPrimary" :disabled="initiativeForm.processing">Сохранить</button>
                    </div>
                </form>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Этапы</h2>
                    <button type="button" :class="crmBtnNeutral" @click="milestoneOpen = !milestoneOpen">
                        {{ milestoneOpen ? 'Скрыть' : 'Добавить этап' }}
                    </button>
                </div>

                <form v-if="milestoneOpen" class="grid gap-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800" @submit.prevent="submitMilestone">
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Название этапа</span>
                        <input v-model="milestoneForm.title" :class="crmFieldFluid" />
                        <InputError :message="milestoneForm.errors.title" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Ответственный</span>
                        <select v-model="milestoneForm.responsible_id" :class="crmFieldFluid">
                            <option :value="null">Не назначен</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Статус</span>
                        <select v-model="milestoneForm.status" :class="crmFieldFluid">
                            <option v-for="(label, value) in milestoneStatusLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Начало</span>
                        <input v-model="milestoneForm.starts_on" type="date" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Конец</span>
                        <input v-model="milestoneForm.ends_on" type="date" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField" class="md:col-span-2">
                        <span :class="crmLabelCompact">Критерий готовности</span>
                        <textarea v-model="milestoneForm.done_criteria" rows="2" :class="crmFieldFluid" />
                    </label>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" :class="crmBtnPrimary" :disabled="milestoneForm.processing">Добавить</button>
                    </div>
                </form>

                <div v-if="initiative.milestones.length === 0" class="rounded-2xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    Этапов пока нет. Разбейте инициативу на контрольные точки со сроками.
                </div>

                <div v-else class="space-y-3">
                    <article
                        v-for="milestone in initiative.milestones"
                        :key="milestone.id"
                        class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ milestone.title }}</div>
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ milestone.responsible_name || 'Ответственный не назначен' }}
                                    · {{ formatPeriod(milestone.starts_on, milestone.ends_on) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ milestone.status_label }}
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ milestone.progress_percent }}%</span>
                            </div>
                        </div>

                        <p v-if="milestone.done_criteria" class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ milestone.done_criteria }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" :class="crmBtnNeutral" @click="editMilestone(milestone)">Изменить</button>
                            <button
                                v-if="canSpawnTasks && !milestone.task_id"
                                type="button"
                                :class="crmBtnNeutral"
                                @click="spawnTask(milestone)"
                            >
                                Поставить задачу
                            </button>
                            <span v-if="milestone.task_id" class="inline-flex items-center rounded-xl border border-zinc-200 px-3 py-1.5 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                                Задача {{ milestone.task_number || `#${milestone.task_id}` }}
                            </span>
                            <button type="button" :class="crmBtnDangerMuted" @click="deleteMilestone(milestone)">Удалить</button>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <section v-if="timelineRows.length > 0" :class="`${crmPanel} space-y-4 p-5`">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Дорожная карта</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ formatDate(timelineStart) }} — {{ formatDate(timelineEnd) }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="min-w-[720px] space-y-3">
                    <div
                        v-for="row in timelineRows"
                        :key="row.key"
                        class="grid grid-cols-[180px_minmax(0,1fr)] items-center gap-3"
                    >
                        <div class="truncate text-sm text-zinc-700 dark:text-zinc-300" :title="row.label">{{ row.label }}</div>
                        <div class="relative h-8 rounded-xl bg-zinc-100 dark:bg-zinc-900">
                            <div
                                class="absolute top-1/2 h-4 -translate-y-1/2 rounded-full"
                                :class="row.tone"
                                :style="barStyle(row)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <Modal :show="editMilestoneModal" max-width="lg" @close="closeEditMilestone">
            <form class="space-y-4 p-6" @submit.prevent="saveMilestone">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Этап</h3>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Название</span>
                    <input v-model="editMilestoneForm.title" :class="crmFieldFluid" />
                    <InputError :message="editMilestoneForm.errors.title" />
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Статус</span>
                        <select v-model="editMilestoneForm.status" :class="crmFieldFluid">
                            <option v-for="(label, value) in milestoneStatusLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Прогресс, %</span>
                        <input v-model="editMilestoneForm.progress_percent" type="number" min="0" max="100" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Начало</span>
                        <input v-model="editMilestoneForm.starts_on" type="date" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Конец</span>
                        <input v-model="editMilestoneForm.ends_on" type="date" :class="crmFieldFluid" />
                    </label>
                </div>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Критерий готовности</span>
                    <textarea v-model="editMilestoneForm.done_criteria" rows="3" :class="crmFieldFluid" />
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="closeEditMilestone">Отмена</button>
                    <button type="submit" :class="crmBtnPrimary" :disabled="editMilestoneForm.processing">Сохранить</button>
                </div>
            </form>
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnDangerMuted,
    crmBtnNeutral,
    crmBtnPrimary,
    crmFieldFluid,
    crmFilterField,
    crmLabelCompact,
    crmPanel,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'company-planning', mainFill: true }, () => page),
});

const props = defineProps({
    initiative: { type: Object, required: true },
    status_labels: { type: Object, default: () => ({}) },
    milestone_status_labels: { type: Object, default: () => ({}) },
    priority_labels: { type: Object, default: () => ({}) },
    direction_labels: { type: Object, default: () => ({}) },
    risk_labels: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    expense_categories: { type: Array, default: () => [] },
    can_spawn_tasks: { type: Boolean, default: false },
});

const statusLabels = computed(() => props.status_labels);
const milestoneStatusLabels = computed(() => props.milestone_status_labels);
const priorityLabels = computed(() => props.priority_labels);
const directionLabels = computed(() => props.direction_labels);
const riskLabels = computed(() => props.risk_labels);
const users = computed(() => props.users);
const expenseCategories = computed(() => props.expense_categories);
const canSpawnTasks = computed(() => props.can_spawn_tasks);

const milestoneOpen = ref(false);
const editMilestoneModal = ref(false);
const editingMilestoneId = ref(null);

const initiativeForm = useForm({
    title: props.initiative.title,
    description: props.initiative.description,
    goal: props.initiative.goal,
    expected_result: props.initiative.expected_result,
    status: props.initiative.status,
    priority: props.initiative.priority ?? 'normal',
    direction: props.initiative.direction,
    starts_on: props.initiative.starts_on,
    ends_on: props.initiative.ends_on,
    owner_id: props.initiative.owner_id,
    planned_budget_amount: props.initiative.planned_budget_amount,
    budget_currency: props.initiative.budget_currency ?? 'RUB',
    management_expense_category_id: props.initiative.management_expense_category_id,
    budget_notes: props.initiative.budget_notes,
    progress_percent: props.initiative.progress_percent ?? 0,
    risk_level: props.initiative.risk_level ?? 'normal',
    risk_summary: props.initiative.risk_summary,
});

const milestoneForm = useForm({
    title: '',
    responsible_id: null,
    status: 'planned',
    starts_on: null,
    ends_on: null,
    done_criteria: null,
});

const editMilestoneForm = useForm({
    title: '',
    status: 'planned',
    starts_on: null,
    ends_on: null,
    done_criteria: null,
    progress_percent: 0,
});

const timelineStart = computed(() => {
    const dates = collectTimelineDates();

    return dates.length ? dates[0] : null;
});

const timelineEnd = computed(() => {
    const dates = collectTimelineDates();

    return dates.length ? dates[dates.length - 1] : null;
});

const timelineRows = computed(() => {
    const rows = [];

    if (props.initiative.starts_on || props.initiative.ends_on) {
        rows.push({
            key: `initiative-${props.initiative.id}`,
            label: props.initiative.title,
            starts_on: props.initiative.starts_on,
            ends_on: props.initiative.ends_on ?? props.initiative.starts_on,
            tone: 'bg-sky-500/80',
        });
    }

    for (const milestone of props.initiative.milestones ?? []) {
        if (!milestone.starts_on && !milestone.ends_on) {
            continue;
        }

        rows.push({
            key: `milestone-${milestone.id}`,
            label: milestone.title,
            starts_on: milestone.starts_on ?? milestone.ends_on,
            ends_on: milestone.ends_on ?? milestone.starts_on,
            tone: milestone.status === 'completed' ? 'bg-emerald-500/80' : 'bg-violet-500/80',
        });
    }

    return rows;
});

function collectTimelineDates() {
    const values = [];

    if (props.initiative.starts_on) {
        values.push(props.initiative.starts_on);
    }
    if (props.initiative.ends_on) {
        values.push(props.initiative.ends_on);
    }

    for (const milestone of props.initiative.milestones ?? []) {
        if (milestone.starts_on) {
            values.push(milestone.starts_on);
        }
        if (milestone.ends_on) {
            values.push(milestone.ends_on);
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

function saveInitiative() {
    initiativeForm.patch(route('company-planning.update', props.initiative.id), {
        preserveScroll: true,
    });
}

function submitMilestone() {
    milestoneForm.post(route('company-planning.milestones.store', props.initiative.id), {
        preserveScroll: true,
        onSuccess: () => {
            milestoneForm.reset();
            milestoneForm.status = 'planned';
            milestoneOpen.value = false;
        },
    });
}

function editMilestone(milestone) {
    editingMilestoneId.value = milestone.id;
    editMilestoneForm.title = milestone.title;
    editMilestoneForm.status = milestone.status;
    editMilestoneForm.starts_on = milestone.starts_on;
    editMilestoneForm.ends_on = milestone.ends_on;
    editMilestoneForm.done_criteria = milestone.done_criteria;
    editMilestoneForm.progress_percent = milestone.progress_percent ?? 0;
    editMilestoneModal.value = true;
}

function closeEditMilestone() {
    editMilestoneModal.value = false;
    editingMilestoneId.value = null;
    editMilestoneForm.clearErrors();
}

function saveMilestone() {
    if (!editingMilestoneId.value) {
        return;
    }

    editMilestoneForm.patch(route('company-planning.milestones.update', editingMilestoneId.value), {
        preserveScroll: true,
        onSuccess: () => closeEditMilestone(),
    });
}

function deleteMilestone(milestone) {
    if (!window.confirm(`Удалить этап «${milestone.title}»?`)) {
        return;
    }

    router.delete(route('company-planning.milestones.destroy', milestone.id), {
        preserveScroll: true,
    });
}

function spawnTask(milestone) {
    router.post(route('company-planning.milestones.spawn-task', milestone.id), {}, {
        preserveScroll: true,
    });
}

function destroyInitiative() {
    if (!window.confirm(`Удалить инициативу «${props.initiative.title}»?`)) {
        return;
    }

    router.delete(route('company-planning.destroy', props.initiative.id));
}

function formatPeriod(start, end) {
    if (!start && !end) {
        return 'Сроки не заданы';
    }

    return `${formatDate(start)} → ${formatDate(end)}`;
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
