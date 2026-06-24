<script setup>
import { crmBtnSecondary, crmFieldFluid } from '@/support/crmUi.js';

defineProps({
    selectedLeadId: {
        type: [Number, String, null],
        default: null,
    },
    canUseLeadTasks: {
        type: Boolean,
        default: false,
    },
    canAssignResponsible: {
        type: Boolean,
        default: false,
    },
    responsibleUsers: {
        type: Array,
        default: () => [],
    },
    openTasks: {
        type: Array,
        default: () => [],
    },
    nextStepTitle: {
        type: String,
        default: '',
    },
    nextStepDueAt: {
        type: String,
        default: '',
    },
    nextStepResponsibleId: {
        type: [Number, String, null],
        default: null,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    formatDateTime: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'update:nextStepTitle',
    'update:nextStepDueAt',
    'update:nextStepResponsibleId',
    'create',
    'open-task',
]);
</script>

<template>
    <section v-if="selectedLeadId" id="lead-next-step-panel" class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
        <div class="flex items-start justify-between gap-4">
            <h3 class="text-base font-semibold">Следующий шаг</h3>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Открытых задач: {{ openTasks.length }}</div>
        </div>

        <div v-if="canUseLeadTasks" class="grid gap-3 xl:grid-cols-[minmax(0,1.4fr),220px,220px,160px]">
            <input
                :value="nextStepTitle"
                type="text"
                :class="crmFieldFluid"
                placeholder="Например: перезвонить клиенту после расчёта"
                @input="emit('update:nextStepTitle', $event.target.value)"
            />
            <input
                :value="nextStepDueAt"
                type="datetime-local"
                :class="crmFieldFluid"
                @input="emit('update:nextStepDueAt', $event.target.value)"
            />
            <select
                :value="nextStepResponsibleId"
                :class="crmFieldFluid"
                :disabled="!canAssignResponsible"
                @change="emit('update:nextStepResponsibleId', Number($event.target.value))"
            >
                <option v-for="user in responsibleUsers" :key="`next-step-${user.id}`" :value="user.id">
                    {{ user.name }}
                </option>
            </select>
            <button
                type="button"
                :class="`${crmBtnSecondary} justify-center`"
                :disabled="processing || !nextStepTitle"
                @click="emit('create')"
            >
                Создать шаг
            </button>
        </div>

        <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">
            Модуль задач недоступен для вашей роли.
        </div>

        <div class="space-y-2">
            <div
                v-for="task in openTasks"
                :key="task.id"
                class="flex flex-wrap items-center justify-between gap-3 border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-800"
            >
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ task.title }}</div>
                    <div class="mt-1 text-xs uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">
                        {{ task.status_label }} · {{ task.responsible_name || '—' }} · {{ formatDateTime(task.due_at) }}
                    </div>
                </div>
                <button type="button" :class="crmBtnSecondary" @click="emit('open-task', task.id)">Открыть задачи</button>
            </div>
            <div v-if="openTasks.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                Открытых следующих шагов пока нет.
            </div>
        </div>
    </section>

    <section
        v-else
        class="border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
    >
        Сохраните лид, чтобы назначить следующий шаг.
    </section>
</template>
