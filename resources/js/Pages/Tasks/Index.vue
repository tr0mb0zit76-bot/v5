<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <section class="shrink-0 border border-zinc-200 bg-white p-6 shadow-sm transition dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Модуль задач</div>
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Контроль задач менеджеров</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Единое рабочее место: задачи, чеклисты, комментарии, вложения и история действий.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <button
                        v-if="selectedTaskIds.length > 0"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-rose-700 px-4 py-2 text-rose-800 transition hover:bg-rose-50 dark:border-rose-400 dark:text-rose-200 dark:hover:bg-rose-950/40"
                        @click="bulkCloseSelected"
                    >
                        Закрыть выбранные ({{ selectedTaskIds.length }})
                    </button>
                    <template v-if="canBulkMutateTasks && selectedTaskIds.length > 0">
                        <select
                            v-model="bulkAssignUserId"
                            class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                        >
                            <option :value="null" disabled>Назначить на…</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-zinc-900 px-4 py-2 text-zinc-900 dark:border-zinc-50 dark:text-zinc-50"
                            :disabled="!bulkAssignUserId"
                            @click="bulkAssignSelected"
                        >
                            Назначить выбранные
                        </button>
                    </template>
                    <Link
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-white transition hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :href="route('kanban.index')"
                    >
                        Перейти в Канбан
                    </Link>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <button
                    v-for="filter in quickFilters"
                    :key="filter.label"
                    type="button"
                    class="rounded-xl border px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] transition"
                    :class="activeFilter === filter.label
                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                        : 'border-zinc-200 text-zinc-500 hover:border-zinc-900 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-50 dark:hover:text-zinc-50'"
                    @click="activeFilter = filter.label"
                >
                    {{ filter.label }} · {{ filter.count }}
                </button>
            </div>
        </section>

        <div class="min-h-0 flex-1 overflow-hidden">
            <TasksGrid
                :rows="visibleTasks"
                :user-id="userId"
                @create="openCreateModal"
                @row-dblclick="handleRowDblClick"
                @selection-changed="onTaskSelectionChanged"
            />
        </div>

        <Modal :show="isTaskDetailModalOpen" max-width="5xl" @close="closeTaskDetailModal">
            <section class="flex max-h-[calc(100dvh-3rem)] flex-col overflow-hidden bg-white dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Детали задачи</div>
                        <h3 class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ selectedTask?.title }}</h3>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">#{{ selectedTask?.number }}</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-zinc-900 px-3 py-2 text-xs font-semibold text-zinc-900 dark:border-zinc-50 dark:text-zinc-50"
                            @click="openEditFromDetail"
                        >
                            Редактировать
                        </button>
                        <button
                            v-if="selectedTask && selectedTask.status !== 'done'"
                            type="button"
                            class="rounded-xl border border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700 dark:border-emerald-400 dark:text-emerald-200"
                            @click="markDone(selectedTask)"
                        >
                            Завершить
                        </button>
                    </div>
                </div>

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
            <div class="overflow-y-auto bg-white p-6 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">{{ editingTask ? 'Редактирование задачи' : 'Новая задача' }}</div>
                        <h3 class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ editingTask ? `#${editingTask.number}` : 'Создание' }}</h3>
                    </div>
                    <button type="button" class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-900" @click="closeFormModal">Закрыть</button>
                </div>

                <form class="mt-6 space-y-4" @submit.prevent="submitForm">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Название</label>
                        <input v-model="form.title" type="text" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50" required />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Описание</label>
                        <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Статус</label>
                            <select v-model="form.status" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50">
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Приоритет</label>
                            <select v-model="form.priority" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50">
                                <option value="low">Низкий</option>
                                <option value="medium">Средний</option>
                                <option value="high">Высокий</option>
                                <option value="critical">Критичный</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Срок</label>
                            <input v-model="form.due_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">SLA (если пусто — как срок)</label>
                            <input v-model="form.sla_deadline_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Ответственный</label>
                            <select v-model="form.responsible_id" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50" required>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Связанный лид</label>
                        <select v-model="form.lead_id" class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50">
                            <option :value="null">Без привязки</option>
                            <option v-for="lead in leadOptions" :key="lead.id" :value="lead.id">{{ lead.number }} — {{ lead.title }}</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-zinc-200 px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900" @click="closeFormModal">Отмена</button>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200" :disabled="form.processing">Сохранить</button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import Modal from '@/Components/Modal.vue';
import TasksGrid from '@/Components/Tasks/TasksGrid.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'tasks' }, () => page),
});

const modalPropKeys = ['selectedTask', 'tasks', 'quickFilters'];

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');

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
    can_bulk_mutate_tasks: {
        type: Boolean,
        default: false,
    },
});

const tasks = ref(props.tasks ?? []);
const quickFilters = computed(() => props.quickFilters ?? []);
const statusOptions = computed(() => props.statusOptions ?? []);
const users = computed(() => props.users ?? []);
const leadOptions = computed(() => props.leadOptions ?? []);
const canBulkMutateTasks = computed(() => props.can_bulk_mutate_tasks === true);

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

const activeFilter = ref('Все');
const selectedTaskIds = ref([]);
const bulkAssignUserId = ref(null);

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

const visibleTasks = computed(() => {
    const list = tasks.value ?? [];
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
        return list.filter((task) => isOverdue(task));
    }

    return list;
});

const editingTask = ref(null);
const form = useForm({ title: '', description: '', status: 'new', priority: 'medium', due_at: '', sla_deadline_at: '', responsible_id: null, lead_id: null });

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
            only: modalPropKeys,
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
    router.patch(route('tasks.status.update', task.id), { status: 'done' }, {
        preserveScroll: true,
        only: modalPropKeys,
    });
}

function addChecklistItem() {
    if (!selectedTask.value) {
        return;
    }
    checklistForm.post(route('tasks.checklist-items.store', selectedTask.value.id), {
        preserveScroll: true,
        only: modalPropKeys,
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
        only: modalPropKeys,
    });
}

function addComment() {
    if (!selectedTask.value) {
        return;
    }
    commentForm.post(route('tasks.comments.store', selectedTask.value.id), {
        preserveScroll: true,
        only: modalPropKeys,
        onSuccess: () => {
            commentForm.reset();
        },
    });
}

function onAttachmentSelected(event) {
    const files = event.target?.files;
    attachmentFile.value = files && files[0] ? files[0] : null;
    attachmentForm.file = attachmentFile.value;
}

function addAttachment() {
    if (!selectedTask.value || !attachmentFile.value) {
        return;
    }
    attachmentForm.post(route('tasks.attachments.store', selectedTask.value.id), {
        preserveScroll: true,
        only: modalPropKeys,
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
        only: modalPropKeys,
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

function isOverdue(task) {
    if (!task?.due_at || task.status === 'done') {
        return false;
    }
    const date = new Date(task.due_at);
    if (Number.isNaN(date.getTime())) {
        return false;
    }

    return Date.now() > date.getTime();
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }
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
                only: modalPropKeys,
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
</script>
