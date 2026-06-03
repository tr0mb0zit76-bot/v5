<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Ставка заказчика — безнал. Перевозчик — наличные и безнал: две сводки по марже и KPI (сделка с наличкой и сделка с НДС)."
            title="Считалка"
        />

        <div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <section :class="`${crmPanel} space-y-5 p-5`">
                <div
                    class="grid min-w-[24rem] grid-cols-[minmax(0,9.5rem)_minmax(0,1fr)_minmax(5.5rem,7.5rem)] items-end gap-x-3 gap-y-4 text-sm"
                >
                    <div />
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Сумма, ₽
                    </div>
                    <div />

                    <div class="pb-0.5 font-semibold text-zinc-800 dark:text-zinc-100">
                        Ставка заказчика
                    </div>
                    <div class="min-w-0">
                        <input
                            v-model="amounts.customer_rate"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="—"
                            @input="scheduleRecalculate"
                        />
                    </div>
                    <div class="min-w-0 space-y-1">
                        <span class="block text-xs leading-4 text-zinc-500 dark:text-zinc-400">Бонус</span>
                        <input
                            v-model="amounts.bonus"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        />
                    </div>

                    <div class="pb-0.5 font-semibold text-zinc-800 dark:text-zinc-100">
                        Ставка перевозчика, безнал
                    </div>
                    <div class="min-w-0">
                        <input
                            v-model="amounts.carrier_cashless_rate"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="—"
                            @input="scheduleRecalculate"
                        />
                    </div>
                    <div class="min-w-0" aria-hidden="true" />

                    <div class="pb-0.5 font-semibold text-zinc-800 dark:text-zinc-100">
                        Ставка перевозчика, нал.
                    </div>
                    <div class="min-w-0">
                        <input
                            v-model="amounts.carrier_cash_rate"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="—"
                            @input="scheduleRecalculate"
                        />
                    </div>
                    <div class="min-w-0 space-y-1">
                        <span class="block text-xs leading-4 text-zinc-500 dark:text-zinc-400">Доп. расходы</span>
                        <input
                            v-model="amounts.additional_expenses"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        />
                    </div>
                </div>

                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                    {{ kpiRatesFootnote }}
                </p>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h3 :class="crmSectionTitle">Сводка</h3>

                <div v-if="loading" class="text-sm text-zinc-500">Считаем…</div>

                <div
                    v-else-if="result?.warning"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    {{ result.warning }}
                </div>

                <ul v-if="result?.summary?.hints?.length" class="space-y-1 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <li v-for="(hint, index) in result.summary.hints" :key="index">{{ hint }}</li>
                </ul>

                <div
                    v-if="result?.scenarios?.length"
                    class="grid gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                >
                    <div
                        v-for="scenario in result.scenarios"
                        :key="scenario.scenario_key"
                        class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40"
                    >
                        <h4 class="font-semibold text-zinc-900 dark:text-zinc-50">{{ scenario.deal_type_label }}</h4>
                        <p class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">
                            {{ scenario.amount_comment }}
                        </p>
                        <p
                            v-if="scenario.kpi_deduction_rates_label"
                            class="text-xs leading-5 text-zinc-500 dark:text-zinc-400"
                        >
                            Вычет KPI с заказчика:
                            {{ scenario.kpi_deduction_rates_label }}
                            <template v-if="scenario.kpi_deduction_amount != null">
                                · {{ formatMoney(scenario.kpi_deduction_amount) }}
                            </template>
                        </p>

                        <div
                            v-if="scenario.margin !== null"
                            class="space-y-1 border-t border-zinc-200/80 pt-3 dark:border-zinc-700"
                        >
                            <div class="text-2xl font-semibold tabular-nums">{{ formatMoney(scenario.margin) }}</div>
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ scenario.margin_percent }}% маржи
                            </div>
                            <p class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">
                                {{ scenario.comment }}
                            </p>
                        </div>

                        <p v-else class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ scenario.comment }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmFieldFluid,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-counter' }, () => page),
});

const props = defineProps({
    orderDate: {
        type: String,
        default: '',
    },
    kpiSettings: {
        type: Object,
        default: () => ({}),
    },
});

const activeKpiSettings = computed(() => result.value?.kpi_settings ?? props.kpiSettings ?? {});

function formatPercent(value) {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return '0';
    }

    return String(numeric).replace(/\.?0+$/, '');
}

const kpiRatesFootnote = computed(() => {
    const rates = activeKpiSettings.value?.deduction_rates;
    const multiplier = activeKpiSettings.value?.bonus_multiplier;

    if (!rates) {
        return 'Бонус и доп. расходы учитываются в обеих сводках. Вычеты KPI — из настроек мотивации.';
    }

    const cash = `${formatPercent(rates.cash_primary_percent)}% + ${formatPercent(rates.cash_secondary_percent)}%`;
    const vat = `${formatPercent(rates.vat_percent)}%`;
    const bonusPart = multiplier != null ? ` Бонус ×${formatPercent(multiplier)}.` : '';

    return `Вычеты KPI (настройки мотивации): наличка ${cash}, НДС ${vat} с суммы заказчика.${bonusPart} Доп. расходы и бонус — в обеих сводках.`;
});

const amounts = reactive({
    customer_rate: '',
    carrier_cash_rate: '',
    carrier_cashless_rate: '',
    bonus: '0',
    additional_expenses: '0',
});

const loading = ref(false);
const result = ref(null);
let debounceTimer = null;

function parseAmount(value) {
    const trimmed = String(value ?? '').trim().replace(/\s+/g, '').replace(',', '.');

    if (trimmed === '') {
        return null;
    }

    const numeric = Number(trimmed);

    if (!Number.isFinite(numeric) || numeric < 0) {
        return null;
    }

    return numeric;
}

function scheduleRecalculate() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(recalculate, 450);
}

async function recalculate() {
    loading.value = true;

    try {
        const response = await fetch(route('sales-assistant.counter.calculate'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                customer_rate: parseAmount(amounts.customer_rate),
                carrier_cash_rate: parseAmount(amounts.carrier_cash_rate),
                carrier_cashless_rate: parseAmount(amounts.carrier_cashless_rate),
                bonus: parseAmount(amounts.bonus) ?? 0,
                additional_expenses: parseAmount(amounts.additional_expenses) ?? 0,
                order_date: props.orderDate || undefined,
            }),
        });

        result.value = await response.json();
    } catch {
        result.value = { error: 'Не удалось выполнить расчёт.' };
    } finally {
        loading.value = false;
    }
}

function formatMoney(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }

    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 2,
    }).format(Number(value));
}

recalculate();
</script>
