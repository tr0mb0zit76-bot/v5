<template>
    <div class="space-y-6">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm transition dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Модуль задач</div>
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Контроль задач менеджеров</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Сразу видно, какие задачи горят, кто отвечает и к каким лидам относятся.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Link
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-white transition hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        :href="route('kanban.index')"
                    >
                        Перейти в Канбан
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-100 dark:border-zinc-50 dark:text-zinc-50 dark:hover:bg-zinc-800"
                        @click="openCreateModal"
                    >
                        Создать задачу
                    </button>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <button
                    v-for="filter in quickFilters"
                    :key="filter.label"
                    type="button"
                    class="rounded-xl border px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] transition"
                    :class="
                        activeFilter === filter.label
                            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                            : 'border-zinc-200 text-zinc-500 hover:border-zinc-900 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-50 dark:hover:text-zinc-50'
                    "
                    @click="activeFilter = filter.label"
                >
                    {{ filter.label }} · {{ filter.count }}
                </button>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <article
                v-for="task in visibleTasks"
                :key="task.id"
                class="border border-zinc-200 bg-white p-5 shadow-sm transition dark:border-zinc-800 dark:bg-zinc-950"
            >
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                    <span class="text-zinc-900 dark:text-zinc-50">#{{ task.number }}</span>
                    <span :class="statusClasses(task.status)">{{ task.status_label }}</span>
                </div>

                <h2 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ task.title }}</h2>

                <div class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                    Ответственный:
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ task.responsible_name || '—' }}</span>
                    · Срок:
                    <span :class="dueClasses(task)">{{ formatDue(task.due_at) }}</span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                    <span class="rounded-full border border-zinc-200 px-2 py-1 dark:border-zinc-700">
                        Приоритет: {{ priorityLabel(task.priority) }}
                    </span>
                    <span v-if="task.lead_id" class="rounded-full border border-zinc-200 px-2 py-1 dark:border-zinc-700">
                        Лид: {{ task.lead_number || '—' }}
                    </span>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full bg-emerald-500" :style="{ width: `${taskProgress(task)}%` }"></div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-900 px-3 py-2 text-xs font-semibold text-zinc-900 transition hover:bg-zinc-100 dark:border-zinc-50 dark:text-zinc-50 dark:hover:bg-zinc-800"
                        @click="openEditModal(task)"
                    >
                        Редактировать
                    </button>
                    <button
                        v-if="task.status !== 'done'"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-400 dark:text-emerald-200 dark:hover:bg-emerald-950/40"
                        @click="markDone(task)"
                    >
                        Завершить
                    </button>
                </div>
            </article>

            <div
                v-if="visibleTasks.length === 0"
                class="rounded-xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400 md:col-span-2"
            >
                Задач пока нет — создайте первую или измените фильтр.
            </div>
        </section>

        <div
            v-if="isFormOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-xl rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">
                            {{ editingTask ? 'Редактирование задачи' : 'Новая задача' }}
                        </div>
                        <h3 class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ editingTask ? `#${editingTask.number}` : 'Создание' }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-900"
                        @click="closeModal"
                    >
                        Закрыть
                    </button>
                </div>

                <form class="mt-6 space-y-4" @submit.prevent="submitForm">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Название</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                            required
                        />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Описание</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Статус</label>
                            <select
                                v-model="form.status"
                                class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                            >
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Приоритет</label>
                            <select
                                v-model="form.priority"
                                class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                            >
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
                            <input
                                v-model="form.due_at"
                                type="datetime-local"
                                class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Ответственный</label>
                            <select
                                v-model="form.responsible_id"
                                class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                                required
                            >
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Связанный лид</label>
                        <select
                            v-model="form.lead_id"
                            class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                        >
                            <option :value="null">Без привязки</option>
                            <option v-for="lead in leadOptions" :key="lead.id" :value="lead.id">
                                {{ lead.number }} — {{ lead.title }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-zinc-200 px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                            @click="closeModal"
                        >
                            Отмена
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            :disabled="form.processing"
                        >
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    quickFilters: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
    leadOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const tasks = ref(props.tasks ?? []);
const quickFilters = computed(() => props.quickFilters ?? []);
const statusOptions = computed(() => props.statusOptions ?? []);
const users = computed(() => props.users ?? []);
const leadOptions = computed(() => props.leadOptions ?? []);

watch(
    () => page.props.tasks,
    (next) => {
        tasks.value = next ?? [];
    },
);

const activeFilter = ref('Все');

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

const isFormOpen = ref(false);
const editingTask = ref(null);

const form = useForm({
    title: '',
    description: '',
    status: 'new',
    priority: 'medium',
    due_at: '',
    responsible_id: null,
    lead_id: null,
});

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
    isFormOpen.value = true;
}

function openEditModal(task) {
    editingTask.value = task;
    form.title = task.title;
    form.description = task.description ?? '';
    form.status = task.status;
    form.priority = task.priority ?? 'medium';
    form.due_at = task.due_at ? task.due_at.slice(0, 16) : '';
    form.responsible_id = task.responsible_id;
    form.lead_id = task.lead_id;
    form.clearErrors();
    isFormOpen.value = true;
}

function closeModal() {
    isFormOpen.value = false;
    editingTask.value = null;
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingTask.value) {
        form.patch(route('tasks.update', editingTask.value.id), options);

        return;
    }

    form.post(route('tasks.store'), options);
}

function markDone(task) {
    router.patch(route('tasks.status.update', task.id), { status: 'done' }, { preserveScroll: true });
}

function statusClasses(status) {
    const map = {
        in_progress: 'text-emerald-600',
        review: 'text-amber-500',
        new: 'text-sky-600',
        done: 'text-zinc-400',
        on_hold: 'text-purple-500',
    };

    return map[status] ?? 'text-zinc-500';
}

function priorityLabel(priority) {
    const map = {
        low: 'Низкий',
        medium: 'Средний',
        high: 'Высокий',
        critical: 'Критичный',
    };

    return map[priority] ?? priority ?? '—';
}

function formatDue(value) {
    if (!value) {
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

function dueClasses(task) {
    return isOverdue(task) ? 'font-semibold text-rose-600 dark:text-rose-300' : 'font-semibold text-zinc-900 dark:text-zinc-100';
}

function taskProgress(task) {
    if (task.status === 'done') {
        return 100;
    }

    if (task.status === 'review') {
        return 70;
    }

    if (task.status === 'in_progress') {
        return 50;
    }

    if (task.status === 'on_hold') {
        return 30;
    }

    return 20;
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);

    if (params.get('create') === '1') {
        openCreateModal();
        const url = new URL(window.location.href);
        url.searchParams.delete('create');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
});

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'tasks' }, () => page),
});
</script>
