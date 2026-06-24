<template>
    <section class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 dark:border-zinc-800 dark:bg-zinc-950/40">
        <div v-if="!selectedLeadId" class="flex flex-wrap items-start gap-x-4 gap-y-2">
            <div class="min-w-[14rem] flex-1 space-y-1">
                <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Бизнес-процесс</label>
                <select :value="businessProcessId" class="field" required @change="onProcessChange">
                    <option v-for="process in businessProcesses" :key="process.id" :value="process.id">
                        {{ process.name }}
                    </option>
                </select>
            </div>
            <p
                v-if="selectedProcessDescription"
                class="max-w-md flex-1 pt-5 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400"
            >
                {{ selectedProcessDescriptionPlain }}
            </p>
        </div>

        <template v-else-if="processProgress">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ processProgress.process_name }} · {{ processProgress.current_stage_name }}
                </p>
                <p
                    v-if="processProgress.stage_due_at"
                    class="text-xs"
                    :class="processProgress.is_stage_overdue ? 'font-medium text-rose-600 dark:text-rose-400' : 'text-zinc-500 dark:text-zinc-400'"
                >
                    {{ formatDateTime(processProgress.stage_due_at) }}
                    <span v-if="processProgress.is_stage_overdue"> · просрочен</span>
                </p>
            </div>

            <LeadFocusNowPanel
                v-if="operationalBrief"
                :brief="operationalBrief"
                :process-progress="processProgress"
                @navigate-tab="emit('navigate-tab', $event)"
                @focus-action="emit('focus-action', $event)"
            />

            <div v-if="processProgress.progress_percent > 0" class="mt-3 space-y-1">
                <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                    <span>Этап процесса</span>
                    <span>{{ processProgress.progress_percent }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                    <div
                        class="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500"
                        :style="{ width: `${processProgress.progress_percent}%` }"
                    />
                </div>
            </div>

            <div class="mt-2 flex flex-wrap gap-1.5">
                <span
                    v-for="stage in processProgress.stages"
                    :key="stage.id"
                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px]"
                    :class="stageStateClass(stage.state)"
                    :title="stage.stage_goal || undefined"
                >
                    {{ stage.name }}
                </span>
            </div>

            <div v-if="advanceableStages.length" class="mt-3 flex flex-wrap items-end gap-2">
                <div class="min-w-[12rem] flex-1 space-y-1">
                    <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Следующий этап</label>
                    <select :value="advanceStageId" class="field" @change="onAdvanceChange">
                        <option value="">—</option>
                        <option v-for="stage in advanceableStages" :key="stage.id" :value="stage.id">
                            {{ stage.name }}
                        </option>
                    </select>
                </div>
                <button
                    type="button"
                    class="secondary-button"
                    :disabled="!advanceStageId || processing || !canSubmitAdvance"
                    @click="emit('advance')"
                >
                    Перейти
                </button>
            </div>

            <LeadCloseOutcomeFields
                v-if="selectedAdvanceStage?.is_terminal"
                v-model:primary-flag="closeOutcomePrimaryFlag"
                v-model:note="closeOutcomeNote"
                class="mt-3"
                :terminal-outcome="selectedAdvanceStage?.terminal_outcome"
                :lost-options="lostCloseOutcomeOptions"
                :won-options="wonCloseOutcomeOptions"
                :error="closeOutcomeError"
                input-class="field"
            />
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import LeadFocusNowPanel from '@/Components/Leads/LeadFocusNowPanel.vue';
import LeadCloseOutcomeFields from '@/Components/Leads/LeadCloseOutcomeFields.vue';

const props = defineProps({
    selectedLeadId: [Number, null],
    businessProcesses: { type: Array, default: () => [] },
    businessProcessId: [Number, String, null],
    processProgress: { type: Object, default: null },
    operationalBrief: { type: Object, default: null },
    advanceStageId: { type: [Number, String], default: '' },
    processing: { type: Boolean, default: false },
    lostCloseOutcomeOptions: { type: Array, default: () => [] },
    wonCloseOutcomeOptions: { type: Array, default: () => [] },
    closeOutcomeError: { type: String, default: '' },
});

const closeOutcomePrimaryFlag = defineModel('closeOutcomePrimaryFlag', { type: String, default: '' });
const closeOutcomeNote = defineModel('closeOutcomeNote', { type: String, default: '' });

const emit = defineEmits(['update:businessProcessId', 'update:advanceStageId', 'advance', 'navigate-tab', 'focus-action']);

const selectedProcessDescription = computed(() => {
    const process = props.businessProcesses.find((item) => Number(item.id) === Number(props.businessProcessId));

    return process?.description ?? '';
});

const selectedProcessDescriptionPlain = computed(() => selectedProcessDescription.value
    .replace(/[#>*_`[\]]/g, '')
    .replace(/\s+/g, ' ')
    .trim());

const advanceableStages = computed(() => {
    if (!props.processProgress?.stages) {
        return [];
    }

    return props.processProgress.stages.filter((stage) => stage.state !== 'current');
});

const selectedAdvanceStage = computed(() => {
    if (!props.advanceStageId) {
        return null;
    }

    return advanceableStages.value.find((stage) => Number(stage.id) === Number(props.advanceStageId)) ?? null;
});

const canSubmitAdvance = computed(() => {
    if (!selectedAdvanceStage.value?.is_terminal) {
        return true;
    }

    if (selectedAdvanceStage.value.terminal_outcome === 'lost') {
        return Boolean(closeOutcomePrimaryFlag.value);
    }

    return true;
});

function stageStateClass(state) {
    if (state === 'current') {
        return 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-200';
    }
    if (state === 'completed') {
        return 'border-zinc-300 bg-white text-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-400';
    }

    return 'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400';
}

function onProcessChange(event) {
    emit('update:businessProcessId', Number(event.target.value));
}

function onAdvanceChange(event) {
    const value = event.target.value;
    emit('update:advanceStageId', value === '' ? '' : Number(value));
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
</script>

<style scoped>
.field {
    @apply w-full rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm outline-none transition-colors focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400;
}
.secondary-button {
    @apply inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800;
}
</style>
