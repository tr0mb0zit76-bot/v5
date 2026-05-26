<script setup>
import { blankPartyNormsPenalties } from '@/support/normsPenalties.js';
import { crmFieldCompact, crmFieldFluid } from '@/support/crmUi.js';

const model = defineModel({
    type: Object,
    default: () => blankPartyNormsPenalties(),
});

defineProps({
    currencyOptions: {
        type: Array,
        default: () => [],
    },
    description: {
        type: String,
        default: 'Подставляются в заказ на вкладке «Нормативы / штрафы».',
    },
});
</script>

<template>
    <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
        <div>
            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Нормативы и штрафы по умолчанию</div>
            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ description }}</div>
        </div>
        <div class="flex flex-wrap items-end gap-x-3 gap-y-2">
            <div class="space-y-1">
                <label class="text-xs text-zinc-500 dark:text-zinc-400">Срыв</label>
                <div class="flex gap-1">
                    <input v-model.number="model.miss_amount" type="number" min="0" step="0.01" :class="crmFieldCompact" />
                    <select v-model="model.miss_currency" :class="`${crmFieldCompact} !max-w-[4rem]`">
                        <option v-for="option in currencyOptions" :key="`miss-${option.value}`" :value="option.value">{{ option.value }}</option>
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs text-zinc-500 dark:text-zinc-400">Простой</label>
                <div class="flex gap-1">
                    <input v-model.number="model.downtime_amount" type="number" min="0" step="0.01" :class="crmFieldCompact" />
                    <select v-model="model.downtime_currency" :class="`${crmFieldCompact} !max-w-[4rem]`">
                        <option v-for="option in currencyOptions" :key="`down-${option.value}`" :value="option.value">{{ option.value }}</option>
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs text-zinc-500 dark:text-zinc-400">Штраф</label>
                <div class="flex gap-1">
                    <input v-model.number="model.fine_amount" type="number" min="0" step="0.01" :class="crmFieldCompact" />
                    <select v-model="model.fine_currency" :class="`${crmFieldCompact} !max-w-[4rem]`">
                        <option v-for="option in currencyOptions" :key="`fine-${option.value}`" :value="option.value">{{ option.value }}</option>
                    </select>
                </div>
            </div>
            <div class="min-w-[12rem] flex-1 space-y-1">
                <label class="text-xs text-zinc-500 dark:text-zinc-400">Пеня</label>
                <input v-model="model.penalty_terms" type="text" placeholder="Условия пени…" :class="crmFieldFluid" />
            </div>
            <div class="space-y-1">
                <label class="text-xs text-zinc-500 dark:text-zinc-400" title="Погрузка, ч">Погр., ч</label>
                <input v-model.number="model.norm_loading_hours" type="number" min="0" step="0.25" :class="crmFieldCompact" />
            </div>
            <div class="space-y-1">
                <label class="text-xs text-zinc-500 dark:text-zinc-400" title="Таможня, ч">Там., ч</label>
                <input v-model.number="model.norm_customs_hours" type="number" min="0" step="0.25" :class="crmFieldCompact" />
            </div>
            <div class="space-y-1">
                <label class="text-xs text-zinc-500 dark:text-zinc-400" title="Выгрузка, ч">Выгр., ч</label>
                <input v-model.number="model.norm_unloading_hours" type="number" min="0" step="0.25" :class="crmFieldCompact" />
            </div>
        </div>
    </div>
</template>
