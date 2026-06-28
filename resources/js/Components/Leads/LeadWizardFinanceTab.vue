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
    routePriceBenchmark: { type: Object, default: null },
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

        <section
            v-if="routePriceBenchmark?.available"
            class="space-y-3 rounded-xl border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-900/40 dark:bg-sky-950/20"
        >
            <div>
                <h4 class="text-sm font-semibold text-sky-950 dark:text-sky-100">Похожие перевозки</h4>
                <p class="mt-1 text-xs text-sky-900/80 dark:text-sky-100/80">
                    По маршруту «{{ routePriceBenchmark.loading_location || '—' }} → {{ routePriceBenchmark.unloading_location || '—' }}»
                    · выборка {{ routePriceBenchmark.sample_size }}
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-lg border border-sky-200/70 bg-white/80 p-3 dark:border-sky-900/30 dark:bg-zinc-950/40">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Ставка заказчика</div>
                    <div class="mt-1 text-sm tabular-nums">
                        {{ formatMoney(routePriceBenchmark.customer_rate.min) }} —
                        {{ formatMoney(routePriceBenchmark.customer_rate.avg) }} —
                        {{ formatMoney(routePriceBenchmark.customer_rate.max) }}
                        {{ routePriceBenchmark.customer_rate.currency }}
                    </div>
                </article>
                <article class="rounded-lg border border-sky-200/70 bg-white/80 p-3 dark:border-sky-900/30 dark:bg-zinc-950/40">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Ставка перевозчика</div>
                    <div class="mt-1 text-sm tabular-nums">
                        {{ formatMoney(routePriceBenchmark.carrier_rate.min) }} —
                        {{ formatMoney(routePriceBenchmark.carrier_rate.avg) }} —
                        {{ formatMoney(routePriceBenchmark.carrier_rate.max) }}
                        {{ routePriceBenchmark.carrier_rate.currency }}
                    </div>
                </article>
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
