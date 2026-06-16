<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Ставка заказчика и перевозчика + условие вычета из настроек мотивации."
            title="Считалка"
        />

        <div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
            <section :class="`${crmPanel} space-y-5 p-5`">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Ставка заказчика, ₽</span>
                        <input
                            v-model="amounts.customer_rate"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        >
                    </label>

                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Ставка перевозчика, ₽</span>
                        <input
                            v-model="amounts.carrier_rate"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        >
                    </label>
                </div>

                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Условие вычета</span>
                    <select
                        v-model="selectedRuleId"
                        :class="crmFieldFluid"
                        @change="scheduleRecalculate"
                    >
                        <option v-if="deductionRules.length === 0" value="">
                            Нет активных правил на сегодня
                        </option>
                        <option
                            v-for="rule in deductionRules"
                            :key="rule.id"
                            :value="String(rule.id)"
                        >
                            {{ rule.name }} · {{ rule.deduction_label }}
                        </option>
                    </select>
                    <p
                        v-if="selectedRule?.description"
                        class="mt-1.5 text-xs leading-5 text-zinc-500 dark:text-zinc-400"
                    >
                        {{ selectedRule.description }}
                    </p>
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Бонус, ₽</span>
                        <input
                            v-model="amounts.bonus"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        >
                    </label>

                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Доп. расходы, ₽</span>
                        <input
                            v-model="amounts.additional_expenses"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        >
                    </label>
                </div>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h3 :class="crmSectionTitle">Сводка</h3>

                <div v-if="loading" class="text-sm text-zinc-500">Считаем…</div>

                <div
                    v-else-if="result?.error"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100"
                >
                    {{ result.error }}
                </div>

                <div
                    v-else-if="result?.warning"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    {{ result.warning }}
                </div>

                <template v-else-if="result?.summary">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-zinc-500 dark:text-zinc-400">Вычет KPI</span>
                            <span class="font-medium tabular-nums">
                                {{ formatMoney(result.summary.kpi_deduction_amount) }}
                                <span class="text-zinc-500 dark:text-zinc-400">({{ result.summary.kpi_deduction_rates_label }})</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-zinc-500 dark:text-zinc-400">Расходы (перевозчик + доп.)</span>
                            <span class="font-medium tabular-nums">{{ formatMoney(result.summary.fixed_expense) }}</span>
                        </div>
                        <div
                            v-if="Number(result.summary.margin_supplement) > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-zinc-500 dark:text-zinc-400">Доплата к марже</span>
                            <span class="font-medium tabular-nums">{{ formatMoney(result.summary.margin_supplement) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <div
                            class="text-3xl font-semibold tabular-nums"
                            :class="Number(result.summary.margin) >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                        >
                            {{ formatMoney(result.summary.margin) }}
                        </div>
                        <div class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ result.summary.margin_percent }}% маржи
                        </div>
                        <p class="mt-3 text-xs leading-5 text-zinc-600 dark:text-zinc-400">
                            {{ result.summary.comment }}
                        </p>
                    </div>
                </template>

                <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Укажите ставки и выберите условие вычета.
                </p>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmFieldFluid,
    crmFilterField,
    crmLabelCompact,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-counter' }, () => page),
});

const props = defineProps({
    deductionRules: {
        type: Array,
        default: () => [],
    },
    orderDate: {
        type: String,
        default: '',
    },
});

const amounts = reactive({
    customer_rate: '',
    carrier_rate: '',
    bonus: '0',
    additional_expenses: '0',
});

const selectedRuleId = ref('');
const loading = ref(false);
const result = ref(null);
let debounceTimer = null;

const selectedRule = computed(() => props.deductionRules.find(
    (rule) => String(rule.id) === String(selectedRuleId.value),
) ?? null);

watch(
    () => props.deductionRules,
    (rules) => {
        if (selectedRuleId.value === '' && rules.length > 0) {
            selectedRuleId.value = String(rules[0].id);
            scheduleRecalculate();
        }
    },
    { immediate: true },
);

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
    if (!selectedRuleId.value) {
        result.value = { warning: 'Выберите условие вычета из списка.' };

        return;
    }

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
                kpi_deduction_rule_id: Number.parseInt(selectedRuleId.value, 10),
                customer_rate: parseAmount(amounts.customer_rate),
                carrier_rate: parseAmount(amounts.carrier_rate),
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
</script>
