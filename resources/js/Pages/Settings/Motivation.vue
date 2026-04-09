<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold">Мотивация</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                KPI в гриде и персональные коэффициенты. Периоды начислений и выплаты ведутся в разделе «Финансы → Зарплата».
            </p>
        </div>

        <div class="grid min-h-0 grid-cols-1 gap-3 md:grid-cols-2">
            <Link
                v-for="section in sections"
                :key="section.key"
                :href="section.href"
                class="group flex min-h-[190px] flex-col justify-between border border-zinc-200 bg-white p-5 transition-colors hover:border-zinc-900 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-500 dark:hover:bg-zinc-800"
            >
                <div class="space-y-3">
                    <div class="flex h-11 w-11 items-center justify-center border" :class="iconTone(section.accent)">
                        <component :is="iconFor(section.icon)" class="h-5 w-5" />
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
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { Gauge, Wallet } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'motivation' }, () => page),
});

defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
});

function iconFor(icon) {
    return {
        gauge: Gauge,
        wallet: Wallet,
    }[icon] || Gauge;
}

function iconTone(accent) {
    return {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300',
        amber: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300',
    }[accent] || 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100';
}
</script>
