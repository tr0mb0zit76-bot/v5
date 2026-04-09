<template>
    <div class="space-y-6">
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

        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Визуальный Канбан</div>
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Задачи по статусам</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Перетаскивайте карточки между статусами; те же данные отображаются в разделе «Задачи».
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Link
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-white transition hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :href="route('tasks.index')"
                    >
                        Перейти в задачи
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-50 dark:text-zinc-50 dark:hover:bg-zinc-800"
                        :disabled="featureUnavailable || !canMutateTasks"
                        @click="createTask"
                    >
                        Создать задачу
                    </button>
                </div>
            </div>
        </section>

        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Рабочий Канбан</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Колонки можно растягивать, горизонтальный скрол остаётся</div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <div class="flex gap-4 border-t border-zinc-200 pt-4 pb-3" style="min-height: 70vh;">
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
        </section>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const page = usePage();
const featureUnavailable = computed(() => Boolean(page.props.featureUnavailable));
const canMutateTasks = computed(() => page.props.canMutateTasks !== false);
const statusOptions = computed(() => page.props.statusOptions ?? []);
const tasks = ref(page.props.tasks ?? []);
const draggedTaskId = ref(null);
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
    if (featureUnavailable.value || !canMutateTasks.value || draggedTaskId.value === null) {
        return;
    }

    const task = tasks.value.find((item) => item.id === draggedTaskId.value);

    if (!task || task.status === status) {
        handleDragEnd();

        return;
    }

    router.patch(
        route('tasks.status.update', task.id),
        { status },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                task.status = status;
                if (status === 'done') {
                    task.status_label = 'Завершена';
                }
            },
            onFinish: handleDragEnd,
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
    layout: (h, page) => h(CrmLayout, { activeKey: 'kanban' }, () => page),
});
</script>
