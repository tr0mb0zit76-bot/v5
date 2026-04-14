<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold">Настройки</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Администрирование пользователей, ролей и базовых представлений системы.
            </p>
        </div>

        <div class="flex min-h-0 flex-col gap-5">
            <section v-for="group in groupedSections" :key="group.name" class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800" />
                    <h2 class="shrink-0 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                        {{ group.name }}
                    </h2>
                    <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800" />
                </div>

                <div class="grid min-h-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="section in group.items"
                        :key="section.key"
                        :href="section.href"
                        class="group flex min-h-[190px] flex-col justify-between border border-zinc-200 bg-white p-5 transition-colors hover:border-zinc-900 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-500 dark:hover:bg-zinc-800"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex h-11 w-11 items-center justify-center border" :class="iconTone(section.accent)">
                                    <component :is="iconFor(section.icon)" class="h-5 w-5" />
                                </div>

                                <div class="rounded-full border border-zinc-200 px-2 py-1 text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                    {{ section.group }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-lg font-semibold">{{ section.title }}</div>
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
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Files, Shield, Table2, TrendingUp, Users } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings' }, () => page),
});

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
});

const groupedSections = computed(() => {
    const groups = new Map();

    props.sections.forEach((section) => {
        const groupName = section.group || 'Прочее';

        if (!groups.has(groupName)) {
            groups.set(groupName, []);
        }

        groups.get(groupName).push(section);
    });

    return Array.from(groups.entries()).map(([name, items]) => ({ name, items }));
});

function iconFor(icon) {
    return {
        users: Users,
        shield: Shield,
        table: Table2,
        'book-open': BookOpen,
        files: Files,
        'trending-up': TrendingUp,
    }[icon] || Table2;
}

function iconTone(accent) {
    return {
        slate: 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100',
        amber: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300',
    }[accent] || 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100';
}
</script>
