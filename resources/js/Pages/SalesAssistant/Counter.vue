<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Пять связанных полей. При «наличные — наличные» маржа = доход − расход, без вычета KPI."
            title="Считалка"
        />

        <div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <section :class="`${crmPanel} space-y-5 p-5`">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in dealPresets"
                        :key="preset.key"
                        type="button"
                        :class="activePreset === preset.key ? crmPillActive : crmPill"
                        @click="applyDealPreset(preset.key)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
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
                </div>

                <div class="space-y-4">
                    <div>
                        <h3 :class="crmSectionTitle">Заказчик</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label :class="crmFilterField">
                                <span :class="crmLabel">Без НДС</span>
                                <input
                                    v-model.number="form.customer_without_vat"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :class="crmFieldFluid"
                                    @input="onFieldInput('customer_without_vat')"
                                />
                            </label>
                            <label :class="crmFilterField">
                                <span :class="crmLabel">С НДС ({{ customerVatRateLabel }}%)</span>
                                <input
                                    v-model.number="form.customer_with_vat"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :class="crmFieldFluid"
                                    @input="onFieldInput('customer_with_vat')"
                                />
                            </label>
                        </div>
                    </div>

                    <div>
                        <h3 :class="crmSectionTitle">Перевозчик</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label :class="crmFilterField">
                                <span :class="crmLabel">Без НДС</span>
                                <input
                                    v-model.number="form.carrier_without_vat"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :class="crmFieldFluid"
                                    @input="onFieldInput('carrier_without_vat')"
                                />
                            </label>
                            <label :class="crmFilterField">
                                <span :class="crmLabel">С НДС ({{ carrierVatRateLabel }}%)</span>
                                <input
                                    v-model.number="form.carrier_with_vat"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :class="crmFieldFluid"
                                    @input="onFieldInput('carrier_with_vat')"
                                />
                            </label>
                        </div>
                    </div>

                    <div>
                        <h3 :class="crmSectionTitle">Маржа (дельта)</h3>
                        <label :class="`${crmFilterField} mt-3 max-w-sm`">
                            <span :class="crmLabel">Сумма, ₽</span>
                            <input
                                v-model.number="form.margin"
                                type="number"
                                step="0.01"
                                :class="crmFieldFluid"
                                @input="onFieldInput('margin')"
                            />
                        </label>
                    </div>
                </div>

                <details class="text-sm">
                    <summary class="cursor-pointer font-medium text-zinc-700 dark:text-zinc-300">Доп. расходы и порог</summary>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label :class="crmFilterField">
                            <span :class="crmLabel">Мин. маржа, %</span>
                            <input v-model.number="form.min_margin_percent" type="number" min="0" max="100" step="0.1" :class="crmFieldFluid" @input="onContextChange" />
                        </label>
                        <label :class="crmFilterField">
                            <span :class="crmLabel">Доп. расходы</span>
                            <input v-model.number="form.additional_expenses" type="number" min="0" step="0.01" :class="crmFieldFluid" @input="onContextChange" />
                        </label>
                        <label :class="crmFilterField">
                            <span :class="crmLabel">Страховка</span>
                            <input v-model.number="form.insurance" type="number" min="0" step="0.01" :class="crmFieldFluid" @input="onContextChange" />
                        </label>
                        <label :class="crmFilterField">
                            <span :class="crmLabel">Бонус</span>
                            <input v-model.number="form.bonus" type="number" min="0" step="0.01" :class="crmFieldFluid" @input="onContextChange" />
                        </label>
                    </div>
                </details>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h3 :class="crmSectionTitle">Сводка</h3>

                <div v-if="loading" class="text-sm text-zinc-500">Считаем…</div>
                <div v-else-if="result?.error" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ result.error }}
                </div>
                <template v-else-if="result">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span :class="crmPill">{{ result.deal_type_label }}</span>
                        <span v-if="!result.cash_to_cash" :class="crmPill">KPI {{ result.kpi_percent }}%</span>
                        <span v-if="result.cash_to_cash" :class="crmPill">Наличные · без KPI</span>
                    </div>

                    <ul v-if="result.summary?.hints?.length" class="space-y-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        <li v-for="(hint, index) in result.summary.hints" :key="index">{{ hint }}</li>
                    </ul>

                    <div
                        v-if="result.margin_percent !== null && result.fields?.margin !== null"
                        class="space-y-1 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                    >
                        <div :class="crmPageEyebrow">Оценка маржи</div>
                        <div class="text-3xl font-semibold tabular-nums">{{ formatMoney(result.fields.margin) }}</div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ result.margin_percent }}% от ставки заказчика
                            <span
                                class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="qualityClass(result.margin_quality)"
                            >
                                {{ result.margin_quality_label }}
                            </span>
                            <span class="text-zinc-400"> · порог {{ result.min_margin_percent }}%</span>
                        </div>
                        <div v-if="result.salary_accrued !== null" class="text-xs text-zinc-500 dark:text-zinc-400">
                            Начисление по сделке: {{ formatMoney(result.salary_accrued) }}
                        </div>
                    </div>

                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        <template v-if="result.cash_to_cash">
                            Наличные у заказчика и перевозчика: маржа = доход − расход (ставка заказчика − перевозчик − доп. расходы).
                        </template>
                        <template v-else>
                            Поля «с НДС» — для переговоров; в формуле дельты используются суммы без НДС и вычитается KPI периода.
                        </template>
                    </p>
                </template>
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
    crmFilterField,
    crmLabel,
    crmPageEyebrow,
    crmPanel,
    crmPill,
    crmPillActive,
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

const dealPresets = [
    { key: 'direct', label: 'Прямая' },
    { key: 'indirect', label: 'Кривая' },
    { key: 'cash', label: 'Наличные' },
];

const form = reactive({
    customer_without_vat: null,
    customer_with_vat: null,
    carrier_without_vat: null,
    carrier_with_vat: null,
    margin: null,
    customer_payment_form: props.defaultCustomerPaymentForm,
    carrier_payment_form: 'no_vat',
    min_margin_percent: props.defaultMinMarginPercent,
    additional_expenses: 0,
    insurance: 0,
    bonus: 0,
});

const activePreset = ref(null);
const anchorField = ref(null);
const loading = ref(false);
const result = ref(null);
let debounceTimer = null;

const customerVatRateLabel = computed(() => formatRate(result.value?.fields?.customer_vat_rate_percent ?? 20));
const carrierVatRateLabel = computed(() => formatRate(result.value?.fields?.carrier_vat_rate_percent ?? 20));

function formatRate(value) {
    const numeric = Number(value ?? 0);

    return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(1).replace(/\.0$/, '');
}

function applyDealPreset(key) {
    activePreset.value = key;
    const vatDefault = props.paymentFormOptions.find((o) => String(o.value).startsWith('vat_'))?.value
        ?? props.defaultCustomerPaymentForm;

    if (key === 'cash') {
        form.customer_payment_form = 'cash';
        form.carrier_payment_form = 'cash';
    } else if (key === 'direct') {
        form.carrier_payment_form = form.customer_payment_form;
    } else if (key === 'indirect') {
        form.customer_payment_form = 'no_vat';
        form.carrier_payment_form = vatDefault;
    }

    onContextChange();
}

function onFieldInput(field) {
    anchorField.value = field;
    scheduleRecalculate();
}

function onContextChange() {
    if (activePreset.value === 'direct') {
        form.carrier_payment_form = form.customer_payment_form;
    }

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
    form.margin = fields.margin;
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
                margin: form.margin,
                customer_payment_form: form.customer_payment_form,
                carrier_payment_form: form.carrier_payment_form,
                min_margin_percent: form.min_margin_percent,
                additional_expenses: form.additional_expenses,
                insurance: form.insurance,
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
