<template>
    <div
        v-if="showBlock"
        :class="variant === 'inline'
            ? 'flex min-w-0 flex-1 flex-wrap items-end gap-x-3 gap-y-2'
            : 'space-y-2 rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 dark:border-zinc-800 dark:bg-zinc-950/40'"
    >
        <div :class="variant === 'inline' ? 'min-w-[12rem] flex-1 space-y-1' : 'space-y-1'">
            <label
                v-if="variant !== 'inline'"
                class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"
            >
                {{ heading }}
            </label>
            <label
                v-else
                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500"
            >
                {{ headingShort }}
            </label>
            <select
                v-model="primaryFlag"
                :class="selectClass"
                :required="terminalOutcome === 'lost'"
            >
                <option value="" disabled hidden>{{ selectPlaceholder }}</option>
                <option
                    v-for="option in activeOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <p
                v-if="variant !== 'inline' && terminalOutcome === 'lost'"
                class="text-xs text-zinc-500 dark:text-zinc-400"
            >
                {{ hint }}
            </p>
        </div>

        <div
            v-if="needsNote"
            :class="variant === 'inline' ? 'min-w-[12rem] flex-1 space-y-1' : 'space-y-1'"
        >
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Уточнение</label>
            <input
                v-model="note"
                type="text"
                :class="inputClass"
                placeholder="Кратко, одной строкой"
            />
        </div>

        <p
            v-if="error"
            class="w-full text-xs text-rose-600 dark:text-rose-400"
            :class="variant === 'inline' ? 'basis-full' : ''"
        >
            {{ error }}
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { crmFieldFluid } from '@/support/crmUi.js';

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
    variant: {
        type: String,
        default: 'card',
        validator: (value) => ['card', 'inline'].includes(value),
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

const headingShort = computed(() => (props.terminalOutcome === 'won' ? 'Выигрыш' : 'Причина'));

const hint = computed(() =>
    props.terminalOutcome === 'won'
        ? 'Опционально — поможет аналитике Outcome Intelligence.'
        : 'Обязательно при закрытии лида как проигранного.',
);

const selectPlaceholder = computed(() =>
    props.terminalOutcome === 'won' ? 'Выберите причину' : 'Выберите причину проигрыша',
);

const needsNote = computed(() => primaryFlag.value === 'lost_other' || primaryFlag.value === 'won_other');

const selectClass = computed(() => {
    if (props.variant === 'inline') {
        return `${crmFieldFluid} !w-auto min-w-[11rem] flex-1 rounded-lg border-zinc-300 bg-zinc-50 py-1.5 text-sm text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-200`;
    }

    return props.inputClass;
});
</script>
