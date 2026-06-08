<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Вычеты с суммы заказчика: НДС у всех, прочие НДС, наличка, НДС 0% / 22%."
            title="Настройки вычетов"
        />

        <section :class="`${crmPanel} flex min-h-0 flex-col p-5`">
            <div class="mb-4 space-y-1">
                <h2 class="text-lg font-semibold">Вычеты KPI и delta</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    «НДС у всех» — заказчик и все перевозчики с НДС. «Прочие НДС» — остальные сочетания (0% / 0%, без НДС у одной стороны и т.д.).
                </p>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,220px)_1fr]">
                <label class="space-y-1">
                    <span class="text-sm font-medium">Множитель бонуса в delta</span>
                    <input
                        v-model.number="settingsForm.bonus_multiplier"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="crmFieldFluid"
                    >
                </label>

                <div class="border border-zinc-200 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    Сейчас множитель применяется так:
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">bonus * {{ formattedBonusMultiplier }}</span>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-50">НДС у всех</h3>
                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                        Заказчик платит с НДС, всем перевозчикам (все плечи) — тоже с НДС. Типичная прямая сделка 22% / 22%.
                    </p>
                    <label class="block space-y-1">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Вычет с суммы заказчика, %</span>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="settingsForm.deduction_rates.vat_all_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :class="`${crmField} w-28`"
                            >
                            <span class="text-zinc-500 dark:text-zinc-400">%</span>
                        </div>
                    </label>
                </div>

                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-50">Прочие НДС</h3>
                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                        Остальные сочетания: 0% / 0%, без НДС у одной стороны, разные ставки НДС, наличные у заказчика.
                    </p>
                    <label class="block space-y-1">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Вычет с суммы заказчика, %</span>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="settingsForm.deduction_rates.vat_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :class="`${crmField} w-28`"
                            >
                            <span class="text-zinc-500 dark:text-zinc-400">%</span>
                        </div>
                    </label>
                </div>

                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-50">Наличка</h3>
                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                        Когда у всех перевозчиков (все плечи) — «Наличные». Форма оплаты заказчика не важна.
                    </p>
                    <label class="block space-y-1">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Первый вычет, %</span>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="settingsForm.deduction_rates.cash_primary_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :class="`${crmField} w-28`"
                            >
                            <span class="text-zinc-500 dark:text-zinc-400">%</span>
                        </div>
                    </label>
                    <label class="block space-y-1">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Второй вычет, %</span>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="settingsForm.deduction_rates.cash_secondary_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :class="`${crmField} w-28`"
                            >
                            <span class="text-zinc-500 dark:text-zinc-400">%</span>
                        </div>
                    </label>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Эффективный вычет (последовательно): {{ formattedCashEffectivePercent }}%.
                        Не сумма процентов ({{ formattedCashNominalPercent }}%).
                    </p>
                </div>

                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-50">НДС 0% / 22%</h3>
                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                        Заказчик «С НДС 0%», у перевозчика (рейс или плечо) «С НДС 22%». Не путать с вариантом 0% / 0% — он в блоке «НДС».
                    </p>
                    <label class="block space-y-1">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Вычет KPI с суммы заказчика, %</span>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="settingsForm.deduction_rates.vat_zero_22_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :class="`${crmField} w-28`"
                            >
                            <span class="text-zinc-500 dark:text-zinc-400">%</span>
                        </div>
                    </label>
                    <label class="block space-y-1">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Доплата к марже (delta), %</span>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="settingsForm.deduction_rates.vat_zero_22_supplement_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :class="`${crmField} w-28`"
                            >
                            <span class="text-zinc-500 dark:text-zinc-400">%</span>
                        </div>
                    </label>
                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                        Доплата считается от суммы перевозчиков с НДС 22% и прибавляется к delta после вычета KPI и расходов.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <p v-if="settingsForm.hasErrors" class="text-sm text-red-600 dark:text-red-400">
                    Проверьте проценты вычетов и множитель бонуса.
                </p>
                <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Изменения применяются ко всем новым расчётам delta.
                </div>

                <button
                    type="button"
                    :class="crmBtnCreate"
                    :disabled="settingsForm.processing"
                    @click="saveSettings"
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
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmField, crmFieldFluid, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'motivation', activeLeafKey: 'kpi-settings' }, () => page),
});

const props = defineProps({
    bonusMultiplier: {
        type: Number,
        default: 1.3,
    },
    deductionRates: {
        type: Object,
        default: () => ({
            vat_percent: 3,
            vat_all_percent: 4,
            vat_zero_22_percent: 3,
            vat_zero_22_supplement_percent: 15,
            cash_primary_percent: 3,
            cash_secondary_percent: 21,
        }),
    },
});

const settingsForm = useForm({
    bonus_multiplier: props.bonusMultiplier,
    deduction_rates: {
        vat_percent: Number(props.deductionRates.vat_percent ?? props.deductionRates.cashless_percent ?? 3),
        vat_all_percent: Number(props.deductionRates.vat_all_percent ?? 4),
        vat_zero_22_percent: Number(props.deductionRates.vat_zero_22_percent ?? 3),
        vat_zero_22_supplement_percent: Number(props.deductionRates.vat_zero_22_supplement_percent ?? 15),
        cash_primary_percent: Number(props.deductionRates.cash_primary_percent ?? 3),
        cash_secondary_percent: Number(props.deductionRates.cash_secondary_percent ?? 21),
    },
});

const formattedBonusMultiplier = computed(() => Number(settingsForm.bonus_multiplier || 0).toFixed(2));

const formattedCashNominalPercent = computed(() => {
    const primary = Number(settingsForm.deduction_rates.cash_primary_percent || 0);
    const secondary = Number(settingsForm.deduction_rates.cash_secondary_percent || 0);

    return (primary + secondary).toFixed(2);
});

const formattedCashEffectivePercent = computed(() => {
    const primary = Number(settingsForm.deduction_rates.cash_primary_percent || 0);
    const secondary = Number(settingsForm.deduction_rates.cash_secondary_percent || 0);
    const effective = 100 * (1 - (1 - primary / 100) * (1 - secondary / 100));

    return effective.toFixed(2);
});

function saveSettings() {
    settingsForm.patch(route('settings.motivation.kpi.update'), {
        preserveScroll: true,
    });
}
</script>
