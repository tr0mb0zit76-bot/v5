<script setup>
import { X } from 'lucide-vue-next';

defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
    closeLabel: {
        type: String,
        default: 'Закрыть',
    },
});

defineEmits(['close']);
</script>

<template>
    <header class="flex flex-wrap items-start gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800 sm:gap-4 sm:px-6">
        <div class="min-w-0 flex-1">
            <div
                v-if="eyebrow"
                class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400"
            >
                {{ eyebrow }}
            </div>
            <h2 class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                <slot name="title">{{ title }}</slot>
            </h2>
            <div v-if="$slots.default" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                <slot />
            </div>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center justify-end gap-2 sm:max-w-[50%]">
            <slot name="actions" />
        </div>
        <button
            type="button"
            class="shrink-0 rounded-xl p-2 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
            :aria-label="closeLabel"
            @click="$emit('close')"
        >
            <X class="h-5 w-5" stroke-width="2" />
        </button>
    </header>
</template>
