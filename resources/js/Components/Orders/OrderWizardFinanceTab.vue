<script setup>
import { inject } from 'vue';
import PaymentTermsWizardBlock from '@/Pages/Orders/Components/PaymentTermsWizardBlock.vue';
import { ORDER_WIZARD_FINANCE_TAB_KEY } from '@/support/orderWizardFinanceTabKey.js';

const {
    form,
    order,
    canEditFinancialFields,
    highlightRequiredField,
    currencyOptions,
    paymentFormOptions,
    legContractorCosts,
    costRowTitle,
    contractorCostAmountLabel,
    contractorCostOrderDate,
    syncContractorCostsFromPerformers,
    crmFieldFluid,
    addAdditionalCostRow,
    removeAdditionalCostRow,
    additionalCostSearchValue,
    setAdditionalCostSearchValue,
    setAdditionalCostResultsVisible,
    hideAdditionalCostResults,
    isAdditionalCostResultsVisible,
    additionalCostCombinedResults,
    selectAdditionalCostContractor,
    additionalExpenseAmountFieldClass,
    bonusMultiplier,
} = inject(ORDER_WIZARD_FINANCE_TAB_KEY);
</script>

<template>
    <div class="space-y-3">
        <div class="space-y-3">
            <div class="space-y-2.5 rounded-xl border border-zinc-200 p-2.5 dark:border-zinc-800">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Оплата клиентом</h2>
                    <div v-if="form.performers.length > 1" class="rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-[11px] text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ form.financial_term.client_request_mode === 'split_by_leg' ? 'Несколько заявок' : 'Одна заявка' }}
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <div class="flex min-w-[10rem] flex-[1_1_10rem] items-center gap-1.5">
                        <label class="shrink-0 whitespace-nowrap text-xs font-medium text-zinc-600 dark:text-zinc-400">Стоимость</label>
                        <input
                            v-model="form.financial_term.client_price"
                            type="number"
                            min="0"
                            step="0.01"
                            :disabled="!canEditFinancialFields"
                            :class="['min-w-0 flex-1 rounded-xl border bg-white px-2.5 py-1.5 text-sm dark:bg-zinc-950', highlightRequiredField('client_price', form.financial_term.client_price)]"
                        />
                    </div>
                    <div class="flex min-w-[7rem] flex-[1_1_7rem] items-center gap-1.5">
                        <label class="shrink-0 whitespace-nowrap text-xs font-medium text-zinc-600 dark:text-zinc-400">Валюта</label>
                        <select v-model="form.financial_term.client_currency" :disabled="!canEditFinancialFields" :class="['min-w-0 flex-1 rounded-xl border px-2.5 py-1.5 text-sm dark:bg-zinc-950', highlightRequiredField('client_currency', form.financial_term.client_currency, form.financial_term.client_price)]">
                            <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="flex min-w-[11rem] flex-[1_1_11rem] items-center gap-1.5">
                        <label class="shrink-0 whitespace-nowrap text-xs font-medium text-zinc-600 dark:text-zinc-400">Форма оплаты</label>
                        <select v-model="form.financial_term.client_payment_form" :disabled="!canEditFinancialFields" :class="`${crmFieldFluid} min-w-0 flex-1 py-1.5`">
                            <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <p v-if="form.errors['financial_term.client_price']" class="text-xs text-rose-500">{{ form.errors['financial_term.client_price'] }}</p>
                <PaymentTermsWizardBlock
                    :key="`client-pay-${order?.id ?? 'draft'}`"
                    v-model:summary-text="form.financial_term.client_payment_terms"
                    :schedule="form.financial_term.client_payment_schedule"
                    :total-amount="form.financial_term.client_price"
                    :currency="form.financial_term.client_currency"
                    :route-points="form.route_points"
                    :order-date="form.order_date"
                    :editable-summary="canEditFinancialFields"
                />
            </div>

            <div class="space-y-2.5 rounded-xl border border-zinc-200 p-2.5 dark:border-zinc-800">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Затраты по исполнителям</h2>
                    <button type="button" class="rounded-lg border border-zinc-200 px-2.5 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="syncContractorCostsFromPerformers">
                        Подтянуть из этапов
                    </button>
                </div>

                <div class="space-y-2">
                    <div v-for="(cost, index) in legContractorCosts" :key="`contractor-cost-${index}`" class="space-y-2 rounded-lg border border-zinc-200 p-2.5 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <div class="min-w-0 basis-full text-sm font-medium text-zinc-900 dark:text-zinc-100 sm:basis-auto sm:min-w-[8rem] sm:flex-1">
                                {{ costRowTitle(cost) }}
                            </div>
                            <div class="flex min-w-[9rem] flex-[1_1_9rem] items-center gap-1.5">
                                <label class="shrink-0 whitespace-nowrap text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                    {{ contractorCostAmountLabel(cost) }}
                                </label>
                                <input v-model="cost.amount" type="number" min="0" step="0.01" :disabled="!canEditFinancialFields" :class="`${crmFieldFluid} min-w-0 flex-1 py-1.5`" placeholder="0" />
                            </div>
                            <div class="flex min-w-[6.5rem] flex-[1_1_6.5rem] items-center gap-1.5">
                                <label class="shrink-0 whitespace-nowrap text-xs font-medium text-zinc-600 dark:text-zinc-400">Валюта</label>
                                <select v-model="cost.currency" :disabled="!canEditFinancialFields" :class="`${crmFieldFluid} min-w-0 flex-1 py-1.5`">
                                    <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="flex min-w-[10rem] flex-[1_1_10rem] items-center gap-1.5">
                                <label class="shrink-0 whitespace-nowrap text-xs font-medium text-zinc-600 dark:text-zinc-400">Форма оплаты</label>
                                <select v-model="cost.payment_form" :disabled="!canEditFinancialFields" :class="`${crmFieldFluid} min-w-0 flex-1 py-1.5`">
                                    <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>
                        <PaymentTermsWizardBlock
                            :key="`carrier-pay-${order?.id ?? 'draft'}-${index}`"
                            v-model:summary-text="cost.payment_terms"
                            :schedule="cost.payment_schedule"
                            :total-amount="cost.amount"
                            :currency="cost.currency"
                            :route-points="form.route_points"
                            :order-date="contractorCostOrderDate(cost)"
                            :editable-summary="canEditFinancialFields"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-2.5 rounded-xl border border-zinc-200 p-2.5 dark:border-zinc-800">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Дополнительные затраты</h2>
                <button
                    type="button"
                    class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="addAdditionalCostRow"
                >
                    Добавить затрату
                </button>
            </div>

            <div v-if="form.financial_term.additional_costs.length === 0" class="rounded-xl border border-dashed border-zinc-200 px-3 py-3 text-sm text-zinc-500 dark:border-zinc-700">
                Нет дополнительных затрат.
            </div>

            <div
                v-for="(row, index) in form.financial_term.additional_costs"
                :key="`additional-cost-${row.id}`"
                class="space-y-2 border-b border-zinc-100 pb-4 last:border-b-0 last:pb-0 dark:border-zinc-800"
            >
                <div class="grid grid-cols-1 gap-2 md:grid-cols-12 md:items-end">
                    <div class="relative min-w-0 space-y-1 md:col-span-4">
                        <label class="text-xs font-medium text-zinc-500">Подрядчик</label>
                        <input
                            :value="additionalCostSearchValue(row.id)"
                            type="text"
                            autocomplete="off"
                            placeholder="Название или ИНН"
                            :class="crmFieldFluid"
                            @input="setAdditionalCostSearchValue(row.id, $event.target.value)"
                            @focus="setAdditionalCostResultsVisible(row.id, true)"
                            @blur="hideAdditionalCostResults(row.id)"
                        />
                        <div
                            v-if="isAdditionalCostResultsVisible(row.id) && additionalCostCombinedResults(row.id).length > 0"
                            class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
                        >
                            <button
                                v-for="contractor in additionalCostCombinedResults(row.id)"
                                :key="`additional-cost-search-${row.id}-${contractor.id}`"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-900"
                                @mousedown.prevent="selectAdditionalCostContractor(index, contractor)"
                            >
                                <div class="font-medium">{{ contractor.name }}</div>
                                <div v-if="contractor.inn" class="text-xs text-zinc-500">ИНН {{ contractor.inn }}</div>
                            </button>
                        </div>
                    </div>
                    <div class="min-w-0 space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-zinc-500">Дата услуги</label>
                        <input v-model="row.service_date" type="date" :class="crmFieldFluid" />
                    </div>
                    <div class="min-w-0 space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-zinc-500">Стоимость</label>
                        <input v-model="row.amount" type="number" min="0" step="0.01" :class="crmFieldFluid" placeholder="0" />
                    </div>
                    <div class="min-w-0 space-y-1 md:col-span-1">
                        <label class="text-xs font-medium text-zinc-500">Вал.</label>
                        <select v-model="row.currency" :class="crmFieldFluid">
                            <option v-for="option in currencyOptions" :key="`additional-currency-${row.id}-${option.value}`" :value="option.value">{{ option.value }}</option>
                        </select>
                    </div>
                    <div class="min-w-0 space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-zinc-500">Форма оплаты</label>
                        <select v-model="row.payment_form" :class="crmFieldFluid">
                            <option v-for="option in paymentFormOptions" :key="`additional-payform-${row.id}-${option.value}`" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="flex md:col-span-1 md:justify-end">
                        <button
                            type="button"
                            class="mt-5 rounded-xl border border-rose-200 px-3 py-2 text-xs text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40 md:mt-0"
                            @click="removeAdditionalCostRow(index)"
                        >
                            Удалить
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <span class="whitespace-nowrap text-sm font-medium">Страховка</span>
                    <input v-model="form.insurance" type="number" min="0" step="0.01" :class="additionalExpenseAmountFieldClass" placeholder="0" />
                </div>
                <div class="flex items-center gap-2">
                    <span class="whitespace-nowrap text-sm font-medium">Бонус</span>
                    <input v-model="form.bonus" type="number" min="0" step="0.01" :class="additionalExpenseAmountFieldClass" placeholder="0" />
                </div>
            </div>
            <p class="text-xs text-zinc-500">
                В марже бонус учитывается с коэффициентом {{ Number(bonusMultiplier || 0).toFixed(2) }}.
            </p>
        </div>
    </div>
</template>
