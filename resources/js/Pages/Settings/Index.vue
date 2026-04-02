<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="shrink-0">
            <h1 class="text-2xl font-semibold">Настройки</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Администрирование пользователей, ролей и базовых представлений таблиц.
            </p>
        </div>

        <div class="grid min-h-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="section in sections"
                :key="section.key"
                :href="section.href"
                class="group flex min-h-[180px] flex-col justify-between border border-zinc-200 bg-white p-5 transition-colors hover:border-zinc-900 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-500 dark:hover:bg-zinc-800"
            >
                <div class="space-y-2">
                    <div class="text-lg font-semibold">{{ section.title }}</div>
                    <p class="text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        {{ section.description }}
                    </p>
                </div>

                <div class="text-sm font-medium text-zinc-900 group-hover:translate-x-1 transition-transform dark:text-zinc-100">
                    Открыть →
                </div>
            </Link>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings' }, () => page),
});

defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
});
</script>
