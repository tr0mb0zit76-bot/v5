<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});

const chipClass =
    'inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200';

const chipInteractiveClass =
    `${chipClass} hover:border-indigo-300 hover:text-indigo-700 dark:hover:border-indigo-500 dark:hover:text-indigo-300`;
</script>

<template>
    <div v-if="links.length" class="flex flex-wrap items-center gap-2">
        <template v-for="link in links" :key="link.key">
            <Link
                v-if="link.href"
                :href="link.href"
                :class="chipInteractiveClass"
            >
                <span>{{ link.label }}</span>
                <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">
                    {{ link.count }}
                </span>
            </Link>
            <span
                v-else
                :class="chipClass"
                title="Открытые задачи по карточке"
            >
                <span>{{ link.label }}</span>
                <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">
                    {{ link.count }}
                </span>
            </span>
        </template>
    </div>
</template>
