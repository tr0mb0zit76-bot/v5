<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Ставка заказчика и перевозчика по форме оплаты — маржа с учётом KPI и процента от ставки заказчика."
            title="Считалка"
        />

        <div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <section :class="`${crmPanel} space-y-5 p-5`">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label :class="crmFilterField">
                        <span :class="crmLabel">Оплата заказчика</span>
                        <select v-model="form.customer_payment_form" :class="crmFieldFluid" @change="onContextChange">
                            <option v-for="opt in paymentFormOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabel">Оплата перевозчику</span>
                        <select v-model="form.carrier_payment_form" :class="crmFieldFluid" @change="onContextChange">
                            <option v-for="opt in paymentFormOptions" :key="`c-${opt.value}`" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabel">Бонус</span>
                        <input
                            v-model.number="form.bonus"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="crmFieldFluid"
                            @input="onContextChange"
                        />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabel">Доп. расходы</span>
                        <input
                            v-model.number="form.additional_expenses"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="crmFieldFluid"
                            @input="onContextChange"
                        />
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[28rem] border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="pb-2 pr-3 w-28" />
                                <th class="pb-2 px-2">Без НДС, ₽</th>
                                <th class="pb-2 px-2">С НДС, ₽</th>
                            </tr>
                        </thead>
                        <tbody class="align-top">
                            <tr>
                                <th scope="row" class="py-2 pr-3 text-left font-semibold text-zinc-800 dark:text-zinc-100">
                                    Заказчик
                                </th>
                                <td class="px-2 py-2">
                                    <input
                                        v-model.number="form.customer_without_vat"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :class="crmFieldFluid"
                                        placeholder="0"
                                        @input="onFieldInput('customer_without_vat')"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        v-model.number="form.customer_with_vat"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :class="crmFieldFluid"
                                        placeholder="0"
                                        @input="onFieldInput('customer_with_vat')"
                                    />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" class="py-2 pr-3 text-left font-semibold text-zinc-800 dark:text-zinc-100">
                                    Перевозчик
                                </th>
                                <td class="px-2 py-2">
                                    <input
                                        v-model.number="form.carrier_without_vat"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :class="crmFieldFluid"
                                        placeholder="0"
                                        @input="onFieldInput('carrier_without_vat')"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        v-model.number="form.carrier_with_vat"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :class="crmFieldFluid"
                                        placeholder="0"
                                        @input="onFieldInput('carrier_with_vat')"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <label :class="`${crmFilterField} max-w-xs`">
                    <span :class="crmLabel">Мин. маржа, % (порог)</span>
                    <input
                        v-model.number="form.min_margin_percent"
                        type="number"
                        min="0"
                        max="100"
                        step="0.1"
                        :class="crmFieldFluid"
                        @input="onContextChange"
                    />
                </label>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h3 :class="crmSectionTitle">Сводка</h3>

                <div v-if="loading" class="text-sm text-zinc-500">Считаем…</div>
                <div
                    v-else-if="result?.error"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    {{ result.error }}
                </div>
                <template v-else-if="result">
                    <ul v-if="result.summary?.hints?.length" class="space-y-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        <li v-for="(hint, index) in result.summary.hints" :key="index">{{ hint }}</li>
                    </ul>

                    <div
                        v-if="result.scenarios?.length"
                        class="grid gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                        :class="result.scenarios.length >= 3 ? 'lg:grid-cols-3' : 'sm:grid-cols-2'"
                    >
                        <div
                            v-for="scenario in result.scenarios"
                            :key="scenario.scenario_key"
                            class="space-y-3 rounded-xl border p-4"
                            :class="scenario.matches_payment_forms
                                ? 'border-emerald-300 bg-emerald-50/50 dark:border-emerald-800 dark:bg-emerald-950/20'
                                : 'border-zinc-200 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-900/40'"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-semibold text-zinc-900 dark:text-zinc-50">{{ scenario.deal_type_label }}</h4>
                                <span
                                    v-if="scenario.matches_payment_forms"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200"
                                >
                                    по формам оплаты
                                </span>
                            </div>

                            <p class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">
                                {{ scenario.period_note }}
                            </p>

                            <div
                                v-if="scenario.margin !== null"
                                class="space-y-1 border-t border-zinc-200/80 pt-3 dark:border-zinc-700"
                            >
                                <div :class="crmPageEyebrow">Маржа (дельта)</div>
                                <div class="text-2xl font-semibold tabular-nums">{{ formatMoney(scenario.margin) }}</div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ scenario.margin_percent }}% · KPI {{ scenario.kpi_percent }}%
                                    <span
                                        class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="qualityClass(scenario.margin_quality)"
                                    >
                                        {{ scenario.margin_quality_label }}
                                    </span>
                                </div>
                                <div v-if="scenario.salary_accrued !== null" class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Начисление: {{ formatMoney(scenario.salary_accrued) }}
                                </div>
                            </div>

                            <div
                                v-else-if="scenario.max_carrier_without_vat !== null"
                                class="space-y-1 border-t border-zinc-200/80 pt-3 text-sm dark:border-zinc-700"
                            >
                                <div :class="crmPageEyebrow">Макс. перевозчик</div>
                                <div class="font-semibold tabular-nums">{{ formatMoney(scenario.max_carrier_without_vat) }} без НДС</div>
                                <div v-if="scenario.max_carrier_with_vat !== null" class="text-zinc-600 dark:text-zinc-400">
                                    {{ formatMoney(scenario.max_carrier_with_vat) }} с НДС · KPI {{ scenario.kpi_percent }}%
                                </div>
                            </div>

                            <div
                                v-else-if="scenario.min_customer_without_vat !== null"
                                class="space-y-1 border-t border-zinc-200/80 pt-3 text-sm dark:border-zinc-700"
                            >
                                <div :class="crmPageEyebrow">Мин. заказчик</div>
                                <div class="font-semibold tabular-nums">{{ formatMoney(scenario.min_customer_without_vat) }} без НДС</div>
                                <div v-if="scenario.min_customer_with_vat !== null" class="text-zinc-600 dark:text-zinc-400">
                                    {{ formatMoney(scenario.min_customer_with_vat) }} с НДС · KPI {{ scenario.kpi_percent }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmFieldFluid,
    crmFilterField,
    crmLabel,
    crmPageEyebrow,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-counter' }, () => page),
});

const props = defineProps({
    paymentFormOptions: {
        type: Array,
        default: () => [],
    },
    defaultMinMarginPercent: {
        type: Number,
        default: 10,
    },
    defaultCustomerPaymentForm: {
        type: String,
        default: 'no_vat',
    },
    orderDate: {
        type: String,
        default: '',
    },
});

const form = reactive({
    customer_without_vat: null,
    customer_with_vat: null,
    carrier_without_vat: null,
    carrier_with_vat: null,
    customer_payment_form: props.defaultCustomerPaymentForm,
    carrier_payment_form: 'no_vat',
    min_margin_percent: props.defaultMinMarginPercent,
    additional_expenses: 0,
    bonus: 0,
});

const anchorField = ref(null);
const loading = ref(false);
const result = ref(null);
let debounceTimer = null;

function onFieldInput(field) {
    anchorField.value = field;
    scheduleRecalculate();
}

function onContextChange() {
    scheduleRecalculate();
}

function scheduleRecalculate() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(recalculate, 300);
}

function applyServerFields(fields) {
    if (!fields) {
        return;
    }

    form.customer_without_vat = fields.customer_without_vat;
    form.customer_with_vat = fields.customer_with_vat;
    form.carrier_without_vat = fields.carrier_without_vat;
    form.carrier_with_vat = fields.carrier_with_vat;
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
                anchor_field: anchorField.value,
                customer_without_vat: form.customer_without_vat,
                customer_with_vat: form.customer_with_vat,
                carrier_without_vat: form.carrier_without_vat,
                carrier_with_vat: form.carrier_with_vat,
                customer_payment_form: form.customer_payment_form,
                carrier_payment_form: form.carrier_payment_form,
                min_margin_percent: form.min_margin_percent,
                additional_expenses: form.additional_expenses,
                bonus: form.bonus,
                order_date: props.orderDate,
            }),
        });

        result.value = await response.json();
        applyServerFields(result.value?.fields);
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

function qualityClass(quality) {
    if (quality === 'below_minimum') {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200';
    }

    if (quality === 'acceptable') {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200';
    }

    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200';
}

recalculate();
</script>
