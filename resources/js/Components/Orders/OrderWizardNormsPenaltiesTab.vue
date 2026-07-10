<script setup>
const clientNormsPenalties = defineModel('clientNormsPenalties', { type: Object, required: true });
const carrierNormsByLeg = defineModel('carrierNormsByLeg', { type: Array, required: true });

defineProps({
    currencyOptions: { type: Array, default: () => [] },
    isOrderFormEditable: { type: Boolean, default: true },
    validationMessages: { type: Array, default: () => [] },
    stageLabel: { type: Function, required: true },
});

const emit = defineEmits(['sync-carrier-norms']);
</script>

<template>
    <div class="space-y-6">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Штрафы и нормативы по времени (часы) для заказчика и отдельно по каждому плечу перевозчика. Данные сохраняются в карточке заказа и доступны для дальнейших сопоставлений.
        </p>
        <div
            v-if="validationMessages.length > 0"
            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100"
            role="alert"
        >
            <ul class="list-inside list-disc space-y-1">
                <li v-for="(msg, i) in validationMessages" :key="`norms-err-${i}`">{{ msg }}</li>
            </ul>
        </div>

        <div class="rounded-2xl border border-zinc-200 p-3 dark:border-zinc-800">
            <div class="-mx-1 flex min-h-9 min-w-0 flex-wrap items-center gap-x-3 gap-y-2 px-1 pb-0.5">
                <h2 class="shrink-0 text-base font-semibold">Заказчик</h2>
                <div class="flex min-w-0 flex-1 flex-nowrap items-center gap-x-2 gap-y-1 overflow-x-auto">
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Срыв</span>
                        <input
                            v-model.number="clientNormsPenalties.miss_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                        <select v-model="clientNormsPenalties.miss_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                            <option v-for="option in currencyOptions" :key="`cn-miss-${option.value}`" :value="option.value">{{ option.value }}</option>
                        </select>
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Простой</span>
                        <input
                            v-model.number="clientNormsPenalties.downtime_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                        <select v-model="clientNormsPenalties.downtime_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                            <option v-for="option in currencyOptions" :key="`cn-down-${option.value}`" :value="option.value">{{ option.value }}</option>
                        </select>
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Штраф</span>
                        <input
                            v-model.number="clientNormsPenalties.fine_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                        <select v-model="clientNormsPenalties.fine_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                            <option v-for="option in currencyOptions" :key="`cn-fine-${option.value}`" :value="option.value">{{ option.value }}</option>
                        </select>
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex min-w-0 shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Пеня</span>
                        <input
                            v-model="clientNormsPenalties.penalty_terms"
                            type="text"
                            class="h-8 min-w-[10rem] max-w-[28rem] flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950"
                            placeholder="Условия пени…"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Погрузка, ч">Погр.</span>
                        <input
                            v-model.number="clientNormsPenalties.norm_loading_hours"
                            type="number"
                            min="0"
                            step="0.25"
                            class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Таможня, ч">Там.</span>
                        <input
                            v-model.number="clientNormsPenalties.norm_customs_hours"
                            type="number"
                            min="0"
                            step="0.25"
                            class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Выгрузка, ч">Выгр.</span>
                        <input
                            v-model.number="clientNormsPenalties.norm_unloading_hours"
                            type="number"
                            min="0"
                            step="0.25"
                            class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Строки по плечам синхронизируются с вкладкой «Маршрут».</p>
            <button
                type="button"
                class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                :disabled="!isOrderFormEditable"
                @click="emit('sync-carrier-norms')"
            >
                Подтянуть плечи
            </button>
        </div>

        <div v-for="(normRow, legIndex) in carrierNormsByLeg" :key="`carrier-norms-${normRow.stage}-${legIndex}`" class="rounded-2xl border border-zinc-200 p-3 dark:border-zinc-800">
            <div class="-mx-1 flex min-h-9 min-w-0 flex-wrap items-center gap-x-3 gap-y-2 px-1 pb-0.5">
                <h2 class="shrink-0 text-base font-semibold">Перевозчик · {{ stageLabel(normRow.stage) }}</h2>
                <div class="flex min-w-0 flex-1 flex-nowrap items-center gap-x-2 gap-y-1 overflow-x-auto">
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Срыв</span>
                        <input
                            v-model.number="normRow.miss_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                        <select v-model="normRow.miss_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                            <option v-for="option in currencyOptions" :key="`leg-${legIndex}-miss-${option.value}`" :value="option.value">{{ option.value }}</option>
                        </select>
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Простой</span>
                        <input
                            v-model.number="normRow.downtime_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                        <select v-model="normRow.downtime_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                            <option v-for="option in currencyOptions" :key="`leg-${legIndex}-down-${option.value}`" :value="option.value">{{ option.value }}</option>
                        </select>
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Штраф</span>
                        <input
                            v-model.number="normRow.fine_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                        <select v-model="normRow.fine_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                            <option v-for="option in currencyOptions" :key="`leg-${legIndex}-fine-${option.value}`" :value="option.value">{{ option.value }}</option>
                        </select>
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex min-w-0 shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Пеня</span>
                        <input
                            v-model="normRow.penalty_terms"
                            type="text"
                            class="h-8 min-w-[10rem] max-w-[28rem] flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950"
                            placeholder="Условия пени…"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                    <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Погрузка, ч">Погр.</span>
                        <input
                            v-model.number="normRow.norm_loading_hours"
                            type="number"
                            min="0"
                            step="0.25"
                            class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Таможня, ч">Там.</span>
                        <input
                            v-model.number="normRow.norm_customs_hours"
                            type="number"
                            min="0"
                            step="0.25"
                            class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Выгрузка, ч">Выгр.</span>
                        <input
                            v-model.number="normRow.norm_unloading_hours"
                            type="number"
                            min="0"
                            step="0.25"
                            class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="!isOrderFormEditable"
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
