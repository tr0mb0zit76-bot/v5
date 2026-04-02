<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold">Настройки KPI</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Заготовка для порогов KPI, коэффициентов мотивации и правил расчета delta по прямым и кривым сделкам.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <section class="border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-4 space-y-1">
                    <h2 class="text-lg font-semibold">Пороги KPI</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Следующим этапом сюда лягут редактируемые диапазоны доли прямых сделок и проценты KPI для прямой и кривой схемы.
                    </p>
                </div>

                <div class="overflow-hidden border border-zinc-200 dark:border-zinc-800">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Диапазон</th>
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Прямая</th>
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Кривая</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr v-for="row in thresholdPreview" :key="row.range">
                                <td class="px-4 py-3">{{ row.range }}</td>
                                <td class="px-4 py-3">{{ row.direct }}%</td>
                                <td class="px-4 py-3">{{ row.indirect }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">Что сюда войдет</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Этот модуль станет базой для расчета KPI, delta, менеджерской мотивации и статусов оплаты по заказу.
                        </p>
                    </div>

                    <div class="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                        <div class="border border-zinc-200 p-3 dark:border-zinc-800">
                            Пороговые диапазоны по доле прямых сделок менеджера за расчетный период.
                        </div>
                        <div class="border border-zinc-200 p-3 dark:border-zinc-800">
                            Отдельные значения KPI для прямой и кривой сделки.
                        </div>
                        <div class="border border-zinc-200 p-3 dark:border-zinc-800">
                            Коэффициенты мотивации менеджеров и история их изменения.
                        </div>
                        <div class="border border-zinc-200 p-3 dark:border-zinc-800">
                            Подготовка к синхронизации с 1С и сверке оплат по документам.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'motivation', activeLeafKey: 'kpi-settings' }, () => page),
});

defineProps({
    thresholdPreview: {
        type: Array,
        default: () => [],
    },
});
</script>
