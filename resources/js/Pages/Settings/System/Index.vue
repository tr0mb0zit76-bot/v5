<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Системные параметры CRM: нумерация заявок и другие глобальные правила."
            title="Системные"
        />

        <div class="grid min-h-0 grid-cols-1 gap-3 md:grid-cols-2">
            <Link
                v-for="section in sections"
                :key="section.key"
                :href="section.href"
                :class="`${crmModuleCard} flex min-h-[190px] flex-col justify-between`"
            >
                <div class="space-y-3">
                    <div class="flex h-11 w-11 items-center justify-center border border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                        <Hash class="h-5 w-5" />
                    </div>
                    <div class="space-y-2">
                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ section.title }}</div>
                        <p class="text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                            {{ section.description }}
                        </p>
                    </div>
                </div>
                <div class="text-sm font-medium text-zinc-900 transition-transform group-hover:translate-x-1 dark:text-zinc-100">
                    Открыть →
                </div>
            </Link>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { Hash } from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmModuleCard } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'system' }, () => page),
});

defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
});
</script>
