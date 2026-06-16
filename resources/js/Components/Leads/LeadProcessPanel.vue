<template>
    <section class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Бизнес-процесс</h2>
                <p v-if="!selectedLeadId" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Выберите воронку при создании лида — этапы, сроки и playbook подтянутся из справочника.
                </p>
                <p v-else-if="processProgress" class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ processProgress.process_name }} · {{ processProgress.current_stage_name }}
                </p>
            </div>
            <p
                v-if="processProgress?.stage_due_at"
                class="text-xs"
                :class="processProgress.is_stage_overdue ? 'font-medium text-rose-600 dark:text-rose-400' : 'text-zinc-500 dark:text-zinc-400'"
            >
                Срок этапа: {{ formatDateTime(processProgress.stage_due_at) }}
                <span v-if="processProgress.is_stage_overdue"> (просрочен)</span>
            </p>
        </div>

        <div v-if="!selectedLeadId" class="max-w-xl space-y-2">
            <label class="label">Процесс</label>
            <select :value="businessProcessId" class="field" required @change="onProcessChange">
                <option v-for="process in businessProcesses" :key="process.id" :value="process.id">
                    {{ process.name }}
                </option>
            </select>
            <div v-if="selectedProcessDescription" class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                <CrmMarkdownView :model-value="selectedProcessDescription" compact />
            </div>
        </div>

        <template v-else-if="processProgress">
            <div
                v-if="processProgress.current_stage_goal || processProgress.current_stage_playbook || processProgress.current_stage_success_criteria"
                class="space-y-3 rounded-lg border border-emerald-200 bg-emerald-50/80 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/20"
            >
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">
                    Playbook текущего этапа
                </div>
                <p v-if="processProgress.current_stage_goal" class="text-sm font-medium text-emerald-950 dark:text-emerald-100">
                    Цель: {{ processProgress.current_stage_goal }}
                </p>
                <div v-if="processProgress.current_stage_playbook" class="rounded-lg border border-emerald-100 bg-white/90 p-2 dark:border-emerald-900/30 dark:bg-zinc-950/60">
                    <CrmMarkdownView :model-value="processProgress.current_stage_playbook" />
                </div>
                <div v-if="processProgress.current_stage_success_criteria">
                    <div class="mb-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">Критерии готовности</div>
                    <CrmMarkdownView :model-value="processProgress.current_stage_success_criteria" compact />
                </div>
                <div v-if="processProgress.current_stage_sales_script">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-emerald-400 bg-white px-3 py-1.5 text-xs font-medium text-emerald-900 hover:bg-emerald-50 dark:border-emerald-700 dark:bg-zinc-900 dark:text-emerald-100"
                        @click="startSalesScript(processProgress.current_stage_sales_script.version_id)"
                    >
                        Открыть скрипт «{{ processProgress.current_stage_sales_script.title }}»
                    </button>
                </div>
            </div>

            <div class="space-y-1">
                <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                    <span>Прогресс</span>
                    <span>{{ processProgress.progress_percent }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                    <div
                        class="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500"
                        :style="{ width: `${processProgress.progress_percent}%` }"
                    />
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <span
                    v-for="stage in processProgress.stages"
                    :key="stage.id"
                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs"
                    :class="stageStateClass(stage.state)"
                    :title="stage.stage_goal || undefined"
                >
                    {{ stage.name }}
                </span>
            </div>

            <div v-if="advanceableStages.length" class="space-y-3">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-[14rem] flex-1 space-y-1">
                        <label class="label">Перевести на этап</label>
                        <select :value="advanceStageId" class="field" @change="onAdvanceChange">
                            <option value="">— выберите —</option>
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
                    :terminal-outcome="selectedAdvanceStage?.terminal_outcome"
                    :lost-options="lostCloseOutcomeOptions"
                    :won-options="wonCloseOutcomeOptions"
                    :error="closeOutcomeError"
                    input-class="field"
                />
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import CrmMarkdownView from '@/Components/Crm/CrmMarkdownView.vue';
import LeadCloseOutcomeFields from '@/Components/Leads/LeadCloseOutcomeFields.vue';

const props = defineProps({
    selectedLeadId: [Number, null],
    businessProcesses: { type: Array, default: () => [] },
    businessProcessId: [Number, String, null],
    processProgress: { type: Object, default: null },
    advanceStageId: { type: [Number, String], default: '' },
    processing: { type: Boolean, default: false },
    lostCloseOutcomeOptions: { type: Array, default: () => [] },
    wonCloseOutcomeOptions: { type: Array, default: () => [] },
    closeOutcomeError: { type: String, default: '' },
});

const closeOutcomePrimaryFlag = defineModel('closeOutcomePrimaryFlag', { type: String, default: '' });
const closeOutcomeNote = defineModel('closeOutcomeNote', { type: String, default: '' });

const emit = defineEmits(['update:businessProcessId', 'update:advanceStageId', 'advance']);

const selectedProcessDescription = computed(() => {
    const process = props.businessProcesses.find((item) => Number(item.id) === Number(props.businessProcessId));

    return process?.description ?? '';
});

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

function startSalesScript(versionId) {
    router.post(route('scripts.sessions.store'), {
        sales_script_version_id: versionId,
    });
}
</script>

<style scoped>
.field {
    @apply w-full border border-zinc-200 bg-white px-3 py-2 text-sm outline-none transition-colors focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400;
}
.label {
    @apply text-sm font-medium;
}
.secondary-button {
    @apply inline-flex items-center justify-center border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800;
}
</style>
