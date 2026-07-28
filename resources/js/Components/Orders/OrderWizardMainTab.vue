<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';
import CustomerPortalInviteButton from '@/Components/Orders/CustomerPortalInviteButton.vue';
import OrderAsyncSearchSelect from '@/Components/Orders/OrderAsyncSearchSelect.vue';
import OrderTrakloChatButton from '@/Components/Orders/OrderTrakloChatButton.vue';
import { crmBtnNeutral, crmFieldFluid } from '@/support/crmUi.js';
import { ORDER_WIZARD_MAIN_TAB_KEY } from '@/support/orderWizardMainTabKey.js';

const {
    form,
    order,
    isEditing,
    isOrderFormEditable,
    canAssignResponsible,
    ownCompanyOptions,
    showOwnCompanyBankAccountPicker,
    selectableOwnCompanyBankAccounts,
    ownCompanyBankAccountLabel,
    withSubcontract,
    subcontractOwnCompanyOptions,
    clientSearch,
    showClientResults,
    combinedClientResults,
    isSearchingClients,
    serverSearchResults,
    customerDebtBlocked,
    selectedClient,
    highlightRequiredField,
    openCounterpartyModal,
    selectClient,
    onOrderNumberManualInput,
    suggestedOrderNumberCipher,
    responsibleUsers,
    onCompensationOwnerPercentInput,
    onCompensationDispatcherPercentInput,
    clientRequestModeOptions,
    financialSummary,
    showPaymentSettlementBlock,
    paymentSettlementLines,
    paymentSettlementLineTitle,
    paymentSettlementLineValue,
    linkedOrder,
    linkingOrder,
    linkOrderError,
    linkToOrder,
    unlinkOrder,
} = inject(ORDER_WIZARD_MAIN_TAB_KEY);
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">
                        {{ form.carrier_own_company_id ? 'Генподрядчик (заявка заказчику)' : 'Своя компания' }}
                    </label>
                    <select
                        v-model="form.own_company_id"
                        :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('own_company_id', form.own_company_id)]"
                    >
                        <option :value="null">Не выбрано</option>
                        <option v-for="company in ownCompanyOptions" :key="company.id" :value="company.id">
                            {{ company.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.own_company_id" class="text-xs text-rose-500">{{ form.errors.own_company_id }}</p>
                </div>

                <div v-if="showOwnCompanyBankAccountPicker" class="space-y-2">
                    <label class="text-sm font-medium">Расчётный счёт своей компании</label>
                    <select
                        v-model="form.own_company_bank_account_id"
                        :class="crmFieldFluid"
                    >
                        <option :value="null">Основной (по умолчанию)</option>
                        <option
                            v-for="acc in selectableOwnCompanyBankAccounts"
                            :key="String(acc.id)"
                            :value="acc.id"
                        >
                            {{ ownCompanyBankAccountLabel(acc) }}
                        </option>
                    </select>
                    <p v-if="form.errors.own_company_bank_account_id" class="text-xs text-rose-500">{{ form.errors.own_company_bank_account_id }}</p>
                </div>

                <div class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                    <label class="flex items-start gap-2 text-sm">
                        <input
                            v-model="withSubcontract"
                            type="checkbox"
                            class="mt-0.5 rounded border-zinc-300"
                            :disabled="!isOrderFormEditable"
                        />
                        <span>
                            <span class="font-medium">Субподряд (другая наша компания)</span>
                            <span class="mt-0.5 block text-xs text-zinc-500">
                                В заявке перевозчику подставятся реквизиты субподрядчика; заказчику — генподрядчика.
                            </span>
                        </span>
                    </label>
                    <div v-if="withSubcontract" class="space-y-2 pt-1">
                        <label class="text-sm font-medium">Субподрядчик (заявка перевозчику)</label>
                        <select
                            v-model="form.carrier_own_company_id"
                            :class="crmFieldFluid"
                            :disabled="!isOrderFormEditable"
                        >
                            <option :value="null">Выберите компанию</option>
                            <option
                                v-for="company in subcontractOwnCompanyOptions"
                                :key="company.id"
                                :value="company.id"
                            >
                                {{ company.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.carrier_own_company_id" class="text-xs text-rose-500">{{ form.errors.carrier_own_company_id }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-medium">Контрагент</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <CustomerPortalInviteButton
                                v-if="order?.id && order?.can_edit_order && form.client_id"
                                :order-id="order.id"
                            />
                            <OrderTrakloChatButton
                                v-if="order?.id && form.client_id"
                                :order-id="order.id"
                                :contractor-id="form.client_id"
                                external-party="customer"
                            />
                            <button
                                type="button"
                                class="rounded-xl border border-zinc-200 px-3 py-1.5 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                @click.stop="openCounterpartyModal"
                            >
                                Новый контрагент
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <input
                            v-model="clientSearch"
                            type="text"
                            :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('client_id', form.client_id)]"
                            placeholder="Начни вводить название или ИНН"
                            @focus="showClientResults = true"
                        />

                        <div
                            v-if="showClientResults && combinedClientResults.length > 0"
                            class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <div v-if="isSearchingClients" class="px-4 py-3 text-center text-sm text-zinc-500">
                                Поиск...
                            </div>
                            <button
                                v-for="contractor in combinedClientResults"
                                :key="contractor.id"
                                type="button"
                                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                @click="selectClient(contractor)"
                            >
                                <span class="text-sm font-medium">{{ contractor.name }}</span>
                                <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                <span v-if="serverSearchResults.some(c => c.id === contractor.id)" class="text-xs text-green-500 mt-1">
                                    ✓ Найден в базе
                                </span>
                            </button>
                        </div>
                    </div>
                    <p v-if="customerDebtBlocked" class="text-xs text-rose-500">
                        Лимит задолженности контрагента достигнут: {{ selectedClient?.current_debt ?? 0 }} {{ selectedClient?.debt_limit_currency || 'RUB' }}. Новый заказ сохранить нельзя.
                    </p>
                    <p v-if="form.errors.client_id" class="text-xs text-rose-500">{{ form.errors.client_id }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Дата заказа</label>
                        <input v-model="form.order_date" type="date" :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('order_date', form.order_date)]" />
                        <p v-if="form.errors.order_date" class="text-xs text-rose-500">{{ form.errors.order_date }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Номер</label>
                        <input
                            v-model="form.order_number"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Сгенерируется автоматически"
                            @input="onOrderNumberManualInput"
                        />
                        <p v-if="!isEditing && suggestedOrderNumberCipher" class="text-xs text-zinc-500">
                            По правилу «{{ suggestedOrderNumberCipher }}»; можно изменить вручную.
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Владелец сделки</label>
                        <select
                            v-model.number="form.order_owner_id"
                            :class="crmFieldFluid"
                            :disabled="!canAssignResponsible || !isOrderFormEditable"
                        >
                            <option v-for="user in responsibleUsers" :key="`order-owner-${user.id}`" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.order_owner_id" class="text-xs text-rose-500">{{ form.errors.order_owner_id }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Диспетчер</label>
                        <select
                            v-model="form.dispatcher_id"
                            :class="crmFieldFluid"
                            :disabled="!canAssignResponsible || !isOrderFormEditable"
                        >
                            <option :value="null">Не назначен</option>
                            <option v-for="user in responsibleUsers" :key="`order-dispatcher-${user.id}`" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.dispatcher_id" class="text-xs text-rose-500">{{ form.errors.dispatcher_id }}</p>
                    </div>
                </div>

                <div v-if="form.dispatcher_id" class="space-y-2 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <label class="text-sm font-medium">Доля KPI / компенсации</label>
                    <p class="text-xs text-zinc-500">Сумма долей владельца и диспетчера должна быть 100%.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-500">Владелец, %</label>
                            <input
                                v-model.number="form.compensation_owner_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                :class="crmFieldFluid"
                                :disabled="!canAssignResponsible || !isOrderFormEditable"
                                @input="onCompensationOwnerPercentInput"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-zinc-500">Диспетчер, %</label>
                            <input
                                v-model.number="form.compensation_dispatcher_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                :class="crmFieldFluid"
                                :disabled="!canAssignResponsible || !isOrderFormEditable"
                                @input="onCompensationDispatcherPercentInput"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.compensation_owner_percent" class="text-xs text-rose-500">{{ form.errors.compensation_owner_percent }}</p>
                </div>

                <div v-if="form.performers.length > 1" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div>
                        <h2 class="text-base font-semibold">Клиентская заявка</h2>
                        <p class="text-sm text-zinc-500">Выбери, оформляем ли весь маршрут одной заявкой или разбиваем по плечам.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="option in clientRequestModeOptions"
                            :key="option.value"
                            class="flex cursor-pointer gap-3 rounded-2xl border border-zinc-200 p-4 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/70"
                            :class="form.financial_term.client_request_mode === option.value ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-200 dark:bg-zinc-800/70' : ''"
                        >
                            <input v-model="form.financial_term.client_request_mode" type="radio" :value="option.value" class="mt-1 rounded border-zinc-300" />
                            <span class="space-y-1">
                                <span class="block text-sm font-medium">{{ option.label }}</span>
                                <span class="block text-xs text-zinc-500">{{ option.description }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div
                    v-if="isEditing && order?.id"
                    class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                >
                    <div>
                        <h2 class="text-base font-semibold">Связанный заказ</h2>
                        <p class="text-sm text-zinc-500">
                            Цепочка экспедирования (например Автоальянс ↔ Гросс): два заказа на одну перевозку.
                        </p>
                    </div>

                    <div
                        v-if="linkedOrder"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900/50"
                    >
                        <div class="min-w-0 text-sm">
                            <Link
                                :href="linkedOrder.edit_url || route('orders.edit', linkedOrder.id)"
                                class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                            >
                                {{ linkedOrder.order_number || ('#' + linkedOrder.id) }}
                            </Link>
                            <div class="text-xs text-zinc-500">
                                {{ [linkedOrder.own_company_name, linkedOrder.customer_name].filter(Boolean).join(' · ') }}
                            </div>
                        </div>
                        <button
                            type="button"
                            :class="crmBtnNeutral"
                            :disabled="linkingOrder || !isOrderFormEditable"
                            @click="unlinkOrder"
                        >
                            Отвязать
                        </button>
                    </div>

                    <div v-else class="space-y-2">
                        <label class="text-sm font-medium">Связать с заказом</label>
                        <OrderAsyncSearchSelect
                            :exclude-order-id="order.id"
                            @select="linkToOrder"
                        />
                        <p v-if="linkOrderError" class="text-xs text-rose-500">{{ linkOrderError }}</p>
                        <p v-if="linkingOrder" class="text-xs text-zinc-500">Сохраняем связь…</p>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium">Особые отметки</label>
                <textarea v-model="form.special_notes" rows="6" :class="crmFieldFluid" />
            </div>
        </div>

        <div class="space-y-3">
            <h2 class="text-base font-semibold">Финансовая сводка</h2>
            <div class="grid gap-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-800 md:grid-cols-5">
                <div>Цена клиента: <span class="font-medium">{{ financialSummary.clientPrice.toFixed(2) }}</span></div>
                <div>Себестоимость: <span class="font-medium">{{ financialSummary.totalCost.toFixed(2) }}</span></div>
                <div>Маржа: <span class="font-medium">{{ financialSummary.margin.toFixed(2) }}</span></div>
                <div>Доп. расходы: <span class="font-medium">{{ financialSummary.additionalCosts.toFixed(2) }}</span></div>
                <div>Вычет: <span class="font-medium">{{ Number(form.financial_term.kpi_percent || 0).toFixed(2) }}%</span></div>
            </div>

            <div
                v-if="showPaymentSettlementBlock"
                class="space-y-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-800"
            >
                <div class="font-semibold text-zinc-800 dark:text-zinc-100">Расчёты по графику оплат</div>
                <div class="space-y-1.5 text-zinc-700 dark:text-zinc-200">
                    <div
                        v-for="line in paymentSettlementLines"
                        :key="line.key"
                    >
                        {{ paymentSettlementLineTitle(line) }}:
                        <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ paymentSettlementLineValue(line) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
