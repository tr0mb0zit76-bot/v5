<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto lg:min-h-0">
        <div
            v-if="isMobileStandalone"
            class="space-y-3 rounded-[28px] border border-zinc-200 bg-white px-4 py-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60"
                        title="К реестру"
                        @click="goBack"
                    >
                        <X class="h-5 w-5" />
                        <span class="sr-only">К реестру</span>
                    </button>

                    <div class="min-w-0">
                        <div class="text-xs uppercase tracking-[0.22em] text-zinc-400 dark:text-zinc-500">Мобильный мастер</div>
                        <h1 class="truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ isEditing ? form.order_number || `Заказ #${order.id}` : 'Новый заказ' }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Статус</span>
                                <span
                                    class="inline-flex max-w-full items-center rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-100"
                                    title="Рассчитывается автоматически по фактическим датам маршрута, документам и оплатам"
                                >
                                    {{ orderStatusBadgeLabel }}
                                </span>
                                <button
                                    v-if="canShowMarkDisruptionButton"
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 transition-colors hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
                                    title="Перевозка не началась, заказ уже не «Новый». Доступно руководителю и администратору."
                                    @click="markOrderDisruption"
                                >
                                    <OctagonAlert class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                    Срыв
                                </button>
                            </div>
                            <span class="h-4 w-px shrink-0 bg-zinc-200 dark:bg-zinc-600" aria-hidden="true" />
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Перевозка</span>
                                <div class="inline-flex rounded-lg border border-zinc-200 p-0.5 dark:border-zinc-700">
                                    <button
                                        type="button"
                                        class="rounded-md px-2.5 py-1 text-[11px] font-medium transition-colors"
                                        :class="!form.is_international_transport
                                            ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                                            : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                                        @click="form.is_international_transport = false"
                                    >
                                        Внутренняя
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md px-2.5 py-1 text-[11px] font-medium transition-colors"
                                        :class="form.is_international_transport
                                            ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                                            : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                                        @click="form.is_international_transport = true"
                                    >
                                        Международная
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    :class="crmBtnCreate"
                    class="h-11 shrink-0"
                    :disabled="form.processing || customerDebtBlocked || !isOrderFormEditable"
                    @click="submit"
                >
                    <Save class="h-4 w-4" />
                    {{ form.processing ? '...' : 'Сохранить' }}
                </button>
            </div>

            <p v-if="hasUnsavedDocumentFiles" class="text-xs text-amber-800 dark:text-amber-200">
                В документах выбран новый файл — нажмите «Сохранить» выше, иначе вложение не попадёт в заказ.
            </p>
            <p v-if="coreValidationIssues.length > 0" class="text-xs text-rose-700 dark:text-rose-300">
                Для сохранения заполните: {{ coreValidationIssues.join(', ') }}.
            </p>

            <div class="space-y-2">
                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500">Шаг</label>
                <select
                    v-model="activeTab"
                    class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                >
                    <option v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</option>
                </select>
            </div>
        </div>

        <template v-else>
            <div class="flex items-center justify-between gap-4 border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60"
                        title="К реестру"
                        @click="goBack"
                    >
                        <X class="h-5 w-5" />
                        <span class="sr-only">К реестру</span>
                    </button>

                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-semibold">
                            {{ isEditing ? form.order_number || `Заказ #${order.id}` : 'Новый заказ' }}
                        </h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span
                        v-if="hasUnsavedDocumentFiles"
                        class="max-w-md text-right text-xs text-amber-800 dark:text-amber-200"
                    >
                        В документах есть новый файл — сохраните заказ.
                    </span>
                    <button
                        type="button"
                        :class="crmBtnCreate"
                        :disabled="form.processing || customerDebtBlocked || !isOrderFormEditable"
                        @click="submit"
                    >
                        <Save class="h-4 w-4" />
                        {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-2 border border-zinc-200 bg-white px-5 py-2.5 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:flex-nowrap sm:items-center sm:justify-between sm:gap-x-3 sm:gap-y-2">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex items-center gap-2 border px-3 py-2 text-sm transition-colors"
                        :class="activeTab === tab.key
                            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                            : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800'"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>
                <div class="flex w-full min-w-0 flex-wrap items-center gap-x-4 gap-y-2 border-t border-zinc-200 pt-2.5 sm:w-auto sm:min-w-0 sm:flex-nowrap sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Перевозка</span>
                        <div class="inline-flex rounded-lg border border-zinc-200 p-0.5 dark:border-zinc-700">
                            <button
                                type="button"
                                class="rounded-md px-3 py-2 text-sm font-medium leading-none transition-colors"
                                :class="!form.is_international_transport
                                    ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                                    : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                                @click="form.is_international_transport = false"
                            >
                                Внутренняя
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-2 text-sm font-medium leading-none transition-colors"
                                :class="form.is_international_transport
                                    ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                                    : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                                @click="form.is_international_transport = true"
                            >
                                Международная
                            </button>
                        </div>
                    </div>
                    <span class="hidden h-6 w-px shrink-0 bg-zinc-200 sm:block dark:bg-zinc-600" aria-hidden="true" />
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Статус заказа</span>
                        <span
                            class="inline-flex max-w-full items-center rounded-full border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium leading-none text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-100"
                            title="Рассчитывается автоматически по фактическим датам маршрута, документам и оплатам"
                        >
                            {{ orderStatusBadgeLabel }}
                        </span>
                        <button
                            v-if="canShowMarkDisruptionButton"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold leading-none text-red-700 transition-colors hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
                            title="Перевозка не началась, заказ уже не «Новый». Доступно руководителю и администратору."
                            @click="markOrderDisruption"
                        >
                            <OctagonAlert class="h-4 w-4 shrink-0" aria-hidden="true" />
                            Срыв
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div
            class="min-h-0 overflow-auto border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 md:p-5"
            :inert="isEditing && !isOrderFormEditable"
        >
            <p
                v-if="isEditing && !isOrderFormEditable"
                class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
                role="status"
            >
                Редактирование заказа недоступно: все печатные заявки по заказу доведены до финального PDF. Данные можно просматривать; изменения не сохраняются.
            </p>
            <p v-if="saveAttempted && coreValidationIssues.length > 0" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">
                Не удалось сохранить: заполните {{ coreValidationIssues.join(', ') }}.
            </p>
            <div v-if="activeTab === 'main'" class="space-y-6">
                <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Своя компания</label>
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
                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
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

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm font-medium">Контрагент</label>
                            <button
                                type="button"
                                class="rounded-xl border border-zinc-200 px-3 py-1.5 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                @click.stop="openCounterpartyModal"
                            >
                                Новый контрагент
                            </button>
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
                            <input v-model="form.order_number" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="Сгенерируется автоматически" />
                        </div>
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

                </div>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <h2 class="text-base font-semibold">Этапы маршрута</h2>
                        <p class="mt-1 text-sm text-zinc-500">Этапы и исполнители настраиваются на вкладках «Маршрут» и «Финансы».</p>
                        <div class="mt-3 space-y-2 text-sm">
                            <div v-for="(performer, index) in form.performers" :key="`stage-preview-${index}`" class="rounded-xl bg-zinc-50 px-3 py-2 dark:bg-zinc-800/60">
                                {{ stageLabel(performer.stage) }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Особые отметки</label>
                        <textarea v-model="form.special_notes" rows="4" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>
                </div>
                </div>

                <div class="space-y-4">
                    <h2 class="text-base font-semibold">Финансовая сводка</h2>
                    <div class="grid gap-3 rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-800 md:grid-cols-5">
                        <div>Цена клиента: <span class="font-medium">{{ financialSummary.clientPrice.toFixed(2) }}</span></div>
                        <div>Себестоимость: <span class="font-medium">{{ financialSummary.totalCost.toFixed(2) }}</span></div>
                        <div>Маржа: <span class="font-medium">{{ financialSummary.margin.toFixed(2) }}</span></div>
                        <div>Доп. расходы: <span class="font-medium">{{ financialSummary.additionalCosts.toFixed(2) }}</span></div>
                        <div>KPI: <span class="font-medium">{{ Number(form.financial_term.kpi_percent || 0).toFixed(2) }}%</span></div>
                    </div>

                    <div
                        v-if="showPaymentSettlementBlock"
                        class="space-y-2 rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-800"
                    >
                        <div class="font-semibold text-zinc-800 dark:text-zinc-100">Расчёты по графику оплат</div>
                        <div class="space-y-1.5 text-zinc-700 dark:text-zinc-200">
                            <div>
                                Клиент рассчитался с нами:
                                <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ paymentSettlementLineLabel(orderPaymentSettlement?.customer) }}</span>
                            </div>
                            <div>
                                Мы рассчитались с перевозчиками:
                                <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ paymentSettlementLineLabel(orderPaymentSettlement?.carrier) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'route'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Маршрут</h2>
                        <p class="text-sm text-zinc-500">Этапы маршрута, точки погрузки и выгрузки</p>
                        <p v-if="form.errors.performers" class="mt-1 text-xs text-rose-500">{{ form.errors.performers }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addPerformer">
                            Добавить плечо
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 rounded-2xl border border-dashed border-zinc-200 p-4 dark:border-zinc-700 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="min-w-0 flex-1 text-sm leading-relaxed text-zinc-500">
                        {{ routeChainLabel }}
                    </div>
                    <div
                        v-if="form.is_international_transport"
                        class="flex w-full shrink-0 flex-col gap-2 sm:ml-auto sm:max-w-xs sm:items-end"
                    >
                        <label class="sr-only" for="wizard-border-crossing-leg">Добавить прохождение границы на плечо</label>
                        <select
                            id="wizard-border-crossing-leg"
                            v-model="borderCrossingLegPicker"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-55 dark:border-zinc-700 dark:bg-zinc-950 sm:max-w-xs"
                            :disabled="hasBorderCrossingPoint"
                            @change="onBorderCrossingLegPickerChange"
                        >
                            <option value="">Добавить прохождение границы…</option>
                            <option v-for="(p, idx) in form.performers" :key="p.stage" :value="String(idx)">
                                {{ stageLabel(p.stage) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="space-y-6">
                    <div
                        v-for="(performer, legIndex) in form.performers"
                        :key="`leg-route-${legIndex}`"
                        class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div class="space-y-3 border-b border-zinc-100 pb-4 dark:border-zinc-800 sm:space-y-0 sm:flex sm:flex-nowrap sm:items-end sm:gap-x-3 sm:gap-y-2">
                            <div class="flex items-center justify-between gap-3 sm:hidden">
                                <span class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ stageLabel(performer.stage) }}</span>
                                <button
                                    v-if="form.performers.length > 1"
                                    type="button"
                                    class="shrink-0 rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                                    @click="removePerformer(legIndex)"
                                >
                                    Удалить плечо
                                </button>
                            </div>
                            <span class="hidden text-base font-semibold leading-none text-zinc-900 dark:text-zinc-50 sm:inline sm:shrink-0">{{ stageLabel(performer.stage) }}</span>
                            <div class="grid min-w-0 w-full flex-1 grid-cols-1 gap-2 sm:grid-cols-12 sm:gap-3">
                                <div class="space-y-1 sm:col-span-5">
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Перевозчик</label>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-zinc-200 px-2 py-1 text-[11px] hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                            @click.stop="openCounterpartyModal({ kind: 'performer', index: legIndex, type: 'carrier' })"
                                        >
                                            + Новый
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <input
                                            :value="carrierSearchValue('performer', legIndex)"
                                            type="text"
                                            :class="['w-full rounded-xl border bg-white px-3 py-2 pr-10 text-sm dark:bg-zinc-950', highlightRequiredField('performer_carrier', performer.contractor_id)]"
                                            placeholder="Поиск перевозчика"
                                            @focus="setCarrierResultsVisible('performer', legIndex, true)"
                                            @input="onPerformerCarrierInput(legIndex, $event.target.value)"
                                            @blur="restorePerformerCarrierSearch(legIndex)"
                                        />
                                        <button
                                            v-if="normalizeNullableNumber(form.performers[legIndex]?.contractor_id) !== null"
                                            type="button"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                            title="Очистить перевозчика"
                                            @click="clearPerformerContractor(legIndex)"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                        <div
                                            v-if="isCarrierResultsVisible('performer', legIndex) && filteredCarrierResults('performer', legIndex).length > 0"
                                            class="absolute left-0 top-full z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                        >
                                            <button
                                                v-for="contractor in filteredCarrierResults('performer', legIndex)"
                                                :key="contractor.id"
                                                type="button"
                                                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                                @mousedown.prevent
                                                @click="selectPerformerContractor(legIndex, contractor)"
                                            >
                                                <span class="text-sm font-medium">{{ contractor.name }}</span>
                                                <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-1 sm:col-span-3">
                                    <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Авто</label>
                                    <select
                                        v-model="performer.fleet_vehicle_id"
                                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                        :disabled="normalizeNullableNumber(performer.contractor_id) === null"
                                        @focus="loadFleetOptionsForLeg(legIndex)"
                                    >
                                        <option :value="null">—</option>
                                        <option v-for="v in fleetVehicleOptionsForLeg(legIndex)" :key="v.id" :value="v.id">{{ v.label }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1 sm:col-span-4">
                                    <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Водитель</label>
                                    <select
                                        v-model="performer.fleet_driver_id"
                                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                        :disabled="normalizeNullableNumber(performer.contractor_id) === null"
                                        @focus="loadFleetOptionsForLeg(legIndex)"
                                    >
                                        <option :value="null">—</option>
                                        <option v-for="d in fleetDriverOptionsForLeg(legIndex)" :key="d.id" :value="d.id">{{ d.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <button
                                v-if="form.performers.length > 1"
                                type="button"
                                class="hidden shrink-0 rounded-xl border border-rose-200 px-3 py-2 text-sm leading-none text-rose-600 hover:bg-rose-50 sm:inline-flex dark:border-rose-900 dark:hover:bg-rose-950/40"
                                @click="removePerformer(legIndex)"
                            >
                                Удалить плечо
                            </button>
                        </div>

                        <div
                            v-for="item in routePointsWithIndicesForLeg(performer.stage)"
                            :key="`point-${item.globalIndex}`"
                            :draggable="routePointsDragEnabled()"
                            class="space-y-3 rounded-2xl border border-zinc-200 bg-white/40 p-4 dark:border-zinc-700 dark:bg-zinc-950/30"
                            :class="[
                                draggedRoutePointIndex === item.globalIndex ? 'opacity-60 ring-2 ring-zinc-300 dark:ring-zinc-700' : '',
                                dragOverRoutePointIndex === item.globalIndex ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-200 dark:bg-zinc-800/60' : '',
                            ]"
                            @dragstart="handleRoutePointDragStart(item.globalIndex, $event)"
                            @dragover.prevent="handleRoutePointDragOver(item.globalIndex)"
                            @drop.prevent="handleRoutePointDrop(item.globalIndex)"
                            @dragend="handleRoutePointDragEnd"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0 text-base font-semibold text-zinc-900 dark:text-zinc-50">
                                    {{ routePointTitle(item.point, item.globalIndex) }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 text-zinc-400 dark:border-zinc-700 dark:text-zinc-500"
                                        :class="routePointsDragEnabled() ? 'cursor-grab' : 'cursor-not-allowed opacity-50'"
                                        :title="routePointsDragEnabled() ? 'Перетащить этап' : 'Порядок этапов фиксирован по плечам — перетаскивание отключено'"
                                    >
                                        ⋮⋮
                                    </span>
                                    <button type="button" class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.route_points, item.globalIndex)">
                                        Удалить
                                    </button>
                                </div>
                            </div>

                            <template v-if="item.point.type === 'border_crossing'">
                                <div class="w-full space-y-2">
                                    <label class="text-sm font-medium">СВХ / таможенный склад</label>
                                    <input
                                        v-model="form.svh_name"
                                        type="text"
                                        :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', form.errors.svh_name ? 'border-rose-500 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700']"
                                        placeholder="Наименование или адрес СВХ для документов"
                                    />
                                    <p v-if="form.errors.svh_name" class="text-xs text-rose-500">{{ form.errors.svh_name }}</p>
                                </div>
                            </template>
                            <div v-else class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_9.5rem_9.5rem_14rem] lg:items-end">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Адрес</label>
                                    <div class="relative">
                                        <input
                                            v-model="item.point.address"
                                            type="text"
                                            :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('route_point_address_' + item.globalIndex, routePointAddressHighlightValue(item.point))]"
                                            placeholder="Начни вводить адрес"
                                            @input="queueAddressLookup(item.globalIndex)"
                                        />

                                        <div
                                            v-if="addressSuggestions[item.globalIndex]?.length"
                                            class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                        >
                                            <button
                                                v-for="suggestion in addressSuggestions[item.globalIndex]"
                                                :key="suggestion.value"
                                                type="button"
                                                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                                @click="selectAddress(item.globalIndex, suggestion)"
                                            >
                                                <span class="text-sm font-medium">{{ suggestion.value }}</span>
                                                <span class="text-xs text-zinc-500">{{ suggestion.data?.region_with_type || suggestion.data?.region || '' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Плановая дата</label>
                                    <input v-model="item.point.planned_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-2.5 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Фактическая дата</label>
                                    <input v-model="item.point.actual_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-2.5 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">{{ routePointTimeBlockHeading(item.point.type) }}</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input v-model="item.point.planned_time_from" type="time" class="w-full rounded-xl border border-zinc-200 bg-white px-2.5 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" aria-label="Время с" />
                                        <input v-model="item.point.planned_time_to" type="time" class="w-full rounded-xl border border-zinc-200 bg-white px-2.5 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" aria-label="Время до" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="item.point.type === 'loading'" class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Отправитель</label>
                                    <input v-model="item.point.sender_name" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контакт на загрузке</label>
                                    <input
                                        :value="routePointCombinedContact(item.point)"
                                        type="text"
                                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                        placeholder="Имя и телефон"
                                        @input="setRoutePointCombinedContact(item.point, $event.target.value)"
                                    />
                                </div>
                            </div>

                            <div v-if="item.point.type === 'unloading'" class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Получатель</label>
                                    <input v-model="item.point.recipient_name" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контакт на выгрузке</label>
                                    <input
                                        :value="routePointCombinedContact(item.point)"
                                        type="text"
                                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                        placeholder="Имя и телефон"
                                        @input="setRoutePointCombinedContact(item.point, $event.target.value)"
                                    />
                                </div>
                            </div>

                            <div v-if="form.performers.length > 1" class="border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-zinc-500">Отнести этап к плечу</label>
                                    <select v-model="item.point.stage" class="w-full max-w-md rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" @change="onRoutePointLegChanged">
                                        <option v-for="p in form.performers" :key="p.stage" :value="p.stage">
                                            {{ stageLabel(p.stage) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'cargo'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Грузовые позиции</h2>
                        <p class="text-sm text-zinc-500">Несколько грузов в одном заказе</p>
                    </div>
                    <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addCargoItem">
                        Добавить груз
                    </button>
                </div>

                <div class="space-y-4">
                    <div v-for="(item, index) in form.cargo_items" :key="`cargo-${index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium">Груз {{ index + 1 }}</div>
                            <button type="button" class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.cargo_items, index)">
                                Удалить
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-12 lg:gap-x-2 lg:gap-y-2">
                            <div class="space-y-1 lg:col-span-4">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Наименование</label>
                                <input v-model="item.name" list="cargo-title-suggestions" type="text" :class="['w-full rounded-lg border px-2 py-1.5 text-sm dark:bg-zinc-950', highlightRequiredField('cargo_name_' + index, item.name)]" />
                            </div>
                            <div class="space-y-1 lg:col-span-2">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Тип груза</label>
                                <select
                                    v-model.number="item.cargo_type_id"
                                    :class="['w-full rounded-lg border px-2 py-1.5 text-sm dark:bg-zinc-950', highlightRequiredField('cargo_type_' + index, item.cargo_type_id)]"
                                    @change="applyCargoTypeOption(item)"
                                >
                                    <option v-for="option in cargoTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1 lg:col-span-2">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Вес</label>
                                <div class="flex gap-1.5">
                                    <input
                                        v-model="item.weight_value"
                                        type="number"
                                        min="0"
                                        step="0.001"
                                        class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    />
                                    <select v-model="item.weight_unit" class="w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1.5 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="kg">кг</option>
                                        <option value="t">т</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-1 lg:col-span-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Мест</label>
                                <input v-model="item.package_count" type="number" min="0" step="1" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-1 lg:col-span-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Упаковка</label>
                                <select v-model="item.pack_type_id" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950" @change="applyPackageTypeOption(item)">
                                    <option :value="null">—</option>
                                    <option v-for="option in packageTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1 lg:col-span-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">ТН ВЭД</label>
                                <input v-model="item.hs_code" type="text" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-1 lg:col-span-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Класс опасн.</label>
                                <input v-model="item.dangerous_class" type="text" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-end gap-x-2 gap-y-2">
                            <div class="grid min-w-[17rem] flex-1 gap-2 sm:grid-cols-3">
                                <div class="space-y-1">
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Погрузка</label>
                                    <details class="relative">
                                        <summary class="flex h-8 cursor-pointer list-none items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-2 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                            <span class="truncate">{{ dictionarySelectionLabel(item.loading_type_items) }}</span>
                                            <span class="text-zinc-400">▾</span>
                                        </summary>
                                        <div class="absolute z-30 mt-1 max-h-44 w-full space-y-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                            <label v-for="option in loadingTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                                <input v-model="item.loading_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyLoadingTypeOption(item)" />
                                                <span class="leading-tight">{{ option.label }}</span>
                                            </label>
                                        </div>
                                    </details>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Кузов</label>
                                    <details class="relative">
                                        <summary class="flex h-8 cursor-pointer list-none items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-2 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                            <span class="truncate">{{ dictionarySelectionLabel(item.truck_body_type_items) }}</span>
                                            <span class="text-zinc-400">▾</span>
                                        </summary>
                                        <div class="absolute z-30 mt-1 max-h-44 w-full space-y-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                            <label v-for="option in truckBodyTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                                <input v-model="item.truck_body_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyTruckBodyTypeOption(item)" />
                                                <span class="leading-tight">{{ option.label }}</span>
                                            </label>
                                        </div>
                                    </details>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Прицеп</label>
                                    <details class="relative">
                                        <summary class="flex h-8 cursor-pointer list-none items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-2 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                            <span class="truncate">{{ dictionarySelectionLabel(item.trailer_type_items) }}</span>
                                            <span class="text-zinc-400">▾</span>
                                        </summary>
                                        <div class="absolute z-30 mt-1 max-h-44 w-full space-y-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                            <label v-for="option in trailerTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                                <input v-model="item.trailer_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyTrailerTypeOption(item)" />
                                                <span class="leading-tight">{{ option.label }}</span>
                                            </label>
                                        </div>
                                    </details>
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-wrap items-end gap-x-1.5 gap-y-1">
                                <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                                    <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Д</label>
                                    <input
                                        v-model="item.length_m"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                    />
                                </div>
                                <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                                    <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Ш</label>
                                    <input
                                        v-model="item.width_m"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                    />
                                </div>
                                <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                                    <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">В</label>
                                    <input
                                        v-model="item.height_m"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                    />
                                </div>
                                <div class="flex w-[8.25rem] shrink-0 items-center gap-1">
                                    <label class="w-12 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Объём</label>
                                    <input
                                        v-model="item.volume_m3"
                                        type="number"
                                        min="0"
                                        step="0.001"
                                        :readonly="cargoComputedVolumeM3(item) !== null"
                                        placeholder="—"
                                        :class="[
                                            'h-8 min-w-0 flex-1 rounded px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950',
                                            cargoComputedVolumeM3(item) !== null
                                                ? 'cursor-default border border-dashed border-zinc-200 bg-zinc-50 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-100'
                                                : 'border border-zinc-200 bg-white dark:border-zinc-700',
                                        ]"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-12">
                            <div class="space-y-2 md:col-span-8">
                                <label class="text-sm font-medium">Описание</label>
                                <textarea v-model="item.description" rows="2" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 px-3 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-900/40 md:col-span-4">
                                <div class="font-medium text-zinc-700 dark:text-zinc-200">Сводка позиции</div>
                                <div class="mt-1">
                                    Вес: {{ cargoLineTotalWeightKg(item).toFixed(2) }} кг
                                    <span v-if="cargoPackageCountFactor(item) > 1" class="text-zinc-500">({{ cargoWeightInKg(item).toFixed(2) }} кг × {{ cargoPackageCountFactor(item) }})</span>
                                </div>
                                <div>
                                    Объём:
                                    <template v-if="cargoLineTotalVolumeM3(item) > 0">{{ cargoLineTotalVolumeM3(item).toFixed(3) }} м³</template>
                                    <template v-else>—</template>
                                    <span v-if="cargoPackageCountFactor(item) > 1 && cargoLineTotalVolumeM3(item) > 0" class="text-zinc-500">({{ Number(item.volume_m3 || 0).toFixed(3) }} м³ × {{ cargoPackageCountFactor(item) }})</span>
                                </div>
                                <div v-if="cargoHasDimensions(item)">Габариты (Д×Ш×В): {{ cargoDimensionsLabel(item) }}</div>
                                <div>Мест: {{ Number(item.package_count || 0) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <datalist id="cargo-title-suggestions">
                    <option v-for="title in cargoTitleSuggestions" :key="title" :value="title" />
                </datalist>

                <div class="grid gap-3 rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-800 md:grid-cols-3">
                    <div>Общий вес: <span class="font-medium">{{ cargoSummary.totalWeight.toFixed(2) }} кг</span></div>
                    <div>Общий объём: <span class="font-medium">{{ cargoSummary.totalVolume.toFixed(2) }} м³</span></div>
                    <div>Всего мест: <span class="font-medium">{{ cargoSummary.totalPackages }}</span></div>
                </div>
            </div>

            <div v-else-if="activeTab === 'finance'" class="space-y-6">
                <div class="space-y-6">
                    <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold">Оплата клиентом</h2>
                                <p class="text-xs text-zinc-500">Условия клиента задаются первым блоком, остальные расходы идут ниже по маршруту.</p>
                            </div>
                            <div v-if="form.performers.length > 1" class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-right text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                {{ form.financial_term.client_request_mode === 'split_by_leg' ? 'Маршрут разбивается на несколько клиентских заявок' : 'Маршрут оформляется одной клиентской заявкой' }}
                            </div>
                        </div>
                        <div class="grid gap-3 lg:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Цена клиента</label>
                                <input
                                    v-model="form.financial_term.client_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('client_price', form.financial_term.client_price)]"
                                />
                                <p v-if="form.errors['financial_term.client_price']" class="text-xs text-rose-500">{{ form.errors['financial_term.client_price'] }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Валюта</label>
                                <select v-model="form.financial_term.client_currency" :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('client_currency', form.financial_term.client_currency, form.financial_term.client_price)]">
                                    <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Форма оплаты</label>
                                <select v-model="form.financial_term.client_payment_form" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>
                        <PaymentTermsWizardBlock
                            v-model:summary-text="form.financial_term.client_payment_terms"
                            :schedule="form.financial_term.client_payment_schedule"
                            :total-amount="form.financial_term.client_price"
                            :currency="form.financial_term.client_currency"
                            :route-points="form.route_points"
                            :order-date="form.order_date"
                            editable-summary
                        />
                    </div>

                    <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold">Затраты по исполнителям</h2>
                                <p class="text-xs text-zinc-500">Каждое плечо идет отдельной карточкой, чтобы структура не ломалась при нескольких этапах.</p>
                            </div>
                            <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="syncContractorCostsFromPerformers">
                                Подтянуть из этапов
                            </button>
                        </div>

                    <div class="space-y-3">
                        <div v-for="(cost, index) in form.financial_term.contractors_costs" :key="`contractor-cost-${index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ stageLabel(cost.stage) }}</div>
                                <p class="text-xs text-zinc-500">Перевозчик и условия оплаты для этого плеча.</p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                                <div class="min-w-0 space-y-2 md:col-span-4">
                                    <label class="text-sm font-medium">Плечо маршрута</label>
                                    <select v-model="cost.stage" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="performer in form.performers" :key="performer.stage" :value="performer.stage">{{ stageLabel(performer.stage) }}</option>
                                    </select>
                                </div>
                                <div class="min-w-0 space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium">Стоимость перевозки</label>
                                    <input v-model="cost.amount" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="0" />
                                </div>
                                <div class="min-w-0 space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium">Валюта</label>
                                    <select v-model="cost.currency" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div class="min-w-0 space-y-2 md:col-span-4">
                                    <label class="text-sm font-medium">Форма оплаты</label>
                                    <select v-model="cost.payment_form" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <PaymentTermsWizardBlock
                                :key="`carrier-pay-${index}`"
                                v-model:summary-text="cost.payment_terms"
                                :schedule="cost.payment_schedule"
                                :total-amount="cost.amount"
                                :currency="cost.currency"
                                :route-points="form.route_points"
                                :order-date="form.order_date"
                                editable-summary
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div>
                    <h2 class="text-base font-semibold">Дополнительные затраты</h2>
                    <p class="text-xs text-zinc-500">Прочие расходы по заказу (не оплата перевозчикам по этапам)</p>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Доп. расходы</label>
                        <input v-model="form.additional_expenses" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="0" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Страховка</label>
                        <input v-model="form.insurance" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="0" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Бонус</label>
                        <input v-model="form.bonus" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="0" />
                        <p class="text-xs text-zinc-500">
                            В марже бонус учитывается с коэффициентом {{ Number(props.bonusMultiplier || 0).toFixed(2) }}.
                        </p>
                    </div>
                </div>
            </div>

            </div>

            <div v-else-if="activeTab === 'documents'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Документы</h2>
                        <p class="text-sm text-zinc-500">Общий блок заказчика + отдельные блоки по каждому плечу перевозки</p>
                    </div>
                </div>

                <div
                    v-if="documentTabValidationMessages.length > 0"
                    class="rounded-2xl border border-rose-200 bg-rose-50/80 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-100"
                    role="alert"
                >
                    <div class="font-medium">Не удалось сохранить заказ</div>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                        <li v-for="(msg, idx) in documentTabValidationMessages" :key="`doc-err-${idx}`">{{ msg }}</li>
                    </ul>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div
                        v-if="documentChecklist.length > 0"
                        class="rounded-2xl border border-amber-200/80 bg-amber-50/50 p-4 text-sm dark:border-amber-900/50 dark:bg-amber-950/20"
                    >
                        <div class="font-medium text-amber-950 dark:text-amber-100">Обязательные документы для этапов «Оплата» и «Завершено»</div>
                        <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/80">
                            Пока не выполнены все пункты, после выгрузки статус заказа останется «Документы». Для загружаемых файлов — прикрепите файл и поставьте статус «Отправлен» или «Подписан». Для печатных форм — завершите цепочку документа (финальный PDF и подписи по шаблону).
                        </p>
                        <ul class="mt-3 space-y-1.5">
                            <li
                                v-for="item in documentChecklist"
                                :key="`doc-req-${item.key}`"
                                class="flex items-start gap-2 text-amber-950 dark:text-amber-100"
                            >
                                <span class="mt-0.5 shrink-0" :class="item.completed ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400'">
                                    {{ item.completed ? '✓' : '○' }}
                                </span>
                                <span>
                                    <span class="font-medium">{{ item.label }}</span>
                                    <span class="text-zinc-600 dark:text-zinc-400"> — {{ item.description }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div
                        class="space-y-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/25"
                    >
                        <div>
                            <h3 class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">Печатные формы</h3>
                            <p class="text-xs text-emerald-900/80 dark:text-emerald-200/80">
                                Черновик DOCX → согласование руководителем → печать/подпись у нас → загрузка финального PDF. В одном заказе могут быть отдельные заявки для заказчика и для перевозчика — у каждой строки указана сторона шаблона.
                            </p>
                        </div>
                        <template v-if="!order?.id">
                            <p class="text-xs text-emerald-900/80 dark:text-emerald-200/80">
                                Печатные формы привязаны к заказу в базе. Сохраните заказ — появится выбор шаблона и кнопка «Создать в карточке».
                            </p>
                        </template>
                        <template v-else>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-[200px] flex-1 space-y-1">
                                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Шаблон</label>
                                    <select
                                        v-model="workflowTemplateId"
                                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    >
                                        <option :value="null">Выберите шаблон</option>
                                        <option v-for="template in printFormTemplateOptions" :key="`wf-tpl-${template.id}`" :value="template.id">
                                            {{ templateOptionLabel(template) }}
                                        </option>
                                    </select>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                    :disabled="!workflowTemplateId || !isOrderFormEditable"
                                    @click="createPersistedPrintWorkflowDocument"
                                >
                                    Создать в карточке
                                </button>
                            </div>

                            <div v-if="printWorkflowDocuments.length === 0" class="rounded-xl border border-dashed border-emerald-300/80 px-3 py-3 text-sm text-emerald-900/70 dark:border-emerald-800 dark:text-emerald-200/70">
                                Пока нет печатных форм по этому процессу.
                            </div>

                            <div v-for="doc in printWorkflowDocuments" :key="`print-wf-${doc.id}`" class="space-y-3 rounded-xl border border-zinc-200 bg-white/80 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="text-sm font-medium">
                                        {{ printWorkflowDocumentTitle(doc) }}
                                        <span
                                            v-if="doc.print_party_label"
                                            class="ml-2 inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-normal text-sky-900 dark:bg-sky-950/50 dark:text-sky-200"
                                        >
                                            Сторона: {{ doc.print_party_label }}
                                        </span>
                                        <span
                                            v-if="doc.workflow_status_label"
                                            class="ml-2 inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-normal text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                                        >
                                            {{ doc.workflow_status_label }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Link
                                            v-if="doc.draft_preview_url"
                                            class="rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                                            :href="doc.draft_preview_url"
                                        >
                                            Предпросмотр
                                        </Link>
                                        <a
                                            v-if="doc.draft_download_url"
                                            class="rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                                            :href="doc.draft_download_url"
                                        >
                                            Скачать черновик DOCX
                                        </a>
                                        <a
                                            v-if="doc.final_pdf_download_url"
                                            class="rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                                            :href="doc.final_pdf_download_url"
                                        >
                                            {{
                                                doc.workflow_status === 'finalized'
                                                    ? 'Скачать PDF с нашей подписью'
                                                    : doc.workflow_status === 'approved'
                                                      ? 'Скачать PDF для контрагента'
                                                      : 'Скачать PDF'
                                            }}
                                        </a>
                                    </div>
                                </div>
                                <p v-if="doc.rejection_reason" class="text-xs text-rose-700 dark:text-rose-300">
                                    Причина отклонения: {{ doc.rejection_reason }}
                                </p>
                                <p
                                    v-if="doc.signature_status_label"
                                    class="text-xs text-zinc-600 dark:text-zinc-400"
                                >
                                    Подпись (юр.): {{ doc.signature_status_label }}
                                    <span v-if="doc.requires_counterparty_signature" class="text-zinc-500"> · по шаблону нужна сторона клиента</span>
                                </p>
                                <p
                                    v-if="doc.signature_followup_hint"
                                    class="rounded-lg border border-amber-200 bg-amber-50/80 px-2 py-1.5 text-xs text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
                                >
                                    {{ doc.signature_followup_hint }}
                                </p>
                                <p
                                    v-if="doc.workflow_status === 'finalized' && doc.final_pdf_storage_path"
                                    class="text-xs text-zinc-600 dark:text-zinc-400"
                                >
                                    Финальный файл в хранилище ({{ documentStorage.label }}):
                                    <code class="rounded bg-zinc-100 px-1 font-mono text-[11px] dark:bg-zinc-800">{{ doc.final_pdf_storage_path }}</code>
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="doc.can_request_approval"
                                        type="button"
                                        class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                        :disabled="!isOrderFormEditable"
                                        @click="postWorkflowAction('request-approval', doc.id)"
                                    >
                                        Отправить на согласование
                                    </button>
                                    <button
                                        v-if="doc.can_regenerate_draft"
                                        type="button"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                                        :disabled="!isOrderFormEditable"
                                        @click="postWorkflowAction('regenerate-draft', doc.id)"
                                    >
                                        Пересоздать черновик
                                    </button>
                                    <button
                                        v-if="doc.can_approve"
                                        type="button"
                                        class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs text-white hover:bg-emerald-800"
                                        @click="postWorkflowAction('approve', doc.id)"
                                    >
                                        Подписать
                                    </button>
                                    <button
                                        v-if="doc.can_reject"
                                        type="button"
                                        class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        @click="toggleWorkflowReject(doc.id)"
                                    >
                                        Отказать
                                    </button>
                                    <label
                                        v-if="doc.can_finalize"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-1.5 text-xs font-medium text-emerald-900 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100 dark:hover:bg-emerald-950/70"
                                    >
                                        <span>Сохранить финальный PDF в заказе</span>
                                        <input type="file" accept="application/pdf" class="hidden" @change="finalizeWorkflowPdf(doc, $event)" />
                                    </label>
                                    <button
                                        v-if="doc.can_discard_print_draft"
                                        type="button"
                                        class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        :disabled="!isOrderFormEditable"
                                        @click="confirmDiscardPrintWorkflow(doc)"
                                    >
                                        Удалить черновик
                                    </button>
                                </div>
                                <div v-if="workflowRejectTargetId === doc.id" class="space-y-2 rounded-lg border border-rose-200 bg-rose-50/50 p-2 dark:border-rose-900 dark:bg-rose-950/30">
                                    <label class="text-xs font-medium text-rose-900 dark:text-rose-200">Причина отклонения</label>
                                    <textarea
                                        v-model="workflowRejectReason"
                                        rows="2"
                                        class="w-full rounded-lg border border-rose-200 bg-white px-2 py-1.5 text-sm dark:border-rose-800 dark:bg-zinc-950"
                                        placeholder="Укажите причину"
                                    />
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-rose-700 px-3 py-1 text-xs text-white hover:bg-rose-800"
                                            :disabled="!workflowRejectReason.trim()"
                                            @click="submitWorkflowReject(doc.id)"
                                        >
                                            Подтвердить отклонение
                                        </button>
                                        <button type="button" class="rounded-lg border border-zinc-200 px-3 py-1 text-xs dark:border-zinc-600" @click="cancelWorkflowReject">
                                            Отмена
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    v-if="page.props.flash?.message"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                    role="status"
                >
                    {{ page.props.flash.message }}
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">Документы заказчика</div>
                            <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addDocumentFor('customer', null)">
                                Добавить документ заказчика
                            </button>
                        </div>

                        <div v-if="customerDocuments.length === 0" class="rounded-xl border border-dashed border-zinc-200 px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700">
                            Документы заказчика пока не добавлены.
                        </div>

                        <div v-for="item in customerDocuments" :key="`customer-document-${item.index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium">Документ заказчика</div>
                                <button type="button" class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeDocumentAt(item.index)">
                                    Удалить
                                </button>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Вид</label>
                                    <select v-model="item.document.flow" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="uploaded">Загружаемый</option>
                                        <option value="generated">Формируемый</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Тип</label>
                                    <select v-model="item.document.type" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Номер</label>
                                    <input v-model="item.document.number" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Дата</label>
                                    <input v-model="item.document.document_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Статус</label>
                                    <select v-model="item.document.status" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in documentStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div v-if="item.document.flow === 'generated'" class="space-y-2">
                                    <label class="text-sm font-medium">Шаблон DOCX</label>
                                    <select v-model="item.document.template_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option :value="null">Не выбран</option>
                                        <option v-for="template in printFormTemplateOptions" :key="template.id" :value="template.id">{{ templateOptionLabel(template) }}</option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="item.document.flow === 'generated'" class="flex flex-wrap justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    :disabled="!isOrderFormEditable || !order?.id || !item.document.template_id"
                                    @click="previewDocumentDraft(item.document)"
                                >
                                    Предпросмотр
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    :disabled="!isOrderFormEditable || !order?.id || !item.document.template_id"
                                    @click="downloadDocumentDraft(item.document)"
                                >
                                    Скачать DOCX
                                </button>
                            </div>
                            <div v-if="item.document.flow === 'uploaded'" class="flex flex-wrap items-center gap-3">
                                <label
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                                >
                                    <Paperclip class="h-4 w-4 text-zinc-500" />
                                    <span>Прикрепить файл</span>
                                    <input type="file" class="hidden" @change="onDocumentFileChange(item.index, $event)" />
                                </label>
                                <span v-if="item.document.original_name" class="text-xs text-zinc-500">Файл: {{ item.document.original_name }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="text-sm font-semibold">Документы перевозчика</div>
                        <p v-if="form.performers.length === 0" class="rounded-xl border border-dashed border-zinc-200 px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700">
                            Добавьте плечо маршрута, чтобы прикреплять документы перевозчика.
                        </p>
                        <div v-for="(performer, performerIndex) in form.performers" :key="`carrier-doc-stage-${performerIndex}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-semibold">{{ stageLabel(performer.stage) }}</div>
                                    <p class="text-xs text-zinc-500">Блок связан с конкретным плечом маршрута.</p>
                                </div>
                                <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addDocumentFor('carrier', performer.stage)">
                                    Добавить документ перевозчика
                                </button>
                            </div>

                            <div v-if="carrierDocumentsForStage(performer.stage).length === 0" class="rounded-xl border border-dashed border-zinc-200 px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700">
                                Для {{ stageLabel(performer.stage) }} документы перевозчика пока не добавлены.
                            </div>

                            <div v-for="item in carrierDocumentsForStage(performer.stage)" :key="`carrier-document-${performerIndex}-${item.index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium">Документ перевозчика</div>
                                <button type="button" class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeDocumentAt(item.index)">
                                    Удалить
                                </button>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Вид</label>
                                    <select v-model="item.document.flow" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="uploaded">Загружаемый</option>
                                        <option value="generated">Формируемый</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Тип</label>
                                    <select v-model="item.document.type" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Номер</label>
                                    <input v-model="item.document.number" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Дата</label>
                                    <input v-model="item.document.document_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Статус</label>
                                    <select v-model="item.document.status" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in documentStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div v-if="item.document.flow === 'generated'" class="space-y-2">
                                    <label class="text-sm font-medium">Шаблон DOCX</label>
                                    <select v-model="item.document.template_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option :value="null">Не выбран</option>
                                        <option v-for="template in printFormTemplateOptions" :key="template.id" :value="template.id">{{ templateOptionLabel(template) }}</option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="item.document.flow === 'generated'" class="flex flex-wrap justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    :disabled="!isOrderFormEditable || !order?.id || !item.document.template_id"
                                    @click="previewDocumentDraft(item.document)"
                                >
                                    Предпросмотр
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    :disabled="!isOrderFormEditable || !order?.id || !item.document.template_id"
                                    @click="downloadDocumentDraft(item.document)"
                                >
                                    Скачать DOCX
                                </button>
                            </div>
                            <div v-if="item.document.flow === 'uploaded'" class="flex flex-wrap items-center gap-3">
                                <label
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                                >
                                    <Paperclip class="h-4 w-4 text-zinc-500" />
                                    <span>Прикрепить файл</span>
                                    <input type="file" class="hidden" @change="onDocumentFileChange(item.index, $event)" />
                                </label>
                                <span v-if="item.document.original_name" class="text-xs text-zinc-500">Файл: {{ item.document.original_name }}</span>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-show="showCounterpartyModal"
            class="fixed inset-0 flex items-center justify-center bg-black/40 p-4"
            style="z-index: 2147483647;"
            @click.self="closeCounterpartyModal"
        >
            <div class="w-full max-w-xl rounded-3xl border border-zinc-200 bg-white p-5 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900" @click.stop>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <div class="text-lg font-semibold">Новый контрагент</div>
                        <div class="text-sm text-zinc-500">
                            {{
                                counterpartyTarget.kind === 'performer'
                                    ? 'Создаётся в справочнике и сразу подставляется как перевозчик в это плечо'
                                    : 'Создаётся в справочнике и сразу подставляется в заказ'
                            }}
                        </div>
                    </div>
                    <button type="button" class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closeCounterpartyModal">×</button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <input ref="counterpartyNameInput" v-model="counterpartyForm.name" type="text" placeholder="Название" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 md:col-span-2" />
                    <input v-model="counterpartyForm.inn" type="text" placeholder="ИНН" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.kpp" type="text" placeholder="КПП" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.address" type="text" placeholder="Адрес" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 md:col-span-2" />
                    <input v-model="counterpartyForm.phone" type="text" placeholder="Телефон" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.email" type="email" placeholder="Email" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.contact_person" type="text" placeholder="Контактное лицо" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 md:col-span-2" />
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="closeCounterpartyModal">
                        Отмена
                    </button>
                    <button type="button" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200" :disabled="inlineContractorSaving" @click="createInlineCounterparty">
                        {{ inlineContractorSaving ? 'Создание...' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, toRaw, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ClipboardList, FileText, MapPinned, OctagonAlert, Package, Paperclip, Save, Wallet, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import PaymentTermsWizardBlock from '@/Pages/Orders/Components/PaymentTermsWizardBlock.vue';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import { crmBtnCreate } from '@/support/crmUi.js';
import * as orderPs from '@/support/orderPaymentScheduleUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const page = usePage();

const props = defineProps({
    order: { type: Object, default: null },
    contractors: { type: Array, default: () => [] },
    ownCompanies: { type: Array, default: () => [] },
    cargoTypeOptions: { type: Array, default: () => [] },
    packageTypeOptions: { type: Array, default: () => [] },
    loadingTypeOptions: { type: Array, default: () => [] },
    truckBodyTypeOptions: { type: Array, default: () => [] },
    trailerTypeOptions: { type: Array, default: () => [] },
    currencyOptions: { type: Array, default: () => [] },
    paymentFormOptions: { type: Array, default: () => [] },
    defaultClientPaymentFormCode: { type: String, default: '' },
    documentTypeOptions: { type: Array, default: () => [] },
    documentPartyOptions: { type: Array, default: () => [] },
    orderStatusOptions: { type: Array, default: () => [] },
    documentStatusOptions: { type: Array, default: () => [] },
    printFormTemplateOptions: { type: Array, default: () => [] },
    orderDocumentWorkflow: { type: Object, default: () => ({ status_options: [] }) },
    documentStorage: {
        type: Object,
        default: () => ({
            driver: 'local',
            label: 'локальное хранилище приложения',
        }),
    },
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    currentUser: { type: Object, default: () => ({}) },
    bonusMultiplier: { type: Number, default: 0 },
    cargoTitleSuggestions: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'main', label: 'Основное', icon: ClipboardList },
    { key: 'route', label: 'Маршрут', icon: MapPinned },
    { key: 'cargo', label: 'Груз', icon: Package },
    { key: 'finance', label: 'Финансы', icon: Wallet },
    { key: 'documents', label: 'Документы', icon: FileText },
];

const activeTab = ref('main');
const borderCrossingLegPicker = ref('');

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    const allowed = new Set(['main', 'route', 'cargo', 'finance', 'documents']);
    if (tab && allowed.has(tab)) {
        activeTab.value = tab;
    }
    if (tab) {
        url.searchParams.delete('tab');
        const qs = url.searchParams.toString();
        const next = `${url.pathname}${qs ? `?${qs}` : ''}${url.hash}`;
        window.history.replaceState({}, '', next);
    }

    if (!form.is_international_transport && Array.isArray(form.route_points)) {
        const nextPoints = form.route_points.filter((p) => p.type !== 'border_crossing');
        if (nextPoints.length !== form.route_points.length) {
            form.route_points = nextPoints;
            normalizeRoutePointSequences();
        }
    }
});

const workflowTemplateId = ref(null);
const workflowRejectTargetId = ref(null);
const workflowRejectReason = ref('');
const contractors = ref([...props.contractors]);

const printWorkflowDocuments = computed(() => {
    const docs = props.order?.documents;
    if (!Array.isArray(docs)) {
        return [];
    }

    return docs.filter((d) => d.is_print_workflow);
});

function createPersistedPrintWorkflowDocument() {
    if (!props.order?.id || !workflowTemplateId.value) {
        return;
    }

    router.post(
        route('orders.documents.from-template', props.order.id),
        { print_form_template_id: workflowTemplateId.value },
        { preserveScroll: true },
    );
}

function postWorkflowAction(action, documentId) {
    if (!props.order?.id) {
        return;
    }

    const routeNames = {
        'request-approval': 'orders.documents.request-approval',
        'regenerate-draft': 'orders.documents.regenerate-draft',
        approve: 'orders.documents.approve',
    };
    const routeName = routeNames[action];

    if (!routeName) {
        return;
    }

    router.post(route(routeName, [props.order.id, documentId]), {}, { preserveScroll: true });
}

function toggleWorkflowReject(documentId) {
    if (workflowRejectTargetId.value === documentId) {
        cancelWorkflowReject();
    } else {
        workflowRejectTargetId.value = documentId;
        workflowRejectReason.value = '';
    }
}

function cancelWorkflowReject() {
    workflowRejectTargetId.value = null;
    workflowRejectReason.value = '';
}

function submitWorkflowReject(documentId) {
    if (!props.order?.id || !workflowRejectReason.value.trim()) {
        return;
    }

    router.post(
        route('orders.documents.reject', [props.order.id, documentId]),
        { rejection_reason: workflowRejectReason.value },
        {
            preserveScroll: true,
            onFinish: () => {
                cancelWorkflowReject();
            },
        },
    );
}

async function finalizeWorkflowPdf(doc, event) {
    const target = event.target;
    const file = target?.files?.[0];

    if (!file || !props.order?.id) {
        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});

    const formData = new FormData();
    formData.append('pdf', file);

    router.post(route('orders.documents.finalize', [props.order.id, doc.id]), formData, {
        forceFormData: true,
        preserveScroll: true,
    });

    target.value = '';
}

function confirmDiscardPrintWorkflow(doc) {
    if (!props.order?.id || !doc?.id) {
        return;
    }

    if (!window.confirm('Удалить черновик по шаблону из заказа? Файл DOCX будет удалён; для нового документа создайте его снова из шаблона.')) {
        return;
    }

    router.delete(route('orders.documents.discard-print-workflow', [props.order.id, doc.id]), {
        preserveScroll: true,
    });
}

if (props.order?.client_snapshot) {
    const snap = props.order.client_snapshot;
    const exists = contractors.value.some((c) => Number(c.id) === Number(snap.id));

    if (!exists) {
        contractors.value.unshift({
            id: snap.id,
            name: snap.name,
            inn: snap.inn ?? null,
            type: snap.type ?? 'customer',
            phone: null,
            email: null,
            is_own_company: false,
        });
    }
}

const ownCompanyOptions = ref([...props.ownCompanies]);
const clientSearch = ref('');
const showClientResults = ref(false);
const carrierSearch = ref({});
const showCarrierResults = ref({});
const fleetOptionsCache = ref({});
const showCounterpartyModal = ref(false);
const counterpartyNameInput = ref(null);
const inlineContractorSaving = ref(false);
const counterpartyTarget = ref({ kind: 'client', index: null });
const saveAttempted = ref(false);
const addressSuggestions = ref({});
const addressTimers = {};
const draggedRoutePointIndex = ref(null);
const dragOverRoutePointIndex = ref(null);
const paymentFormOptions = computed(() => {
    const raw = props.paymentFormOptions;
    if (Array.isArray(raw) && raw.length > 0) {
        return raw;
    }

    return [
        { value: 'vat_22', label: 'С НДС 22%' },
        { value: 'vat_5', label: 'С НДС 5%' },
        { value: 'vat_0', label: 'С НДС 0%' },
        { value: 'no_vat', label: 'Без НДС' },
        { value: 'cash', label: 'Нал' },
    ];
});

function defaultClientPaymentForm() {
    const c = props.defaultClientPaymentFormCode;
    if (c && String(c).trim() !== '') {
        return String(c).trim();
    }

    return paymentFormOptions.value.find((o) => String(o.value).startsWith('vat_'))?.value ?? 'no_vat';
}

/** Согласование кода с опциями <select>; legacy `vat` и текстовые подписи. */
function normalizePaymentFormCode(value, fallback) {
    const fb = fallback ?? defaultClientPaymentForm();
    if (value === null || value === undefined || value === '') {
        return fb;
    }
    const trimmed = String(value).trim();
    const allowed = new Set(paymentFormOptions.value.map((o) => o.value));
    allowed.add('vat');
    if (allowed.has(trimmed)) {
        if (trimmed === 'vat') {
            return defaultClientPaymentForm();
        }

        return trimmed;
    }
    const lower = trimmed.toLowerCase();
    if (lower.includes('без') && lower.includes('ндс')) {
        return 'no_vat';
    }
    if (lower.includes('нал')) {
        return 'cash';
    }
    if (lower.includes('ндс')) {
        return defaultClientPaymentForm();
    }

    return fb;
}
const clientRequestModeOptions = [
    { value: 'single_request', label: 'Одна заявка', description: 'Все плечи включаются в одну клиентскую заявку.' },
    { value: 'split_by_leg', label: 'Разбить по плечам', description: 'Для каждого плеча оформляется отдельная клиентская заявка.' },
];
const blankPaymentSchedule = orderPs.blankPaymentSchedule;
const normalizePaymentSchedule = orderPs.normalizePaymentSchedule;
const counterpartyForm = useForm({
    name: '',
    inn: '',
    kpp: '',
    address: '',
    phone: '',
    email: '',
    contact_person: '',
    type: 'customer',
});

async function openCounterpartyModal(options = {}) {
    counterpartyTarget.value = {
        kind: options.kind === 'performer' ? 'performer' : 'client',
        index: Number.isInteger(options.index) ? options.index : null,
    };
    counterpartyForm.type = options.type === 'carrier' ? 'carrier' : 'customer';
    showCounterpartyModal.value = true;

    await nextTick();
    counterpartyNameInput.value?.focus?.();
}

function closeCounterpartyModal() {
    showCounterpartyModal.value = false;
    counterpartyTarget.value = { kind: 'client', index: null };
}

function templatePartyShortLabel(party) {
    if (party === 'customer') {
        return 'заказчик';
    }
    if (party === 'carrier') {
        return 'перевозчик';
    }
    if (party === 'internal') {
        return 'внутр.';
    }

    return party ? String(party) : '';
}

function templateOptionLabel(template) {
    const suffix = [];
    const partyLabel = templatePartyShortLabel(template.party);
    if (partyLabel) {
        suffix.push(partyLabel);
    }

    if (template.contractor_name) {
        suffix.push(template.contractor_name);
    }

    if (template.is_default) {
        suffix.push('по умолчанию');
    }

    return suffix.length > 0 ? `${template.name} (${suffix.join(', ')})` : template.name;
}

function printWorkflowDocumentTitle(document) {
    return document?.print_template_name || document?.original_name || 'Документ';
}

function normalizeDocument(document = {}) {
    return {
        type: 'request',
        flow: 'uploaded',
        number: '',
        document_date: '',
        status: 'draft',
        template_id: null,
        file: null,
        original_name: '',
        generated_pdf_path: null,
        party: 'internal',
        stage: null,
        requirement_key: null,
        ...document,
    };
}

function previewDocumentDraft(document) {
    if (!props.order?.id || !document?.template_id) {
        return;
    }

    window.open(
        route('orders.templates.generate-draft', {
            order: props.order.id,
            printFormTemplate: document.template_id,
            preview: 1,
            preview_mode: 'browser',
        }),
        '_blank'
    );
}

function downloadDocumentDraft(document) {
    if (!props.order?.id || !document?.template_id) {
        return;
    }

    window.location.href = route('orders.templates.generate-draft', {
        order: props.order.id,
        printFormTemplate: document.template_id,
    });
}

function normalizeContractorCost(cost = {}) {
    const merged = {
        stage: '',
        contractor_id: null,
        amount: null,
        currency: 'RUB',
        payment_form: 'no_vat',
        payment_schedule: blankPaymentSchedule(),
        payment_terms: '',
        ...cost,
        payment_schedule: normalizePaymentSchedule(cost.payment_schedule),
    };
    merged.payment_form = normalizePaymentFormCode(merged.payment_form, 'no_vat');
    merged.payment_terms = String(merged.payment_terms ?? '').trim();

    return merged;
}

function blankRoutePoint(type, sequence, stage) {
    return {
        stage,
        type,
        sequence,
        address: '',
        normalized_data: {},
        planned_date: '',
        planned_time_from: '',
        planned_time_to: '',
        actual_date: '',
        actual_time: '',
        contact_person: '',
        contact_phone: '',
        sender_name: '',
        sender_contact: '',
        sender_phone: '',
        recipient_name: '',
        recipient_contact: '',
        recipient_phone: '',
    };
}

function blankOrder() {
    return {
        status: 'new',
        manual_status: null,
        own_company_id: null,
        own_company_bank_account_id: null,
        client_id: null,
        order_date: new Date().toISOString().slice(0, 10),
        order_number: '',
        payment_terms: '',
        special_notes: '',
        svh_name: '',
        is_international_transport: false,
        loading_types: [],
        cargo_sender_name: '',
        cargo_sender_address: '',
        cargo_sender_contact: '',
        cargo_sender_phone: '',
        cargo_recipient_name: '',
        cargo_recipient_address: '',
        cargo_recipient_contact: '',
        cargo_recipient_phone: '',
        performers: [
            { stage: stageLabel('leg_1'), contractor_id: null, contractor_name: null, fleet_vehicle_id: null, fleet_driver_id: null },
        ],
        route_points: [
            blankRoutePoint('loading', 1, stageLabel('leg_1')),
            blankRoutePoint('unloading', 2, stageLabel('leg_1')),
        ],
        cargo_items: [
            {
                name: '',
                description: '',
                weight_value: null,
                weight_kg: null,
                weight_unit: 'kg',
                volume_m3: null,
                length_m: null,
                width_m: null,
                height_m: null,
                diameter_m: null,
                pack_type_id: null,
                pack_type_label: '',
                package_type: null,
                loading_type_id: null,
                loading_type_ids: [],
                loading_type_code: null,
                loading_type_label: '',
                loading_type_items: [],
                truck_body_type_id: null,
                truck_body_type_ids: [],
                truck_body_type_code: null,
                truck_body_type_label: '',
                truck_body_type_items: [],
                trailer_type_id: null,
                trailer_type_ids: [],
                trailer_type_code: null,
                trailer_type_label: '',
                trailer_type_items: [],
                package_count: null,
                dangerous_goods: false,
                dangerous_class: '',
                hs_code: '',
                cargo_type_id: defaultCargoTypeOption()?.value ?? 1,
                cargo_type_label: defaultCargoTypeOption()?.label ?? 'Общий груз',
                cargo_type: defaultCargoTypeOption()?.code ?? 'general',
                is_oversized: false,
                is_fragile: false,
                ati_cargo_payload: {},
            },
        ],
        financial_term: {
            client_price: null,
            client_currency: 'RUB',
            client_payment_form: defaultClientPaymentForm(),
            client_request_mode: 'single_request',
            client_payment_schedule: blankPaymentSchedule(),
            client_payment_terms: '',
            contractors_costs: [],
            additional_costs: [],
            kpi_percent: 0,
        },
        additional_expenses: null,
        insurance: null,
        bonus: null,
        documents: [],
    };
}

function normalizeNullableNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function dictionaryOptionByValue(options, value) {
    const normalized = normalizeNullableNumber(value);
    if (normalized === null) {
        return null;
    }

    return options.find((option) => Number(option.value) === normalized) ?? null;
}

function dictionaryOptionByCode(options, code) {
    const normalized = code ? String(code).trim() : '';
    if (normalized === '') {
        return null;
    }

    return options.find((option) => option.code === normalized) ?? null;
}

function defaultCargoTypeOption() {
    return props.cargoTypeOptions[0] ?? { value: 1, code: 'general', label: 'Общий груз' };
}

function applyCargoTypeOption(item) {
    const option = dictionaryOptionByValue(props.cargoTypeOptions, item.cargo_type_id) ?? defaultCargoTypeOption();
    item.cargo_type_id = normalizeNullableNumber(option.value);
    item.cargo_type = option.code ?? item.cargo_type ?? 'general';
    item.cargo_type_label = option.label ?? '';
    item.dangerous_goods = item.cargo_type === 'dangerous';
    item.is_oversized = item.cargo_type === 'oversized';
    item.is_fragile = item.cargo_type === 'fragile';
}

function applyPackageTypeOption(item) {
    const option = dictionaryOptionByValue(props.packageTypeOptions, item.pack_type_id);
    item.pack_type_id = option ? normalizeNullableNumber(option.value) : null;
    item.package_type = option?.code ?? null;
    item.pack_type_label = option?.label ?? '';
}

function applyDictionaryOption(item, options, idKey, codeKey, labelKey) {
    const option = dictionaryOptionByValue(options, item[idKey]);
    item[idKey] = option ? normalizeNullableNumber(option.value) : null;
    item[codeKey] = option?.code ?? null;
    item[labelKey] = option?.label ?? '';
}

function selectedDictionaryItems(options, ids) {
    if (!Array.isArray(ids)) {
        return [];
    }

    return ids
        .map((id) => dictionaryOptionByValue(options, id))
        .filter(Boolean)
        .map((option) => ({
            id: normalizeNullableNumber(option.value),
            code: option.code ?? null,
            label: option.label ?? '',
        }));
}

function dictionarySelectionLabel(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return 'Выберите';
    }

    const labels = items
        .map((item) => item?.label)
        .filter((label) => label !== null && label !== undefined && String(label).trim() !== '')
        .map((label) => String(label).trim());

    if (labels.length === 0) {
        return 'Выбрано: ' + items.length;
    }

    return labels.length <= 2 ? labels.join(', ') : `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`;
}

function normalizeDictionaryItems(rawItems, options, fallbackOption) {
    const items = Array.isArray(rawItems) ? rawItems : [];
    const normalized = items
        .map((item) => {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const option = dictionaryOptionByValue(options, item.id) ?? dictionaryOptionByCode(options, item.code);

            return {
                id: normalizeNullableNumber(option?.value ?? item.id),
                code: option?.code ?? item.code ?? null,
                label: option?.label ?? item.label ?? '',
            };
        })
        .filter((item) => item && (item.id !== null || item.code || item.label));

    if (normalized.length > 0) {
        return normalized;
    }

    return fallbackOption
        ? [{
            id: normalizeNullableNumber(fallbackOption.value),
            code: fallbackOption.code ?? null,
            label: fallbackOption.label ?? '',
        }]
        : [];
}

function applyDictionaryItems(item, options, idsKey, idKey, codeKey, labelKey, itemsKey) {
    const selected = selectedDictionaryItems(options, item[idsKey]);
    const first = selected[0] ?? null;
    item[itemsKey] = selected;
    item[idKey] = first?.id ?? null;
    item[codeKey] = first?.code ?? null;
    item[labelKey] = first?.label ?? '';
}

function applyLoadingTypeOption(item) {
    applyDictionaryItems(item, props.loadingTypeOptions, 'loading_type_ids', 'loading_type_id', 'loading_type_code', 'loading_type_label', 'loading_type_items');
}

function applyTruckBodyTypeOption(item) {
    applyDictionaryItems(item, props.truckBodyTypeOptions, 'truck_body_type_ids', 'truck_body_type_id', 'truck_body_type_code', 'truck_body_type_label', 'truck_body_type_items');
}

function applyTrailerTypeOption(item) {
    applyDictionaryItems(item, props.trailerTypeOptions, 'trailer_type_ids', 'trailer_type_id', 'trailer_type_code', 'trailer_type_label', 'trailer_type_items');
}

function normalizeCargoItem(raw = {}) {
    const selectedCargoType = dictionaryOptionByValue(props.cargoTypeOptions, raw.cargo_type_id)
        ?? dictionaryOptionByCode(props.cargoTypeOptions, raw.cargo_type)
        ?? defaultCargoTypeOption();
    let cargoType = selectedCargoType.code ?? (raw.cargo_type && String(raw.cargo_type).trim() !== '' ? raw.cargo_type : 'general');
    if (cargoType === 'general' && Boolean(raw.dangerous_goods)) {
        cargoType = 'dangerous';
    }
    const effectiveCargoType = dictionaryOptionByCode(props.cargoTypeOptions, cargoType) ?? selectedCargoType;
    const selectedPackageType = dictionaryOptionByValue(props.packageTypeOptions, raw.pack_type_id)
        ?? dictionaryOptionByCode(props.packageTypeOptions, raw.package_type);
    const selectedLoadingType = dictionaryOptionByValue(props.loadingTypeOptions, raw.loading_type_id)
        ?? dictionaryOptionByCode(props.loadingTypeOptions, raw.loading_type_code)
        ?? dictionaryOptionByCode(props.loadingTypeOptions, Array.isArray(raw.loading_types) ? raw.loading_types[0] : null)
        ?? dictionaryOptionByCode(props.loadingTypeOptions, Array.isArray(props.order?.loading_types) ? props.order.loading_types[0] : null);
    const selectedTruckBodyType = dictionaryOptionByValue(props.truckBodyTypeOptions, raw.truck_body_type_id)
        ?? dictionaryOptionByCode(props.truckBodyTypeOptions, raw.truck_body_type_code);
    const selectedTrailerType = dictionaryOptionByValue(props.trailerTypeOptions, raw.trailer_type_id)
        ?? dictionaryOptionByCode(props.trailerTypeOptions, raw.trailer_type_code);
    const loadingTypeItems = normalizeDictionaryItems(raw.loading_type_items, props.loadingTypeOptions, selectedLoadingType);
    const truckBodyTypeItems = normalizeDictionaryItems(raw.truck_body_type_items, props.truckBodyTypeOptions, selectedTruckBodyType);
    const trailerTypeItems = normalizeDictionaryItems(raw.trailer_type_items, props.trailerTypeOptions, selectedTrailerType);
    const weightValue = raw.weight_value ?? raw.weight_kg ?? null;

    return {
        name: raw.name ?? '',
        description: raw.description ?? '',
        weight_value: weightValue,
        weight_kg: weightValue,
        weight_unit: raw.weight_unit === 't' ? 't' : 'kg',
        volume_m3: raw.volume_m3 ?? null,
        length_m: raw.length_m ?? null,
        width_m: raw.width_m ?? null,
        height_m: raw.height_m ?? null,
        diameter_m: raw.diameter_m ?? null,
        pack_type_id: selectedPackageType ? normalizeNullableNumber(selectedPackageType.value) : normalizeNullableNumber(raw.pack_type_id),
        pack_type_label: raw.pack_type_label ?? selectedPackageType?.label ?? '',
        package_type: raw.package_type ?? selectedPackageType?.code ?? null,
        loading_type_id: loadingTypeItems[0]?.id ?? normalizeNullableNumber(raw.loading_type_id),
        loading_type_ids: loadingTypeItems.map((item) => item.id).filter((id) => id !== null),
        loading_type_code: loadingTypeItems[0]?.code ?? raw.loading_type_code ?? null,
        loading_type_label: loadingTypeItems[0]?.label ?? raw.loading_type_label ?? '',
        loading_type_items: loadingTypeItems,
        truck_body_type_id: truckBodyTypeItems[0]?.id ?? normalizeNullableNumber(raw.truck_body_type_id),
        truck_body_type_ids: truckBodyTypeItems.map((item) => item.id).filter((id) => id !== null),
        truck_body_type_code: truckBodyTypeItems[0]?.code ?? raw.truck_body_type_code ?? null,
        truck_body_type_label: truckBodyTypeItems[0]?.label ?? raw.truck_body_type_label ?? '',
        truck_body_type_items: truckBodyTypeItems,
        trailer_type_id: trailerTypeItems[0]?.id ?? normalizeNullableNumber(raw.trailer_type_id),
        trailer_type_ids: trailerTypeItems.map((item) => item.id).filter((id) => id !== null),
        trailer_type_code: trailerTypeItems[0]?.code ?? raw.trailer_type_code ?? null,
        trailer_type_label: trailerTypeItems[0]?.label ?? raw.trailer_type_label ?? '',
        trailer_type_items: trailerTypeItems,
        package_count: raw.package_count ?? null,
        dangerous_goods: cargoType === 'dangerous',
        dangerous_class: raw.dangerous_class ?? '',
        hs_code: raw.hs_code ?? '',
        cargo_type_id: effectiveCargoType ? normalizeNullableNumber(effectiveCargoType.value) : normalizeNullableNumber(raw.cargo_type_id),
        cargo_type_label: raw.cargo_type_label ?? effectiveCargoType?.label ?? '',
        cargo_type: cargoType,
        is_oversized: Boolean(raw.is_oversized ?? cargoType === 'oversized'),
        is_fragile: Boolean(raw.is_fragile ?? cargoType === 'fragile'),
        ati_cargo_payload: raw.ati_cargo_payload && typeof raw.ati_cargo_payload === 'object' ? raw.ati_cargo_payload : {},
    };
}

function cargoWeightInKg(item) {
    const v = Number(item.weight_value ?? item.weight_kg ?? 0);
    if (item.weight_unit === 't') {
        return v * 1000;
    }

    return v;
}

/** Вес и габариты в строке — на одно место; множитель для сводок по числу мест. */
function cargoPackageCountFactor(item) {
    const n = Number(item.package_count);
    if (Number.isFinite(n) && n > 0) {
        return Math.trunc(n);
    }

    return 1;
}

function cargoLineTotalWeightKg(item) {
    return cargoWeightInKg(item) * cargoPackageCountFactor(item);
}

function cargoLineTotalVolumeM3(item) {
    const per = Number(item.volume_m3);
    if (!Number.isFinite(per) || per <= 0) {
        return 0;
    }

    return per * cargoPackageCountFactor(item);
}

function cargoHasDimensions(item) {
    return [item.length_m, item.width_m, item.height_m].some((v) => v !== null && v !== undefined && String(v).trim() !== '');
}

function cargoDimensionsLabel(item) {
    const l = item.length_m !== null && item.length_m !== undefined && item.length_m !== '' ? Number(item.length_m).toFixed(2) : '—';
    const w = item.width_m !== null && item.width_m !== undefined && item.width_m !== '' ? Number(item.width_m).toFixed(2) : '—';
    const h = item.height_m !== null && item.height_m !== undefined && item.height_m !== '' ? Number(item.height_m).toFixed(2) : '—';

    return `${l}×${w}×${h} м`;
}

/**
 * Объём по габаритам (м³). Только если заданы все три стороны и они положительны.
 */
function cargoComputedVolumeM3(item) {
    const l = Number(item.length_m);
    const w = Number(item.width_m);
    const h = Number(item.height_m);

    if (!Number.isFinite(l) || !Number.isFinite(w) || !Number.isFinite(h) || l <= 0 || w <= 0 || h <= 0) {
        return null;
    }

    return l * w * h;
}

function cargoDimensionFieldsEmpty(item) {
    return [item.length_m, item.width_m, item.height_m].every(
        (v) => v === null || v === undefined || v === '',
    );
}

/**
 * Текст для readonly «Объём»: сначала расчёт по габаритам, иначе значение из базы.
 */
function cargoVolumeDisplay(item) {
    const computed = cargoComputedVolumeM3(item);
    if (computed !== null) {
        return (Math.round(computed * 1000) / 1000).toFixed(3);
    }

    const legacy = Number(item.volume_m3);
    if (Number.isFinite(legacy)) {
        return legacy.toFixed(3);
    }

    return '';
}

function selectedLoadingTypeCodes() {
    const fromCargo = form.cargo_items
        .flatMap((item) => Array.isArray(item.loading_type_items) && item.loading_type_items.length > 0
            ? item.loading_type_items.map((selected) => selected.code)
            : [item.loading_type_code])
        .filter((value) => value !== null && value !== undefined && String(value).trim() !== '')
        .map((value) => String(value).trim());

    return [...new Set(fromCargo.length > 0 ? fromCargo : (Array.isArray(form.loading_types) ? form.loading_types : []))];
}

const form = useForm({
    ...blankOrder(),
    ...(props.order ?? {}),
    own_company_id: normalizeNullableNumber(props.order?.own_company_id),
    own_company_bank_account_id: props.order?.own_company_bank_account_id
        ? String(props.order.own_company_bank_account_id)
        : null,
    client_id: normalizeNullableNumber(props.order?.client_id),
    manual_status: props.order?.manual_status ?? null,
    additional_expenses: props.order?.additional_expenses ?? null,
    insurance: props.order?.insurance ?? null,
    bonus: props.order?.bonus ?? null,
    loading_types: Array.isArray(props.order?.loading_types)
        ? props.order.loading_types
        : [],
    cargo_items: Array.isArray(props.order?.cargo_items)
        ? props.order.cargo_items.map((c) => normalizeCargoItem(c))
        : blankOrder().cargo_items,
    performers: Array.isArray(props.order?.performers)
        ? props.order.performers.map((performer) => ({
            stage: stageLabel(performer.stage ?? 'leg_1'),
            contractor_id: normalizeNullableNumber(performer.contractor_id),
            contractor_name: performer.contractor_name ? String(performer.contractor_name).trim() || null : null,
            fleet_vehicle_id: normalizeNullableNumber(performer.fleet_vehicle_id),
            fleet_driver_id: normalizeNullableNumber(performer.fleet_driver_id),
        }))
        : blankOrder().performers,
    route_points: Array.isArray(props.order?.route_points)
        ? props.order.route_points.map((point, index) => ({
            ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), stageLabel(point.stage ?? 'leg_1')),
            ...point,
            stage: stageLabel(point.stage ?? 'leg_1'),
            sequence: Number(point.sequence ?? (index + 1)),
            normalized_data: point.normalized_data || {},
        }))
        : blankOrder().route_points,
    financial_term: {
        ...blankOrder().financial_term,
        ...(props.order?.financial_term ?? {}),
        client_payment_schedule: normalizePaymentSchedule(props.order?.financial_term?.client_payment_schedule),
        client_payment_terms:
            props.order?.financial_term?.client_payment_terms
            ?? props.order?.customer_payment_term
            ?? '',
        client_payment_form: normalizePaymentFormCode(
            props.order?.financial_term?.client_payment_form ?? blankOrder().financial_term.client_payment_form,
            'vat',
        ),
        contractors_costs: Array.isArray(props.order?.financial_term?.contractors_costs)
            ? props.order.financial_term.contractors_costs.map((cost) => normalizeContractorCost(cost))
            : [],
    },
    documents: Array.isArray(props.order?.documents)
        ? props.order.documents.map((document) => normalizeDocument(document))
        : [],
    is_international_transport: props.order?.is_international_transport === true,
});

watch(() => form.own_company_id, () => {
    form.own_company_bank_account_id = null;
});

watch(
    () => form.is_international_transport,
    (international) => {
        if (!international) {
            form.route_points = form.route_points.filter((p) => p.type !== 'border_crossing');
            normalizeRoutePointSequences();
        }
        borderCrossingLegPicker.value = '';
    },
);

function resolveOwnCompanyRecord(companyId) {
    if (companyId == null || companyId === '') {
        return null;
    }
    const fromOwn = ownCompanyOptions.value.find((c) => Number(c.id) === Number(companyId));
    if (fromOwn) {
        return fromOwn;
    }
    return contractors.value.find((c) => Boolean(c.is_own_company) && Number(c.id) === Number(companyId)) ?? null;
}

function ownCompanyBankAccountLabel(row) {
    const label = String(row?.label ?? '').trim();
    if (label) {
        return label;
    }
    const digits = String(row?.account_number ?? '').replace(/\D/g, '');
    if (digits.length >= 4) {
        return `Р/с …${digits.slice(-4)}`;
    }
    return 'Расчётный счёт';
}

const selectableOwnCompanyBankAccounts = computed(() => {
    const company = resolveOwnCompanyRecord(form.own_company_id);
    const raw = company?.bank_accounts;
    if (!Array.isArray(raw)) {
        return [];
    }
    return raw.filter((row) => row && row.id != null && String(row.id).trim() !== '');
});

const showOwnCompanyBankAccountPicker = computed(() => selectableOwnCompanyBankAccounts.value.length > 1);

const calculatedCompensation = ref({
    kpi_percent: 0,
    delta: 0,
    salary_accrued: 0,
    deal_type: 'unknown',
});

const isCalculatingCompensation = ref(false);

async function calculateCompensation() {
    if (isCalculatingCompensation.value) {
        return;
    }

    isCalculatingCompensation.value = true;

    try {
        const response = await fetch(route('orders.calculate-compensation'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                customer_rate: form.financial_term.client_price,
                carrier_rate: form.financial_term.contractors_costs.reduce((sum, cost) => sum + Number(cost.amount || 0), 0),
                additional_expenses: Number(form.additional_expenses || 0),
                insurance: Number(form.insurance || 0),
                bonus: Number(form.bonus || 0),
                manager_id: props.order?.responsible_id ?? props.currentUser?.id,
                order_date: form.order_date,
                customer_payment_form: normalizePaymentFormCode(form.financial_term.client_payment_form, defaultClientPaymentForm()),
                contractors_costs: form.financial_term.contractors_costs,
            }),
        });

        if (!response.ok) {
            throw new Error(`Calculation failed with status ${response.status}`);
        }

        const result = await response.json();
        calculatedCompensation.value = result;
        
        // Update the form's KPI percentage with the calculated value
        form.financial_term.kpi_percent = result.kpi_percent || 0;
    } catch (error) {
        console.error('Compensation calculation error', error);
        calculatedCompensation.value = {
            kpi_percent: 0,
            delta: 0,
            salary_accrued: 0,
            deal_type: 'unknown',
        };
        // Reset KPI to 0 on error
        form.financial_term.kpi_percent = 0;
    } finally {
        isCalculatingCompensation.value = false;
    }
}

watch(
    [
        () => form.financial_term.client_price,
        () => form.financial_term.contractors_costs,
        () => form.financial_term.client_payment_form,
        () => form.additional_expenses,
        () => form.insurance,
        () => form.bonus,
        () => form.order_date,
        () => form.client_id,
        () => form.performers,
    ],
    () => {
        calculateCompensation();
    },
    { deep: true, immediate: true },
);

const isEditing = computed(() => props.order !== null);

const orderStatusBadgeLabel = computed(() => {
    const manual = form.manual_status != null && String(form.manual_status).trim() !== '' ? String(form.manual_status).trim() : null;
    const code = manual ?? form.status;
    const opt = props.orderStatusOptions.find((o) => o.value === code);

    return opt?.label ?? code ?? '—';
});

/** Ложь, когда владелец заказа не может менять карточку (все печатные заявки финализированы). */
const isOrderFormEditable = computed(() => {
    if (!isEditing.value) {
        return true;
    }

    return props.order?.can_edit_order !== false;
});

function wizardRouteLoadingHasActualDate() {
    if (!Array.isArray(form.route_points)) {
        return false;
    }

    return form.route_points.some(
        (p) => p.type === 'loading' && p.actual_date != null && String(p.actual_date).trim() !== '',
    );
}

const canShowMarkDisruptionButton = computed(() => {
    if (!isEditing.value || !isOrderFormEditable.value || !props.order?.id) {
        return false;
    }

    const role = props.currentUser?.role_name ?? page.props.auth?.user?.role?.name;
    if (role !== 'admin' && role !== 'supervisor') {
        return false;
    }

    const manual = form.manual_status != null && String(form.manual_status).trim() !== '' ? String(form.manual_status).trim() : null;
    const effective = manual ?? String(form.status || 'new').trim();
    if (effective === 'new') {
        return false;
    }

    if (['disruption', 'cancelled', 'closed'].includes(effective)) {
        return false;
    }

    return ! wizardRouteLoadingHasActualDate();
});
const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});
const selectedClient = computed(() => contractors.value.find((contractor) => Number(contractor.id) === Number(form.client_id)) ?? null);
const carrierOptions = computed(() => contractors.value.filter((contractor) => contractor.type === 'carrier' || contractor.type === 'both'));
const customerDebtBlocked = computed(() => !isEditing.value && Boolean(selectedClient.value?.debt_limit_reached));

const hasSelectedCarrier = computed(() => {
    const performerCarrier = form.performers.some((performer) => Number(performer?.contractor_id || 0) > 0);
    const financialCarrier = form.financial_term.contractors_costs.some((cost) => Number(cost?.contractor_id || 0) > 0);

    return performerCarrier || financialCarrier;
});

const hasClientPrice = computed(() => Number(form.financial_term.client_price || 0) > 0);

const coreValidationIssues = computed(() => {
    const issues = [];

    if (!form.client_id) {
        issues.push('Заказчик');
    }

    if (!form.order_date) {
        issues.push('Дата заказа');
    }

    if (!hasSelectedCarrier.value) {
        issues.push('Перевозчик');
    }

    if (!hasClientPrice.value) {
        issues.push('Цена клиента');
    }

    return issues;
});

const coreRequiredFieldsValid = computed(() => coreValidationIssues.value.length === 0);

const mandatoryWizardFields = new Set([
    'client_id',
    'order_date',
]);

const coreRequiredHighlightClass = 'border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/30';
const defaultFieldBorderClass = 'border-zinc-200 dark:border-zinc-700';

/** Подсветка полей, обязательных для сохранения заказа (см. coreValidationIssues). */
const highlightRequiredField = (fieldName, value, conditionValue = null) => {
    if (fieldName === 'client_currency') {
        if (conditionValue && (!value || value === '' || value === null)) {
            return coreRequiredHighlightClass;
        }

        return defaultFieldBorderClass;
    }

    if (fieldName === 'client_price') {
        const price = Number(value);
        if (!value || value === '' || Number.isNaN(price) || price <= 0) {
            return coreRequiredHighlightClass;
        }

        return defaultFieldBorderClass;
    }

    if (fieldName === 'performer_carrier') {
        if (hasSelectedCarrier.value) {
            return defaultFieldBorderClass;
        }

        if (normalizeNullableNumber(value) === null) {
            return coreRequiredHighlightClass;
        }

        return defaultFieldBorderClass;
    }

    if (!mandatoryWizardFields.has(fieldName)) {
        return defaultFieldBorderClass;
    }

    if (!value || value === '' || value === null) {
        return coreRequiredHighlightClass;
    }

    return defaultFieldBorderClass;
};
const orderPeriodPreview = computed(() => {
    if (!form.order_date) {
        return {
            label: 'Появится после выбора даты',
        };
    }

    const [year, month, day] = String(form.order_date).split('-').map(Number);

    if (!year || !month || !day) {
        return {
            label: 'Некорректная дата',
        };
    }

    const lastDayOfMonth = new Date(year, month, 0).getDate();
    const startDay = day <= 15 ? 1 : 16;
    const endDay = day <= 15 ? 15 : lastDayOfMonth;
    const paddedMonth = String(month).padStart(2, '0');

    return {
        label: `${String(startDay).padStart(2, '0')}-${String(endDay).padStart(2, '0')}.${paddedMonth}.${year}`,
    };
});
const dealTypePreview = computed(() => {
    const clientPaymentForm = String(form.financial_term.client_payment_form ?? '').trim();
    const carrierPaymentForms = form.financial_term.contractors_costs
        .map((cost) => String(cost.payment_form ?? '').trim())
        .filter((value) => value !== '');

    if (clientPaymentForm === '' || carrierPaymentForms.length === 0) {
        return {
            key: 'unknown',
            label: 'Появится после заполнения оплат',
        };
    }

    const isDirectDeal = carrierPaymentForms.every((paymentForm) => paymentForm === clientPaymentForm);

    return {
        key: isDirectDeal ? 'direct' : 'indirect',
        label: isDirectDeal ? 'Прямая' : 'Кривая',
    };
});

/** Меньше порога не фильтруем и не даём «поиск по одной букве» — только общий топ без сужения. */
const MIN_CONTRACTOR_QUERY_LENGTH = 2;

const filteredClients = computed(() => {
    const query = clientSearch.value.trim().toLowerCase();

    // Filter contractors that can be customers (type === 'customer' or type === 'both')
    const customerContractors = contractors.value.filter((contractor) => 
        contractor.type === 'customer' || contractor.type === 'both'
    );

    if (query === '' || query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        return customerContractors.slice(0, 50); // Увеличено с 8 до 50
    }

    return customerContractors
        .filter((contractor) => [contractor.name, contractor.full_name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .slice(0, 50); // Увеличено с 8 до 50
});

// Server-side search for clients
const serverSearchResults = ref([]);
const isSearchingClients = ref(false);
const searchTimer = ref(null);
const clientSearchAbortController = ref(null);
const clientSearchFetchSeq = ref(0);

// Server-side search for carriers
const serverCarrierSearchResults = ref({});
const isSearchingCarriers = ref({});
const carrierSearchTimers = ref({});
const carrierSearchAbortControllers = ref({});
const carrierSearchFetchSeq = ref({});

watch(clientSearch, (newQuery) => {
    clearTimeout(searchTimer.value);

    const trimmed = newQuery.trim();

    if (trimmed.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        clientSearchAbortController.value?.abort();
        clientSearchFetchSeq.value += 1;
        serverSearchResults.value = [];
        isSearchingClients.value = false;
        return;
    }

    searchTimer.value = setTimeout(async () => {
        await searchClients(trimmed);
    }, 550);
});

    async function searchClients(query) {
        if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
            serverSearchResults.value = [];
            return;
        }

        clientSearchAbortController.value?.abort();
        const ac = new AbortController();
        clientSearchAbortController.value = ac;
        const seq = (clientSearchFetchSeq.value += 1);

        isSearchingClients.value = true;

        try {
            const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=customer&limit=100`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                signal: ac.signal,
            });

            if (!response.ok) {
                throw new Error(`Search failed with status ${response.status}`);
            }

            const data = await response.json();
            if (seq !== clientSearchFetchSeq.value) {
                return;
            }

            serverSearchResults.value = data.contractors || [];
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('Client search error', error);
            if (seq === clientSearchFetchSeq.value) {
                serverSearchResults.value = [];
            }
        } finally {
            if (seq === clientSearchFetchSeq.value) {
                isSearchingClients.value = false;
            }
        }
    }

// Watch for carrier search input changes
watch(carrierSearch, (newSearchValues, oldSearchValues) => {
    // Find changed fields
    for (const [key, value] of Object.entries(newSearchValues)) {
        const oldValue = oldSearchValues[key] || '';
        if (value !== oldValue) {
            // Parse kind and index from key
            const match = key.match(/^(\w+)-(\d+)$/);
            if (match) {
                const [, kind, indexStr] = match;
                const index = parseInt(indexStr, 10);
                queueCarrierSearch(kind, index, value);
            }
        }
    }
}, { deep: true });

function queueCarrierSearch(kind, index, query) {
    const key = carrierSearchKey(kind, index);

    // Clear existing timer
    if (carrierSearchTimers.value[key]) {
        clearTimeout(carrierSearchTimers.value[key]);
    }

    // Clear results for empty query
    if (query.trim().length < MIN_CONTRACTOR_QUERY_LENGTH) {
        carrierSearchAbortControllers.value[key]?.abort();
        carrierSearchFetchSeq.value = {
            ...carrierSearchFetchSeq.value,
            [key]: (carrierSearchFetchSeq.value[key] ?? 0) + 1,
        };
        serverCarrierSearchResults.value = {
            ...serverCarrierSearchResults.value,
            [key]: [],
        };
        isSearchingCarriers.value = {
            ...isSearchingCarriers.value,
            [key]: false,
        };
        return;
    }

    // Set new timer
    carrierSearchTimers.value[key] = setTimeout(async () => {
        await searchCarriers(kind, index, query.trim());
    }, 550);
}

async function searchCarriers(kind, index, query) {
    if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        const keyEmpty = carrierSearchKey(kind, index);
        serverCarrierSearchResults.value = {
            ...serverCarrierSearchResults.value,
            [keyEmpty]: [],
        };
        return;
    }

    const key = carrierSearchKey(kind, index);
    carrierSearchAbortControllers.value[key]?.abort();
    const ac = new AbortController();
    carrierSearchAbortControllers.value = {
        ...carrierSearchAbortControllers.value,
        [key]: ac,
    };
    const seq = (carrierSearchFetchSeq.value[key] ?? 0) + 1;
    carrierSearchFetchSeq.value = {
        ...carrierSearchFetchSeq.value,
        [key]: seq,
    };

    isSearchingCarriers.value = {
        ...isSearchingCarriers.value,
        [key]: true,
    };

    try {
        const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=carrier&limit=100`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
            signal: ac.signal,
        });

        if (!response.ok) {
            throw new Error(`Carrier search failed with status ${response.status}`);
        }

        const data = await response.json();
        if (seq !== carrierSearchFetchSeq.value[key]) {
            return;
        }

        serverCarrierSearchResults.value = {
            ...serverCarrierSearchResults.value,
            [key]: data.contractors || [],
        };
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        console.error('Carrier search error', error);
        if (seq === carrierSearchFetchSeq.value[key]) {
            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [key]: [],
            };
        }
    } finally {
        if (seq === carrierSearchFetchSeq.value[key]) {
            isSearchingCarriers.value = {
                ...isSearchingCarriers.value,
                [key]: false,
            };
        }
    }
}

// Combined results: server search results + local preloaded results
const combinedClientResults = computed(() => {
    const query = clientSearch.value.trim().toLowerCase();
    
    if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        // Use local results for short queries
        return filteredClients.value;
    }
    
    // Combine server results with local results, removing duplicates
    const serverIds = new Set(serverSearchResults.value.map(c => c.id));
    const localResults = filteredClients.value.filter(c => !serverIds.has(c.id));
    
    return [...serverSearchResults.value, ...localResults].slice(0, 50);
});

if (selectedClient.value) {
    clientSearch.value = selectedClient.value.name;
}

function selectClient(contractor) {
    ensureContractorInLocalList(contractor);

    form.client_id = normalizeNullableNumber(contractor.id);
    clientSearch.value = contractor.name;
    showClientResults.value = false;
    applyClientDefaults(contractor);
}

function addPerformer() {
    const stage = stageLabel(`leg_${form.performers.length + 1}`);

    form.performers.push({
        stage,
        contractor_id: null,
        contractor_name: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    });
    syncContractorCostsFromPerformers();
    syncRoutePointsFromPerformers();
}

function removePerformer(index) {
    const performer = form.performers[index];

    if (!performer) {
        return;
    }

    const removedStage = performer.stage;

    removeCarrierDocumentsForStage(removedStage);

    form.performers.splice(index, 1);
    form.route_points = form.route_points.filter((point) => !stageMatches(point.stage, removedStage));
    normalizeRoutePointSequences();

    if (form.performers.length > 0) {
        reindexLegStagesAndRemap();
    }

    if (form.performers.length <= 1) {
        form.financial_term.client_request_mode = 'single_request';
    }

    syncContractorCostsFromPerformers();
}

function stageLabel(stage) {
    const match = String(stage ?? '').match(/^leg_(\d+)$/);

    if (match) {
        return `Плечо ${match[1]}`;
    }

    return String(stage ?? '');
}

function toStageKey(label) {
    const match = String(label ?? '').match(/^Плечо (\d+)$/);

    if (match) {
        return `leg_${match[1]}`;
    }

    return String(label ?? '');
}

function stageMatches(left, right) {
    return toStageKey(left) === toStageKey(right);
}

function remapStageReferences(fromStage, toStage) {
    if (stageMatches(fromStage, toStage)) {
        return;
    }

    form.route_points.forEach((point) => {
        if (stageMatches(point.stage, fromStage)) {
            point.stage = toStage;
        }
    });

    form.financial_term.contractors_costs.forEach((row) => {
        if (stageMatches(row.stage, fromStage)) {
            row.stage = toStage;
        }
    });

    form.documents.forEach((doc) => {
        if (doc.party === 'carrier' && doc.stage && stageMatches(doc.stage, fromStage)) {
            doc.stage = toStage;
        }
    });
}

/**
 * После удаления плеча оставшиеся «Плечо 2» и т.д. перенумеровываются в leg_1, leg_2…
 */
function reindexLegStagesAndRemap() {
    const oldStages = form.performers.map((p) => p.stage);

    form.performers = form.performers.map((performer, i) => ({
        ...performer,
        stage: stageLabel(`leg_${i + 1}`),
    }));

    const newStages = form.performers.map((p) => p.stage);

    for (let i = 0; i < form.performers.length; i++) {
        if (!stageMatches(oldStages[i], newStages[i])) {
            remapStageReferences(oldStages[i], newStages[i]);
        }
    }
}

function removeCarrierDocumentsForStage(stage) {
    form.documents = form.documents.filter((doc) => {
        if (doc.party !== 'carrier' || !doc.stage) {
            return true;
        }

        return !stageMatches(doc.stage, stage);
    });
}

/**
 * Убирает плечи, для которых не осталось ни одной точки маршрута (например после удаления этапов).
 */
function pruneEmptyLegPerformers() {
    const stagesWithPoints = new Set(form.route_points.map((p) => toStageKey(p.stage)));
    const before = form.performers.length;

    const filtered = form.performers.filter((p) => stagesWithPoints.has(toStageKey(p.stage)));

    if (filtered.length === before) {
        return;
    }

    const removedStages = form.performers
        .filter((p) => !stagesWithPoints.has(toStageKey(p.stage)))
        .map((p) => p.stage);

    removedStages.forEach((stage) => removeCarrierDocumentsForStage(stage));

    form.performers = filtered;

    if (form.performers.length === 0) {
        form.performers = [{ stage: stageLabel('leg_1'), contractor_id: null, contractor_name: null, fleet_vehicle_id: null, fleet_driver_id: null }];
        syncRoutePointsFromPerformers();
    } else {
        reindexLegStagesAndRemap();
    }

    if (form.performers.length <= 1) {
        form.financial_term.client_request_mode = 'single_request';
    }

    syncContractorCostsFromPerformers();
}

function onRoutePointLegChanged() {
    nextTick(() => {
        pruneEmptyLegPerformers();
    });
}

function getContractorById(contractorId) {
    return contractors.value.find((contractor) => Number(contractor.id) === Number(contractorId)) ?? null;
}

/**
 * Серверный поиск возвращает контрагента, которого может не быть в props.contractors.
 * Без записи в локальный список getContractorById (blur, watch) обнуляет подпись в поле перевозчика.
 */
function ensureContractorInLocalList(contractor) {
    if (!contractor?.id) {
        return;
    }

    const id = Number(contractor.id);
    if (contractors.value.some((c) => Number(c.id) === id)) {
        return;
    }

    contractors.value.unshift({ ...contractor });
}

/**
 * Подпись в поле поиска перевозчика: справочник в props может быть укороченным, а contractor_id при этом валиден.
 */
function performerCarrierSearchLabel(performerIndex, contractorId) {
    const id = normalizeNullableNumber(contractorId);
    if (id === null) {
        return '';
    }

    const contractor = getContractorById(id);
    const fromLookup = contractor?.name ? String(contractor.name).trim() : '';
    if (fromLookup) {
        return fromLookup;
    }

    const row = form.performers[performerIndex];
    const fromRow = row?.contractor_name ? String(row.contractor_name).trim() : '';

    return fromRow || '';
}

function carrierSearchKey(kind, index) {
    return `${kind}-${index}`;
}

function carrierSearchValue(kind, index) {
    return carrierSearch.value[carrierSearchKey(kind, index)] ?? '';
}

function setCarrierSearchValue(kind, index, value) {
    carrierSearch.value = {
        ...carrierSearch.value,
        [carrierSearchKey(kind, index)]: value,
    };
}

function setCarrierResultsVisible(kind, index, visible) {
    showCarrierResults.value = {
        ...showCarrierResults.value,
        [carrierSearchKey(kind, index)]: visible,
    };
}

function isCarrierResultsVisible(kind, index) {
    return Boolean(showCarrierResults.value[carrierSearchKey(kind, index)]);
}

function filteredCarrierResults(kind, index) {
    const query = carrierSearchValue(kind, index).trim().toLowerCase();
    const selectedContractorId = kind === 'performer'
        ? normalizeNullableNumber(form.performers[index]?.contractor_id)
        : normalizeNullableNumber(form.financial_term.contractors_costs[index]?.contractor_id);
    const selectedContractor = getContractorById(selectedContractorId);
    
    // Get server search results for this specific field
    const serverResults = serverCarrierSearchResults.value[carrierSearchKey(kind, index)] || [];
    const serverIds = new Set(serverResults.map(c => c.id));

    if (query === '' || query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        const visibleContractors = carrierOptions.value.slice(0, 50);

        if (!selectedContractor || visibleContractors.some((contractor) => contractor.id === selectedContractor.id)) {
            return visibleContractors;
        }

        return [selectedContractor, ...visibleContractors.slice(0, 49)];
    }

    // Combine server results with local results
    const localResults = carrierOptions.value
        .filter((contractor) => [contractor.name, contractor.full_name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .filter(c => !serverIds.has(c.id));
    
    return [...serverResults, ...localResults].slice(0, 50);
}

function parsePaymentTermPreset(term) {
    if (!term) {
        return blankPaymentSchedule();
    }

    const normalized = String(term).trim().toUpperCase();
    const prepaymentPercentMatch = normalized.match(/^(\d{1,2})%\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)\s*\/\s*(\d{1,2})%\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)$/u);

    if (prepaymentPercentMatch) {
        return normalizePaymentSchedule({
            has_prepayment: true,
            prepayment_ratio: Number(prepaymentPercentMatch[1]),
            prepayment_days: Number(prepaymentPercentMatch[2]),
            prepayment_mode: prepaymentPercentMatch[3].toLowerCase(),
            postpayment_days: Number(prepaymentPercentMatch[5]),
            postpayment_mode: prepaymentPercentMatch[6].toLowerCase(),
        });
    }

    const prepaymentMatch = normalized.match(/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)\s*\/\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)$/u);

    if (prepaymentMatch) {
        return normalizePaymentSchedule({
            has_prepayment: true,
            prepayment_ratio: Number(prepaymentMatch[1]),
            prepayment_days: Number(prepaymentMatch[3]),
            prepayment_mode: prepaymentMatch[4].toLowerCase(),
            postpayment_days: Number(prepaymentMatch[5]),
            postpayment_mode: prepaymentMatch[6].toLowerCase(),
        });
    }

    const postpaymentMatch = normalized.match(/^(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN|LOADING|UNLOADING)$/u);

    if (postpaymentMatch) {
        return normalizePaymentSchedule({
            has_prepayment: false,
            postpayment_days: Number(postpaymentMatch[1]),
            postpayment_mode: postpaymentMatch[2].toLowerCase(),
        });
    }

    return blankPaymentSchedule();
}

function contractorPaymentSchedule(contractor, scheduleField, legacyField) {
    if (contractor?.[scheduleField]) {
        return normalizePaymentSchedule(contractor[scheduleField]);
    }

    if (contractor?.[legacyField]) {
        return parsePaymentTermPreset(contractor[legacyField]);
    }

    return blankPaymentSchedule();
}

function applyClientDefaults(contractor) {
    if (!contractor) {
        return;
    }

    if (contractor.default_customer_payment_form) {
        form.financial_term.client_payment_form = normalizePaymentFormCode(contractor.default_customer_payment_form, defaultClientPaymentForm());
    }

    form.financial_term.client_payment_schedule = contractorPaymentSchedule(contractor, 'default_customer_payment_schedule', 'default_customer_payment_term');

    if (contractor.cooperation_terms_notes && !String(form.special_notes || '').trim()) {
        form.special_notes = contractor.cooperation_terms_notes;
    }
}

function applyCarrierDefaultsByStage(stage, contractorId) {
    const contractor = getContractorById(contractorId);

    if (!contractor) {
        return;
    }

    const costRow = form.financial_term.contractors_costs.find((row) => stageMatches(row.stage, stage));

    if (!costRow) {
        return;
    }

    if (contractor.default_carrier_payment_form) {
        costRow.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
    }

    costRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
}

function selectPerformerContractor(index, contractor) {
    ensureContractorInLocalList(contractor);

    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: Number(contractor.id),
        contractor_name: contractor.name ? String(contractor.name).trim() || null : null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, contractor.name);
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[index].stage, contractor.id);
    loadFleetOptionsForLeg(index);
}

function clearPerformerContractor(index) {
    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: null,
        contractor_name: null,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, '');
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    fleetOptionsCache.value = { ...fleetOptionsCache.value, [index]: { vehicles: [], drivers: [] } };
}

function syncPerformerContractor(stage, contractorId) {
    const performer = form.performers.find((item) => stageMatches(item.stage, stage));

    if (!performer) {
        return;
    }

    performer.contractor_id = contractorId !== null ? Number(contractorId) : null;
    performer.contractor_name = null;
}

function onPerformerCarrierInput(index, value) {
    setCarrierSearchValue('performer', index, value);
    setCarrierResultsVisible('performer', index, true);

    const performer = form.performers[index];
    if (!performer) {
        return;
    }

    const typed = String(value ?? '').trim().toLowerCase();
    const selectedContractor = getContractorById(performer.contractor_id);
    const selectedName = String(selectedContractor?.name ?? performer.contractor_name ?? '').trim().toLowerCase();

    if (typed === '') {
        clearPerformerContractor(index);

        return;
    }

    if (normalizeNullableNumber(performer.contractor_id) !== null && selectedName !== '' && selectedName !== typed) {
        performer.contractor_id = null;
        performer.contractor_name = null;
        performer.fleet_vehicle_id = null;
        performer.fleet_driver_id = null;
        syncContractorCostsFromPerformers();
    }
}

function restorePerformerCarrierSearch(index) {
    window.setTimeout(() => {
        const performer = form.performers[index];
        if (!performer) {
            return;
        }

        setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, performer.contractor_id));
        setCarrierResultsVisible('performer', index, false);
    }, 120);
}

async function loadFleetOptionsForLeg(legIndex) {
    const contractorId = normalizeNullableNumber(form.performers[legIndex]?.contractor_id);
    if (!contractorId) {
        fleetOptionsCache.value = { ...fleetOptionsCache.value, [legIndex]: { vehicles: [], drivers: [] } };

        return;
    }

    const requestOptions = {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'include',
    };

    const loadVehicles = fetch(`${route('fleet.options.vehicles')}?owner_contractor_id=${contractorId}`, requestOptions)
        .then(async (response) => {
            if (!response.ok) {
                return [];
            }

            const payload = await response.json();

            return Array.isArray(payload?.vehicles) ? payload.vehicles : [];
        })
        .catch(() => []);

    const loadDrivers = fetch(`${route('fleet.options.drivers')}?carrier_contractor_id=${contractorId}`, requestOptions)
        .then(async (response) => {
            if (!response.ok) {
                return [];
            }

            const payload = await response.json();

            return Array.isArray(payload?.drivers) ? payload.drivers : [];
        })
        .catch(() => []);

    const [vehicles, drivers] = await Promise.all([loadVehicles, loadDrivers]);
    fleetOptionsCache.value = { ...fleetOptionsCache.value, [legIndex]: { vehicles, drivers } };
}

function fleetVehicleOptionsForLeg(legIndex) {
    return fleetOptionsCache.value[legIndex]?.vehicles ?? [];
}

function fleetDriverOptionsForLeg(legIndex) {
    return fleetOptionsCache.value[legIndex]?.drivers ?? [];
}

const routeChainLabel = computed(() => {
    if (form.route_points.length === 0) {
        return 'Маршрут пока не задан';
    }

    return form.route_points
        .slice()
        .sort((left, right) => Number(left.sequence ?? 0) - Number(right.sequence ?? 0))
        .map((point) => {
            if (point.type === 'border_crossing') {
                const svh = String(form.svh_name ?? '').trim();

                return `${routePointTypeHeading(point.type)}: ${svh || 'СВХ не указан'}`;
            }

            return `${routePointTypeHeading(point.type)}: ${point.address || 'адрес не указан'}`;
        })
        .join(' → ');
});

const hasBorderCrossingPoint = computed(
    () => Array.isArray(form.route_points) && form.route_points.some((p) => p.type === 'border_crossing'),
);

const cargoSummary = computed(() => {
    return form.cargo_items.reduce((summary, item) => {
        summary.totalWeight += cargoLineTotalWeightKg(item);
        summary.totalVolume += cargoLineTotalVolumeM3(item);
        summary.totalPackages += Number(item.package_count || 0);

        return summary;
    }, {
        totalWeight: 0,
        totalVolume: 0,
        totalPackages: 0,
    });
});

watch(
    () => form.cargo_items,
    (items) => {
        items.forEach((item) => {
            item.dangerous_goods = item.cargo_type === 'dangerous';
            const v = cargoComputedVolumeM3(item);
            if (v !== null) {
                item.volume_m3 = Math.round(v * 1000) / 1000;
            } else if (!cargoDimensionFieldsEmpty(item)) {
                item.volume_m3 = null;
            }
        });
    },
    { deep: true, immediate: true },
);

/** Ошибки валидации по документам и полю order_payload (вкладка «Документы»). */
const documentTabValidationMessages = computed(() => {
    return Object.entries(form.errors)
        .filter(([key]) => key === 'order_payload' || key.startsWith('documents'))
        .map(([, value]) => (Array.isArray(value) ? value.join(' ') : String(value)));
});

const hasUnsavedDocumentFiles = computed(() => form.documents.some((d) => d.file instanceof File));

const financialSummary = computed(() => {
    const clientPrice = Number(form.financial_term.client_price || 0);
    const contractorCosts = form.financial_term.contractors_costs.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const additionalExpenses = Number(form.additional_expenses || 0);
    const insurance = Number(form.insurance || 0);
    const rawBonus = Number(form.bonus || 0);
    const kpiPercent = Number(form.financial_term.kpi_percent || 0);
    const serverDelta = Number(calculatedCompensation.value?.delta ?? NaN);
    const hasReliableServerDelta = Number.isFinite(serverDelta) && calculatedCompensation.value?.deal_type !== 'unknown';

    let effectiveBonusCost = rawBonus;
    let margin;

    if (hasReliableServerDelta) {
        const baseAfterKpi = clientPrice * (1 - (kpiPercent / 100));
        const computedBonusCost = baseAfterKpi - contractorCosts - additionalExpenses - insurance - serverDelta;
        effectiveBonusCost = Math.max(0, computedBonusCost);
        margin = serverDelta;
    } else {
        margin = clientPrice * (1 - (kpiPercent / 100)) - (contractorCosts + additionalExpenses + insurance + rawBonus);
    }

    const additionalCosts = additionalExpenses + insurance + effectiveBonusCost;
    const totalCost = contractorCosts + additionalCosts;

    return {
        clientPrice,
        contractorCosts,
        additionalCosts,
        totalCost,
        margin,
    };
});

const orderPaymentSettlement = computed(() => props.order?.payment_settlement ?? null);

const showPaymentSettlementBlock = computed(() => {
    const settlement = orderPaymentSettlement.value;
    if (!settlement) {
        return false;
    }

    return Boolean(settlement.customer?.has_rows || settlement.carrier?.has_rows);
});

function formatRuDate(isoDate) {
    if (!isoDate) {
        return '';
    }
    const parsed = new Date(`${isoDate}T12:00:00`);
    if (Number.isNaN(parsed.getTime())) {
        return String(isoDate);
    }

    return parsed.toLocaleDateString('ru-RU');
}

function paymentSettlementLineLabel(party) {
    if (!party?.has_rows) {
        return '—';
    }
    if (party.complete && party.settled_at) {
        return formatRuDate(party.settled_at);
    }

    return 'не завершено';
}

const documentChecklist = computed(() => {
    if (props.order?.id && Array.isArray(props.requiredDocumentChecklist) && props.requiredDocumentChecklist.length > 0) {
        return props.requiredDocumentChecklist;
    }

    const documents = Array.isArray(form.documents) ? form.documents : [];

    return props.requiredDocumentRules.map((rule) => {
        const matchedDocument = documents.find((document) => {
            if (!Array.isArray(rule.accepted_types) || !rule.accepted_types.includes(document.type)) {
                return false;
            }

            if (String(document.party ?? 'internal') !== rule.party) {
                return false;
            }

            const status = String(document.status ?? '');

            return ['sent', 'signed'].includes(status);
        });

        return {
            ...rule,
            completed: matchedDocument !== undefined,
            matched_document_id: matchedDocument?.id ?? null,
        };
    });
});

const customerDocuments = computed(() => {
    return form.documents
        .map((document, index) => ({ document, index }))
        .filter((item) => item.document.party === 'customer');
});

function carrierDocumentsForStage(stage) {
    return form.documents
        .map((document, index) => ({ document, index }))
        .filter((item) => item.document.party === 'carrier' && stageMatches(item.document.stage, stage));
}

function addRoutePoint(type) {
    form.route_points.push(blankRoutePoint(
        type,
        form.route_points.length + 1,
        form.performers[0]?.stage ?? stageLabel('leg_1'),
    ));
}

function addRoutePointForLeg(stage, type) {
    const stagePoints = form.route_points
        .map((p, i) => ({ p, i }))
        .filter(({ p }) => stageMatches(p.stage, stage));
    let insertAt = form.route_points.length;
    if (type === 'border_crossing') {
        const firstUnload = stagePoints.find(({ p }) => p.type === 'unloading');
        if (firstUnload) {
            insertAt = firstUnload.i;
        } else if (stagePoints.length > 0) {
            insertAt = stagePoints[stagePoints.length - 1].i + 1;
        }
    } else if (stagePoints.length > 0) {
        insertAt = stagePoints[stagePoints.length - 1].i + 1;
    }
    form.route_points.splice(insertAt, 0, blankRoutePoint(type, 0, stage));
    normalizeRoutePointSequences();
}

function onBorderCrossingLegPickerChange() {
    const raw = borderCrossingLegPicker.value;
    if (raw === '' || raw === null || raw === undefined) {
        return;
    }
    const idx = Number.parseInt(String(raw), 10);
    if (!Number.isFinite(idx) || idx < 0) {
        borderCrossingLegPicker.value = '';

        return;
    }
    const performer = form.performers[idx];
    if (!performer) {
        borderCrossingLegPicker.value = '';

        return;
    }
    addRoutePointForLeg(performer.stage, 'border_crossing');
    borderCrossingLegPicker.value = '';
}

function routePointTypeHeading(type) {
    if (type === 'loading') {
        return 'Погрузка';
    }
    if (type === 'border_crossing') {
        return 'Граница';
    }

    return 'Выгрузка';
}

function routePointTimeBlockHeading(type) {
    if (type === 'loading') {
        return 'Время загрузки';
    }
    if (type === 'border_crossing') {
        return 'Окно (план)';
    }

    return 'Время выгрузки';
}

function routePointAddressHighlightValue(point) {
    if (point.type === 'border_crossing') {
        return String(point.address ?? '').trim() || String(point.planned_date ?? '').trim();
    }

    return point.address;
}

function normalizeRoutePointSequences() {
    form.route_points = form.route_points.map((point, index) => ({
        ...point,
        sequence: index + 1,
    }));
}

function syncRoutePointsFromPerformers() {
    const performerStages = form.performers.map((performer) => performer.stage);

    if (performerStages.length === 0) {
        form.route_points = [];

        return;
    }

    const existingPoints = Array.isArray(form.route_points)
        ? form.route_points.map((point, index) => ({
            ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), point.stage ?? performerStages[0]),
            ...point,
            stage: point.stage ?? performerStages[0],
        }))
        : [];

    const nextPoints = [];

    performerStages.forEach((stage) => {
        const stagePoints = existingPoints.filter((point) => stageMatches(point.stage, stage));
        const normalizedStagePoints = stagePoints.map((point) => ({
            ...point,
            stage,
        }));

        if (!normalizedStagePoints.some((point) => point.type === 'loading')) {
            normalizedStagePoints.unshift(blankRoutePoint('loading', 0, stage));
        }

        if (!normalizedStagePoints.some((point) => point.type === 'unloading')) {
            normalizedStagePoints.push(blankRoutePoint('unloading', 0, stage));
        }

        nextPoints.push(...normalizedStagePoints);
    });

    form.route_points = nextPoints.map((point, index) => ({
        ...point,
        sequence: index + 1,
    }));
}

function routePointOrdinal(index) {
    const currentPoint = form.route_points[index];

    return form.route_points
        .slice(0, index + 1)
        .filter((point) => point.type === currentPoint?.type)
        .length;
}

function routePointTitle(point, index) {
    const ordinal = routePointOrdinal(index);

    if (point.type === 'loading') {
        return `Погрузка ${ordinal}`;
    }
    if (point.type === 'border_crossing') {
        return `Прохождение границы ${ordinal}`;
    }

    return `Выгрузка ${ordinal}`;
}

function routePointCombinedContact(point) {
    if (point.type === 'loading') {
        return point.sender_contact || point.sender_phone || point.contact_person || point.contact_phone || '';
    }

    if (point.type === 'unloading') {
        return point.recipient_contact || point.recipient_phone || point.contact_person || point.contact_phone || '';
    }

    return point.contact_person || point.contact_phone || '';
}

function setRoutePointCombinedContact(point, value) {
    const normalizedValue = String(value ?? '').trim();
    point.contact_person = normalizedValue;
    point.contact_phone = '';

    if (point.type === 'loading') {
        point.sender_contact = normalizedValue;
        point.sender_phone = '';
    }

    if (point.type === 'unloading') {
        point.recipient_contact = normalizedValue;
        point.recipient_phone = '';
    }
}

/**
 * @return {Array<{ point: object, globalIndex: number }>}
 */
function routePointsWithIndicesForLeg(stage) {
    const result = [];

    form.route_points.forEach((point, globalIndex) => {
        if (stageMatches(point.stage, stage)) {
            result.push({ point, globalIndex });
        }
    });

    return result.sort((left, right) => Number(left.point.sequence ?? 0) - Number(right.point.sequence ?? 0));
}

function routePointsDragEnabled() {
    return form.performers.length <= 1;
}

function handleRoutePointDragStart(index, event) {
    if (!routePointsDragEnabled()) {
        return;
    }

    draggedRoutePointIndex.value = index;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
}

function handleRoutePointDragOver(index) {
    if (!routePointsDragEnabled()) {
        return;
    }

    if (draggedRoutePointIndex.value === null || draggedRoutePointIndex.value === index) {
        return;
    }

    dragOverRoutePointIndex.value = index;
}

function handleRoutePointDrop(targetIndex) {
    if (!routePointsDragEnabled()) {
        return;
    }

    const sourceIndex = draggedRoutePointIndex.value;

    if (sourceIndex === null || sourceIndex === targetIndex) {
        return;
    }

    const nextPoints = [...form.route_points];
    const [movedPoint] = nextPoints.splice(sourceIndex, 1);
    nextPoints.splice(targetIndex, 0, movedPoint);
    form.route_points = nextPoints;
    normalizeRoutePointSequences();
    draggedRoutePointIndex.value = null;
    dragOverRoutePointIndex.value = null;
}

function handleRoutePointDragEnd() {
    if (!routePointsDragEnabled()) {
        return;
    }

    draggedRoutePointIndex.value = null;
    dragOverRoutePointIndex.value = null;
}

function addCargoItem() {
    form.cargo_items.push(normalizeCargoItem({}));
}

function addDocument() {
    addDocumentFor('customer', null);
}

function addDocumentFor(party, stage = null) {
    form.documents.push(normalizeDocument({
        party,
        stage,
    }));
}

function removeDocumentAt(index) {
    form.documents.splice(index, 1);
}

function removeItem(collection, index) {
    collection.splice(index, 1);

    if (collection === form.route_points) {
        normalizeRoutePointSequences();
        pruneEmptyLegPerformers();
    }
}

function syncContractorCostsFromPerformers() {
    const existingRows = Array.isArray(form.financial_term.contractors_costs)
        ? form.financial_term.contractors_costs
        : [];

    form.financial_term.contractors_costs = form.performers.map((performer) => {
        const existingRow = existingRows.find((row) => stageMatches(row.stage, performer.stage));

        const nextRow = normalizeContractorCost({
            ...existingRow,
            stage: performer.stage,
            contractor_id: performer.contractor_id,
        });

        // Apply carrier defaults when contractor is set (even if row already exists)
        if (performer.contractor_id) {
            const contractor = getContractorById(performer.contractor_id);

            if (contractor?.default_carrier_payment_form) {
                nextRow.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
            }

            nextRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
        }

        return nextRow;
    });
}

// Watch for changes in contractors_costs to sync back to performers
// Удалено для предотвращения циклической синхронизации при очистке исполнителя
// watch(
//     () => form.financial_term.contractors_costs,
//     (costs) => {
//         costs.forEach((cost) => {
//             const performer = form.performers.find((item) => item.stage === cost.stage);
//             if (performer && performer.contractor_id !== cost.contractor_id) {
//                 performer.contractor_id = cost.contractor_id;
//             }
//         });
//     },
//     { deep: true },
// );

if (Array.isArray(props.order?.performers)) {
    props.order.performers.forEach((p) => {
        const id = normalizeNullableNumber(p.contractor_id);
        const name = p.contractor_name ? String(p.contractor_name).trim() : '';
        if (id !== null && name !== '') {
            ensureContractorInLocalList({
                id,
                name,
                type: 'carrier',
                inn: null,
                phone: null,
                email: null,
                is_own_company: false,
            });
        }
    });
}

watch(
    () => form.performers.map((performer) => [performer.stage, performer.contractor_id, performer.contractor_name]),
    (performers, prev) => {
        performers.forEach(([stage, contractorId], index) => {
            setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, contractorId));
            const costIndex = form.financial_term.contractors_costs.findIndex((row) => stageMatches(row.stage, stage));

            if (costIndex !== -1) {
                setCarrierSearchValue('cost', costIndex, performerCarrierSearchLabel(index, contractorId));
            }

            const prevRow = prev?.[index];
            if (prevRow && prevRow[1] !== contractorId) {
                const performerRow = form.performers[index];
                if (performerRow) {
                    performerRow.fleet_vehicle_id = null;
                    performerRow.fleet_driver_id = null;
                }
            }

            if (!prevRow || prevRow[1] !== contractorId) {
                loadFleetOptionsForLeg(index);
            }
        });
    },
    { deep: true, immediate: true },
);

watch(
    () => form.client_id,
    () => {
        if (selectedClient.value) {
            clientSearch.value = selectedClient.value.name;
        }
    },
    { immediate: true },
);

async function onDocumentFileChange(index, event) {
    const file = event.target.files?.[0] ?? null;
    if (file) {
        await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    }
    form.documents[index].file = file;
    if (file) {
        form.documents[index].original_name = file.name;
    }
}

function documentTypeLabel(type) {
    return props.documentTypeOptions.find((option) => option.value === type)?.label ?? type;
}

function documentRequirementLabel(key) {
    return props.requiredDocumentRules.find((rule) => rule.key === key)?.label ?? '';
}

function paymentFormLabel(value) {
    return paymentFormOptions.value.find((option) => option.value === value)?.label ?? value;
}

function paymentBasisLabel(value) {
    return orderPs.PAYMENT_BASIS_OPTIONS.find((option) => option.value === value)?.label ?? value;
}

function queueAddressLookup(index) {
    clearTimeout(addressTimers[index]);

    if (String(form.route_points[index]?.address ?? '').trim().length < 3) {
        addressSuggestions.value[index] = [];
        return;
    }

    addressTimers[index] = window.setTimeout(() => {
        fetchAddressSuggestions(index);
    }, 300);
}

async function fetchAddressSuggestions(index) {
    const query = form.route_points[index]?.address ?? '';

    try {
        const response = await fetch(`${route('orders.suggest-address')}?query=${encodeURIComponent(query)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        addressSuggestions.value[index] = Array.isArray(data.suggestions) ? data.suggestions : [];
    } catch (error) {
        console.error('Address suggestions error', error);
        addressSuggestions.value[index] = [];
    }
}

function selectAddress(index, suggestion) {
    form.route_points[index].address = suggestion.value ?? '';
    form.route_points[index].normalized_data = {
        city: suggestion.data?.city ?? suggestion.data?.settlement ?? null,
        region: suggestion.data?.region_with_type ?? suggestion.data?.region ?? null,
        street: suggestion.data?.street_with_type ?? suggestion.data?.street ?? null,
        house: suggestion.data?.house ?? null,
        coordinates: {
            lat: suggestion.data?.geo_lat ?? null,
            lng: suggestion.data?.geo_lon ?? null,
        },
        kladr_id: suggestion.data?.kladr_id ?? null,
        fias_id: suggestion.data?.fias_id ?? null,
    };
    addressSuggestions.value[index] = [];
}

async function createInlineCounterparty() {
    inlineContractorSaving.value = true;

    try {
        const response = await fetch(route('orders.contractors.store'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(counterpartyForm.data()),
        });

        if (!response.ok) {
            throw new Error(`Inline contractor creation failed with status ${response.status}`);
        }

        const payload = await response.json();
        const contractor = payload.contractor;

        contractors.value.unshift(contractor);
        if (contractor.is_own_company) {
            ownCompanyOptions.value.unshift(contractor);
        }
        if (counterpartyTarget.value.kind === 'performer' && counterpartyTarget.value.index !== null) {
            selectPerformerContractor(counterpartyTarget.value.index, contractor);
        } else {
            selectClient(contractor);
        }
        counterpartyForm.reset();
        counterpartyForm.type = 'customer';
        showCounterpartyModal.value = false;
        counterpartyTarget.value = { kind: 'client', index: null };
    } catch (error) {
        console.error(error);
    } finally {
        inlineContractorSaving.value = false;
    }
}

function buildSubmitPayload() {
    // Снимок без Vue/Inertia proxy: иначе вложенные суммы иногда уходят в запросе «как при загрузке».
    const rawFinancial = JSON.parse(JSON.stringify(toRaw(form.financial_term)));

    return {
        // Basic order fields
        status: form.status,
        own_company_id: form.own_company_id,
        own_company_bank_account_id: form.own_company_bank_account_id && String(form.own_company_bank_account_id).trim() !== ''
            ? String(form.own_company_bank_account_id).trim()
            : null,
        client_id: form.client_id,
        order_date: form.order_date,
        order_number: form.order_number,
        payment_terms: form.payment_terms,
        special_notes: form.special_notes,
        svh_name: form.svh_name,
        is_international_transport: Boolean(form.is_international_transport),
        additional_expenses: form.additional_expenses,
        insurance: form.insurance,
        bonus: form.bonus,

        // Performers array (the server expects this field)
        performers: form.performers.map((performer) => ({
            stage: performer.stage,
            contractor_id: normalizeNullableNumber(performer.contractor_id),
            fleet_vehicle_id: normalizeNullableNumber(performer.fleet_vehicle_id),
            fleet_driver_id: normalizeNullableNumber(performer.fleet_driver_id),
        })),

        // Route points
        route_points: form.route_points.map((point) => ({
            stage: point.stage,
            type: point.type,
            sequence: point.sequence,
            address: point.address,
            normalized_data: point.normalized_data || {},
            planned_date: point.planned_date,
            planned_time_from: point.planned_time_from || null,
            planned_time_to: point.planned_time_to || null,
            actual_date: point.actual_date,
            actual_time: point.actual_time || null,
            contact_person: point.contact_person,
            contact_phone: point.contact_phone,
            sender_name: point.sender_name,
            sender_contact: point.sender_contact,
            sender_phone: point.sender_phone,
            recipient_name: point.recipient_name,
            recipient_contact: point.recipient_contact,
            recipient_phone: point.recipient_phone,
        })),
        loading_types: selectedLoadingTypeCodes(),

        // Cargo items
        cargo_items: form.cargo_items.map((item) => ({
            name: item.name,
            description: item.description,
            weight_value: item.weight_value ?? item.weight_kg,
            weight_kg: item.weight_value ?? item.weight_kg,
            weight_unit: item.weight_unit === 't' ? 't' : 'kg',
            volume_m3: item.volume_m3,
            length_m: item.length_m,
            width_m: item.width_m,
            height_m: item.height_m,
            diameter_m: item.diameter_m,
            package_type: item.package_type,
            pack_type_id: normalizeNullableNumber(item.pack_type_id),
            pack_type_label: item.pack_type_label,
            loading_type_id: normalizeNullableNumber(item.loading_type_id),
            loading_type_code: item.loading_type_code,
            loading_type_label: item.loading_type_label,
            loading_type_items: item.loading_type_items || [],
            truck_body_type_id: normalizeNullableNumber(item.truck_body_type_id),
            truck_body_type_code: item.truck_body_type_code,
            truck_body_type_label: item.truck_body_type_label,
            truck_body_type_items: item.truck_body_type_items || [],
            trailer_type_id: normalizeNullableNumber(item.trailer_type_id),
            trailer_type_code: item.trailer_type_code,
            trailer_type_label: item.trailer_type_label,
            trailer_type_items: item.trailer_type_items || [],
            package_count: item.package_count,
            dangerous_goods: item.dangerous_goods,
            dangerous_class: item.dangerous_class,
            hs_code: item.hs_code,
            cargo_type: item.cargo_type,
            cargo_type_id: normalizeNullableNumber(item.cargo_type_id),
            cargo_type_label: item.cargo_type_label,
            is_oversized: item.is_oversized,
            is_fragile: item.is_fragile,
            ati_cargo_payload: item.ati_cargo_payload || {},
        })),

        // Financial term
        financial_term: {
            client_price: rawFinancial.client_price,
            client_currency: rawFinancial.client_currency,
            client_payment_form: normalizePaymentFormCode(rawFinancial.client_payment_form, defaultClientPaymentForm()),
            client_request_mode: rawFinancial.client_request_mode,
            client_payment_schedule: rawFinancial.client_payment_schedule || {},
            client_payment_terms: rawFinancial.client_payment_terms ?? '',
            contractors_costs: (rawFinancial.contractors_costs || []).map((cost) => ({
                stage: cost.stage,
                contractor_id: normalizeNullableNumber(cost.contractor_id),
                amount: cost.amount,
                currency: cost.currency || 'RUB',
                payment_form: normalizePaymentFormCode(cost.payment_form, 'no_vat'),
                payment_schedule: cost.payment_schedule || {},
                payment_terms: cost.payment_terms ?? '',
            })),
            additional_costs: [],
            kpi_percent: rawFinancial.kpi_percent,
        },

        // Documents
        documents: form.documents
            .filter((document) => !document.is_print_workflow && document.flow !== 'print_template_workflow')
            .map((document) => ({
                type: document.type,
                flow: document.flow,
                party: document.party,
                stage: document.stage,
                requirement_key: document.requirement_key,
                number: document.number,
                document_date: document.document_date && String(document.document_date).trim() !== ''
                    ? document.document_date
                    : null,
                status: document.status,
                template_id: document.template_id,
                file: document.file instanceof File ? document.file : null,
                original_name: document.original_name,
                generated_pdf_path: document.generated_pdf_path,
            })),
    };
}

function markOrderDisruption() {
    if (!canShowMarkDisruptionButton.value || !props.order?.id) {
        return;
    }

    if (! window.confirm('Установить статус «Срыв»? Убедитесь, что по маршруту ещё не указана фактическая дата погрузки.')) {
        return;
    }

    const previousStatus = form.status;
    form.status = 'disruption';

    submit({
        skipCoreValidation: true,
        revertStatusOnError: previousStatus,
    });
}

function submit(options = {}) {
    const skipCoreValidation = options.skipCoreValidation === true;
    const revertStatusOnError = options.revertStatusOnError ?? null;

    saveAttempted.value = true;

    if (isEditing.value && !isOrderFormEditable.value) {
        return;
    }

    if (! skipCoreValidation && ! coreRequiredFieldsValid.value) {
        const errors = {};

        if (!form.client_id) {
            errors.client_id = 'Выберите заказчика.';
        }

        if (!form.order_date) {
            errors.order_date = 'Укажите дату заказа.';
        }

        if (!hasSelectedCarrier.value) {
            errors.performers = 'Укажите хотя бы одного перевозчика.';
            errors['financial_term.contractors_costs'] = 'Для сохранения нужен выбранный перевозчик.';
        }

        if (!hasClientPrice.value) {
            errors['financial_term.client_price'] = 'Укажите цену клиента больше 0.';
        }

        if (!form.client_id || !form.order_date) {
            activeTab.value = 'main';
        } else if (!hasSelectedCarrier.value) {
            activeTab.value = 'route';
        } else if (!hasClientPrice.value) {
            activeTab.value = 'finance';
        }

        form.clearErrors().setError(errors);

        return;
    }

    const costsByStage = new Map(
        form.financial_term.contractors_costs.map((cost) => [toStageKey(cost.stage), cost]),
    );
    form.performers = form.performers.map((performer) => {
        const syncedCost = costsByStage.get(toStageKey(performer.stage));
        const prevId = normalizeNullableNumber(performer.contractor_id);
        const nextId = normalizeNullableNumber(syncedCost?.contractor_id ?? performer.contractor_id ?? null);
        let nextName = performer.contractor_name ?? null;
        if (nextId === null) {
            nextName = null;
        } else if (nextId !== prevId) {
            const resolved = getContractorById(nextId);
            nextName = resolved?.name ? String(resolved.name).trim() || null : null;
        }

        return {
            ...performer,
            contractor_id: nextId,
            contractor_name: nextName,
        };
    });

    const hasNewDocumentFiles = form.documents.some((document) => document.file instanceof File);

    const handleRequestError = (errors) => {
        if (revertStatusOnError !== null) {
            form.status = revertStatusOnError;
        }

        form.clearErrors().setError(errors);
    };

    // Multipart FormData с глубокой вложенностью из браузера часто «ломает» financial_term / route_points на PHP.
    // При новых файлах шлём JSON в `order_payload` и прикладываем бинарники отдельными полями `document_file_{i}`.
    if (hasNewDocumentFiles) {
        const payload = buildSubmitPayload();
        const jsonBody = {
            ...payload,
            documents: payload.documents.map(({ file: _file, ...meta }) => meta),
        };
        const formData = new FormData();
        formData.append('order_payload', JSON.stringify(jsonBody));
        payload.documents.forEach((doc, index) => {
            if (doc.file instanceof File) {
                formData.append(`document_file_${index}`, doc.file);
            }
        });

        const url = isEditing.value ? route('orders.update', props.order.id) : route('orders.store');
        const opts = {
            preserveScroll: true,
            forceFormData: true,
            onBefore: () => {
                form.processing = true;
            },
            onFinish: () => {
                form.processing = false;
            },
            onError: handleRequestError,
        };

        if (isEditing.value) {
            formData.append('_method', 'patch');
            router.post(url, formData, opts);

            return;
        }

        router.post(url, formData, opts);

        return;
    }

    const submitOptions = {
        preserveScroll: true,
        onError: handleRequestError,
    };

    if (isEditing.value) {
        form.transform(() => buildSubmitPayload()).patch(route('orders.update', props.order.id), submitOptions);

        return;
    }

    form.transform(() => buildSubmitPayload()).post(route('orders.store'), submitOptions);
}

function goBack() {
    // Всегда запрашиваем реестр с сервера: history.back() отдаёт старый снимок Inertia без свежих строк.
    router.get(route('orders.index'), {}, { preserveScroll: true });
}

</script>
