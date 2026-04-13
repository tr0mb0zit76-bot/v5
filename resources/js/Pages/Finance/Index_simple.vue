<template>
    <div class="space-y-6">
        <section
            v-if="activeSubmodule === 'overview'"
            class="space-y-3 border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Финансы</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Номера счетов и УПД ведутся в карточке заказа (вкладка «Документы»). Здесь — график оплат и
                        зарплатный модуль.
                    </p>
                </div>
            </div>

            <div class="mt-4 grid min-h-0 grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="tile in submoduleTiles"
                    :key="tile.key"
                    :href="tile.href"
                    class="group flex min-h-[190px] flex-col justify-between border border-zinc-200 bg-white p-5 transition hover:border-zinc-900 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-500 dark:hover:bg-zinc-800"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-none border"
                                :class="iconTone(tile.accent)"
                            >
                                <component :is="iconFor(tile.icon)" class="h-5 w-5" />
                            </div>
                            <div class="border border-zinc-200 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                {{ tile.group }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ tile.title }}</div>
                            <p
                                v-if="tile.key === 'cashflow'"
                                class="text-sm leading-6 text-zinc-500 dark:text-zinc-400"
                            >
                                Сегодня по плану: поступление {{ formatMoney(todaysCashFlow.incoming) }}, оплата
                                перевозчикам {{ formatMoney(todaysCashFlow.outgoing) }}.
                            </p>
                            <p
                                v-else
                                class="text-sm leading-6 text-zinc-500 dark:text-zinc-400"
                            >
                                {{ tile.description }}
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
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { BarChart3, Wallet } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) =>
        h(
            CrmLayout,
            {
                activeKey: 'finance',
                activeSubKey: null,
            },
            () => page,
        ),
});

const props = defineProps({
    active_submodule: {
        type: String,
        default: 'overview',
    },
});

const submoduleTiles = computed(() => {
    const tiles = [
        {
            key: 'cashflow',
            title: 'График оплат',
            description: 'План, факт, суммы и статусы оплат по заказам.',
            href: '/finance?section=cashflow',
            group: 'Оплаты',
            accent: 'emerald',
            icon: 'bar-chart-3',
        },
    ];

    return tiles;
});

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function iconFor(icon) {
    return {
        'bar-chart-3': BarChart3,
        wallet: Wallet,
    }[icon] ?? BarChart3;
}

function iconTone(accent) {
    return {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300',
        indigo: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-300',
    }[accent] ?? 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100';
}
</script>