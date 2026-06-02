<template>
    <div v-if="showBlock" class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
        <div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ heading }}</h3>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ hint }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="option in activeOptions"
                :key="option.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                :class="primaryFlag === option.value
                    ? 'border-sky-700 bg-sky-700 text-white dark:border-sky-500 dark:bg-sky-600'
                    : 'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200'"
                @click="primaryFlag = option.value"
            >
                {{ option.label }}
            </button>
        </div>

        <div v-if="primaryFlag === 'lost_other' || primaryFlag === 'won_other'" class="space-y-1">
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Уточнение</label>
            <input
                v-model="note"
                type="text"
                :class="inputClass"
                placeholder="Кратко, одной строкой"
            />
        </div>

        <p v-if="error" class="text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    terminalOutcome: {
        type: String,
        default: null,
    },
    lostOptions: {
        type: Array,
        default: () => [],
    },
    wonOptions: {
        type: Array,
        default: () => [],
    },
    inputClass: {
        type: String,
        default: 'w-full border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950',
    },
    error: {
        type: String,
        default: '',
    },
});

const primaryFlag = defineModel('primaryFlag', { type: String, default: '' });
const note = defineModel('note', { type: String, default: '' });

const showBlock = computed(() => props.terminalOutcome === 'lost' || props.terminalOutcome === 'won');

const activeOptions = computed(() => {
    if (props.terminalOutcome === 'lost') {
        return props.lostOptions;
    }
    if (props.terminalOutcome === 'won') {
        return props.wonOptions;
    }

    return [];
});

const heading = computed(() => (props.terminalOutcome === 'won' ? 'Причина выигрыша' : 'Причина проигрыша'));

const hint = computed(() =>
    props.terminalOutcome === 'won'
        ? 'Опционально — поможет аналитике Outcome Intelligence.'
        : 'Обязательно при закрытии лида как проигранного.',
);
</script>
