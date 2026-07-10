<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            :lead="`Контроль задач менеджеров. Всего: ${(tasks ?? []).length}`"
            title="Задачи"
        >
            <template #actions>
                <Link
                    :class="crmBtnSecondaryOutline"
                    :href="route('kanban.index')"
                >
                    Канбан
                </Link>
                <button
                    v-if="canCreateLeads"
                    type="button"
                    :class="crmBtnSecondaryOutline"
                    @click="openCreateLead"
                >
                    <Plus class="h-4 w-4" />
                    Создать лид
                </button>
                <button
                    type="button"
                    :class="crmBtnCreate"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" />
                    Создать задачу
                </button>
            </template>
        </CrmPageHeader>

        <div v-if="selectedTaskIds.length > 0" class="flex shrink-0 flex-wrap items-center gap-2">
            <button
                v-if="canDeleteTasks && selectedTaskIds.length > 0"
                type="button"
                :class="crmBtnDangerMuted"
                @click="bulkDeleteSelected"
            >
                Удалить выбранные ({{ selectedTaskIds.length }})
            </button>
            <button
                v-if="selectedTaskIds.length > 0"
                type="button"
                :class="crmBtnDangerMuted"
                @click="bulkCloseSelected"
            >
                Закрыть выбранные ({{ selectedTaskIds.length }})
            </button>
            <template v-if="canBulkMutateTasks && selectedTaskIds.length > 0">
                <select
                    v-model="bulkAssignUserId"
                    :class="crmFieldWide"
                >
                    <option :value="null" disabled>Назначить на…</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
                <button
                    type="button"
                    :class="crmBtnNeutral"
                    :disabled="!bulkAssignUserId"
                    @click="bulkAssignSelected"
                >
                    Назначить выбранные
                </button>
            </template>
            <template v-if="selectedTaskIds.length > 0">
                <select
                    v-model="bulkStatus"
                    :class="crmFieldWide"
                >
                    <option value="" disabled>Статус для выбранных…</option>
                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <button
                    type="button"
                    :class="crmBtnNeutral"
                    :disabled="!bulkStatus"
                    @click="bulkSetStatus"
                >
                    Применить статус
                </button>
                <input
                    v-model="bulkDueAt"
                    type="datetime-local"
                    :class="crmFieldWide"
                >
                <button
                    type="button"
                    :class="crmBtnNeutral"
                    :disabled="!bulkDueAt"
                    @click="bulkRescheduleDue"
                >
                    Перенести срок
                </button>
            </template>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-3 border-b border-zinc-200 pb-2 dark:border-zinc-800">
            <div class="flex flex-wrap gap-2">
            <button
                v-for="filter in quickFilters"
                :key="filter.label"
                type="button"
                class="rounded-xl border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] transition"
                :class="activeFilter === filter.label
                    ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                    : 'border-zinc-200 text-zinc-500 hover:border-zinc-900 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-50 dark:hover:text-zinc-50'"
                @click="activeFilter = filter.label"
            >
                {{ filter.label }} · {{ filter.count }}
            </button>
            </div>
            <label v-if="users.length > 1" class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="shrink-0 font-medium uppercase tracking-[0.15em]">Ответственный</span>
                <select
                    v-model="filterResponsibleId"
                    class="rounded-xl border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                >
                    <option :value="null">Все</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
            </label>
        </div>

        <div :class="crmGridPanel">
            <TasksGrid
                :rows="visibleTasks"
                :user-id="userId"
                :users="users"
                :status-options="statusOptions"
                :can-bulk-mutate-tasks="canBulkMutateTasks"
                :can-delete-tasks="canDeleteTasks"
                @row-dblclick="handleRowDblClick"
                @selection-changed="onTaskSelectionChanged"
                @quick-status="onQuickStatus"
                @quick-reschedule-due="onQuickRescheduleDue"
                @assign-request="onAssignRequest"
                @delete-task="deleteTask"
                @cell-save="handleCellSave"
            />
        </div>

        <Modal :show="assignOneTask !== null" max-width="sm" @close="assignOneTask = null">
            <section class="space-y-4 bg-white p-6 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Назначить ответственного</h2>
                <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                    <label :class="crmModalFieldLabel">Сотрудник</label>
                    <select
                        v-model="assignOneUserId"
                        :class="crmFieldFluid"
                    >
                        <option :value="null" disabled>Выберите</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <button type="button" :class="crmBtnNeutral" @click="assignOneTask = null">Отмена</button>
                    <button
                        type="button"
                        :class="crmBtnCreate"
                        :disabled="!assignOneUserId"
                        @click="confirmAssignOne"
                    >
                        Назначить
                    </button>
                </div>
            </section>
        </Modal>

        <Modal :show="isTaskDetailModalOpen" max-width="5xl" @close="closeTaskDetailModal">
            <section class="flex max-h-[calc(100dvh-3rem)] flex-col overflow-hidden bg-white dark:bg-zinc-900">
                <CrmModalHeader
                    eyebrow="Детали задачи"
                    :title="selectedTask?.title ?? ''"
                    @close="closeTaskDetailModal"
                >
                    <template v-if="selectedTask">
                        #{{ selectedTask.number }}
                    </template>
                    <template #actions>
                        <button
                            type="button"
                            :class="crmBtnNeutral"
                            class="!px-3 !py-2 text-xs"
                            @click="openEditFromDetail"
                        >
                            Редактировать
                        </button>
                        <div
                            v-if="selectedTask && selectedTask.status !== 'done'"
                            ref="completeMenuRoot"
                            class="relative inline-flex"
                        >
                            <button
                                type="button"
                                :class="crmBtnCreate"
                                class="!rounded-r-none !px-3 !py-2 text-xs"
                                @click="markDone(selectedTask)"
                            >
                                Завершить
                            </button>
                            <button
                                type="button"
                                :class="crmBtnCreate"
                                class="!rounded-l-none border-l border-l-white/20 !px-2 !py-2 text-xs dark:border-l-zinc-900/30"
                                aria-label="Другие действия завершения"
                                @click.stop="completeMenuOpen = !completeMenuOpen"
                            >
                                <ChevronDown class="h-4 w-4" />
                            </button>
                            <div
                                v-if="completeMenuOpen"
                                class="absolute right-0 top-full z-20 mt-1 min-w-[15rem] border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <button
                                    type="button"
                                    class="block w-full px-3 py-2 text-left text-sm text-zinc-800 hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-800"
                                    @click="markDoneAndCreateNew(selectedTask)"
                                >
                                    Завершить и создать новую
                                </button>
                            </div>
                        </div>
                        <button
                            v-if="canCreateLeads && selectedTask && !selectedTask.lead_id"
                            type="button"
                            :class="crmBtnNeutral"
                            class="!px-3 !py-2 text-xs"
                            @click="openCreateLeadFromTask(selectedTask)"
                        >
                            Создать лид
                        </button>
                        <button
                            v-if="selectedTask && selectedTask.status !== 'done'"
                            type="button"
                            :class="crmBtnNeutral"
                            class="!px-3 !py-2 text-xs"
                            @click="openRescheduleModal"
                        >
                            Перенести срок
                        </button>
                        <button
                            v-if="canDeleteTasks && selectedTask"
                            type="button"
                            :class="crmBtnDangerMuted"
                            class="!px-3 !py-2 text-xs"
                            @click="deleteTask(selectedTask)"
                        >
                            Удалить
                        </button>
                    </template>
                </CrmModalHeader>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <template v-if="selectedTask">
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ selectedTask.description || 'Без описания' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            <span class="rounded-full border border-zinc-200 px-2 py-1 dark:border-zinc-700">{{ selectedTask.status_label }}</span>
                            <span class="rounded-full border border-zinc-200 px-2 py-1 dark:border-zinc-700">Приоритет: {{ priorityLabel(selectedTask.priority) }}</span>
                            <span class="rounded-full border border-zinc-200 px-2 py-1 dark:border-zinc-700">Срок: {{ formatDue(selectedTask.due_at) }}</span>
                            <span
                                v-if="selectedTask.sla_deadline_at"
                                class="rounded-full border px-2 py-1"
                                :class="selectedTask.sla_breached ? 'border-rose-400 text-rose-700 dark:border-rose-500 dark:text-rose-300' : 'border-zinc-200 dark:border-zinc-700'"
                            >
                                SLA: {{ formatDue(selectedTask.sla_deadline_at) }}
                            </span>
                            <span
                                v-if="selectedTask.lead_number"
                                class="rounded-full border border-zinc-200 px-2 py-1 dark:border-zinc-700"
                            >
                                Лид: {{ selectedTask.lead_number }}
                            </span>
                            <span
                                v-if="selectedTask.contractor_name"
                                class="rounded-full border border-zinc-200 px-2 py-1 dark:border-zinc-700"
                            >
                                Контрагент: {{ selectedTask.contractor_name }}
                            </span>
                        </div>

                        <div class="mt-8 space-y-8">
                            <section>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Чеклист</div>
                                <form class="mt-2 flex gap-2" @submit.prevent="addChecklistItem">
                                    <input v-model="checklistForm.title" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" placeholder="Новый пункт" required />
                                    <button type="submit" class="rounded-xl border border-zinc-900 px-3 py-2 text-xs font-semibold dark:border-zinc-50" :disabled="checklistForm.processing">Добавить</button>
                                </form>
                                <div class="mt-2 space-y-2">
                                    <label v-for="item in selectedTask.checklist_items || []" :key="item.id" class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :checked="item.is_done" @change="toggleChecklistItem(item)" />
                                        <span :class="item.is_done ? 'line-through text-zinc-400' : 'text-zinc-700 dark:text-zinc-200'">{{ item.title }}</span>
                                    </label>
                                </div>
                            </section>

                            <section>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Комментарии</div>
                                <form class="mt-2 space-y-2" @submit.prevent="addComment">
                                    <textarea v-model="commentForm.body" rows="2" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" placeholder="Комментарий" required />
                                    <button type="submit" class="rounded-xl border border-zinc-900 px-3 py-2 text-xs font-semibold dark:border-zinc-50" :disabled="commentForm.processing">Отправить</button>
                                </form>
                                <div class="mt-2 max-h-40 space-y-2 overflow-auto">
                                    <div v-for="comment in selectedTask.comments || []" :key="comment.id" class="rounded-lg border border-zinc-200 px-3 py-2 text-xs dark:border-zinc-700">
                                        <div class="font-semibold">{{ comment.author_name || 'Пользователь' }}</div>
                                        <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ comment.body }}</div>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Вложения</div>
                                <form class="mt-2 flex gap-2" @submit.prevent="addAttachment">
                                    <input type="file" class="w-full text-xs" @change="onAttachmentSelected" />
                                    <button type="submit" class="rounded-xl border border-zinc-900 px-3 py-2 text-xs font-semibold dark:border-zinc-50" :disabled="attachmentForm.processing || !attachmentFile">Загрузить</button>
                                </form>
                                <div class="mt-2 space-y-2">
                                    <div v-for="file in selectedTask.attachments || []" :key="file.id" class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-xs dark:border-zinc-700">
                                        <a :href="file.download_url" class="truncate underline">{{ file.original_name }}</a>
                                        <button type="button" class="text-rose-600" @click="deleteAttachment(file)">Удалить</button>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">История</div>
                                <div class="mt-2 max-h-56 space-y-2 overflow-auto">
                                    <div v-for="eventItem in selectedTask.events || []" :key="eventItem.id" class="rounded-lg border border-zinc-200 px-3 py-2 text-xs dark:border-zinc-700">
                                        <div class="font-semibold">{{ eventItem.title }}</div>
                                        <div class="text-zinc-500">{{ eventItem.author_name || 'Система' }} · {{ formatDateTime(eventItem.created_at) }}</div>
                                        <div v-if="eventItem.description" class="mt-1 text-zinc-600 dark:text-zinc-300">{{ eventItem.description }}</div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </template>
                </div>
            </section>
        </Modal>

        <Modal :show="isFormOpen" max-width="xl" @close="closeFormModal">
            <div class="overflow-y-auto bg-white dark:bg-zinc-900">
                <CrmModalHeader
                    :eyebrow="editingTask ? 'Редактирование задачи' : 'Новая задача'"
                    :title="editingTask ? `#${editingTask.number}` : 'Создание задачи'"
                    @close="closeFormModal"
                />

                <form class="space-y-4 px-6 pb-6 pt-2" @submit.prevent="submitForm">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Название</label>
                        <input v-model="form.title" type="text" :class="crmFieldFluid" required />
                    </div>

                    <div :class="crmModalFieldStack">
                        <label :class="crmModalFieldLabel">Описание</label>
                        <textarea v-model="form.description" rows="3" :class="crmFieldFluid" />
                    </div>

                    <div :class="crmModalFieldsWrap">
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Статус</label>
                            <select v-model="form.status" :class="crmFieldFluid">
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Приоритет</label>
                            <select v-model="form.priority" :class="crmFieldFluid">
                                <option value="low">Низкий</option>
                                <option value="medium">Средний</option>
                                <option value="high">Высокий</option>
                                <option value="critical">Критичный</option>
                            </select>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Срок</label>
                            <input v-model="form.due_at" type="datetime-local" :class="crmFieldFluid" />
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">SLA</label>
                            <input v-model="form.sla_deadline_at" type="datetime-local" :class="crmFieldFluid" />
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Ответственный</label>
                            <select v-model="form.responsible_id" :class="crmFieldFluid" required>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--full flex-wrap`">
                            <label :class="crmModalFieldLabel">Лид</label>
                            <select v-model="form.lead_id" :class="crmFieldFluid">
                                <option :value="null">Без привязки</option>
                                <option v-for="lead in leadOptions" :key="lead.id" :value="lead.id">{{ lead.number }} — {{ lead.title }}</option>
                            </select>
                            <button
                                v-if="canCreateLeads"
                                type="button"
                                :class="crmBtnSecondaryOutline"
                                class="shrink-0 !px-3"
                                title="Создать новый лид"
                                @click="openCreateLeadFromForm"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                            <label :class="crmModalFieldLabel">Контрагент</label>
                            <select v-model="form.contractor_id" :class="crmFieldFluid">
                                <option :value="null">Без привязки</option>
                                <option v-for="contractor in contractorOptions" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" :class="crmBtnNeutral" @click="closeFormModal">
                            Отмена
                        </button>
                        <button type="submit" :class="crmBtnCreate" :disabled="form.processing">
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isRescheduleModalOpen" max-width="sm" @close="closeRescheduleModal">
            <div class="overflow-y-auto bg-white dark:bg-zinc-900">
                <CrmModalHeader
                    eyebrow="Срок задачи"
                    title="Перенести срок"
                    @close="closeRescheduleModal"
                />
                <form class="space-y-4 px-6 pb-6 pt-2" @submit.prevent="submitReschedule">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Новый срок</label>
                        <input
                            v-model="rescheduleDueAt"
                            type="datetime-local"
                            :class="crmFieldFluid"
                            required
                        />
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" :class="crmBtnNeutral" @click="closeRescheduleModal">
                            Отмена
                        </button>
                        <button type="submit" :class="crmBtnCreate" :disabled="rescheduleProcessing">
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import { crmBtnCreate, crmBtnDangerMuted, crmBtnNeutral, crmBtnSecondaryOutline, crmFieldFluid, crmFieldWide, crmGridPanel, crmModalFieldLabel, crmModalFieldRow, crmModalFieldsWrap, crmModalFieldStack } from '@/support/crmUi.js';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import Modal from '@/Components/Modal.vue';
import TasksGrid from '@/Components/Tasks/TasksGrid.vue';
import { ChevronDown, Plus } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'tasks', mainFill: true }, () => page),
});

const taskModalOpenKeys = ['selectedTask'];
const taskListRefreshKeys = ['selectedTask', 'tasks', 'quickFilters'];

function readPersistedTasksPageFilters(storedUserId) {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = localStorage.getItem(`tasks_page_filters_v1_${storedUserId}`);

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const persistedPageFilters = readPersistedTasksPageFilters(userId.value);

const props = defineProps({
    tasks: Array,
    selectedTask: {
        type: Object,
        default: null,
    },
    quickFilters: Array,
    statusOptions: Array,
    users: Array,
    leadOptions: Array,
    contractorOptions: {
        type: Array,
        default: () => [],
    },
    can_bulk_mutate_tasks: {
        type: Boolean,
        default: false,
    },
    can_delete_tasks: {
        type: Boolean,
        default: false,
    },
    can_create_leads: {
        type: Boolean,
        default: false,
    },
});

const tasks = ref(props.tasks ?? []);
const quickFilters = computed(() => props.quickFilters ?? []);
const statusOptions = computed(() => props.statusOptions ?? []);
const users = computed(() => props.users ?? []);
const leadOptions = computed(() => props.leadOptions ?? []);
const contractorOptions = computed(() => props.contractorOptions ?? []);
const canBulkMutateTasks = computed(() => props.can_bulk_mutate_tasks === true);
const canDeleteTasks = computed(() => props.can_delete_tasks === true);
const canCreateLeads = computed(() => props.can_create_leads === true);

watch(() => page.props.tasks, (next) => {
    tasks.value = next ?? [];
});

const selectedTask = computed(() => page.props.selectedTask ?? null);
const isTaskDetailDismissed = ref(false);
const isFormOpen = ref(false);

const isTaskDetailModalOpen = computed(() => selectedTask.value !== null && !isTaskDetailDismissed.value && !isFormOpen.value);

watch(selectedTask, (next) => {
    if (next !== null) {
        isTaskDetailDismissed.value = false;
    }
});

const activeFilter = ref(persistedPageFilters?.activeFilter ?? 'Все');
const filterResponsibleId = ref(persistedPageFilters?.filterResponsibleId ?? null);
const pageFiltersStorageKey = computed(() => `tasks_page_filters_v1_${userId.value}`);
watch([activeFilter, filterResponsibleId], () => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        localStorage.setItem(pageFiltersStorageKey.value, JSON.stringify({
            activeFilter: activeFilter.value,
            filterResponsibleId: filterResponsibleId.value,
        }));
    } catch {
        /* ignore */
    }
});

const selectedTaskIds = ref([]);
const bulkAssignUserId = ref(null);
const bulkStatus = ref('');
const bulkDueAt = ref('');
const assignOneTask = ref(null);
const assignOneUserId = ref(null);

function onTaskSelectionChanged(ids) {
    selectedTaskIds.value = Array.isArray(ids) ? ids : [];
}

function bulkCloseSelected() {
    if (!selectedTaskIds.value.length) {
        return;
    }
    router.post(route('tasks.bulk'), {
        task_ids: selectedTaskIds.value,
        action: 'close',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedTaskIds.value = [];
        },
    });
}

function bulkDeleteSelected() {
    if (!selectedTaskIds.value.length || !canDeleteTasks.value) {
        return;
    }

    const count = selectedTaskIds.value.length;
    if (!window.confirm(`Удалить выбранные задачи (${count})? Это действие нельзя отменить.`)) {
        return;
    }

    router.post(route('tasks.bulk'), {
        task_ids: selectedTaskIds.value,
        action: 'delete',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedTaskIds.value = [];
            isTaskDetailDismissed.value = true;
        },
    });
}

function deleteTask(task) {
    if (!task?.id || !canDeleteTasks.value) {
        return;
    }

    const label = task.number ? `#${task.number}` : `#${task.id}`;
    if (!window.confirm(`Удалить задачу ${label}? Это действие нельзя отменить.`)) {
        return;
    }

    router.delete(route('tasks.destroy', task.id), {
        preserveScroll: true,
        onSuccess: () => {
            isTaskDetailDismissed.value = true;
        },
    });
}

function bulkAssignSelected() {
    if (!selectedTaskIds.value.length || !bulkAssignUserId.value) {
        return;
    }
    router.post(route('tasks.bulk'), {
        task_ids: selectedTaskIds.value,
        action: 'assign',
        responsible_id: bulkAssignUserId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedTaskIds.value = [];
            bulkAssignUserId.value = null;
        },
    });
}

function bulkSetStatus() {
    if (!selectedTaskIds.value.length || !bulkStatus.value) {
        return;
    }
    router.post(route('tasks.bulk'), {
        task_ids: selectedTaskIds.value,
        action: 'status',
        status: bulkStatus.value,
    }, {
        preserveScroll: true,
        only: ['tasks', 'quickFilters', 'selectedTask'],
        onSuccess: () => {
            selectedTaskIds.value = [];
            bulkStatus.value = '';
        },
    });
}

function bulkRescheduleDue() {
    if (!selectedTaskIds.value.length || !bulkDueAt.value) {
        return;
    }
    router.post(route('tasks.bulk'), {
        task_ids: selectedTaskIds.value,
        action: 'reschedule',
        due_at: bulkDueAt.value,
    }, {
        preserveScroll: true,
        only: ['tasks', 'quickFilters', 'selectedTask'],
        onSuccess: () => {
            selectedTaskIds.value = [];
            bulkDueAt.value = '';
        },
    });
}

function handleCellSave({ row, field, value }) {
    if (!row?.id || !field) {
        return;
    }

    if (field === 'status') {
        router.patch(route('tasks.status.update', row.id), { status: value }, {
            preserveScroll: true,
            only: ['tasks', 'quickFilters', 'selectedTask'],
        });

        return;
    }

    if (field === 'responsible_id') {
        if (canBulkMutateTasks.value) {
            router.post(route('tasks.bulk'), {
                task_ids: [row.id],
                action: 'assign',
                responsible_id: value,
            }, {
                preserveScroll: true,
                only: ['tasks', 'quickFilters', 'selectedTask'],
            });

            return;
        }

        router.patch(route('tasks.inline-update', row.id), { field, value }, {
            preserveScroll: true,
            only: ['tasks', 'quickFilters', 'selectedTask'],
        });

        return;
    }

    if (field === 'priority') {
        router.patch(route('tasks.inline-update', row.id), { field, value }, {
            preserveScroll: true,
            only: ['tasks', 'quickFilters', 'selectedTask'],
        });
    }
}

function onQuickStatus({ row, status }) {
    if (!row?.id || !status) {
        return;
    }
    router.patch(route('tasks.status.update', row.id), { status }, {
        preserveScroll: true,
        only: ['tasks', 'quickFilters', 'selectedTask'],
    });
}

function onQuickRescheduleDue(row) {
    if (!row?.id) {
        return;
    }
    const current = row.due_at ? String(row.due_at).slice(0, 16).replace('T', ' ') : '';
    const next = window.prompt('Новый срок (ГГГГ-ММ-ДД ЧЧ:ММ)', current);
    if (!next || !next.trim()) {
        return;
    }
    const normalized = next.trim().includes('T') ? next.trim() : next.trim().replace(' ', 'T');
    router.patch(route('tasks.due.update', row.id), { due_at: normalized }, {
        preserveScroll: true,
        only: ['tasks', 'quickFilters', 'selectedTask'],
    });
}

function onAssignRequest(row) {
    assignOneTask.value = row;
    assignOneUserId.value = row?.responsible_id ?? null;
}

function confirmAssignOne() {
    if (!assignOneTask.value?.id || !assignOneUserId.value) {
        return;
    }
    router.post(route('tasks.bulk'), {
        task_ids: [assignOneTask.value.id],
        action: 'assign',
        responsible_id: assignOneUserId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            assignOneTask.value = null;
            assignOneUserId.value = null;
        },
    });
}

const visibleTasks = computed(() => {
    let list = tasks.value ?? [];

    if (filterResponsibleId.value) {
        list = list.filter((task) => Number(task.responsible_id) === Number(filterResponsibleId.value));
    }

    if (activeFilter.value === 'Срочные') {
        return list.filter((task) => task.priority === 'critical');
    }
    if (activeFilter.value === 'В работе') {
        return list.filter((task) => task.status === 'in_progress');
    }
    if (activeFilter.value === 'На проверке') {
        return list.filter((task) => task.status === 'review');
    }
    if (activeFilter.value === 'Просроченные') {
        return list.filter((task) => isDueOverdue(task));
    }

    return list;
});

const editingTask = ref(null);
const form = useForm({
    title: '',
    description: '',
    status: 'new',
    priority: 'medium',
    due_at: '',
    sla_deadline_at: '',
    responsible_id: null,
    lead_id: null,
    contractor_id: null,
});

const completeMenuOpen = ref(false);
const completeMenuRoot = ref(null);
const isRescheduleModalOpen = ref(false);
const rescheduleDueAt = ref('');
const rescheduleProcessing = ref(false);

const checklistForm = useForm({ title: '' });
const commentForm = useForm({ body: '' });
const attachmentForm = useForm({ file: null });
const attachmentFile = ref(null);

function handleRowDblClick(row) {
    if (row?.id) {
        isFormOpen.value = false;
        isTaskDetailDismissed.value = false;
        router.get(route('tasks.show', row.id), {}, {
            preserveScroll: true,
            preserveState: true,
            only: taskModalOpenKeys,
        });
    }
}

function closeTaskDetailModal() {
    isTaskDetailDismissed.value = true;
    router.get(route('tasks.index'), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['selectedTask'],
    });
}

function resetFormDefaults() {
    const currentUserId = page.props.auth?.user?.id ?? null;
    form.title = '';
    form.description = '';
    form.status = 'new';
    form.priority = 'medium';
    form.due_at = '';
    form.responsible_id = currentUserId ?? users.value[0]?.id ?? null;
    form.lead_id = null;
    form.contractor_id = null;
    form.sla_deadline_at = '';
    form.clearErrors();
}

function openCreateModal() {
    editingTask.value = null;
    resetFormDefaults();
    if (selectedTask.value !== null) {
        isTaskDetailDismissed.value = true;
        router.get(route('tasks.index'), {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['selectedTask'],
            onFinish: () => {
                isFormOpen.value = true;
            },
        });

        return;
    }
    isFormOpen.value = true;
}

function openCreateLead(task = null) {
    if (!canCreateLeads.value) {
        return;
    }

    const params = task?.id ? { from_task: task.id } : {};

    router.visit(route('leads.create', params));
}

function openCreateLeadFromTask(task) {
    openCreateLead(task);
}

function openCreateLeadFromForm() {
    openCreateLead(editingTask.value ?? selectedTask.value ?? null);
}

function openEditModal(task) {
    editingTask.value = task;
    form.title = task.title;
    form.description = task.description ?? '';
    form.status = task.status;
    form.priority = task.priority ?? 'medium';
    form.due_at = task.due_at ? task.due_at.slice(0, 16) : '';
    form.sla_deadline_at = task.sla_deadline_at ? task.sla_deadline_at.slice(0, 16) : '';
    form.responsible_id = task.responsible_id;
    form.lead_id = task.lead_id;
    form.contractor_id = task.contractor_id ?? null;
    form.clearErrors();
    isFormOpen.value = true;
}

function openEditFromDetail() {
    if (selectedTask.value) {
        openEditModal(selectedTask.value);
    }
}

function closeFormModal() {
    isFormOpen.value = false;
    editingTask.value = null;
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeFormModal();
        },
    };
    if (editingTask.value) {
        form.patch(route('tasks.update', editingTask.value.id), options);

        return;
    }
    form.post(route('tasks.store'), options);
}

function markDone(task) {
    completeMenuOpen.value = false;
    router.patch(route('tasks.status.update', task.id), { status: 'done' }, {
        preserveScroll: true,
        only: taskListRefreshKeys,
    });
}

function markDoneAndCreateNew(task) {
    completeMenuOpen.value = false;
    router.post(route('tasks.complete-and-follow-up', task.id), {}, {
        preserveScroll: true,
        only: taskListRefreshKeys,
        onSuccess: () => {
            isTaskDetailDismissed.value = false;
        },
    });
}

function openRescheduleModal() {
    completeMenuOpen.value = false;
    if (!selectedTask.value) {
        return;
    }
    rescheduleDueAt.value = selectedTask.value.due_at ? selectedTask.value.due_at.slice(0, 16) : '';
    isRescheduleModalOpen.value = true;
}

function closeRescheduleModal() {
    isRescheduleModalOpen.value = false;
    rescheduleDueAt.value = '';
}

function submitReschedule() {
    if (!selectedTask.value || !rescheduleDueAt.value) {
        return;
    }
    rescheduleProcessing.value = true;
    router.patch(route('tasks.due.update', selectedTask.value.id), {
        due_at: rescheduleDueAt.value,
    }, {
        preserveScroll: true,
        only: taskListRefreshKeys,
        onFinish: () => {
            rescheduleProcessing.value = false;
            closeRescheduleModal();
        },
    });
}

function onDocumentClick(event) {
    if (!completeMenuOpen.value) {
        return;
    }
    const root = completeMenuRoot.value;
    if (root && !root.contains(event.target)) {
        completeMenuOpen.value = false;
    }
}

function addChecklistItem() {
    if (!selectedTask.value) {
        return;
    }
    checklistForm.post(route('tasks.checklist-items.store', selectedTask.value.id), {
        preserveScroll: true,
        only: taskListRefreshKeys,
        onSuccess: () => {
            checklistForm.reset();
        },
    });
}

function toggleChecklistItem(item) {
    if (!selectedTask.value) {
        return;
    }
    router.patch(route('tasks.checklist-items.toggle', [selectedTask.value.id, item.id]), {}, {
        preserveScroll: true,
        only: taskListRefreshKeys,
    });
}

function addComment() {
    if (!selectedTask.value) {
        return;
    }
    commentForm.post(route('tasks.comments.store', selectedTask.value.id), {
        preserveScroll: true,
        only: taskListRefreshKeys,
        onSuccess: () => {
            commentForm.reset();
        },
    });
}

async function onAttachmentSelected(event) {
    const files = event.target?.files;
    const picked = files && files[0] ? files[0] : null;
    if (picked) {
        await warnIfDocumentExceedsBudget(picked, page.props.document_upload_limits ?? {});
    }
    attachmentFile.value = picked;
    attachmentForm.file = attachmentFile.value;
}

function addAttachment() {
    if (!selectedTask.value || !attachmentFile.value) {
        return;
    }
    attachmentForm.post(route('tasks.attachments.store', selectedTask.value.id), {
        preserveScroll: true,
        only: taskListRefreshKeys,
        forceFormData: true,
        onSuccess: () => {
            attachmentForm.reset();
            attachmentFile.value = null;
        },
    });
}

function deleteAttachment(file) {
    if (!selectedTask.value) {
        return;
    }
    router.delete(route('tasks.attachments.destroy', [selectedTask.value.id, file.id]), {
        preserveScroll: true,
        only: taskListRefreshKeys,
    });
}

function priorityLabel(priority) {
    return { low: 'Низкий', medium: 'Средний', high: 'Высокий', critical: 'Критичный' }[priority] ?? priority ?? '—';
}

function formatDue(value) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
}


function isDueOverdue(task) {
    if (task?.is_due_overdue !== undefined) {
        return Boolean(task.is_due_overdue);
    }
    if (!task?.due_at || task.status === 'done') {
        return false;
    }
    const date = new Date(task.due_at);
    if (Number.isNaN(date.getTime())) {
        return false;
    }

    return Date.now() > date.getTime();
}

function isSlaBreached(task) {
    if (task?.status === 'done' || !task?.sla_deadline_at) {
        return false;
    }
    const date = new Date(task.sla_deadline_at);
    if (Number.isNaN(date.getTime())) {
        return false;
    }

    return Date.now() > date.getTime();
}

const filterQueryMap = {
    overdue: 'Просроченные',
};

function applyFilterFromQuery() {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    const filterKey = url.searchParams.get('filter');
    const mapped = filterQueryMap[filterKey ?? ''];

    if (mapped) {
        activeFilter.value = mapped;
        url.searchParams.delete('filter');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);

    if (typeof window === 'undefined') {
        return;
    }

    applyFilterFromQuery();

    let url = new URL(window.location.href);
    const taskParam = url.searchParams.get('task');
    if (taskParam) {
        const id = Number.parseInt(taskParam, 10);
        if (!Number.isNaN(id)) {
            isTaskDetailDismissed.value = false;
            router.get(route('tasks.show', id), {}, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: taskModalOpenKeys,
            });
        }
        url.searchParams.delete('task');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
    url = new URL(window.location.href);
    if (url.searchParams.get('create') === '1') {
        openCreateModal();
        url.searchParams.delete('create');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>
