<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Введите суммы заказчика и перевозчика — маржа и комментарий по трём вариантам сделки. Поля «Без НДС» и «С НДС» не пересчитывают друг друга."
            title="Считалка"
        />

        <div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <section :class="`${crmPanel} space-y-5 p-5`">
                <div
                    class="grid min-w-[28rem] grid-cols-[5.5rem_minmax(0,1fr)_minmax(0,1fr)_minmax(5.5rem,7.5rem)] gap-x-3 gap-y-4 text-sm"
                >
                    <div />
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Без НДС, ₽
                    </div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        С НДС, ₽
                    </div>
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        Бонус
                    </div>

                    <div class="self-center font-semibold text-zinc-800 dark:text-zinc-100">
                        Заказчик
                    </div>
                    <div class="min-w-0 self-center">
                        <input
                            v-model="amounts.customer_without_vat"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="—"
                            @input="scheduleRecalculate"
                        />
                    </div>
                    <div class="min-w-0 self-center">
                        <input
                            v-model="amounts.customer_with_vat"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="—"
                            @input="scheduleRecalculate"
                        />
                    </div>
                    <div class="min-w-0 self-center">
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

                    <div class="self-center font-semibold text-zinc-800 dark:text-zinc-100">
                        Перевозчик
                    </div>
                    <div class="min-w-0 self-end">
                        <input
                            v-model="amounts.carrier_without_vat"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="—"
                            @input="scheduleRecalculate"
                        />
                    </div>
                    <div class="min-w-0 self-end">
                        <input
                            v-model="amounts.carrier_with_vat"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="—"
                            @input="scheduleRecalculate"
                        />
                    </div>
                    <label class="block min-w-0 space-y-1 self-end">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Доп. расходы</span>
                        <input
                            v-model="amounts.additional_expenses"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        />
                    </label>
                </div>
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
import { reactive, ref } from 'vue';
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
});

const amounts = reactive({
    customer_without_vat: '',
    customer_with_vat: '',
    carrier_without_vat: '',
    carrier_with_vat: '',
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
                customer_without_vat: parseAmount(amounts.customer_without_vat),
                customer_with_vat: parseAmount(amounts.customer_with_vat),
                carrier_without_vat: parseAmount(amounts.carrier_without_vat),
                carrier_with_vat: parseAmount(amounts.carrier_with_vat),
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
