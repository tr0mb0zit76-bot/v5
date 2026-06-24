<script setup>
import { computed } from 'vue';
import { crmFieldFluid, crmLabel } from '@/support/crmUi.js';

const targetPrice = defineModel('targetPrice', { type: [Number, String, null], default: null });
const targetCurrency = defineModel('targetCurrency', { type: String, default: 'RUB' });
const calculatedCost = defineModel('calculatedCost', { type: [Number, String, null], default: null });
const customerPaymentForm = defineModel('customerPaymentForm', { type: [String, null], default: null });
const carrierPaymentForm = defineModel('carrierPaymentForm', { type: [String, null], default: null });

const props = defineProps({
    currencyOptions: { type: Array, default: () => [] },
    paymentFormOptions: { type: Array, default: () => [] },
    expectedMargin: { type: [Number, String, null], default: null },
});

const previewMargin = computed(() => {
    const client = parseAmount(targetPrice.value);
    const carrier = parseAmount(calculatedCost.value);

    if (client === null || carrier === null) {
        return props.expectedMargin ?? null;
    }

    return Math.round((client - carrier) * 100) / 100;
});

function parseAmount(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value));
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="text-base font-semibold">Финансы</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Ставки заказчика и перевозчика без графика оплат и сроков — только сумма и форма оплаты.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="space-y-2">
                <label :class="crmLabel">Валюта</label>
                <select v-model="targetCurrency" :class="crmFieldFluid">
                    <option v-for="option in currencyOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>

        <section class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400">Заказчик</h4>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label :class="crmLabel">Цена клиента</label>
                    <input v-model="targetPrice" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Форма оплаты</label>
                    <select v-model="customerPaymentForm" :class="crmFieldFluid">
                        <option value="">Не указано</option>
                        <option v-for="option in paymentFormOptions" :key="`customer-${option.value}`" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400">Перевозчик</h4>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label :class="crmLabel">Цена перевозчика</label>
                    <input v-model="calculatedCost" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Форма оплаты</label>
                    <select v-model="carrierPaymentForm" :class="crmFieldFluid">
                        <option value="">Не указано</option>
                        <option v-for="option in paymentFormOptions" :key="`carrier-${option.value}`" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900/40">
            <span class="text-zinc-500 dark:text-zinc-400">Ожидаемая маржа:</span>
            <span class="ml-2 font-medium tabular-nums text-zinc-900 dark:text-zinc-50">
                {{ formatMoney(previewMargin) }}
                <span v-if="previewMargin !== null && targetCurrency" class="text-zinc-500 dark:text-zinc-400">{{ targetCurrency }}</span>
            </span>
        </div>
    </div>
</template>
