<template>
    <div class="shrink-0 rounded-lg border border-zinc-200 bg-zinc-50/80 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900/60">
        <p class="text-xs font-medium text-zinc-700 dark:text-zinc-200">Насколько статья помогла?</p>
        <textarea
            v-model="comment"
            class="mt-2 min-h-16 w-full rounded-lg border border-zinc-200 bg-white px-2.5 py-2 text-xs text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            maxlength="2000"
            placeholder="Что поправить? Необязательно."
        />
        <div class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="option in ratingOptions"
                :key="option.value"
                type="button"
                class="rounded-md border px-2.5 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                :class="option.value === 'helpful'
                    ? 'border-emerald-300 text-emerald-800 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-200 dark:hover:bg-emerald-950/40'
                    : option.value === 'unclear'
                        ? 'border-amber-300 text-amber-800 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-200 dark:hover:bg-amber-950/40'
                        : 'border-zinc-300 text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                :disabled="busy"
                @click="submit(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
        <p v-if="summary?.total" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            Оценок: {{ summary.total }}
            <span v-if="summary.helpful"> · полезно {{ summary.helpful }}</span>
            <span v-if="summary.unclear"> · непонятно {{ summary.unclear }}</span>
            <span v-if="summary.outdated"> · устарело {{ summary.outdated }}</span>
            <span
                v-if="summary.needs_rewrite"
                class="ml-1 font-medium text-amber-700 dark:text-amber-300"
            >— требует правки</span>
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    articleId: {
        type: Number,
        required: true,
    },
    summary: {
        type: Object,
        default: null,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['rate']);

const comment = ref('');

const ratingOptions = [
    { value: 'helpful', label: 'Полезно' },
    { value: 'unclear', label: 'Непонятно' },
    { value: 'outdated', label: 'Устарело' },
];

function submit(rating) {
    emit('rate', {
        rating,
        comment: comment.value.trim(),
    });

    comment.value = '';
}
</script>
