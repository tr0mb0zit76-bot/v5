<script setup>
import { computed } from 'vue';
import LeadCloseOutcomeFields from '@/Components/Leads/LeadCloseOutcomeFields.vue';
import { crmFieldFluid } from '@/support/crmUi.js';
import { LEAD_PIPELINE_STEPS, LEAD_TERMINAL_STATUS_OPTIONS, leadPipelineIndex } from '@/support/leadStatusPipeline.js';

const status = defineModel({ type: String, required: true });
const closeOutcomePrimaryFlag = defineModel('closeOutcomePrimaryFlag', { type: String, default: '' });
const closeOutcomeNote = defineModel('closeOutcomeNote', { type: String, default: '' });

const props = defineProps({
    selectedLeadId: {
        type: [Number, String, null],
        default: null,
    },
    convertedOrderNumber: {
        type: String,
        default: '',
    },
    lostCloseOutcomeOptions: {
        type: Array,
        default: () => [],
    },
    wonCloseOutcomeOptions: {
        type: Array,
        default: () => [],
    },
    closeOutcomeError: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['manual-change']);

const activePipelineIndex = computed(() => leadPipelineIndex(status.value));
const isTerminal = computed(() => LEAD_TERMINAL_STATUS_OPTIONS.some((option) => option.value === status.value));

const progressPercent = computed(() => {
    if (!props.selectedLeadId) {
        return 0;
    }

    if (isTerminal.value) {
        return 100;
    }

    const index = activePipelineIndex.value;

    if (index <= 0) {
        return 0;
    }

    const lastIndex = LEAD_PIPELINE_STEPS.length - 1;

    return Math.round((index / lastIndex) * 100);
});

const showProgressBar = computed(() => Boolean(props.selectedLeadId));

const terminalSelectValue = computed(() => (isTerminal.value ? status.value : ''));

const showCloseOutcome = computed(() => status.value === 'lost' || status.value === 'won');

const closeOutcomeTerminal = computed(() => (status.value === 'won' ? 'won' : 'lost'));

function selectPipelineStatus(nextStatus) {
    status.value = nextStatus;
    emit('manual-change');
}

function onTerminalSelect(event) {
    const value = event.target.value;

    if (!value) {
        return;
    }

    status.value = value;
    emit('manual-change');
}

function stepClasses(step, index) {
    const isActive = status.value === step.value;
    const isPast = !isTerminal.value && activePipelineIndex.value > index;

    if (isActive) {
        return 'border-sky-500 bg-sky-50 text-sky-950 dark:border-sky-500 dark:bg-sky-950/40 dark:text-sky-100';
    }

    if (isPast) {
        return 'border-emerald-200 bg-emerald-50/80 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100';
    }

    return 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800';
}
</script>

<template>
    <section class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400">Статус</h3>
            <p
                v-if="convertedOrderNumber"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100"
            >
                Заказ <span class="font-medium">{{ convertedOrderNumber }}</span>
            </p>
        </div>

        <div v-if="showProgressBar" class="space-y-1">
            <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>Прогресс</span>
                <span>{{ progressPercent }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                <div
                    class="h-full rounded-full transition-all"
                    :class="isTerminal && status === 'lost' ? 'bg-rose-500' : isTerminal && status === 'on_hold' ? 'bg-amber-500' : 'bg-emerald-600 dark:bg-emerald-500'"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                <button
                    v-for="(step, index) in LEAD_PIPELINE_STEPS"
                    :key="step.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="stepClasses(step, index)"
                    @click="selectPipelineStatus(step.value)"
                >
                    {{ step.shortLabel }}
                </button>
            </div>

            <div class="flex shrink-0 flex-wrap items-end gap-3 border-l border-dashed border-zinc-300 pl-3 dark:border-zinc-600">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">Исход</span>
                    <select
                        :value="terminalSelectValue"
                        :class="`${crmFieldFluid} !w-auto min-w-[10.5rem] rounded-lg border-zinc-300 bg-zinc-50 py-1.5 text-sm text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-200`"
                        @change="onTerminalSelect"
                    >
                        <option value="" disabled hidden>Не выбран</option>
                        <option
                            v-for="option in LEAD_TERMINAL_STATUS_OPTIONS"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <LeadCloseOutcomeFields
                    v-if="showCloseOutcome"
                    v-model:primary-flag="closeOutcomePrimaryFlag"
                    v-model:note="closeOutcomeNote"
                    variant="inline"
                    :terminal-outcome="closeOutcomeTerminal"
                    :lost-options="lostCloseOutcomeOptions"
                    :won-options="wonCloseOutcomeOptions"
                    :error="closeOutcomeError"
                />
            </div>
        </div>
    </section>
</template>
