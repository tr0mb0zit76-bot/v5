<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold">Бизнес-процессы</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Справочник воронок для лидов: этапы, нормативные сроки и финальные исходы.
            </p>
        </div>

        <div class="grid min-h-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
            <aside class="space-y-3 border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <form class="space-y-2 border border-dashed border-zinc-300 p-3 dark:border-zinc-700" @submit.prevent="submitNewProcess">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Новый процесс</div>
                    <input v-model="newProcessForm.name" type="text" placeholder="Название" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50" required />
                    <textarea v-model="newProcessForm.description" rows="2" placeholder="Описание" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50" />
                    <button type="submit" class="w-full border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" :disabled="newProcessForm.processing">
                        Добавить
                    </button>
                </form>

                <button
                    v-for="process in processes"
                    :key="process.id"
                    type="button"
                    class="flex w-full items-start justify-between gap-3 border px-3 py-3 text-left transition-colors"
                    :class="selectedProcessId === process.id
                        ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-500 dark:bg-zinc-800'
                        : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/70'"
                    @click="selectProcess(process.id)"
                >
                    <div class="space-y-1">
                        <div class="text-sm font-medium">{{ process.name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ process.stages.length }} этапов</div>
                        <div v-if="!process.is_active" class="text-xs text-amber-600">Неактивен</div>
                    </div>
                </button>
            </aside>

            <section v-if="selectedProcess" class="flex min-h-0 flex-col gap-4 border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveProcess">
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Название</label>
                        <input v-model="processForm.name" type="text" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50" required />
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Описание</label>
                        <textarea v-model="processForm.description" rows="2" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Порядок</label>
                        <input v-model.number="processForm.sort_order" type="number" min="0" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50" />
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input v-model="processForm.is_active" type="checkbox" class="rounded border-zinc-300" />
                            Активен
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <button type="submit" class="border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" :disabled="processForm.processing">
                            Сохранить процесс
                        </button>
                        <button type="button" class="border border-rose-200 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300" @click="deleteProcess">
                            Удалить процесс
                        </button>
                    </div>
                </form>

                <div class="space-y-3">
                    <h2 class="text-lg font-semibold">Этапы</h2>

                    <form class="space-y-3 rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-700" @submit.prevent="submitStage">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-sm font-medium">{{ editingStageId ? 'Редактирование этапа' : 'Новый этап' }}</div>
                            <button v-if="editingStageId" type="button" class="text-xs text-zinc-500 hover:underline" @click="resetStageForm">
                                Отмена
                            </button>
                        </div>
                        <div class="grid gap-2 md:grid-cols-6">
                            <input v-model="stageForm.name" type="text" placeholder="Название этапа" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm md:col-span-2 dark:border-zinc-700 dark:bg-zinc-950" required />
                            <input v-model.number="stageForm.duration_days" type="number" min="0" max="365" placeholder="Норматив, дней" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" title="Норматив SLA этапа" />
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="stageForm.is_terminal" type="checkbox" class="rounded border-zinc-300" />
                                Финал
                            </label>
                            <select v-model="stageForm.terminal_outcome" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" :disabled="!stageForm.is_terminal">
                                <option :value="null">—</option>
                                <option value="won">Выигран</option>
                                <option value="lost">Проигран</option>
                                <option value="neutral">Нейтрально</option>
                            </select>
                            <button type="submit" class="border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700" :disabled="stageForm.processing">
                                {{ editingStageId ? 'Сохранить' : 'Добавить' }}
                            </button>
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="stageForm.auto_create_task" type="checkbox" class="rounded border-zinc-300" />
                                Создавать задачу при входе на этап
                            </label>
                            <select v-model="stageForm.task_priority" class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" :disabled="!stageForm.auto_create_task">
                                <option value="low">Низкий</option>
                                <option value="medium">Средний</option>
                                <option value="high">Высокий</option>
                                <option value="critical">Срочный</option>
                            </select>
                            <input
                                v-model="stageForm.task_title_template"
                                type="text"
                                placeholder="Шаблон задачи: {stage_name} — {lead_number}"
                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm md:col-span-2 dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!stageForm.auto_create_task"
                            />
                            <input
                                v-model.number="stageForm.task_due_days_offset"
                                type="number"
                                min="0"
                                max="365"
                                placeholder="Срок задачи, дней от входа"
                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!stageForm.auto_create_task"
                            />
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Плейсхолдеры: {stage_name}, {process_name}, {lead_number}, {lead_title}
                        </p>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500 dark:border-zinc-700">
                                <tr>
                                    <th class="px-2 py-2">№</th>
                                    <th class="px-2 py-2">Этап</th>
                                    <th class="px-2 py-2">Дней</th>
                                    <th class="px-2 py-2">Финал</th>
                                    <th class="px-2 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="stage in selectedProcess.stages" :key="stage.id" class="border-b border-zinc-100 dark:border-zinc-800">
                                    <td class="px-2 py-2 text-zinc-500">{{ stage.sequence }}</td>
                                    <td class="px-2 py-2 font-medium">{{ stage.name }}</td>
                                    <td class="px-2 py-2">{{ stage.duration_days || '—' }}</td>
                                    <td class="px-2 py-2">
                                        <span v-if="stage.is_terminal">{{ terminalLabels[stage.terminal_outcome] ?? 'финал' }}</span>
                                        <span v-else class="text-zinc-400">—</span>
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <button type="button" class="mr-2 text-zinc-600 hover:underline" @click="editStage(stage)">Изменить</button>
                                        <button type="button" class="text-rose-600 hover:underline" @click="deleteStage(stage.id)">Удалить</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section v-else class="flex items-center justify-center border border-dashed border-zinc-300 p-8 text-sm text-zinc-500 dark:border-zinc-700">
                Выберите процесс слева или создайте новый.
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'administration', activeLeafKey: 'business-processes' }, () => page),
});

const props = defineProps({
    processes: {
        type: Array,
        default: () => [],
    },
});

const terminalLabels = {
    won: 'Выигран',
    lost: 'Проигран',
    neutral: 'Нейтрально',
};

const selectedProcessId = ref(props.processes[0]?.id ?? null);

const selectedProcess = computed(() => props.processes.find((process) => process.id === selectedProcessId.value) ?? null);

const newProcessForm = useForm({
    name: '',
    description: '',
    is_active: true,
    sort_order: 0,
});

const processForm = useForm({
    name: '',
    description: '',
    is_active: true,
    sort_order: 0,
});

const editingStageId = ref(null);

const stageForm = useForm({
    name: '',
    description: '',
    sequence: null,
    duration_days: 0,
    is_terminal: false,
    terminal_outcome: null,
    auto_create_task: false,
    task_title_template: '',
    task_description_template: '',
    task_due_days_offset: 0,
    task_priority: 'medium',
});

watch(selectedProcess, (process) => {
    if (!process) {
        return;
    }

    processForm.name = process.name;
    processForm.description = process.description ?? '';
    processForm.is_active = process.is_active;
    processForm.sort_order = process.sort_order ?? 0;
}, { immediate: true });

function selectProcess(id) {
    selectedProcessId.value = id;
}

function submitNewProcess() {
    newProcessForm.post(route('settings.business-processes.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newProcessForm.reset();
            newProcessForm.is_active = true;
        },
    });
}

function saveProcess() {
    if (!selectedProcess.value) {
        return;
    }

    processForm.patch(route('settings.business-processes.update', selectedProcess.value.id), {
        preserveScroll: true,
    });
}

function deleteProcess() {
    if (!selectedProcess.value || !window.confirm('Удалить бизнес-процесс?')) {
        return;
    }

    router.delete(route('settings.business-processes.destroy', selectedProcess.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedProcessId.value = props.processes[0]?.id ?? null;
        },
    });
}

function resetStageForm() {
    editingStageId.value = null;
    stageForm.reset();
    stageForm.duration_days = 0;
    stageForm.is_terminal = false;
    stageForm.terminal_outcome = null;
    stageForm.auto_create_task = false;
    stageForm.task_due_days_offset = 0;
    stageForm.task_priority = 'medium';
}

function editStage(stage) {
    editingStageId.value = stage.id;
    stageForm.name = stage.name;
    stageForm.description = stage.description ?? '';
    stageForm.sequence = stage.sequence;
    stageForm.duration_days = stage.duration_days ?? 0;
    stageForm.is_terminal = Boolean(stage.is_terminal);
    stageForm.terminal_outcome = stage.terminal_outcome;
    stageForm.auto_create_task = Boolean(stage.auto_create_task);
    stageForm.task_title_template = stage.task_title_template ?? '';
    stageForm.task_description_template = stage.task_description_template ?? '';
    stageForm.task_due_days_offset = stage.task_due_days_offset ?? 0;
    stageForm.task_priority = stage.task_priority ?? 'medium';
}

function submitStage() {
    if (!selectedProcess.value) {
        return;
    }

    const onSuccess = () => resetStageForm();

    if (editingStageId.value) {
        stageForm.patch(route('settings.business-processes.stages.update', [selectedProcess.value.id, editingStageId.value]), {
            preserveScroll: true,
            onSuccess,
        });

        return;
    }

    stageForm.post(route('settings.business-processes.stages.store', selectedProcess.value.id), {
        preserveScroll: true,
        onSuccess,
    });
}

function deleteStage(stageId) {
    if (!selectedProcess.value || !window.confirm('Удалить этап?')) {
        return;
    }

    router.delete(route('settings.business-processes.stages.destroy', [selectedProcess.value.id, stageId]), {
        preserveScroll: true,
    });
}
</script>
