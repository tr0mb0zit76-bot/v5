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

                <div
                    v-if="initiative.budget_snapshot"
                    class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Бюджет: план и факт</h3>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ initiative.expense_category_name || 'Статья управленки' }}
                                · {{ formatDate(initiative.budget_snapshot.period_start) }} — {{ formatDate(initiative.budget_snapshot.period_end) }}
                            </p>
                        </div>
                        <Link
                            v-if="canOpenManagementAccounting && initiative.budget_snapshot?.category_id"
                            :href="managementAccountingUrl"
                            :class="crmBtnNeutral"
                        >
                            Управленка
                        </Link>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <article class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">План</div>
                            <div class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                                {{ formatMoney(initiative.budget_snapshot.planned_amount) }}
                            </div>
                        </article>
                        <article class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Факт (расход)</div>
                            <div class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                                {{ formatMoney(initiative.budget_snapshot.fact_out_amount) }}
                            </div>
                        </article>
                        <article class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Отклонение</div>
                            <div
                                class="mt-1 text-lg font-semibold tabular-nums"
                                :class="varianceTone(initiative.budget_snapshot.variance_amount)"
                            >
                                {{ formatMoney(initiative.budget_snapshot.variance_amount, true) }}
                            </div>
                            <div
                                v-if="initiative.budget_snapshot.usage_percent !== null"
                                class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"
                            >
                                Использовано {{ initiative.budget_snapshot.usage_percent }}% плана
                            </div>
                        </article>
                    </div>
                </div>
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
                        v-for="(milestone, milestoneIndex) in initiative.milestones"
                        :key="milestone.id"
                        class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ milestone.title }}</div>
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ milestone.responsible_name || 'Ответственный не назначен' }}
                                    · {{ formatPeriod(milestone.starts_on, milestone.ends_on) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <div v-if="initiative.milestones.length > 1" class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-1 text-zinc-500 transition hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-900"
                                        :disabled="milestoneIndex === 0 || reorderForm.processing"
                                        title="Выше"
                                        @click="moveMilestone(milestoneIndex, -1)"
                                    >
                                        <ChevronUp class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-1 text-zinc-500 transition hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-900"
                                        :disabled="milestoneIndex === initiative.milestones.length - 1 || reorderForm.processing"
                                        title="Ниже"
                                        @click="moveMilestone(milestoneIndex, 1)"
                                    >
                                        <ChevronDown class="h-4 w-4" />
                                    </button>
                                </div>
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ milestone.status_label }}
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ milestone.progress_percent }}%</span>
                            </div>
                        </div>

                        <p v-if="milestone.done_criteria" class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ milestone.done_criteria }}
                        </p>

                        <div
                            v-if="milestone.blocked_by?.length"
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="blocker in milestone.blocked_by"
                                :key="`${milestone.id}-${blocker.id}`"
                                class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                            >
                                Ждёт: {{ blocker.title || `#${blocker.id}` }}
                            </span>
                        </div>

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
                            <Link
                                v-if="milestone.task_id && canOpenTasks"
                                :href="route('tasks.show', milestone.task_id)"
                                :class="crmBtnNeutral"
                            >
                                Задача {{ milestone.task_number || `#${milestone.task_id}` }}
                            </Link>
                            <span
                                v-else-if="milestone.task_id"
                                class="inline-flex items-center rounded-xl border border-zinc-200 px-3 py-1.5 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300"
                            >
                                Задача {{ milestone.task_number || `#${milestone.task_id}` }}
                            </span>
                            <button type="button" :class="crmBtnDangerMuted" @click="deleteMilestone(milestone)">Удалить</button>
                        </div>
                    </article>
                </div>

                <div v-if="initiative.milestones.length >= 2" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Зависимости этапов</h3>
                        <button type="button" :class="crmBtnNeutral" @click="dependencyOpen = !dependencyOpen">
                            {{ dependencyOpen ? 'Скрыть' : 'Добавить' }}
                        </button>
                    </div>

                    <form
                        v-if="dependencyOpen"
                        class="grid gap-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                        @submit.prevent="submitDependency"
                    >
                        <label :class="crmFilterField">
                            <span :class="crmLabelCompact">Этап (зависит от другого)</span>
                            <select v-model="dependencyForm.blocked_milestone_id" :class="crmFieldFluid">
                                <option :value="null">Выберите этап</option>
                                <option v-for="milestone in initiative.milestones" :key="`blocked-${milestone.id}`" :value="milestone.id">
                                    {{ milestone.title }}
                                </option>
                            </select>
                            <InputError :message="dependencyForm.errors.blocked_milestone_id" />
                        </label>
                        <label :class="crmFilterField">
                            <span :class="crmLabelCompact">Предшествующий этап</span>
                            <select v-model="dependencyForm.depends_on_milestone_id" :class="crmFieldFluid">
                                <option :value="null">Выберите этап</option>
                                <option v-for="milestone in initiative.milestones" :key="`depends-${milestone.id}`" :value="milestone.id">
                                    {{ milestone.title }}
                                </option>
                            </select>
                            <InputError :message="dependencyForm.errors.depends_on_milestone_id" />
                        </label>
                        <label :class="crmFilterField" class="md:col-span-2">
                            <span :class="crmLabelCompact">Комментарий</span>
                            <textarea v-model="dependencyForm.notes" rows="2" :class="crmFieldFluid" />
                        </label>
                        <div class="md:col-span-2 flex justify-end">
                            <button type="submit" :class="crmBtnPrimary" :disabled="dependencyForm.processing">Добавить зависимость</button>
                        </div>
                    </form>

                    <div v-if="!(initiative.dependencies?.length)" class="text-sm text-zinc-500 dark:text-zinc-400">
                        Связи между этапами пока не заданы.
                    </div>

                    <div v-else class="space-y-2">
                        <article
                            v-for="dependency in initiative.dependencies"
                            :key="dependency.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800"
                        >
                            <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                <span class="font-medium">{{ dependency.blocked_milestone_title }}</span>
                                <span class="mx-2 text-zinc-400">←</span>
                                <span>{{ dependency.depends_on_milestone_title }}</span>
                                <span v-if="dependency.notes" class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ dependency.notes }}
                                </span>
                            </div>
                            <button type="button" :class="crmBtnDangerMuted" @click="deleteDependency(dependency)">
                                Удалить
                            </button>
                        </article>
                    </div>
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
                <div :class="`${crmModalFieldRow} crm-modal-field-row--full flex-wrap`">
                    <label :class="crmModalFieldLabel">Название</label>
                    <input v-model="editMilestoneForm.title" :class="crmFieldFluid" />
                    <InputError :message="editMilestoneForm.errors.title" class="w-full" />
                </div>
                <div :class="crmModalFieldsWrap">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Статус</label>
                        <select v-model="editMilestoneForm.status" :class="crmFieldFluid">
                            <option v-for="(label, value) in milestoneStatusLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Прогресс</label>
                        <input v-model="editMilestoneForm.progress_percent" type="number" min="0" max="100" :class="crmFieldFluid" />
                    </div>
                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Начало</label>
                        <input v-model="editMilestoneForm.starts_on" type="date" :class="crmFieldFluid" />
                    </div>
                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Конец</label>
                        <input v-model="editMilestoneForm.ends_on" type="date" :class="crmFieldFluid" />
                    </div>
                </div>
                <div :class="crmModalFieldStack">
                    <label :class="crmModalFieldLabel">Критерий готовности</label>
                    <textarea v-model="editMilestoneForm.done_criteria" rows="3" :class="crmFieldFluid" />
                </div>
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
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnDangerMuted,
    crmBtnNeutral,
    crmBtnPrimary,
    crmFieldFluid,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
    crmModalFieldStack,
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
    can_open_tasks: { type: Boolean, default: false },
    can_open_management_accounting: { type: Boolean, default: false },
});

const statusLabels = computed(() => props.status_labels);
const milestoneStatusLabels = computed(() => props.milestone_status_labels);
const priorityLabels = computed(() => props.priority_labels);
const directionLabels = computed(() => props.direction_labels);
const riskLabels = computed(() => props.risk_labels);
const users = computed(() => props.users);
const expenseCategories = computed(() => props.expense_categories);
const canSpawnTasks = computed(() => props.can_spawn_tasks);
const canOpenTasks = computed(() => props.can_open_tasks);
const canOpenManagementAccounting = computed(() => props.can_open_management_accounting);

const managementAccountingUrl = computed(() => {
    const snapshot = props.initiative.budget_snapshot;
    if (!snapshot?.period_start) {
        return route('finance.management-accounting.index');
    }

    return route('finance.management-accounting.index', {
        period_type: 'month',
        period_anchor: `${String(snapshot.period_start).slice(0, 7)}-01`,
    });
});

const reorderForm = useForm({
    milestone_ids: [],
});

const milestoneOpen = ref(false);
const dependencyOpen = ref(false);
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

const dependencyForm = useForm({
    blocked_milestone_id: null,
    depends_on_milestone_id: null,
    type: 'finish_to_start',
    notes: null,
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

function moveMilestone(index, direction) {
    const milestones = [...(props.initiative.milestones ?? [])];
    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= milestones.length) {
        return;
    }

    const [moved] = milestones.splice(index, 1);
    milestones.splice(targetIndex, 0, moved);

    reorderForm.milestone_ids = milestones.map((milestone) => milestone.id);
    reorderForm.post(route('company-planning.milestones.reorder', props.initiative.id), {
        preserveScroll: true,
    });
}

function submitDependency() {
    dependencyForm.post(route('company-planning.dependencies.store', props.initiative.id), {
        preserveScroll: true,
        onSuccess: () => {
            dependencyForm.reset();
            dependencyForm.type = 'finish_to_start';
            dependencyOpen.value = false;
        },
    });
}

function deleteDependency(dependency) {
    if (!window.confirm('Удалить зависимость между этапами?')) {
        return;
    }

    router.delete(route('company-planning.dependencies.destroy', dependency.id), {
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

function formatMoney(value, signed = false) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const amount = Number(value);
    const formatted = new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Math.abs(amount));

    if (signed && amount > 0) {
        return `+${formatted}`;
    }

    if (signed && amount < 0) {
        return `−${formatted}`;
    }

    return formatted;
}

function varianceTone(value) {
    if (value === null || value === undefined) {
        return 'text-zinc-900 dark:text-zinc-50';
    }

    if (Number(value) > 0) {
        return 'text-rose-700 dark:text-rose-300';
    }

    if (Number(value) < 0) {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    return 'text-zinc-900 dark:text-zinc-50';
}
</script>
