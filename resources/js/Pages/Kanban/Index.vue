<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <div
            v-if="featureUnavailable"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Модуль Канбан недоступен: таблица задач не создана. Выполните миграции.
        </div>

        <div
            v-if="!featureUnavailable && !canMutateTasks"
            class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
        >
            Режим просмотра: у вас нет права менять статусы задач (нужна область «Задачи»).
        </div>

        <CrmPageHeader
            lead="Задачи по статусам. Колонки можно растягивать; те же записи что в списке «Задачи»."
            title="Канбан"
        >
            <template #actions>
                <Link
                    :class="crmBtnSecondaryOutline"
                    :href="route('tasks.index')"
                >
                    Задачи
                </Link>
                <button
                    type="button"
                    :class="crmBtnCreate"
                    :disabled="featureUnavailable || !canMutateTasks"
                    @click="createTask"
                >
                    <Plus class="h-4 w-4" />
                    Создать задачу
                </button>
            </template>
        </CrmPageHeader>

        <div :class="crmGridPanel">
            <div class="border-b border-zinc-200 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                Колонки можно растягивать справа, горизонтальный скролл сохраняется.
            </div>
            <div class="min-h-0 flex-1 overflow-x-auto overflow-y-hidden p-4">
                <div class="flex gap-4 pb-1" style="min-height: min(70vh, calc(100dvh - 18rem));">
                    <div
                        v-for="column in columns"
                        :key="column.status"
                        class="relative flex flex-shrink-0 flex-col rounded-xl border border-zinc-200 bg-zinc-50 p-4 shadow-sm transition dark:border-zinc-800 dark:bg-zinc-900"
                        :class="{
                            'border-zinc-900 bg-white dark:border-zinc-50 dark:bg-zinc-950': dragOverStatus === column.status,
                        }"
                        :style="{ width: `${getColumnWidth(column.status)}px` }"
                        @dragover.prevent="handleColumnDragOver(column.status)"
                        @dragleave="handleColumnDragLeave(column.status)"
                        @drop.prevent="handleDrop(column.status)"
                    >
                        <div class="flex items-center justify-between text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">
                            <span>{{ column.title }}</span>
                            <span class="text-xs text-zinc-400">{{ column.tasks.length }} шт.</span>
                        </div>

                        <div class="mt-4 flex-1 overflow-y-auto pr-1" style="max-height: calc(70vh - 90px);">
                            <div class="space-y-3">
                                <article
                                    v-for="task in column.tasks"
                                    :key="task.id"
                                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 shadow-sm transition-colors hover:border-zinc-900 hover:bg-white dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-50 dark:hover:bg-zinc-950"
                                    :class="{ 'cursor-grab': canMutateTasks && !featureUnavailable, 'cursor-default opacity-80': !canMutateTasks || featureUnavailable }"
                                    :draggable="canMutateTasks && !featureUnavailable"
                                    @dragstart="(event) => handleDragStart(event, task.id)"
                                    @dragend="handleDragEnd"
                                >
                                    <div class="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                                        <span class="text-zinc-900 dark:text-zinc-50">{{ task.number }}</span>
                                        <span
                                            v-if="task.priority === 'critical'"
                                            class="text-xs font-semibold text-rose-600 dark:text-rose-300"
                                        >
                                            срочно
                                        </span>
                                    </div>
                                    <h2 class="mt-2 text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ task.title }}</h2>
                                    <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ task.responsible_name || '—' }}
                                        <span v-if="task.lead_number"> · {{ task.lead_number }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>Срок: {{ formatDue(task.due_at) }}</span>
                                        <span class="text-sky-600 dark:text-sky-300">{{ column.title }}</span>
                                    </div>
                                    <div class="mt-3 flex justify-end">
                                        <Link
                                            class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 underline decoration-zinc-400 underline-offset-2 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                                            :href="route('tasks.index', { task: task.id })"
                                        >
                                            Открыть в задачах
                                        </Link>
                                    </div>
                                </article>

                                <div
                                    v-if="column.tasks.length === 0"
                                    class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                                >
                                    Нет задач
                                </div>
                            </div>
                        </div>

                        <div
                            class="absolute inset-y-0 -right-1 w-2 cursor-ew-resize rounded-full bg-zinc-200/60 transition hover:bg-zinc-400 dark:bg-zinc-700/60 dark:hover:bg-zinc-500/60"
                            @pointerdown="(event) => startResizing(column.status, event)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import { crmBtnCreate, crmBtnSecondaryOutline, crmGridPanel } from '@/support/crmUi.js';

const page = usePage();
const featureUnavailable = computed(() => Boolean(page.props.featureUnavailable));
const canMutateTasks = computed(() => page.props.canMutateTasks !== false);
const statusOptions = computed(() => page.props.statusOptions ?? []);
const tasks = ref(page.props.tasks ?? []);
const draggedTaskId = ref(null);
const statusPatching = ref(false);
const dragOverStatus = ref(null);
const columnWidths = ref({});
const resizingStatus = ref(null);
const resizeStartX = ref(0);
const resizeStartWidth = ref(0);
const DEFAULT_WIDTH = 340;
const MIN_WIDTH = 260;
const MAX_WIDTH = 520;
const STORAGE_KEY = 'kanban-column-widths';

watch(
    () => page.props.tasks,
    (next) => {
        tasks.value = next ?? [];
    },
);

const columns = computed(() =>
    statusOptions.value.map((option) => ({
        status: option.value,
        title: option.label,
        tasks: tasks.value.filter((task) => task.status === option.value),
    })),
);

function getColumnWidth(status) {
    return columnWidths.value[status] ?? DEFAULT_WIDTH;
}

function startResizing(status, event) {
    if (featureUnavailable.value) {
        event.preventDefault();

        return;
    }

    resizingStatus.value = status;
    resizeStartX.value = event.clientX;
    resizeStartWidth.value = getColumnWidth(status);
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', stopResizing);
    event.preventDefault();
}

function handlePointerMove(event) {
    if (!resizingStatus.value) {
        return;
    }

    const delta = event.clientX - resizeStartX.value;
    const tentative = resizeStartWidth.value + delta;
    const width = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, tentative));
    columnWidths.value = {
        ...columnWidths.value,
        [resizingStatus.value]: width,
    };
}

function stopResizing() {
    if (resizingStatus.value) {
        saveColumnWidths();
    }

    resizingStatus.value = null;
    window.removeEventListener('pointermove', handlePointerMove);
    window.removeEventListener('pointerup', stopResizing);
}

function loadColumnWidths() {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.localStorage) {
        try {
            const stored = window.localStorage.getItem(STORAGE_KEY);
            if (stored) {
                columnWidths.value = JSON.parse(stored);
            }
        } catch {
            columnWidths.value = {};
        }
    }
}

function saveColumnWidths() {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.localStorage) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(columnWidths.value));
        } catch {
            // noop
        }
    }
}

onMounted(loadColumnWidths);
onBeforeUnmount(() => {
    stopResizing();
});

function handleDragStart(event, taskId) {
    if (featureUnavailable.value || !canMutateTasks.value) {
        event.preventDefault();

        return;
    }

    draggedTaskId.value = taskId;
    event.dataTransfer?.setData('text/plain', String(taskId));
}

function handleDragEnd() {
    dragOverStatus.value = null;
    draggedTaskId.value = null;
}

function handleColumnDragOver(status) {
    if (featureUnavailable.value || !canMutateTasks.value || draggedTaskId.value === null) {
        return;
    }

    dragOverStatus.value = status;
}

function handleColumnDragLeave(status) {
    if (featureUnavailable.value) {
        return;
    }

    if (dragOverStatus.value === status) {
        dragOverStatus.value = null;
    }
}

function handleDrop(status) {
    if (featureUnavailable.value || !canMutateTasks.value || draggedTaskId.value === null || statusPatching.value) {
        return;
    }

    const task = tasks.value.find((item) => item.id === draggedTaskId.value);

    if (!task || task.status === status) {
        handleDragEnd();

        return;
    }

    statusPatching.value = true;
    router.patch(
        route('tasks.status.update', task.id),
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                statusPatching.value = false;
                handleDragEnd();
            },
        },
    );
}

function formatDue(value) {
    if (value == null || value === '') {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function createTask() {
    router.get(route('tasks.index'), { create: 1 });
}

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'kanban' }, () => page),
});
</script>
