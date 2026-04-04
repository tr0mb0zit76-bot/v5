<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold">Настройки KPI</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Пороги KPI по прямым и кривым сделкам и множитель бонуса в формуле delta.
            </p>
        </div>

        <section class="flex min-h-0 flex-col border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold">Пороги KPI и delta</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Настройка диапазонов доли прямых сделок и коэффициента бонуса в формуле delta.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-zinc-200 px-3 py-2 text-sm transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="addThresholdRow"
                >
                    Добавить диапазон
                </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,220px)_1fr]">
                <label class="space-y-1">
                    <span class="text-sm font-medium">Множитель бонуса в delta</span>
                    <input
                        v-model.number="thresholdForm.bonus_multiplier"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                    >
                </label>

                <div class="border border-zinc-200 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    Сейчас множитель применяется так:
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">bonus * {{ formattedBonusMultiplier }}</span>
                </div>
            </div>

            <div class="min-h-0 overflow-auto border border-zinc-200 dark:border-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                        <tr>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">От</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">До</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Прямая</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Кривая</th>
                            <th class="w-px px-3 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <tr v-for="(row, index) in thresholdForm.thresholds" :key="index">
                            <td class="px-3 py-2">
                                <input
                                    v-model.number="row.threshold_from"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1"
                                    class="w-24 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    v-model.number="row.threshold_to"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1"
                                    class="w-24 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="row.direct_kpi"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="w-20 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                    >
                                    <span class="text-zinc-500 dark:text-zinc-400">%</span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="row.indirect_kpi"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="w-20 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                    >
                                    <span class="text-zinc-500 dark:text-zinc-400">%</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    class="text-xs text-zinc-500 transition hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400"
                                    @click="removeThresholdRow(index)"
                                >
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <p v-if="thresholdForm.hasErrors" class="text-sm text-red-600 dark:text-red-400">
                    Проверьте диапазоны и проценты KPI.
                </p>
                <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Изменения применяются ко всем новым расчетам delta.
                </div>

                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    :disabled="thresholdForm.processing"
                    @click="saveThresholdSettings"
                >
                    Сохранить
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'motivation', activeLeafKey: 'kpi-settings' }, () => page),
});

const props = defineProps({
    bonusMultiplier: {
        type: Number,
        default: 1.3,
    },
    thresholds: {
        type: Array,
        default: () => [],
    },
});

const thresholdForm = useForm({
    bonus_multiplier: props.bonusMultiplier,
    thresholds: props.thresholds.map((row) => ({
        threshold_from: Number(row.threshold_from),
        threshold_to: Number(row.threshold_to),
        direct_kpi: Number(row.direct_kpi),
        indirect_kpi: Number(row.indirect_kpi),
    })),
});

const formattedBonusMultiplier = computed(() => Number(thresholdForm.bonus_multiplier || 0).toFixed(2));

function addThresholdRow() {
    const lastRow = thresholdForm.thresholds[thresholdForm.thresholds.length - 1];
    const nextFrom = lastRow ? Number((Number(lastRow.threshold_to) + 0.01).toFixed(2)) : 0;
    const nextTo = Number(Math.min(nextFrom + 0.24, 1).toFixed(2));

    thresholdForm.thresholds.push({
        threshold_from: nextFrom,
        threshold_to: nextTo,
        direct_kpi: 0,
        indirect_kpi: 0,
    });
}

function removeThresholdRow(index) {
    if (thresholdForm.thresholds.length === 1) {
        return;
    }

    thresholdForm.thresholds.splice(index, 1);
}

function saveThresholdSettings() {
    thresholdForm.patch(route('settings.motivation.kpi.update'), {
        preserveScroll: true,
    });
}
</script>
