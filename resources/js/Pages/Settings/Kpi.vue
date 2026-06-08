<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            :lead="`Для заказов с ${formattedCutoffDate} действуют только пользовательские условия ниже. Более высокий приоритет проверяется первым.`"
            title="Настройки вычетов"
        />

        <section :class="`${crmPanel} flex min-h-0 flex-col p-5`">
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,220px)_1fr]">
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

            <div class="mb-4 flex justify-end">
                <button
                    type="button"
                    :class="crmBtnCreate"
                    :disabled="settingsForm.processing"
                    @click="saveBonusSettings"
                >
                    Сохранить множитель
                </button>
            </div>
        </section>

        <div class="grid min-h-0 flex-1 gap-3 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
            <section :class="`${crmPanel} flex min-h-0 flex-col gap-4 p-5`">
                <div>
                    <h2 class="text-lg font-semibold">Добавить условие</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Выберите сочетание форм оплаты, укажите вычет и период действия.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <label class="space-y-1">
                        <span class="text-sm font-medium">Название</span>
                        <input v-model="createRuleForm.name" type="text" :class="crmFieldFluid">
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium">Приоритет</span>
                        <input v-model.number="createRuleForm.priority" type="number" min="1" max="9999" :class="crmFieldFluid">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Чем выше число, тем раньше проверяется условие.</span>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium">Форма оплаты заказчика</span>
                        <select v-model="createRuleForm.customer_payment_form" :class="crmFieldFluid">
                            <option value="">Любая</option>
                            <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium">НДС заказчика, %</span>
                        <input
                            v-model.number="createRuleForm.customer_vat_rate_percent"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            placeholder="Не задано"
                            :class="crmFieldFluid"
                        >
                    </label>

                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="createRuleForm.customer_positive_vat_required" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400">
                        <span>Заказчик с НДС &gt; 0%</span>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium">Условие по перевозчикам</span>
                        <select v-model="createRuleForm.carrier_rule" :class="crmFieldFluid">
                            <option v-for="option in carrierRuleOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label v-if="needsCarrierForms(createRuleForm.carrier_rule)" class="space-y-1">
                        <span class="text-sm font-medium">Формы перевозчиков</span>
                        <select
                            v-model="createRuleForm.carrier_payment_forms"
                            multiple
                            :class="crmFieldFluid"
                            class="min-h-[88px]"
                        >
                            <option v-for="option in paymentFormOptions" :key="`carrier-${option.value}`" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label v-if="needsCarrierVatRate(createRuleForm.carrier_rule)" class="space-y-1">
                        <span class="text-sm font-medium">Ставка НДС перевозчика, %</span>
                        <input v-model.number="createRuleForm.carrier_vat_rate_percent" type="number" step="0.01" min="0" max="100" :class="crmFieldFluid">
                    </label>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-sm font-medium">Вычет, %</span>
                            <input v-model.number="createRuleForm.deduction_primary_percent" type="number" step="0.01" min="0" max="100" :class="crmFieldFluid">
                        </label>

                        <label class="space-y-1">
                            <span class="text-sm font-medium">Второй вычет, %</span>
                            <input
                                v-model.number="createRuleForm.deduction_secondary_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                placeholder="Не задано"
                                :class="crmFieldFluid"
                            >
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-sm font-medium">Доплата к марже, %</span>
                            <input
                                v-model.number="createRuleForm.margin_supplement_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                placeholder="Не задано"
                                :class="crmFieldFluid"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-sm font-medium">НДС перевозчика для доплаты, %</span>
                            <input
                                v-model.number="createRuleForm.margin_supplement_carrier_vat_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                placeholder="Не задано"
                                :class="crmFieldFluid"
                            >
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-sm font-medium">Действует с</span>
                            <input v-model="createRuleForm.effective_from" type="date" :class="crmFieldFluid">
                        </label>

                        <label class="space-y-1">
                            <span class="text-sm font-medium">Действует по</span>
                            <input v-model="createRuleForm.effective_to" type="date" :class="crmFieldFluid">
                        </label>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="createRuleForm.is_active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400">
                        <span>Условие активно</span>
                    </label>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            :class="crmBtnCreate"
                            :disabled="createRuleForm.processing"
                            @click="storeRule"
                        >
                            Добавить условие
                        </button>
                    </div>
                </div>
            </section>

            <section :class="`${crmPanel} min-h-0 overflow-auto`">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                        <tr>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Название</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Условие</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Вычет</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Приор.</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">С</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">По</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Статус</th>
                            <th class="px-3 py-3 text-right font-medium text-zinc-600 dark:text-zinc-300">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <tr v-for="rule in ruleDrafts" :key="rule.id">
                            <td class="px-3 py-2 align-top">
                                <input
                                    v-model="rule.name"
                                    type="text"
                                    class="w-full min-w-[120px] border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2 align-top text-xs leading-5 text-zinc-600 dark:text-zinc-400">
                                <div class="mb-2 max-w-xs whitespace-normal">{{ rule.description }}</div>
                                <select
                                    v-model="rule.customer_payment_form"
                                    class="mb-1 w-full border border-zinc-300 px-2 py-1 outline-none dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                    <option value="">Заказчик: любая</option>
                                    <option v-for="option in paymentFormOptions" :key="`edit-customer-${rule.id}-${option.value}`" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <select
                                    v-model="rule.carrier_rule"
                                    class="w-full border border-zinc-300 px-2 py-1 outline-none dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                    <option v-for="option in carrierRuleOptions" :key="`edit-carrier-${rule.id}-${option.value}`" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex flex-col gap-1">
                                    <input
                                        v-model.number="rule.deduction_primary_percent"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="w-20 border border-zinc-300 px-2 py-1.5 outline-none dark:border-zinc-700 dark:bg-zinc-950"
                                    >
                                    <input
                                        v-if="rule.deduction_secondary_percent !== null && rule.deduction_secondary_percent !== ''"
                                        v-model.number="rule.deduction_secondary_percent"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        placeholder="2-й %"
                                        class="w-20 border border-zinc-300 px-2 py-1.5 outline-none dark:border-zinc-700 dark:bg-zinc-950"
                                    >
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <input
                                    v-model.number="rule.priority"
                                    type="number"
                                    min="1"
                                    max="9999"
                                    class="w-16 border border-zinc-300 px-2 py-1.5 outline-none dark:border-zinc-700 dark:bg-zinc-950"
                                >
                            </td>
                            <td class="px-3 py-2 align-top">
                                <input v-model="rule.effective_from" type="date" class="border border-zinc-300 px-2 py-1.5 outline-none dark:border-zinc-700 dark:bg-zinc-950">
                            </td>
                            <td class="px-3 py-2 align-top">
                                <input v-model="rule.effective_to" type="date" class="border border-zinc-300 px-2 py-1.5 outline-none dark:border-zinc-700 dark:bg-zinc-950">
                            </td>
                            <td class="px-3 py-2 align-top">
                                <label class="inline-flex items-center gap-2 text-xs">
                                    <input v-model="rule.is_active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400">
                                    <span>{{ rule.is_active ? 'Активно' : 'Выкл.' }}</span>
                                </label>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex justify-end gap-2">
                                    <button type="button" :class="crmBtnCreate" class="px-3 py-1.5 text-xs" @click="saveRule(rule)">
                                        Сохранить
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/40 dark:text-red-400 dark:hover:bg-red-950/30"
                                        @click="deleteRule(rule.id)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="ruleDrafts.length === 0">
                            <td colspan="8" class="px-3 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                Условия вычета ещё не заданы — добавьте первое условие слева.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmFieldFluid, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'motivation', activeLeafKey: 'kpi-settings' }, () => page),
});

const props = defineProps({
    bonusMultiplier: {
        type: Number,
        default: 1.3,
    },
    customRulesCutoffDate: {
        type: String,
        default: '2026-06-01',
    },
    paymentFormOptions: {
        type: Array,
        default: () => [],
    },
    carrierRuleOptions: {
        type: Array,
        default: () => [],
    },
    deductionRules: {
        type: Array,
        default: () => [],
    },
});

const settingsForm = useForm({
    bonus_multiplier: props.bonusMultiplier,
});

const createRuleForm = useForm({
    name: '',
    priority: 100,
    customer_payment_form: '',
    customer_positive_vat_required: false,
    customer_vat_rate_percent: null,
    carrier_rule: 'any',
    carrier_payment_forms: [],
    carrier_vat_rate_percent: null,
    deduction_primary_percent: 3,
    deduction_secondary_percent: null,
    margin_supplement_percent: null,
    margin_supplement_carrier_vat_percent: null,
    effective_from: props.customRulesCutoffDate,
    effective_to: '',
    is_active: true,
});

const ruleDrafts = ref(cloneRules(props.deductionRules));

watch(() => props.deductionRules, (rules) => {
    ruleDrafts.value = cloneRules(rules);
}, { deep: true });

const formattedBonusMultiplier = computed(() => Number(settingsForm.bonus_multiplier || 0).toFixed(2));

const formattedCutoffDate = computed(() => {
    const parts = String(props.customRulesCutoffDate || '').split('-');

    if (parts.length !== 3) {
        return props.customRulesCutoffDate;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
});

function cloneRules(rules) {
    return (rules ?? []).map((rule) => ({
        ...rule,
        customer_payment_form: rule.customer_payment_form ?? '',
        carrier_payment_forms: [...(rule.carrier_payment_forms ?? [])],
        effective_to: rule.effective_to ?? '',
    }));
}

function needsCarrierForms(carrierRule) {
    return ['all_exact', 'all_in', 'any_exact'].includes(carrierRule);
}

function needsCarrierVatRate(carrierRule) {
    return carrierRule === 'any_vat_rate';
}

function saveBonusSettings() {
    settingsForm.patch(route('settings.motivation.kpi.update'), {
        preserveScroll: true,
    });
}

function normalizeRulePayload(rule) {
    return {
        name: rule.name,
        priority: Number(rule.priority ?? 100),
        customer_payment_form: rule.customer_payment_form || null,
        customer_positive_vat_required: Boolean(rule.customer_positive_vat_required),
        customer_vat_rate_percent: rule.customer_vat_rate_percent === '' ? null : rule.customer_vat_rate_percent,
        carrier_rule: rule.carrier_rule,
        carrier_payment_forms: rule.carrier_payment_forms ?? [],
        carrier_vat_rate_percent: rule.carrier_vat_rate_percent === '' ? null : rule.carrier_vat_rate_percent,
        deduction_primary_percent: Number(rule.deduction_primary_percent ?? 0),
        deduction_secondary_percent: rule.deduction_secondary_percent === '' ? null : rule.deduction_secondary_percent,
        margin_supplement_percent: rule.margin_supplement_percent === '' ? null : rule.margin_supplement_percent,
        margin_supplement_carrier_vat_percent: rule.margin_supplement_carrier_vat_percent === '' ? null : rule.margin_supplement_carrier_vat_percent,
        effective_from: rule.effective_from,
        effective_to: rule.effective_to || null,
        is_active: Boolean(rule.is_active),
    };
}

function storeRule() {
    createRuleForm
        .transform(() => normalizeRulePayload(createRuleForm.data()))
        .post(route('settings.motivation.kpi.rules.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createRuleForm.reset(
                    'name',
                    'customer_payment_form',
                    'customer_positive_vat_required',
                    'customer_vat_rate_percent',
                    'carrier_payment_forms',
                    'carrier_vat_rate_percent',
                    'deduction_secondary_percent',
                    'margin_supplement_percent',
                    'margin_supplement_carrier_vat_percent',
                    'effective_to',
                );
                createRuleForm.priority = 100;
                createRuleForm.carrier_rule = 'any';
                createRuleForm.deduction_primary_percent = 3;
                createRuleForm.effective_from = props.customRulesCutoffDate;
                createRuleForm.is_active = true;
            },
        });
}

function saveRule(rule) {
    router.patch(route('settings.motivation.kpi.rules.update', rule.id), normalizeRulePayload(rule), {
        preserveScroll: true,
    });
}

function deleteRule(ruleId) {
    if (!window.confirm('Удалить условие вычета?')) {
        return;
    }

    router.delete(route('settings.motivation.kpi.rules.destroy', ruleId), {
        preserveScroll: true,
    });
}
</script>
