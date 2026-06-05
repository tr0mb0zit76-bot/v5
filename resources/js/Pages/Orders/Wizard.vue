<template>
    <div :class="crmWizardShell">
        <div
            v-if="isMobileStandalone"
            :class="`${crmPanel} space-y-3 px-4 py-4`"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        :class="crmWizardBack"
                        title="К реестру"
                        @click="goBack"
                    >
                        <X class="h-5 w-5" />
                        <span class="sr-only">К реестру</span>
                    </button>

                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Мобильный мастер</div>
                        <h1 class="truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ isEditing ? form.order_number || `Заказ #${order.id}` : 'Новый заказ' }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Статус</span>
                                <span
                                    class="inline-flex max-w-full items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-100"
                                    :title="'Рассчитывается автоматически по фактическим датам плеч, документам и оплатам. Текущий: ' + orderStatusBadgeLabel"
                                >
                                    <OrderStatusIcon v-if="orderStatusIconMeta" :icon-key="orderStatusIconKey" :size="18" />
                                    <span class="min-w-0 truncate">{{ orderStatusBadgeLabel }}</span>
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
                                <div :class="crmSegmented">
                                    <button
                                        type="button"
                                        :class="[!form.is_international_transport ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
                                        @click="form.is_international_transport = false"
                                    >
                                        Внутренняя
                                    </button>
                                    <button
                                        type="button"
                                        :class="[form.is_international_transport ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
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
                    :class="crmFieldFluid"
                >
                    <option v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</option>
                </select>
            </div>
        </div>

        <template v-else>
            <div :class="crmWizardHeader">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :class="crmWizardBack"
                        title="К реестру"
                        @click="goBack"
                    >
                        <X class="h-5 w-5" />
                        <span class="sr-only">К реестру</span>
                    </button>

                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            {{ isEditing ? 'Карточка заказа' : 'Новый заказ' }}
                        </div>
                        <h1 class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ isEditing ? form.order_number || `Заказ #${order.id}` : 'Добавление' }}
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

            <div class="flex flex-col gap-2 border-b border-zinc-200 bg-white px-5 py-2.5 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:flex-nowrap sm:items-center sm:justify-between sm:gap-x-3 sm:gap-y-2">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex items-center gap-2 text-sm transition-colors"
                        :class="crmTabButtonClasses(activeTab === tab.key)"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>
                <div class="flex w-full min-w-0 flex-wrap items-center gap-x-4 gap-y-2 border-t border-zinc-200 pt-2.5 sm:w-auto sm:min-w-0 sm:flex-nowrap sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Перевозка</span>
                        <div :class="crmSegmented">
                            <button
                                type="button"
                                :class="!form.is_international_transport ? crmSegmentedBtnActive : crmSegmentedBtn"
                                @click="form.is_international_transport = false"
                            >
                                Внутренняя
                            </button>
                            <button
                                type="button"
                                :class="form.is_international_transport ? crmSegmentedBtnActive : crmSegmentedBtn"
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
                            class="inline-flex max-w-full items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium leading-none text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-100"
                            :title="'Рассчитывается автоматически по фактическим датам плеч, документам и оплатам. Текущий: ' + orderStatusBadgeLabel"
                        >
                            <OrderStatusIcon v-if="orderStatusIconMeta" :icon-key="orderStatusIconKey" />
                            <span class="min-w-0 truncate">{{ orderStatusBadgeLabel }}</span>
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
            :class="crmWizardBody"
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
            <div
                v-if="!isEditing"
                class="mb-4 rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 dark:border-sky-900/50 dark:bg-sky-950/20"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-sky-950 dark:text-sky-100">Заполнить из заявки заказчика</div>
                        <p class="mt-1 text-xs text-sky-900/80 dark:text-sky-200/80">PDF или DOCX с текстовым слоем. Данные попадут в форму — проверьте перед сохранением.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            ref="intakeFileInput"
                            type="file"
                            accept=".pdf,.docx,image/jpeg,image/png,image/webp"
                            class="max-w-xs text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium dark:file:bg-zinc-800"
                            @change="onIntakeFileSelected"
                        />
                        <button
                            type="button"
                            class="rounded-lg bg-sky-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
                            :disabled="intakeLoading || !intakeSelectedFile"
                            @click="extractIntakeDraft"
                        >
                            {{ intakeLoading ? 'Распознавание…' : 'Распознать' }}
                        </button>
                    </div>
                </div>
                <p v-if="intakeError" class="mt-2 text-xs text-rose-700 dark:text-rose-300">{{ intakeError }}</p>
                <div v-if="intakePreview" class="mt-3 space-y-2 rounded-lg border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-900/40">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs font-medium text-zinc-700 dark:text-zinc-200">
                            Черновик #{{ intakePreview.draft_id }}
                            <span v-if="intakePreview.confidence != null"> · уверенность {{ Math.round(intakePreview.confidence * 100) }}%</span>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                            @click="applyIntakeDraft"
                        >
                            Применить к форме
                        </button>
                    </div>
                    <ul v-if="intakePreview.preview?.length" class="grid gap-1 text-xs text-zinc-700 dark:text-zinc-300 sm:grid-cols-2">
                        <li v-for="row in intakePreview.preview" :key="row.label">
                            <span class="text-zinc-500">{{ row.label }}:</span> {{ row.value }}
                        </li>
                    </ul>
                    <ul v-if="intakePreview.warnings?.length" class="text-xs text-amber-800 dark:text-amber-200">
                        <li v-for="(warning, index) in intakePreview.warnings" :key="index">{{ warning }}</li>
                    </ul>
                </div>
            </div>
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
                        <textarea v-model="form.special_notes" rows="4" :class="crmFieldFluid" />
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
                        <div class="space-y-3 border-b border-zinc-100 pb-4 dark:border-zinc-800">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ stageLabel(performer.stage) }}</span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <div :class="crmSegmented">
                                        <button
                                            type="button"
                                            :class="[!isPerformerSplit(performer) ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
                                            @click="setPerformerCarrierMode(legIndex, CARRIER_MODE_SINGLE)"
                                        >
                                            Один исполнитель
                                        </button>
                                        <button
                                            type="button"
                                            :class="[isPerformerSplit(performer) ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
                                            @click="setPerformerCarrierMode(legIndex, CARRIER_MODE_SPLIT)"
                                        >
                                            Несколько исполнителей
                                        </button>
                                    </div>
                                    <button
                                        v-if="form.performers.length > 1"
                                        type="button"
                                        class="shrink-0 rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                                        @click="removePerformer(legIndex)"
                                    >
                                        Удалить плечо
                                    </button>
                                </div>
                            </div>

                            <template v-if="!isPerformerSplit(performer)">
                                <div class="grid min-w-0 w-full grid-cols-1 gap-2 sm:grid-cols-12 sm:gap-3">
                                    <div class="space-y-1 sm:col-span-5">
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Перевозчик</label>
                                            <div class="flex items-center gap-2">
                                                <CarrierPortalInviteButton
                                                    v-if="order?.id && order?.can_edit_order"
                                                    :order-id="order.id"
                                                    :stage="performer.stage"
                                                    :contractor-id="performer.contractor_id"
                                                    :carrier-slot="1"
                                                />
                                                <button
                                                    type="button"
                                                    class="rounded-lg border border-zinc-200 px-2 py-1 text-[11px] hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                                    @click.stop="openCounterpartyModal({ kind: 'performer', index: legIndex, type: 'carrier' })"
                                                >
                                                    + Новый
                                                </button>
                                            </div>
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
                                                v-if="isCarrierResultsVisible('performer', legIndex)"
                                                class="absolute left-0 top-full z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                            >
                                                <button
                                                    v-if="props.ownFleetContractor?.id"
                                                    type="button"
                                                    class="flex w-full flex-col items-start border-b border-zinc-100 px-4 py-3 text-left hover:bg-sky-50 dark:border-zinc-800 dark:hover:bg-sky-950/30"
                                                    @mousedown.prevent
                                                    @click="selectOwnFleetPerformer(legIndex)"
                                                >
                                                    <span class="text-sm font-medium text-sky-700 dark:text-sky-300">Свой транспорт</span>
                                                    <span class="text-xs text-zinc-500">{{ props.ownFleetContractor.name }}</span>
                                                </button>
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
                                            :class="crmFieldFluid"
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
                                            :class="crmFieldFluid"
                                            :disabled="normalizeNullableNumber(performer.contractor_id) === null"
                                            @focus="loadFleetOptionsForLeg(legIndex)"
                                        >
                                            <option :value="null">—</option>
                                            <option v-for="d in fleetDriverOptionsForLeg(legIndex)" :key="d.id" :value="d.id">{{ d.label }}</option>
                                        </select>
                                    </div>
                                </div>
                                <p
                                    v-if="performer.carrier_portal_submission?.driver_full_name"
                                    class="text-xs text-emerald-600 dark:text-emerald-400"
                                >
                                    Заполнено перевозчиком: {{ performer.carrier_portal_submission.driver_full_name }}
                                </p>
                                <p
                                    v-if="needsCargoPerformerAllocationUi && performerCargoSummaryLabel(performer.stage, null)"
                                    class="text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    Груз на машине: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ performerCargoSummaryLabel(performer.stage, null) }}</span>
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <div class="w-[8.75rem] space-y-0.5">
                                        <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. погрузка</label>
                                        <input v-model="performer.loading_actual" type="date" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" />
                                    </div>
                                    <div class="w-[8.75rem] space-y-0.5">
                                        <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. выгрузка</label>
                                        <input v-model="performer.unloading_actual" type="date" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" />
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-xs text-zinc-500">Точки маршрута ниже общие для всех исполнителей этого плеча.</p>
                                <div
                                    v-for="(slot, slotIndex) in performer.split_carriers"
                                    :key="`leg-${legIndex}-slot-${slot.slot}`"
                                    class="space-y-3 rounded-xl border border-zinc-100 bg-zinc-50/60 p-3 dark:border-zinc-800 dark:bg-zinc-900/40"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ splitCarrierSlotLabel(slot.slot) }}</span>
                                            <p
                                                v-if="needsCargoPerformerAllocationUi && performerCargoSummaryLabel(performer.stage, slot.slot)"
                                                class="text-xs text-zinc-500"
                                            >
                                                Груз: {{ performerCargoSummaryLabel(performer.stage, slot.slot) }}
                                            </p>
                                        </div>
                                        <button
                                            v-if="performer.split_carriers.length > 2"
                                            type="button"
                                            class="rounded-lg border border-rose-200 px-2 py-1 text-[11px] text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                                            @click="removeSplitCarrier(legIndex, slotIndex)"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                    <div class="grid min-w-0 w-full grid-cols-1 gap-2 sm:grid-cols-12 sm:gap-3">
                                        <div class="space-y-1 sm:col-span-5">
                                            <div class="flex items-center justify-between gap-2">
                                                <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Перевозчик</label>
                                                <div class="flex items-center gap-2">
                                                    <CarrierPortalInviteButton
                                                        v-if="order?.id && order?.can_edit_order"
                                                        :order-id="order.id"
                                                        :stage="performer.stage"
                                                        :contractor-id="slot.contractor_id"
                                                        :carrier-slot="slot.slot ?? slotIndex + 1"
                                                    />
                                                    <button
                                                        type="button"
                                                        class="rounded-lg border border-zinc-200 px-2 py-1 text-[11px] hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                                        @click.stop="openCounterpartyModal({ kind: 'performer-slot', index: `${legIndex}-${slotIndex}`, type: 'carrier' })"
                                                    >
                                                        + Новый
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="relative">
                                                <input
                                                    :value="carrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`)"
                                                    type="text"
                                                    :class="['w-full rounded-xl border bg-white px-3 py-2 pr-10 text-sm dark:bg-zinc-950', highlightRequiredField(`performer_carrier_${legIndex}_${slotIndex}`, slot.contractor_id)]"
                                                    placeholder="Поиск перевозчика"
                                                    @focus="setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, true)"
                                                    @input="onSplitPerformerCarrierInput(legIndex, slotIndex, $event.target.value)"
                                                    @blur="restoreSplitPerformerCarrierSearch(legIndex, slotIndex)"
                                                />
                                                <button
                                                    v-if="normalizeNullableNumber(slot.contractor_id) !== null"
                                                    type="button"
                                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                                    title="Очистить перевозчика"
                                                    @click="clearSplitPerformerContractor(legIndex, slotIndex)"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                                <div
                                                    v-if="isCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`)"
                                                    class="absolute left-0 top-full z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                                >
                                                    <button
                                                        v-if="props.ownFleetContractor?.id"
                                                        type="button"
                                                        class="flex w-full flex-col items-start border-b border-zinc-100 px-4 py-3 text-left hover:bg-sky-50 dark:border-zinc-800 dark:hover:bg-sky-950/30"
                                                        @mousedown.prevent
                                                        @click="selectOwnFleetSplitSlot(legIndex, slotIndex)"
                                                    >
                                                        <span class="text-sm font-medium text-sky-700 dark:text-sky-300">Свой транспорт</span>
                                                        <span class="text-xs text-zinc-500">{{ props.ownFleetContractor.name }}</span>
                                                    </button>
                                                    <button
                                                        v-for="contractor in filteredCarrierResults('performer-slot', `${legIndex}-${slotIndex}`)"
                                                        :key="contractor.id"
                                                        type="button"
                                                        class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                                        @mousedown.prevent
                                                        @click="selectSplitPerformerContractor(legIndex, slotIndex, contractor)"
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
                                                v-model="slot.fleet_vehicle_id"
                                                :class="crmFieldFluid"
                                                :disabled="normalizeNullableNumber(slot.contractor_id) === null"
                                                @focus="loadFleetOptionsForLeg(legIndex, slotIndex)"
                                            >
                                                <option :value="null">—</option>
                                                <option v-for="v in fleetVehicleOptionsForLeg(legIndex, slotIndex)" :key="v.id" :value="v.id">{{ v.label }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-1 sm:col-span-4">
                                            <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Водитель</label>
                                            <select
                                                v-model="slot.fleet_driver_id"
                                                :class="crmFieldFluid"
                                                :disabled="normalizeNullableNumber(slot.contractor_id) === null"
                                                @focus="loadFleetOptionsForLeg(legIndex, slotIndex)"
                                            >
                                                <option :value="null">—</option>
                                                <option v-for="d in fleetDriverOptionsForLeg(legIndex, slotIndex)" :key="d.id" :value="d.id">{{ d.label }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 border-t border-zinc-200/80 pt-2 dark:border-zinc-700">
                                        <div class="w-[8.75rem] space-y-0.5">
                                            <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. погрузка</label>
                                            <input v-model="slot.loading_actual" type="date" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" />
                                        </div>
                                        <div class="w-[8.75rem] space-y-0.5">
                                            <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. выгрузка</label>
                                            <input v-model="slot.unloading_actual" type="date" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" />
                                        </div>
                                    </div>
                                </div>
                                <button
                                    v-if="performer.split_carriers.length < 4"
                                    type="button"
                                    class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    @click="addSplitCarrier(legIndex)"
                                >
                                    Добавить исполнителя
                                </button>
                            </template>
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
                                <div class="w-full space-y-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Код поста и наименование СВХ</label>
                                        <div class="grid gap-2 sm:grid-cols-2 sm:gap-3">
                                            <input
                                                v-model="form.customs_post_code"
                                                type="text"
                                                :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', form.errors.customs_post_code ? 'border-rose-500 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700']"
                                                placeholder="Код поста"
                                            />
                                            <input
                                                v-model="form.svh_name"
                                                type="text"
                                                :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', form.errors.svh_name ? 'border-rose-500 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700']"
                                                placeholder="Наименование СВХ / таможенного склада"
                                            />
                                        </div>
                                        <p v-if="form.errors.customs_post_code || form.errors.svh_name" class="text-xs text-rose-500">
                                            {{ form.errors.customs_post_code || form.errors.svh_name }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Адрес СВХ</label>
                                        <input
                                            v-model="form.svh_address"
                                            type="text"
                                            :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', form.errors.svh_address ? 'border-rose-500 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700']"
                                            placeholder="Почтовый или производственный адрес"
                                        />
                                        <p v-if="form.errors.svh_address" class="text-xs text-rose-500">{{ form.errors.svh_address }}</p>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_9rem_9.5rem_14rem] lg:items-end">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Адрес</label>
                                    <div class="relative">
                                        <input
                                            v-model="item.point.address"
                                            type="text"
                                            :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('route_point_address_' + item.globalIndex, routePointAddressHighlightValue(item.point))]"
                                            placeholder="Начни вводить адрес"
                                            @input="onRoutePointAddressInput(item.globalIndex)"
                                            @blur="syncRoutePointCityFromAddress(item.point)"
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
                                    <label class="text-sm font-medium">Город</label>
                                    <input
                                        :value="routePointCityValue(item.point)"
                                        type="text"
                                        :class="crmFieldFluid"
                                        placeholder="Нормализованное название"
                                        @input="setRoutePointCity(item.point, $event.target.value)"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Плановая дата</label>
                                    <input v-model="item.point.planned_date" type="date" :class="crmFieldFluid" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">{{ routePointTimeBlockHeading(item.point.type) }}</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input v-model="item.point.planned_time_from" type="time" :class="crmFieldFluid" aria-label="Время с" />
                                        <input v-model="item.point.planned_time_to" type="time" :class="crmFieldFluid" aria-label="Время до" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="item.point.type === 'loading'" class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Отправитель</label>
                                    <input v-model="item.point.sender_name" type="text" :class="crmFieldFluid" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контакт на загрузке</label>
                                    <input
                                        :value="routePointCombinedContact(item.point)"
                                        type="text"
                                        :class="crmFieldFluid"
                                        placeholder="Имя и телефон"
                                        @input="setRoutePointCombinedContact(item.point, $event.target.value)"
                                    />
                                </div>
                            </div>

                            <div v-if="item.point.type === 'unloading'" class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Получатель</label>
                                    <input v-model="item.point.recipient_name" type="text" :class="crmFieldFluid" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контакт на выгрузке</label>
                                    <input
                                        :value="routePointCombinedContact(item.point)"
                                        type="text"
                                        :class="crmFieldFluid"
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
                <div>
                    <h2 class="text-base font-semibold">Грузовые позиции</h2>
                    <p class="text-sm text-zinc-500">Несколько грузов в одном заказе</p>
                </div>

                <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Объявленная стоимость груза</label>
                        <input
                            v-model="form.cargo_declared_sum"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full max-w-xs rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            placeholder="Сумма для таможни / страхования"
                        />
                        <p v-if="form.errors.cargo_declared_sum" class="text-xs text-rose-500">{{ form.errors.cargo_declared_sum }}</p>
                    </div>
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
                                <input v-model="item.package_count" type="number" min="0" step="1" :class="crmFieldFluid" />
                            </div>
                            <div class="space-y-1 lg:col-span-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Упаковка</label>
                                <select v-model="item.pack_type_id" :class="crmFieldFluid" @change="applyPackageTypeOption(item)">
                                    <option :value="null">—</option>
                                    <option v-for="option in packageTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1 lg:col-span-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">ТН ВЭД</label>
                                <input v-model="item.hs_code" type="text" :class="crmFieldFluid" />
                            </div>
                            <div class="space-y-1 lg:col-span-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Класс опасн.</label>
                                <input v-model="item.dangerous_class" type="text" :class="crmFieldFluid" />
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
                                <textarea v-model="item.description" rows="2" :class="crmFieldFluid" />
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

                <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="grid flex-1 gap-3 text-sm md:grid-cols-3">
                        <div>Общий вес: <span class="font-medium">{{ cargoSummary.totalWeight.toFixed(2) }} кг</span></div>
                        <div>Общий объём: <span class="font-medium">{{ cargoSummary.totalVolume.toFixed(2) }} м³</span></div>
                        <div>Всего мест: <span class="font-medium">{{ cargoSummary.totalPackages }}</span></div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        @click="addCargoItem"
                    >
                        Добавить груз
                    </button>
                </div>

                <div v-if="needsCargoPerformerAllocationUi" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Распределение по исполнителям</h3>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            Места и вес по каждой машине. Вес подставляется из позиции груза (вес места × кол-во), если не указан вручную.
                            На плече с несколькими исполнителями сумма мест и веса должна совпадать с позицией.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr class="text-left text-xs text-zinc-500">
                                    <th class="sticky left-0 z-10 min-w-[10rem] border-b border-zinc-200 bg-white py-2 pr-3 dark:border-zinc-700 dark:bg-zinc-950">Груз</th>
                                    <th
                                        v-for="column in cargoPerformerAllocationColumns"
                                        :key="`alloc-head-${column.key}`"
                                        class="min-w-[8.5rem] border-b border-zinc-200 px-2 py-2 dark:border-zinc-700"
                                    >
                                        {{ column.label }}
                                    </th>
                                    <th class="min-w-[8rem] border-b border-zinc-200 px-2 py-2 dark:border-zinc-700">Проверка по плечам</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, cargoIndex) in form.cargo_items"
                                    :key="`alloc-row-${cargoIndex}`"
                                    :class="cargoAllocationRowStatuses[cargoIndex]?.isMismatch ? 'bg-rose-50/60 dark:bg-rose-950/20' : ''"
                                >
                                    <td class="sticky left-0 z-10 border-b border-zinc-100 bg-white py-2 pr-3 align-top dark:border-zinc-800 dark:bg-zinc-950">
                                        <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ item.name || `Груз ${cargoIndex + 1}` }}</div>
                                        <div class="text-xs text-zinc-500">
                                            {{ Number(item.package_count || 0) }} мест · {{ cargoLineTotalWeightKg(item).toFixed(0) }} кг
                                        </div>
                                    </td>
                                    <td
                                        v-for="column in cargoPerformerAllocationColumns"
                                        :key="`alloc-cell-${cargoIndex}-${column.key}`"
                                        class="border-b border-zinc-100 px-2 py-2 align-top dark:border-zinc-800"
                                    >
                                        <div class="flex flex-col gap-1">
                                            <input
                                                :value="findCargoAllocation(item, column.stage, column.carrier_slot)?.package_count ?? ''"
                                                type="number"
                                                min="0"
                                                step="1"
                                                :class="cargoAllocationFieldClass"
                                                placeholder="Мест"
                                                @input="onCargoAllocationPackagesInput(item, column, $event.target.value)"
                                            />
                                            <input
                                                :value="findCargoAllocation(item, column.stage, column.carrier_slot)?.weight_value ?? ''"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                :class="cargoAllocationFieldClass"
                                                :placeholder="allocationWeightFieldPlaceholder(item, column)"
                                                @input="onCargoAllocationWeightInput(item, column, $event.target.value)"
                                            />
                                        </div>
                                    </td>
                                    <td class="border-b border-zinc-100 px-2 py-2 align-top text-xs dark:border-zinc-800">
                                        <div
                                            v-for="leg in cargoAllocationRowStatuses[cargoIndex]?.legStatuses ?? []"
                                            :key="`alloc-status-${cargoIndex}-${leg.stage}`"
                                            class="leading-relaxed"
                                            :class="leg.packagesMismatch || leg.weightMismatch ? 'text-rose-600' : 'text-zinc-600 dark:text-zinc-400'"
                                        >
                                            <template v-if="leg.isSplitLeg">
                                                {{ leg.stageLabel }}: {{ leg.stagePackages }}/{{ cargoAllocationRowStatuses[cargoIndex]?.expectedPackages }} мест,
                                                {{ leg.stageWeightKg.toFixed(0) }}/{{ cargoAllocationRowStatuses[cargoIndex]?.expectedWeightKg.toFixed(0) }} кг
                                            </template>
                                            <template v-else>
                                                {{ leg.stageLabel }}: {{ leg.stagePackages > 0 ? `${leg.stagePackages} мест` : '—' }}
                                                <span v-if="leg.stageWeightKg > 0"> · {{ leg.stageWeightKg.toFixed(0) }} кг</span>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                    <td class="sticky left-0 z-10 bg-zinc-50 py-2 pr-3 dark:bg-zinc-900/80">Сводка по машине</td>
                                    <td
                                        v-for="column in cargoPerformerAllocationColumnSummaries"
                                        :key="`alloc-foot-${column.key}`"
                                        class="px-2 py-2"
                                    >
                                        <template v-if="column.hasAny">
                                            {{ column.totalPackages }} мест<br>
                                            {{ column.totalWeightKg.toFixed(0) }} кг
                                        </template>
                                        <span v-else class="text-zinc-400">—</span>
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
                                <select v-model="form.financial_term.client_payment_form" :class="crmFieldFluid">
                                    <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>
                        <PaymentTermsWizardBlock
                            :key="`client-pay-${props.order?.id ?? 'draft'}`"
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
                        <div v-for="(cost, index) in legContractorCosts" :key="`contractor-cost-${index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-start">
                                <div class="min-w-0 md:col-span-5">
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ costRowTitle(cost) }}
                                    </div>
                                    <p class="text-xs text-zinc-500">Исполнитель и условия оплаты для плеча маршрута.</p>
                                </div>
                                <div class="min-w-0 space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium">
                                        {{ contractorCostAmountLabel(cost) }}
                                    </label>
                                    <input v-model="cost.amount" type="number" min="0" step="0.01" :class="crmFieldFluid" placeholder="0" />
                                </div>
                                <div class="min-w-0 space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium">Валюта</label>
                                    <select v-model="cost.currency" :class="crmFieldFluid">
                                        <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div class="min-w-0 space-y-2 md:col-span-3">
                                    <label class="text-sm font-medium">Форма оплаты</label>
                                    <select v-model="cost.payment_form" :class="crmFieldFluid">
                                        <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <PaymentTermsWizardBlock
                                :key="`carrier-pay-${props.order?.id ?? 'draft'}-${index}`"
                                v-model:summary-text="cost.payment_terms"
                                :schedule="cost.payment_schedule"
                                :total-amount="cost.amount"
                                :currency="cost.currency"
                                :route-points="form.route_points"
                                :order-date="contractorCostOrderDate(cost)"
                                editable-summary
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Дополнительные затраты</h2>
                        <p class="text-xs text-zinc-500">Подрядчики и прочие расходы по заказу (не оплата перевозчикам по этапам)</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        @click="addAdditionalCostRow"
                    >
                        Добавить затрату
                    </button>
                </div>

                <div v-if="form.financial_term.additional_costs.length === 0" class="rounded-xl border border-dashed border-zinc-200 px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700">
                    Нет дополнительных затрат. Нажмите «Добавить затрату», чтобы указать подрядчика, дату услуги, стоимость, валюту и форму оплаты.
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
                                :value="additionalCostSearchValue(index)"
                                type="text"
                                autocomplete="off"
                                placeholder="Название или ИНН"
                                :class="crmFieldFluid"
                                @input="setAdditionalCostSearchValue(index, $event.target.value)"
                                @focus="setAdditionalCostResultsVisible(index, true)"
                                @blur="hideAdditionalCostResults(index)"
                            />
                            <div
                                v-if="isAdditionalCostResultsVisible(index) && additionalCostCombinedResults(index).length > 0"
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
                            >
                                <button
                                    v-for="contractor in additionalCostCombinedResults(index)"
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
                    В марже бонус учитывается с коэффициентом {{ Number(props.bonusMultiplier || 0).toFixed(2) }}.
                </p>
            </div>

            </div>

            <div v-else-if="activeTab === 'norms_penalties'" class="space-y-6">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Штрафы и нормативы по времени (часы) для заказчика и отдельно по каждому плечу перевозчика. Данные сохраняются в карточке заказа и доступны для дальнейших сопоставлений.
                </p>
                <div
                    v-if="normsPenaltiesTabValidationMessages.length > 0"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100"
                    role="alert"
                >
                    <ul class="list-inside list-disc space-y-1">
                        <li v-for="(msg, i) in normsPenaltiesTabValidationMessages" :key="`norms-err-${i}`">{{ msg }}</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-zinc-200 p-3 dark:border-zinc-800">
                    <div class="-mx-1 flex min-h-9 min-w-0 flex-wrap items-center gap-x-3 gap-y-2 px-1 pb-0.5">
                        <h2 class="shrink-0 text-base font-semibold">Заказчик</h2>
                        <div class="flex min-w-0 flex-1 flex-nowrap items-center gap-x-2 gap-y-1 overflow-x-auto">
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Срыв</span>
                            <input
                                v-model.number="form.financial_term.client_norms_penalties.miss_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                            <select v-model="form.financial_term.client_norms_penalties.miss_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                                <option v-for="option in currencyOptions" :key="`cn-miss-${option.value}`" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Простой</span>
                            <input
                                v-model.number="form.financial_term.client_norms_penalties.downtime_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                            <select v-model="form.financial_term.client_norms_penalties.downtime_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                                <option v-for="option in currencyOptions" :key="`cn-down-${option.value}`" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Штраф</span>
                            <input
                                v-model.number="form.financial_term.client_norms_penalties.fine_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                            <select v-model="form.financial_term.client_norms_penalties.fine_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                                <option v-for="option in currencyOptions" :key="`cn-fine-${option.value}`" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex min-w-0 shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Пеня</span>
                            <input
                                v-model="form.financial_term.client_norms_penalties.penalty_terms"
                                type="text"
                                class="h-8 min-w-[10rem] max-w-[28rem] flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="Условия пени…"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Погрузка, ч">Погр.</span>
                            <input
                                v-model.number="form.financial_term.client_norms_penalties.norm_loading_hours"
                                type="number"
                                min="0"
                                step="0.25"
                                class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Таможня, ч">Там.</span>
                            <input
                                v-model.number="form.financial_term.client_norms_penalties.norm_customs_hours"
                                type="number"
                                min="0"
                                step="0.25"
                                class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Выгрузка, ч">Выгр.</span>
                            <input
                                v-model.number="form.financial_term.client_norms_penalties.norm_unloading_hours"
                                type="number"
                                min="0"
                                step="0.25"
                                class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                    </div>
                </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Строки по плечам синхронизируются с вкладкой «Маршрут».</p>
                    <button
                        type="button"
                        class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        :disabled="!isOrderFormEditable"
                        @click="syncCarrierNormsByLegFromPerformers"
                    >
                        Подтянуть плечи
                    </button>
                </div>

                <div v-for="(normRow, legIndex) in form.financial_term.carrier_norms_by_leg" :key="`carrier-norms-${normRow.stage}-${legIndex}`" class="rounded-2xl border border-zinc-200 p-3 dark:border-zinc-800">
                    <div class="-mx-1 flex min-h-9 min-w-0 flex-wrap items-center gap-x-3 gap-y-2 px-1 pb-0.5">
                        <h2 class="shrink-0 text-base font-semibold">Перевозчик · {{ stageLabel(normRow.stage) }}</h2>
                        <div class="flex min-w-0 flex-1 flex-nowrap items-center gap-x-2 gap-y-1 overflow-x-auto">
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Срыв</span>
                            <input
                                v-model.number="normRow.miss_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                            <select v-model="normRow.miss_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                                <option v-for="option in currencyOptions" :key="`leg-${legIndex}-miss-${option.value}`" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Простой</span>
                            <input
                                v-model.number="normRow.downtime_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                            <select v-model="normRow.downtime_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                                <option v-for="option in currencyOptions" :key="`leg-${legIndex}-down-${option.value}`" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Штраф</span>
                            <input
                                v-model.number="normRow.fine_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-[5.5rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                            <select v-model="normRow.fine_currency" class="h-8 w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" :disabled="!isOrderFormEditable">
                                <option v-for="option in currencyOptions" :key="`leg-${legIndex}-fine-${option.value}`" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex min-w-0 shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">Пеня</span>
                            <input
                                v-model="normRow.penalty_terms"
                                type="text"
                                class="h-8 min-w-[10rem] max-w-[28rem] flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="Условия пени…"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                        <span class="shrink-0 text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Погрузка, ч">Погр.</span>
                            <input
                                v-model.number="normRow.norm_loading_hours"
                                type="number"
                                min="0"
                                step="0.25"
                                class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Таможня, ч">Там.</span>
                            <input
                                v-model.number="normRow.norm_customs_hours"
                                type="number"
                                min="0"
                                step="0.25"
                                class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400" title="Выгрузка, ч">Выгр.</span>
                            <input
                                v-model.number="normRow.norm_unloading_hours"
                                type="number"
                                min="0"
                                step="0.25"
                                class="h-8 w-14 shrink-0 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="!isOrderFormEditable"
                            >
                        </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'timeline' && order?.id" class="space-y-4">
                <div v-if="canAccessMail" :class="`${crmPanel} space-y-3 p-4`">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Переписка</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Письма, привязанные к этому заказу.</p>
                        </div>
                        <Link
                            v-if="mailComposeUrl"
                            :href="mailComposeUrl"
                            :class="crmBtnSecondary"
                        >
                            <Mail class="h-4 w-4" />
                            Написать клиенту
                        </Link>
                    </div>
                    <div v-if="orderMailThreads.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                        Писем по заказу пока нет.
                    </div>
                    <div v-else class="space-y-2">
                        <Link
                            v-for="thread in orderMailThreads"
                            :key="thread.id"
                            :href="route('mail.threads.show', thread.id)"
                            class="block rounded-xl border border-zinc-200 p-3 text-sm transition hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700"
                        >
                            <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ thread.subject }}</div>
                            <div v-if="thread.last_message_at" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ formatOrderMailWhen(thread.last_message_at) }}
                            </div>
                            <p v-if="thread.preview" class="mt-2 line-clamp-2 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ thread.preview }}
                            </p>
                        </Link>
                    </div>
                </div>

                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Хронология по заказу: комментарии диспозиции, письма и другие события.
                </p>
                <ActivityTimeline :order-id="order.id" />
            </div>

            <OrderWizardDocumentsTab
                v-else-if="activeTab === 'documents'"
                ref="documentsTabRef"
                v-model:signed-documents="form.documents"
                :order="order"
                :performers="form.performers"
                :additional-costs="form.financial_term.additional_costs"
                :client-request-mode="form.financial_term.client_request_mode"
                :is-order-form-editable="isOrderFormEditable"
                :all-documents="orderAllDocuments"
                :print-form-template-catalog="printFormTemplateCatalog"
                :print-form-template-options-customer="printFormTemplateOptionsCustomer"
                :print-form-template-options-carrier="printFormTemplateOptionsCarrier"
                :own-company-id="form.own_company_id"
                :is-international-transport="form.is_international_transport"
                :customer-id="form.client_id"
                :document-type-options="documentTypeOptions"
                :document-tab-validation-messages="documentTabValidationMessages"
                :document-storage="documentStorage"
                :saved-print-form-template-selection="order?.print_form_template_selection ?? {}"
            />
        </div>

    <Teleport to="body">
        <div
            v-show="showCounterpartyModal"
            class="fixed inset-0 flex items-center justify-center bg-black/40 p-4"
            style="z-index: 2147483647;"
            @click.self="closeCounterpartyModal"
        >
            <div :class="`${crmModalPanel} w-full max-w-xl p-5 shadow-2xl`" @click.stop>
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
                    <input ref="counterpartyNameInput" v-model="counterpartyForm.name" type="text" placeholder="Название" :class="`${crmFieldFluid} md:col-span-2`" />
                    <input v-model="counterpartyForm.inn" type="text" placeholder="ИНН" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.kpp" type="text" placeholder="КПП" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.address" type="text" placeholder="Адрес" :class="`${crmFieldFluid} md:col-span-2`" />
                    <input v-model="counterpartyForm.phone" type="text" placeholder="Телефон" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.email" type="email" placeholder="Email" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.contact_person" type="text" placeholder="Контактное лицо" :class="`${crmFieldFluid} md:col-span-2`" />
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" :class="crmBtnNeutral" @click="closeCounterpartyModal">
                        Отмена
                    </button>
                    <button type="button" :class="crmBtnCreate" :disabled="inlineContractorSaving" @click="createInlineCounterparty">
                        {{ inlineContractorSaving ? 'Создание...' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <Modal :show="showOrderDocumentAttachModal" max-width="xl" @close="closeOrderDocumentAttachModal">
        <CrmModalHeader :title="orderDocumentAttachModalTitle" @close="closeOrderDocumentAttachModal">
            <template v-if="orderDocumentAttachPresetIndex === null">
                Укажите, чей это документ и тип. Форматы: PDF, Word, Excel, JPG, PNG, WebP.
            </template>
            <template v-else>
                Выберите файл и подтвердите замену.
            </template>
        </CrmModalHeader>
        <div class="space-y-4 border-t border-zinc-200 px-5 py-5 dark:border-zinc-800 sm:px-6">
            <div v-if="orderDocumentAttachPendingFile" class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/50">
                <Paperclip class="h-4 w-4 shrink-0 text-zinc-500" />
                <span class="min-w-0 truncate font-medium text-zinc-800 dark:text-zinc-100">{{ orderDocumentAttachPendingFile.name }}</span>
            </div>
            <div v-else>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                    <span>Выбрать файл…</span>
                    <input
                        ref="orderDocumentAttachModalFileInputRef"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                        class="hidden"
                        @change="onOrderDocumentAttachModalFileChange"
                    >
                </label>
            </div>

            <div v-if="orderDocumentAttachPresetIndex !== null && form.documents[orderDocumentAttachPresetIndex]" class="rounded-xl border border-zinc-100 bg-zinc-50/70 px-3 py-2 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300">
                {{ orderDocumentAttachPresetSummary }}
            </div>

            <div v-if="orderDocumentAttachPresetIndex === null" class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Чей документ</label>
                    <select v-model="orderDocumentAttachTargetKind" :class="crmFieldFluid">
                        <option value="customer">Заказчик</option>
                        <option value="carrier" :disabled="form.performers.length === 0">Плечо (перевозчик)</option>
                    </select>
                </div>
                <div v-if="orderDocumentAttachTargetKind === 'carrier'" class="space-y-2">
                    <label class="text-sm font-medium">Плечо</label>
                    <select v-model="orderDocumentAttachStage" :class="crmFieldFluid">
                        <option v-for="(p, idx) in form.performers" :key="`attach-leg-${idx}`" :value="p.stage">{{ stageLabel(p.stage) }}</option>
                    </select>
                </div>
                <div class="space-y-2 sm:col-span-2">
                    <label class="text-sm font-medium">Тип документа</label>
                    <select v-model="orderDocumentAttachNewDocType" :class="crmFieldFluid">
                        <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <button type="button" :class="crmBtnNeutral" @click="closeOrderDocumentAttachModal">Отмена</button>
                <button type="button" :class="crmBtnCreate" :disabled="!orderDocumentAttachPendingFile" @click="confirmOrderDocumentAttach">
                    {{ orderDocumentAttachPresetIndex !== null ? 'Заменить файл' : 'Прикрепить' }}
                </button>
            </div>
        </div>
    </Modal>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRaw, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ClipboardList, FileText, Gavel, History, Mail, MapPinned, OctagonAlert, Package, Paperclip, Save, Wallet, X } from 'lucide-vue-next';
import ActivityTimeline from '@/Components/CommercialIntelligence/ActivityTimeline.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import PaymentTermsWizardBlock from '@/Pages/Orders/Components/PaymentTermsWizardBlock.vue';
import OrderStatusIcon from '@/Components/Orders/OrderStatusIcon.vue';
import { ORDER_STATUS_ICON_META, resolveOrderStatusIconKey } from '@/support/orderStatusDisplay.js';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import {
    routePointCityValue,
    setRoutePointCity,
    syncRoutePointCityFromAddress,
} from '@/support/routePointNormalizedData.js';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import OrderWizardDocumentsTab from '@/Components/Orders/OrderWizardDocumentsTab.vue';
import { EMPTY_ORDER_DOCUMENTS } from '@/support/emptyOrderDocuments.js';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmBtnSecondary,
    crmField,
    crmFieldFluid,
    crmModalPanel,
    crmPanel,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
    crmWizardBack,
    crmWizardBody,
    crmWizardHeader,
    crmWizardShell,
} from '@/support/crmUi.js';
import * as orderPs from '@/support/orderPaymentScheduleUi.js';
import {
    blankPartyNormsPenalties,
    hasNormsPenaltiesContent,
    normalizePartyNormsPenalties,
} from '@/support/normsPenalties.js';
import {
    buildDocumentRequirementRules,
    documentMatchesRequirementRule,
} from '@/support/orderDocumentRequirementSlots.js';
import {
    blankAdditionalCostRow,
    migrateLegacyAdditionalContractorCosts,
    normalizeAdditionalCostsList,
    serializeAdditionalCostsForSubmit,
    sumAdditionalCostsAmount,
} from '@/support/orderAdditionalCosts.js';
import CarrierPortalInviteButton from '@/Components/Orders/CarrierPortalInviteButton.vue';
import {
    blankPerformer,
    blankSplitCarrier,
    CARRIER_MODE_SINGLE,
    CARRIER_MODE_SPLIT,
    contractorCostRowsFromPerformers,
    costMatchesPerformerSlot,
    isAdditionalContractorCost,
    EXECUTION_MODE_OWN_FLEET,
    isOwnFleetExecutionMode,
    isPerformerSplit,
    normalizePerformer,
    performerFleetCacheKey,
    splitCarrierSlotLabel,
} from '@/support/orderPerformers.js';
import { classifyDealType, paymentFormMetaFromOptions } from '@/support/paymentFormDealType.js';
import {
    allocationWeightPlaceholder,
    cargoAllocationRowStatus,
    cargoLinePerPlaceWeightKg,
    ensureCargoAllocation,
    findCargoAllocation,
    needsCargoPerformerAllocation,
    normalizePerformerAllocations,
    performerAllocationColumns,
    pruneCargoAllocationsToColumns,
    remapCargoAllocationsToCanonicalStages,
    summarizeAllocationsForColumn,
    validateCargoPerformerAllocations,
} from '@/support/orderCargoPerformerAllocations.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const page = usePage();

const additionalExpenseAmountFieldClass =
    'h-9 w-[6.75rem] max-w-full rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-sm tabular-nums dark:border-zinc-700 dark:bg-zinc-950';
const cargoAllocationFieldClass =
    'h-8 w-full min-w-[4.5rem] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950';

const props = defineProps({
    order: { type: Object, default: null },
    contractors: { type: Array, default: () => [] },
    ownCompanies: { type: Array, default: () => [] },
    ownFleetContractor: { type: Object, default: null },
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
    printFormTemplateCatalog: { type: Array, default: () => [] },
    printFormTemplateOptions: { type: Array, default: () => [] },
    printFormTemplateOptionsCustomer: { type: Array, default: () => [] },
    printFormTemplateOptionsCarrier: { type: Array, default: () => [] },
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
    canAccessMail: { type: Boolean, default: false },
    orderMailThreads: { type: Array, default: () => [] },
    mailComposeDefaults: { type: Object, default: null },
});

const tabs = computed(() => [
    { key: 'main', label: 'Основное', icon: ClipboardList },
    { key: 'route', label: 'Маршрут', icon: MapPinned },
    { key: 'cargo', label: 'Груз', icon: Package },
    { key: 'finance', label: 'Финансы', icon: Wallet },
    { key: 'norms_penalties', label: 'Нормативы / штрафы', icon: Gavel },
    { key: 'documents', label: 'Документы', icon: FileText },
    ...(props.order?.id ? [{ key: 'timeline', label: 'Лента', icon: History }] : []),
]);

const activeTab = ref('main');
const borderCrossingLegPicker = ref('');

const orderAllDocuments = computed(() => {
    const docs = props.order?.documents;

    return Array.isArray(docs) ? docs : EMPTY_ORDER_DOCUMENTS;
});

const mailComposeUrl = computed(() => {
    if (!props.canAccessMail || !props.order?.id) {
        return null;
    }

    if (props.mailComposeDefaults?.order_id) {
        return route('mail.index', { order_id: props.mailComposeDefaults.order_id });
    }

    return route('mail.index', { order_id: props.order.id });
});

function formatOrderMailWhen(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('ru-RU');
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    const allowed = new Set(['main', 'route', 'cargo', 'finance', 'norms_penalties', 'documents', 'timeline']);
    if (tab && allowed.has(tab)) {
        activeTab.value = tab;
    }
    if (tab) {
        url.searchParams.delete('tab');
    }

    const intakeDraftParam = url.searchParams.get('intake_draft');
    if (intakeDraftParam && !isEditing.value) {
        void loadAndApplyIntakeDraftById(intakeDraftParam);
        url.searchParams.delete('intake_draft');
    }

    if (tab || intakeDraftParam) {
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

    remapCargoAllocationsToCanonicalStages(form.cargo_items);

    (form.financial_term.additional_costs ?? []).forEach((row, index) => {
        const contractor = getContractorById(row?.contractor_id);
        const label = contractor?.name ?? row?.contractor_name ?? '';
        if (label) {
            additionalCostSearch.value = {
                ...additionalCostSearch.value,
                [index]: label,
            };
        }
    });

    if (!isEditing.value && form.own_company_id && !orderNumberManual.value) {
        void refreshSuggestedOrderNumber();
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
const additionalCostSearch = ref({});
const showAdditionalCostResults = ref({});
const additionalCostSearchTimers = ref({});
const additionalCostSearchAbortControllers = ref({});
const additionalCostSearchFetchSeq = ref({});
const serverAdditionalCostSearchResults = ref({});
const isSearchingAdditionalCosts = ref({});
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
const paymentFormMeta = computed(() => paymentFormMetaFromOptions(paymentFormOptions.value));

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
        kind: options.kind === 'performer-slot'
            ? 'performer-slot'
            : (options.kind === 'performer' ? 'performer' : 'client'),
        index: options.index ?? null,
    };
    counterpartyForm.type = options.type === 'carrier'
        ? 'carrier'
        : (options.type === 'contractor' ? 'contractor' : 'customer');
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
        carrier_slot: null,
        contractor_id: null,
        contractor_name: null,
        amount: null,
        currency: 'RUB',
        payment_form: 'no_vat',
        payment_schedule: blankPaymentSchedule(),
        payment_terms: '',
        execution_mode: null,
        is_additional: false,
        incurred_date: null,
        ...cost,
        payment_schedule: normalizePaymentSchedule(cost.payment_schedule),
    };
    merged.incurred_date = merged.incurred_date ? String(merged.incurred_date).slice(0, 10) : null;
    merged.execution_mode = isOwnFleetExecutionMode(merged.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null;
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

function normalizePartyNormsPenaltiesWithStage(raw) {
    const base = normalizePartyNormsPenalties(raw);

    return {
        ...base,
        stage: raw?.stage != null && String(raw.stage).trim() !== '' ? String(raw.stage).trim() : null,
    };
}

function normalizeCarrierNormsByLegList(existingRows, performers) {
    const existing = Array.isArray(existingRows) ? existingRows : [];
    const legs = Array.isArray(performers) ? performers : [];

    return legs.map((performer) => {
        const existingRow = existing.find((row) => stageMatches(row.stage, performer.stage));

        return normalizePartyNormsPenaltiesWithStage({
            ...existingRow,
            stage: performer.stage,
        });
    });
}

function normalizeLoadedContractorsCosts(rawCosts, order) {
    const migrated = migrateLegacyAdditionalContractorCosts(rawCosts, order?.order_date);

    return migrated.legCosts.map((cost) => normalizeContractorCost(cost));
}

function buildInitialAdditionalCosts(order) {
    const fromFinancial = normalizeAdditionalCostsList(
        order?.financial_term?.additional_costs ?? [],
        order?.order_date ?? null,
    );

    if (fromFinancial.length > 0) {
        return fromFinancial;
    }

    const migrated = migrateLegacyAdditionalContractorCosts(
        order?.financial_term?.contractors_costs ?? [],
        order?.order_date ?? null,
    );

    return migrated.additionalCosts;
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
        svh_address: '',
        customs_post_code: '',
        cargo_declared_sum: null,
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
            blankPerformer('leg_1'),
        ],
        route_points: [
            blankRoutePoint('loading', 1, 'leg_1'),
            blankRoutePoint('unloading', 2, 'leg_1'),
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
                performer_allocations: [],
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
            client_norms_penalties: blankPartyNormsPenalties(),
            carrier_norms_by_leg: [],
        },
        additional_expenses: null,
        additional_expenses_payment_date: null,
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

function normalizeAtiCargoPayload(payload) {
    if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
        return { ...payload };
    }

    return {};
}

function performerAllocationsForSubmitItem(item) {
    const columns = cargoPerformerAllocationColumns.value ?? [];
    const fromMatrix = columns
        .map((column) => {
            const row = findCargoAllocation(item, column.stage, column.carrier_slot);
            if (!row) {
                return null;
            }

            const packageCount = row.package_count;
            const weightValue = row.weight_value;
            const hasPackages = packageCount !== null && packageCount !== '' && Number.isFinite(Number(packageCount));
            const hasWeight = weightValue !== null && weightValue !== '' && Number.isFinite(Number(weightValue));

            if (!hasPackages && !hasWeight) {
                return null;
            }

            return {
                stage: column.stage,
                carrier_slot: column.carrier_slot,
                package_count: hasPackages ? Number(packageCount) : null,
                weight_value: hasWeight ? Number(weightValue) : null,
            };
        })
        .filter(Boolean);

    if (fromMatrix.length > 0) {
        return normalizePerformerAllocations(fromMatrix);
    }

    return normalizePerformerAllocations(item.performer_allocations);
}

function serializeCargoItemsForSubmit() {
    return form.cargo_items.map((item) => {
        const performerAllocations = performerAllocationsForSubmitItem(item);
        const atiBase = normalizeAtiCargoPayload(item.ati_cargo_payload);

        return {
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
            performer_allocations: performerAllocations,
            ati_cargo_payload: {
                ...atiBase,
                performer_allocations: performerAllocations,
            },
        };
    });
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
        ati_cargo_payload: normalizeAtiCargoPayload(raw.ati_cargo_payload),
        performer_allocations: normalizePerformerAllocations(
            raw.performer_allocations ?? normalizeAtiCargoPayload(raw.ati_cargo_payload).performer_allocations,
        ),
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

const initialWizardPerformers = Array.isArray(props.order?.performers)
    ? props.order.performers.map((performer) => normalizePerformer({
        ...performer,
        stage: toStageKey(performer.stage ?? 'leg_1') || 'leg_1',
        contractor_id: normalizeNullableNumber(performer.contractor_id),
        contractor_name: performer.contractor_name ? String(performer.contractor_name).trim() || null : null,
        fleet_vehicle_id: normalizeNullableNumber(performer.fleet_vehicle_id),
        fleet_driver_id: normalizeNullableNumber(performer.fleet_driver_id),
        split_carriers: Array.isArray(performer.split_carriers)
            ? performer.split_carriers.map((slot, index) => ({
                ...slot,
                slot: Number(slot?.slot ?? index + 1),
                contractor_id: normalizeNullableNumber(slot?.contractor_id),
                fleet_vehicle_id: normalizeNullableNumber(slot?.fleet_vehicle_id),
                fleet_driver_id: normalizeNullableNumber(slot?.fleet_driver_id),
                loading_actual: slot?.loading_actual ?? '',
                unloading_actual: slot?.unloading_actual ?? '',
            }))
            : [],
    }))
    : blankOrder().performers;

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
    additional_expenses_payment_date: props.order?.additional_expenses_payment_date ?? props.order?.order_date ?? null,
    insurance: props.order?.insurance ?? null,
    bonus: props.order?.bonus ?? null,
    loading_types: Array.isArray(props.order?.loading_types)
        ? props.order.loading_types
        : [],
    cargo_items: Array.isArray(props.order?.cargo_items)
        ? props.order.cargo_items.map((c) => normalizeCargoItem(c))
        : blankOrder().cargo_items,
    performers: initialWizardPerformers,
    route_points: Array.isArray(props.order?.route_points)
        ? props.order.route_points.map((point, index) => ({
            ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), toStageKey(point.stage ?? 'leg_1') || 'leg_1'),
            ...point,
            stage: toStageKey(point.stage ?? 'leg_1') || 'leg_1',
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
        contractors_costs: normalizeLoadedContractorsCosts(props.order?.financial_term?.contractors_costs, props.order),
        additional_costs: buildInitialAdditionalCosts(props.order),
        client_norms_penalties: normalizePartyNormsPenalties(props.order?.financial_term?.client_norms_penalties),
        carrier_norms_by_leg: normalizeCarrierNormsByLegList(
            props.order?.financial_term?.carrier_norms_by_leg,
            initialWizardPerformers,
        ),
    },
    documents: Array.isArray(props.order?.documents)
        ? props.order.documents
            .filter((document) => !document.is_print_workflow && document.status === 'signed')
            .map((document) => normalizeDocument({ ...document, status: 'signed', flow: 'uploaded' }))
        : [],
    svh_name: props.order?.svh_name ?? '',
    svh_address: props.order?.svh_address ?? '',
    customs_post_code: props.order?.customs_post_code ?? '',
    cargo_declared_sum: props.order?.cargo_declared_sum ?? null,
    is_international_transport: Boolean(props.order?.is_international_transport),
});

const documentsTabRef = ref(null);

const orderNumberManual = ref(Boolean(props.order?.order_number));
const suggestedOrderNumberCipher = ref('');

function onOrderNumberManualInput() {
    orderNumberManual.value = true;
}

async function refreshSuggestedOrderNumber() {
    const companyId = Number(form.own_company_id);
    if (!Number.isFinite(companyId) || companyId <= 0) {
        suggestedOrderNumberCipher.value = '';
        return;
    }

    try {
        const url = route('orders.suggest-order-number', { own_company_id: companyId });
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        suggestedOrderNumberCipher.value = data?.cipher ? String(data.cipher) : '';

        if (!orderNumberManual.value && data?.order_number) {
            form.order_number = String(data.order_number);
        }
    } catch (error) {
        console.error('suggest order number failed', error);
    }
}

watch(() => form.own_company_id, () => {
    form.own_company_bank_account_id = null;

    if (!isEditing.value && !orderNumberManual.value) {
        void refreshSuggestedOrderNumber();
    }
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
let compensationDebounceTimer = null;
let compensationRequestVersion = 0;

async function calculateCompensation() {
    if (isCalculatingCompensation.value) {
        return;
    }

    const requestVersion = ++compensationRequestVersion;
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
                additional_expenses: sumAdditionalCostsAmount(form.financial_term.additional_costs),
                insurance: Number(form.insurance || 0),
                bonus: Number(form.bonus || 0),
                manager_id: props.order?.responsible_id ?? props.currentUser?.id,
                order_date: form.order_date,
                customer_payment_form: normalizePaymentFormCode(form.financial_term.client_payment_form, defaultClientPaymentForm()),
                contractors_costs: legContractorCosts.value,
            }),
        });

        if (!response.ok) {
            throw new Error(`Расчёт компенсации: HTTP ${response.status}`);
        }

        const result = await response.json();

        if (requestVersion !== compensationRequestVersion) {
            return;
        }

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
        () => form.financial_term.additional_costs,
        () => form.insurance,
        () => form.bonus,
        () => form.order_date,
        () => form.client_id,
        () => form.performers,
    ],
    () => {
        if (compensationDebounceTimer !== null) {
            clearTimeout(compensationDebounceTimer);
        }

        compensationDebounceTimer = setTimeout(() => {
            calculateCompensation();
        }, 400);
    },
    { deep: true, immediate: true },
);

const isEditing = computed(() => props.order !== null);

const intakeFileInput = ref(null);
const intakeSelectedFile = ref(null);
const intakeLoading = ref(false);
const intakePreview = ref(null);
const intakeError = ref('');
const activeIntakeDraftId = ref(null);
const intakeDraftCommitted = ref(false);

function getCsrfToken() {
    if (typeof document === 'undefined') {
        return '';
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function activateIntakeDraftLearning(draftId) {
    try {
        await fetch(route('orders.intake.learning.activate', { draft: draftId }), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });
    } catch (error) {
        console.error('intake learning activate failed', error);
    }
}

function discardActiveIntakeLearning() {
    if (isEditing.value || intakeDraftCommitted.value || !activeIntakeDraftId.value) {
        return;
    }

    const id = activeIntakeDraftId.value;
    activeIntakeDraftId.value = null;

    try {
        fetch(route('orders.intake.learning.discard', { draft: id }), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            keepalive: true,
        });
    } catch (error) {
        console.error('intake learning discard failed', error);
    }
}

function mergeFinancialTermFromIntake(patchTerm) {
    if (!patchTerm || typeof patchTerm !== 'object') {
        return;
    }

    const current = form.financial_term;
    const merged = {
        ...current,
        ...patchTerm,
    };

    merged.client_payment_schedule = normalizePaymentSchedule(
        patchTerm.client_payment_schedule !== undefined
            ? patchTerm.client_payment_schedule
            : current.client_payment_schedule,
    );

    if (Array.isArray(patchTerm.contractors_costs)) {
        merged.contractors_costs = patchTerm.contractors_costs.map((row) => normalizeContractorCost(row));
    } else if (Array.isArray(merged.contractors_costs)) {
        merged.contractors_costs = merged.contractors_costs.map((row) => normalizeContractorCost(row));
    }

    form.financial_term = merged;
}

function onIntakeFileSelected(event) {
    intakeError.value = '';
    intakePreview.value = null;
    intakeSelectedFile.value = event.target.files?.[0] ?? null;
}

async function extractIntakeDraft() {
    if (!intakeSelectedFile.value) {
        return;
    }

    intakeLoading.value = true;
    intakeError.value = '';
    intakePreview.value = null;

    const body = new FormData();
    body.append('file', intakeSelectedFile.value);

    try {
        const response = await fetch(route('orders.intake.extract'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body,
        });

        const payload = await response.json();

        if (!response.ok) {
            intakeError.value = payload?.message
                ?? payload?.errors?.file?.[0]
                ?? 'Не удалось распознать заявку.';
            return;
        }

        intakePreview.value = payload;
    } catch (error) {
        console.error('order intake extract failed', error);
        intakeError.value = 'Ошибка сети при распознавании заявки.';
    } finally {
        intakeLoading.value = false;
    }
}

function applyIntakeDraft() {
    const patch = intakePreview.value?.wizard_patch;
    if (!patch || typeof patch !== 'object') {
        return;
    }

    Object.entries(patch).forEach(([key, value]) => {
        if (key === 'route_points' && Array.isArray(value)) {
            form.route_points = value.map((point, index) => ({
                ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), toStageKey(point.stage ?? 'leg_1') || 'leg_1'),
                ...point,
                stage: toStageKey(point.stage ?? 'leg_1') || 'leg_1',
                sequence: Number(point.sequence ?? (index + 1)),
                normalized_data: point.normalized_data || {},
            }));
            normalizeRoutePointSequences();
            return;
        }

        if (key === 'cargo_items' && Array.isArray(value) && value[0]) {
            const base = form.cargo_items[0] ?? blankOrder().cargo_items[0];
            form.cargo_items[0] = normalizeCargoItem({ ...base, ...value[0] });
            return;
        }

        if (key === 'financial_term' && value && typeof value === 'object') {
            mergeFinancialTermFromIntake(value);

            return;
        }

        if (key === 'carrier_contractor_id' && value != null && form.performers[0]) {
            const carrierId = Number(value);
            form.performers[0].contractor_id = carrierId;
            const carrierName = patch.carrier_contractor_name ?? getContractorById(carrierId)?.name ?? '';
            if (carrierName) {
                form.performers[0].contractor_name = carrierName;
                setCarrierSearchValue('performer', 0, carrierName);
            }
            const costIndex = form.financial_term.contractors_costs.findIndex((cost) => stageMatches(cost.stage, form.performers[0].stage));
            if (costIndex !== -1) {
                form.financial_term.contractors_costs[costIndex].contractor_id = carrierId;
            }

            return;
        }

        if (Object.prototype.hasOwnProperty.call(form, key)) {
            form[key] = value;
        }
    });

    syncContractorCostsFromPerformers();

    const draftId = Number(intakePreview.value?.draft_id ?? 0);
    if (draftId > 0 && !isEditing.value) {
        activeIntakeDraftId.value = draftId;
        intakeDraftCommitted.value = false;
        void activateIntakeDraftLearning(draftId);
    }

    activeTab.value = 'main';
}

function applyIntakeDraftPayload(payload) {
    if (!payload?.wizard_patch || typeof payload.wizard_patch !== 'object') {
        return;
    }

    intakePreview.value = {
        draft_id: payload.draft_id,
        confidence: payload.confidence,
        preview: payload.preview ?? [],
        warnings: payload.warnings ?? [],
        wizard_patch: payload.wizard_patch,
        matched_contractors: payload.matched_contractors ?? [],
    };
    applyIntakeDraft();
}

async function loadAndApplyIntakeDraftById(draftId) {
    const id = Number(draftId);
    if (!Number.isFinite(id) || id <= 0) {
        return;
    }

    intakeError.value = '';

    try {
        const response = await fetch(route('orders.intake.draft', { draft: id }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();

        if (!response.ok) {
            intakeError.value = data?.message ?? 'Не удалось загрузить черновик заявки.';
            return;
        }

        applyIntakeDraftPayload(data);
        activeIntakeDraftId.value = id;
        intakeDraftCommitted.value = false;
        void activateIntakeDraftLearning(id);
    } catch (error) {
        console.error('order intake draft load failed', error);
        intakeError.value = 'Ошибка сети при загрузке черновика.';
    }
}

const orderStatusBadgeLabel = computed(() => {
    const manual = form.manual_status != null && String(form.manual_status).trim() !== '' ? String(form.manual_status).trim() : null;
    const code = manual ?? form.status;
    const opt = props.orderStatusOptions.find((o) => o.value === code);

    return opt?.label ?? code ?? '—';
});

const orderStatusIconKey = computed(() => {
    const manual = form.manual_status != null && String(form.manual_status).trim() !== '' ? String(form.manual_status).trim() : null;
    const effective = manual ?? form.status;
    const row = { manual_status: form.manual_status, status: form.status };

    return resolveOrderStatusIconKey(row, effective);
});

const orderStatusIconMeta = computed(() => Boolean(orderStatusIconKey.value && ORDER_STATUS_ICON_META[orderStatusIconKey.value]));

/** Ложь, когда владелец заказа не может менять карточку (все печатные заявки финализированы). */
const isOrderFormEditable = computed(() => {
    if (!isEditing.value) {
        return true;
    }

    return props.order?.can_edit_order !== false;
});

function performerHasLoadingActual(performer) {
    if (!performer) {
        return false;
    }

    if (isPerformerSplit(performer)) {
        return (performer.split_carriers ?? []).some(
            (slot) => slot?.loading_actual != null && String(slot.loading_actual).trim() !== '',
        );
    }

    return performer.loading_actual != null && String(performer.loading_actual).trim() !== '';
}

function wizardRouteLoadingHasActualDate() {
    if (Array.isArray(form.performers) && form.performers.some((performer) => performerHasLoadingActual(performer))) {
        return true;
    }

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
const legContractorCosts = computed(() => (
    Array.isArray(form.financial_term.contractors_costs)
        ? form.financial_term.contractors_costs.filter((row) => !isAdditionalContractorCost(row))
        : []
));

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

    return classifyDealType(clientPaymentForm, carrierPaymentForms, paymentFormMeta.value);
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
    const stage = `leg_${form.performers.length + 1}`;

    form.performers.push(blankPerformer(stage));
    syncContractorCostsFromPerformers();
    syncRoutePointsFromPerformers();
}

function setPerformerCarrierMode(legIndex, mode) {
    const performer = form.performers[legIndex];

    if (!performer || performer.carrier_mode === mode) {
        return;
    }

    if (mode === CARRIER_MODE_SPLIT) {
        const firstCarrier = performer.contractor_id
            ? {
                contractor_id: performer.contractor_id,
                contractor_name: performer.contractor_name,
                fleet_vehicle_id: performer.fleet_vehicle_id,
                fleet_driver_id: performer.fleet_driver_id,
            }
            : {};

        const stage = performer.stage;
        const singleCostRow = form.financial_term.contractors_costs.find(
            (cost) => costMatchesPerformerSlot(cost, performer, null),
        );

        performer.carrier_mode = CARRIER_MODE_SPLIT;
        const legDates = {
            loading_actual: performer.loading_actual || '',
            unloading_actual: performer.unloading_actual || '',
        };
        performer.split_carriers = [
            { ...blankSplitCarrier(1), ...firstCarrier, ...legDates },
            { ...blankSplitCarrier(2), ...legDates },
        ];
        performer.contractor_id = null;
        performer.contractor_name = null;
        performer.fleet_vehicle_id = null;
        performer.fleet_driver_id = null;
        performer.loading_actual = '';
        performer.unloading_actual = '';

        if (singleCostRow) {
            const sharedPayment = {
                payment_form: singleCostRow.payment_form,
                payment_schedule: JSON.parse(JSON.stringify(singleCostRow.payment_schedule ?? blankPaymentSchedule())),
                payment_terms: singleCostRow.payment_terms,
            };
            form.financial_term.contractors_costs = form.financial_term.contractors_costs.filter(
                (cost) => !stageMatches(cost.stage, stage),
            );
            performer.split_carriers.forEach((slot) => {
                if (!normalizeNullableNumber(slot.contractor_id)) {
                    return;
                }

                form.financial_term.contractors_costs.push(normalizeContractorCost({
                    ...sharedPayment,
                    stage,
                    carrier_slot: Number(slot.slot ?? 1),
                    contractor_id: slot.contractor_id,
                    amount: singleCostRow.amount,
                    currency: singleCostRow.currency ?? 'RUB',
                }));
            });
        }
    } else {
        const firstSlot = performer.split_carriers?.[0] ?? blankSplitCarrier(1);
        performer.carrier_mode = CARRIER_MODE_SINGLE;
        performer.contractor_id = firstSlot.contractor_id ?? null;
        performer.contractor_name = firstSlot.contractor_name ?? null;
        performer.fleet_vehicle_id = firstSlot.fleet_vehicle_id ?? null;
        performer.fleet_driver_id = firstSlot.fleet_driver_id ?? null;
        performer.loading_actual = firstSlot.loading_actual ?? '';
        performer.unloading_actual = firstSlot.unloading_actual ?? '';
        performer.split_carriers = [];
    }

    syncContractorCostsFromPerformers();
}

function addSplitCarrier(legIndex) {
    const performer = form.performers[legIndex];

    if (!performer || !isPerformerSplit(performer) || performer.split_carriers.length >= 4) {
        return;
    }

    performer.split_carriers.push(blankSplitCarrier(performer.split_carriers.length + 1));
    syncContractorCostsFromPerformers();
}

function removeSplitCarrier(legIndex, slotIndex) {
    const performer = form.performers[legIndex];

    if (!performer || !isPerformerSplit(performer) || performer.split_carriers.length <= 2) {
        return;
    }

    performer.split_carriers.splice(slotIndex, 1);
    performer.split_carriers = performer.split_carriers.map((slot, index) => ({
        ...slot,
        slot: index + 1,
    }));
    syncContractorCostsFromPerformers();
}

function parsePerformerCarrierTarget(kind, index) {
    if (kind === 'performer-slot') {
        const [legIndex, slotIndex] = String(index).split('-').map((value) => Number(value));

        return { legIndex, slotIndex, kind };
    }

    return { legIndex: Number(index), slotIndex: null, kind };
}

function splitCarrierAt(legIndex, slotIndex) {
    return form.performers[legIndex]?.split_carriers?.[slotIndex] ?? null;
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
    syncCargoAllocationMatrixSlots({ pruneOrphans: true });
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
        if (isAdditionalContractorCost(row)) {
            return;
        }

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
        stage: `leg_${i + 1}`,
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
        form.performers = [blankPerformer('leg_1')];
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
    const row = form.performers[performerIndex];
    if (isOwnFleetExecutionMode(row?.execution_mode)) {
        return 'Свой транспорт';
    }

    const id = normalizeNullableNumber(contractorId);
    if (id === null) {
        return '';
    }

    const contractor = getContractorById(id);
    const fromLookup = contractor?.name ? String(contractor.name).trim() : '';
    if (fromLookup) {
        return fromLookup;
    }

    const fromRow = row?.contractor_name ? String(row.contractor_name).trim() : '';

    return fromRow || '';
}

function costRowTitle(cost) {
    const contractor = getContractorById(cost?.contractor_id);
    const contractorName = contractor?.name ? String(contractor.name).trim() : String(cost?.contractor_name ?? '').trim();
    const stagePart = stageLabel(cost?.stage);
    const slotPart = cost?.carrier_slot ? ` · ${splitCarrierSlotLabel(cost.carrier_slot)}` : '';

    if (contractorName !== '') {
        return `${stagePart}${slotPart} · ${contractorName}`;
    }

    return `${stagePart}${slotPart}`;
}

function contractorCostAmountLabel(cost) {
    return isOwnFleetExecutionMode(cost?.execution_mode) ? 'Примерная стоимость' : 'Стоимость перевозки';
}

function contractorCostOrderDate(cost) {
    return form.order_date;
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
    let selectedContractorId = null;

    if (kind === 'performer-slot') {
        const target = parsePerformerCarrierTarget(kind, index);
        selectedContractorId = normalizeNullableNumber(splitCarrierAt(target.legIndex, target.slotIndex)?.contractor_id);
    } else if (kind === 'performer') {
        selectedContractorId = normalizeNullableNumber(form.performers[index]?.contractor_id);
    } else {
        selectedContractorId = normalizeNullableNumber(form.financial_term.contractors_costs[index]?.contractor_id);
    }

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

function contractorNormsDefaults(contractor, field) {
    if (!contractor?.[field] || !hasNormsPenaltiesContent(contractor[field])) {
        return null;
    }

    return normalizePartyNormsPenalties(contractor[field]);
}

function applyClientDefaults(contractor) {
    if (!contractor) {
        return;
    }

    if (contractor.default_customer_payment_form) {
        form.financial_term.client_payment_form = normalizePaymentFormCode(contractor.default_customer_payment_form, defaultClientPaymentForm());
    }

    form.financial_term.client_payment_schedule = contractorPaymentSchedule(contractor, 'default_customer_payment_schedule', 'default_customer_payment_term');

    const clientNorms = contractorNormsDefaults(contractor, 'default_customer_norms_penalties');
    if (clientNorms) {
        form.financial_term.client_norms_penalties = clientNorms;
    }

    if (contractor.cooperation_terms_notes && !String(form.special_notes || '').trim()) {
        form.special_notes = contractor.cooperation_terms_notes;
    }
}

function applyCarrierNormsDefaultsByStage(stage, contractorId) {
    const norms = contractorNormsDefaults(getContractorById(contractorId), 'default_carrier_norms_penalties');
    if (!norms) {
        return;
    }

    syncCarrierNormsByLegFromPerformers();

    const idx = form.financial_term.carrier_norms_by_leg.findIndex((row) => stageMatches(row.stage, stage));
    if (idx >= 0) {
        form.financial_term.carrier_norms_by_leg[idx] = normalizePartyNormsPenaltiesWithStage({
            ...norms,
            stage: form.financial_term.carrier_norms_by_leg[idx].stage,
        });
    }
}

function applyCarrierDefaultsByStage(stage, contractorId, carrierSlot = null) {
    const contractor = getContractorById(contractorId);

    if (!contractor) {
        return;
    }

    const costRow = form.financial_term.contractors_costs.find((row) => {
        if (!stageMatches(row.stage, stage)) {
            return false;
        }

        if (carrierSlot == null) {
            return row.carrier_slot == null || row.carrier_slot === '';
        }

        return Number(row.carrier_slot) === Number(carrierSlot);
    });

    if (!costRow) {
        return;
    }

    if (contractor.default_carrier_payment_form) {
        costRow.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
    }

    costRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');

    applyCarrierNormsDefaultsByStage(stage, contractorId);
}

function splitCarrierSearchLabel(legIndex, slotIndex, contractorId) {
    const slot = splitCarrierAt(legIndex, slotIndex);
    if (isOwnFleetExecutionMode(slot?.execution_mode)) {
        return 'Свой транспорт';
    }

    const id = normalizeNullableNumber(contractorId);
    if (id === null) {
        return '';
    }

    const contractor = getContractorById(id);
    const fromLookup = contractor?.name ? String(contractor.name).trim() : '';
    if (fromLookup) {
        return fromLookup;
    }

    const fromRow = slot?.contractor_name ? String(slot.contractor_name).trim() : '';

    return fromRow || '';
}

function selectOwnFleetPerformer(index) {
    const ownFleet = props.ownFleetContractor;
    if (!ownFleet?.id) {
        return;
    }

    ensureContractorInLocalList({
        ...ownFleet,
        type: 'carrier',
    });

    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: Number(ownFleet.id),
        contractor_name: ownFleet.name ? String(ownFleet.name).trim() || null : null,
        execution_mode: EXECUTION_MODE_OWN_FLEET,
        fleet_vehicle_id: null,
        fleet_driver_id: null,
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, 'Свой транспорт');
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    loadFleetOptionsForLeg(index);
}

function selectOwnFleetSplitSlot(legIndex, slotIndex) {
    const ownFleet = props.ownFleetContractor;
    if (!ownFleet?.id) {
        return;
    }

    ensureContractorInLocalList({
        ...ownFleet,
        type: 'carrier',
    });

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = Number(ownFleet.id);
    slot.contractor_name = ownFleet.name ? String(ownFleet.name).trim() || null : null;
    slot.execution_mode = EXECUTION_MODE_OWN_FLEET;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, 'Свой транспорт');
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    loadFleetOptionsForLeg(legIndex, slotIndex);
}

function selectSplitPerformerContractor(legIndex, slotIndex, contractor) {
    ensureContractorInLocalList(contractor);

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = Number(contractor.id);
    slot.contractor_name = contractor.name ? String(contractor.name).trim() || null : null;
    slot.execution_mode = null;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, contractor.name);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[legIndex].stage, contractor.id, slot.slot);
    loadFleetOptionsForLeg(legIndex, slotIndex);
}

function clearSplitPerformerContractor(legIndex, slotIndex) {
    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    slot.contractor_id = null;
    slot.contractor_name = null;
    slot.execution_mode = null;
    slot.fleet_vehicle_id = null;
    slot.fleet_driver_id = null;

    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, '');
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    syncContractorCostsFromPerformers();
    fleetOptionsCache.value = {
        ...fleetOptionsCache.value,
        [performerFleetCacheKey(legIndex, slotIndex)]: { vehicles: [], drivers: [] },
    };
}

function onSplitPerformerCarrierInput(legIndex, slotIndex, value) {
    setCarrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`, value);
    setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, true);

    const slot = splitCarrierAt(legIndex, slotIndex);
    if (!slot) {
        return;
    }

    const typed = String(value ?? '').trim().toLowerCase();
    const selectedContractor = getContractorById(slot.contractor_id);
    const selectedName = String(selectedContractor?.name ?? slot.contractor_name ?? '').trim().toLowerCase();

    if (typed === '') {
        clearSplitPerformerContractor(legIndex, slotIndex);

        return;
    }

    if (normalizeNullableNumber(slot.contractor_id) !== null && selectedName !== '' && selectedName !== typed) {
        slot.contractor_id = null;
        slot.contractor_name = null;
        slot.execution_mode = null;
        slot.fleet_vehicle_id = null;
        slot.fleet_driver_id = null;
        syncContractorCostsFromPerformers();
    }
}

function restoreSplitPerformerCarrierSearch(legIndex, slotIndex) {
    window.setTimeout(() => {
        const slot = splitCarrierAt(legIndex, slotIndex);
        if (!slot) {
            return;
        }

        setCarrierSearchValue(
            'performer-slot',
            `${legIndex}-${slotIndex}`,
            splitCarrierSearchLabel(legIndex, slotIndex, slot.contractor_id),
        );
        setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, false);
    }, 120);
}

function selectPerformerContractor(index, contractor) {
    ensureContractorInLocalList(contractor);

    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: Number(contractor.id),
        contractor_name: contractor.name ? String(contractor.name).trim() || null : null,
        execution_mode: null,
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
        execution_mode: null,
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
        performer.execution_mode = null;
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

async function loadFleetOptionsForLeg(legIndex, slotIndex = null) {
    const cacheKey = performerFleetCacheKey(legIndex, slotIndex);
    let contractorId = null;

    if (slotIndex !== null) {
        contractorId = normalizeNullableNumber(splitCarrierAt(legIndex, slotIndex)?.contractor_id);
    } else {
        contractorId = normalizeNullableNumber(form.performers[legIndex]?.contractor_id);
    }

    if (!contractorId) {
        fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles: [], drivers: [] } };

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
    fleetOptionsCache.value = { ...fleetOptionsCache.value, [cacheKey]: { vehicles, drivers } };
}

function fleetVehicleOptionsForLeg(legIndex, slotIndex = null) {
    return fleetOptionsCache.value[performerFleetCacheKey(legIndex, slotIndex)]?.vehicles ?? [];
}

function fleetDriverOptionsForLeg(legIndex, slotIndex = null) {
    return fleetOptionsCache.value[performerFleetCacheKey(legIndex, slotIndex)]?.drivers ?? [];
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
                const parts = [
                    String(form.customs_post_code ?? '').trim(),
                    String(form.svh_name ?? '').trim(),
                    String(form.svh_address ?? '').trim(),
                ].filter((s) => s !== '');
                const label = parts.length > 0 ? parts.join(' · ') : 'СВХ / пост не указаны';

                return `${routePointTypeHeading(point.type)}: ${label}`;
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

const needsCargoPerformerAllocationUi = computed(() => needsCargoPerformerAllocation(form.performers, isPerformerSplit));

function allocationColumnContractorName(stage, carrierSlot) {
    const performer = form.performers.find((row) => stageMatches(row.stage, stage));
    if (!performer) {
        return '';
    }

    if (isPerformerSplit(performer)) {
        const slotNumber = Number(carrierSlot ?? 1);
        const slot = (performer.split_carriers ?? []).find(
            (row, index) => Number(row?.slot ?? index + 1) === slotNumber,
        );
        const fromRow = String(slot?.contractor_name ?? '').trim();
        if (fromRow !== '') {
            return fromRow;
        }

        return String(getContractorById(slot?.contractor_id)?.name ?? '').trim();
    }

    const fromRow = String(performer.contractor_name ?? '').trim();
    if (fromRow !== '') {
        return fromRow;
    }

    return String(getContractorById(performer.contractor_id)?.name ?? '').trim();
}

const cargoPerformerAllocationColumns = computed(() =>
    performerAllocationColumns(
        form.performers,
        stageLabel,
        splitCarrierSlotLabel,
        isPerformerSplit,
        allocationColumnContractorName,
    ),
);

const cargoPerformerAllocationColumnSummaries = computed(() =>
    cargoPerformerAllocationColumns.value.map((column) => ({
        ...column,
        ...summarizeAllocationsForColumn(form.cargo_items, column.stage, column.carrier_slot),
    })),
);

const cargoAllocationRowStatuses = computed(() =>
    form.cargo_items.map((item) =>
        cargoAllocationRowStatus(item, cargoPerformerAllocationColumns.value, stageLabel),
    ),
);

function syncCargoAllocationMatrixSlots(options = {}) {
    if (!needsCargoPerformerAllocationUi.value) {
        return;
    }

    remapCargoAllocationsToCanonicalStages(form.cargo_items);

    if (options.pruneOrphans !== true) {
        return;
    }

    const allowedKeys = new Set(cargoPerformerAllocationColumns.value.map((column) => column.key));
    pruneCargoAllocationsToColumns(form.cargo_items, allowedKeys);
}

function syncAllocationWeightFromPackages(row, item) {
    const packages = Number(row.package_count ?? 0);
    if (!Number.isFinite(packages) || packages <= 0) {
        return;
    }

    const perPlaceKg = cargoLinePerPlaceWeightKg(item);
    if (perPlaceKg <= 0) {
        return;
    }

    const explicit = row.weight_value;
    if (explicit !== null && explicit !== '' && Number(explicit) > 0) {
        return;
    }

    const totalKg = perPlaceKg * packages;
    row.weight_value = item.weight_unit === 't'
        ? Math.round((totalKg / 1000) * 1000) / 1000
        : Math.round(totalKg * 100) / 100;
}

function allocationWeightFieldPlaceholder(item, column) {
    const allocation = findCargoAllocation(item, column.stage, column.carrier_slot);
    if (!allocation) {
        return 'кг';
    }

    return allocationWeightPlaceholder(allocation, item);
}

function onCargoAllocationPackagesInput(item, column, rawValue) {
    const row = ensureCargoAllocation(item, column.stage, column.carrier_slot);
    row.package_count = rawValue === '' || rawValue === null ? null : Number(rawValue);
    syncAllocationWeightFromPackages(row, item);
    touchCargoItemAllocations(item);
}

function onCargoAllocationWeightInput(item, column, rawValue) {
    const row = ensureCargoAllocation(item, column.stage, column.carrier_slot);
    row.weight_value = rawValue === '' || rawValue === null ? null : Number(rawValue);
    touchCargoItemAllocations(item);
}

/** Явно помечаем массив allocations изменённым для useForm / Vue. */
function touchCargoItemAllocations(item) {
    if (!Array.isArray(item.performer_allocations)) {
        item.performer_allocations = [];
    }
    item.performer_allocations = [...item.performer_allocations];
}

function performerCargoSummaryLabel(stage, carrierSlot) {
    const summary = summarizeAllocationsForColumn(form.cargo_items, stage, carrierSlot);
    if (!summary.hasAny) {
        return null;
    }

    return `${summary.totalPackages} мест · ${summary.totalWeightKg.toFixed(0)} кг`;
}

watch(
    () => form.performers.map((performer, index) => ({
        index,
        stage: performer.stage,
        carrier_mode: performer.carrier_mode,
        split_count: Array.isArray(performer.split_carriers) ? performer.split_carriers.length : 0,
    })),
    () => {
        syncCargoAllocationMatrixSlots({ pruneOrphans: true });
    },
);

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

const normsPenaltiesTabValidationMessages = computed(() => {
    return Object.entries(form.errors)
        .filter(([key]) => key.startsWith('financial_term.client_norms_penalties')
            || key.startsWith('financial_term.carrier_norms_by_leg'))
        .map(([, value]) => (Array.isArray(value) ? value.join(' ') : String(value)));
});

const hasUnsavedDocumentFiles = computed(() => form.documents.some((d) => d.file instanceof File));

const financialSummary = computed(() => {
    const clientPrice = Number(form.financial_term.client_price || 0);
    const contractorCosts = legContractorCosts.value.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const additionalExpenses = sumAdditionalCostsAmount(form.financial_term.additional_costs);
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

const paymentSettlementLines = computed(() => {
    const lines = orderPaymentSettlement.value?.lines;

    return Array.isArray(lines) ? lines : [];
});

const showPaymentSettlementBlock = computed(() => paymentSettlementLines.value.length > 0);

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

function paymentSettlementLineTitle(line) {
    if (!line) {
        return '';
    }

    if (line.party === 'customer') {
        return line.state === 'complete'
            ? 'Клиент рассчитался с нами'
            : 'Оплата от клиента';
    }

    const name = String(line.counterparty_name ?? '').trim();
    const counterpartyLabel = line.party === 'contractor' ? 'подрядчиком' : 'перевозчиком';
    const paymentLabel = line.party === 'contractor' ? 'подрядчику' : 'перевозчику';
    if (line.state === 'complete') {
        return name !== '' ? `Мы рассчитались с ${name}` : `Мы рассчитались с ${counterpartyLabel}`;
    }

    return name !== '' ? `Оплата ${paymentLabel} (${name})` : `Оплата ${paymentLabel}`;
}

function paymentSettlementLineValue(line) {
    if (!line?.has_rows) {
        return '—';
    }

    const dateLabel = line.last_payment_at ? formatRuDate(line.last_payment_at) : '';

    if (line.state === 'complete') {
        return dateLabel || 'да';
    }

    if (line.state === 'partial') {
        const percent = Math.round(Number(line.percent_paid ?? 0));
        if (dateLabel) {
            return `Оплачено ${percent}%. ${dateLabel}`;
        }

        return `Оплачено ${percent}%`;
    }

    return 'не было';
}

const effectiveRequiredDocumentRules = computed(() => buildDocumentRequirementRules(
    form.performers,
    form.financial_term.client_request_mode,
    form.financial_term.additional_costs,
));

const documentChecklist = computed(() => {
    const rules = effectiveRequiredDocumentRules.value;
    const documents = Array.isArray(form.documents) ? form.documents : [];
    const usedIds = new Set();

    return rules.map((rule) => {
        const matchedDocument = documents.find((document) => {
            if (document?.id && usedIds.has(document.id)) {
                return false;
            }

            const status = String(document.status ?? '');

            if (!['sent', 'signed'].includes(status)) {
                return false;
            }

            return documentMatchesRequirementRule(document, rule);
        });

        if (matchedDocument?.id && !rule.allows_multiple) {
            usedIds.add(matchedDocument.id);
        }

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
        form.performers[0]?.stage ?? 'leg_1',
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

function addDocumentFor(party, stage = null, overrides = {}) {
    form.documents.push(normalizeDocument({
        party,
        stage,
        ...overrides,
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

function contractorCostRowHasPaymentDetails(costRow) {
    if (!costRow || typeof costRow !== 'object') {
        return false;
    }

    if (String(costRow.payment_terms ?? '').trim() !== '') {
        return true;
    }

    const schedule = costRow.payment_schedule;
    if (!schedule || typeof schedule !== 'object') {
        return false;
    }

    if (orderPs.usesInstallments(schedule)) {
        return true;
    }

    return Boolean(schedule.has_prepayment)
        || Number(schedule.postpayment_days || 0) > 0
        || Number(schedule.prepayment_days || 0) > 0;
}

function syncContractorCostsFromPerformers() {
    const existingRows = Array.isArray(form.financial_term.contractors_costs)
        ? form.financial_term.contractors_costs.filter((row) => !isAdditionalContractorCost(row))
        : [];

    const syncedRows = contractorCostRowsFromPerformers(form.performers).map((row) => {
        const existingRow = existingRows.find(
            (cost) => !isAdditionalContractorCost(cost) && costMatchesPerformerSlot(cost, row.performer, row.slot),
        );

        const nextRow = normalizeContractorCost({
            ...existingRow,
            stage: row.stage,
            carrier_slot: row.carrier_slot,
            contractor_id: row.contractor_id,
            contractor_name: existingRow?.contractor_name ?? null,
            execution_mode: row.execution_mode ?? existingRow?.execution_mode ?? null,
            is_additional: false,
        });

        const previousContractorId = normalizeNullableNumber(existingRow?.contractor_id);
        const nextContractorId = normalizeNullableNumber(row.contractor_id);
        const contractorChanged = previousContractorId !== nextContractorId;
        const shouldApplyCarrierDefaults = nextContractorId !== null
            && (contractorChanged || !contractorCostRowHasPaymentDetails(existingRow));

        if (shouldApplyCarrierDefaults) {
            const contractor = getContractorById(nextContractorId);

            if (contractor?.default_carrier_payment_form) {
                nextRow.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
            }

            nextRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
        }

        return nextRow;
    });
    form.financial_term.contractors_costs = syncedRows;
    syncCarrierNormsByLegFromPerformers();

    form.performers.forEach((performer) => {
        if (isPerformerSplit(performer)) {
            performer.split_carriers.forEach((slot) => {
                if (slot.contractor_id) {
                    applyCarrierNormsDefaultsByStage(performer.stage, slot.contractor_id);
                }
            });

            return;
        }

        if (performer.contractor_id) {
            applyCarrierNormsDefaultsByStage(performer.stage, performer.contractor_id);
        }
    });
}

function addAdditionalCostRow() {
    form.financial_term.additional_costs.push(blankAdditionalCostRow(form.order_date));
}

function removeAdditionalCostRow(index) {
    if (!Array.isArray(form.financial_term.additional_costs)) {
        return;
    }

    form.financial_term.additional_costs.splice(index, 1);
}

function additionalCostSearchValue(index) {
    return additionalCostSearch.value[index] ?? '';
}

function setAdditionalCostSearchValue(index, value) {
    additionalCostSearch.value = {
        ...additionalCostSearch.value,
        [index]: value,
    };
    queueAdditionalCostSearch(index, value);
}

function setAdditionalCostResultsVisible(index, visible) {
    showAdditionalCostResults.value = {
        ...showAdditionalCostResults.value,
        [index]: visible,
    };
}

function isAdditionalCostResultsVisible(index) {
    return Boolean(showAdditionalCostResults.value[index]);
}

function hideAdditionalCostResults(index) {
    window.setTimeout(() => setAdditionalCostResultsVisible(index, false), 150);
}

function queueAdditionalCostSearch(index, query) {
    const key = String(index);

    if (additionalCostSearchTimers.value[key]) {
        clearTimeout(additionalCostSearchTimers.value[key]);
    }

    if (String(query ?? '').trim().length < MIN_CONTRACTOR_QUERY_LENGTH) {
        serverAdditionalCostSearchResults.value = {
            ...serverAdditionalCostSearchResults.value,
            [key]: [],
        };

        return;
    }

    additionalCostSearchTimers.value[key] = window.setTimeout(async () => {
        await searchAdditionalCostContractors(index, String(query).trim());
    }, 550);
}

async function searchAdditionalCostContractors(index, query) {
    const key = String(index);

    additionalCostSearchAbortControllers.value[key]?.abort();
    const ac = new AbortController();
    additionalCostSearchAbortControllers.value = {
        ...additionalCostSearchAbortControllers.value,
        [key]: ac,
    };
    const seq = (additionalCostSearchFetchSeq.value[key] ?? 0) + 1;
    additionalCostSearchFetchSeq.value = {
        ...additionalCostSearchFetchSeq.value,
        [key]: seq,
    };
    isSearchingAdditionalCosts.value = {
        ...isSearchingAdditionalCosts.value,
        [key]: true,
    };

    try {
        const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=contractor&limit=100`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
            signal: ac.signal,
        });

        if (!response.ok) {
            throw new Error(`Contractor search failed with status ${response.status}`);
        }

        const data = await response.json();
        if (seq !== additionalCostSearchFetchSeq.value[key]) {
            return;
        }

        serverAdditionalCostSearchResults.value = {
            ...serverAdditionalCostSearchResults.value,
            [key]: data.contractors || [],
        };
    } catch (error) {
        if (error?.name !== 'AbortError') {
            console.error('Additional cost contractor search error', error);
            serverAdditionalCostSearchResults.value = {
                ...serverAdditionalCostSearchResults.value,
                [key]: [],
            };
        }
    } finally {
        if (seq === additionalCostSearchFetchSeq.value[key]) {
            isSearchingAdditionalCosts.value = {
                ...isSearchingAdditionalCosts.value,
                [key]: false,
            };
        }
    }
}

function additionalCostCombinedResults(index) {
    const query = additionalCostSearchValue(index).trim().toLowerCase();
    const key = String(index);
    const serverResults = serverAdditionalCostSearchResults.value[key] ?? [];

    if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        return contractors.value
            .filter((contractor) => String(contractor.type ?? '') === 'contractor')
            .filter((contractor) => {
                const name = String(contractor.name ?? '').toLowerCase();
                const inn = String(contractor.inn ?? '').toLowerCase();

                return name.includes(query) || inn.includes(query);
            })
            .slice(0, 50);
    }

    const serverIds = new Set(serverResults.map((contractor) => contractor.id));

    return serverResults;
}

function selectAdditionalCostContractor(index, contractor) {
    ensureContractorInLocalList(contractor);

    const row = form.financial_term.additional_costs[index];
    if (!row) {
        return;
    }

    row.contractor_id = normalizeNullableNumber(contractor.id);
    row.contractor_name = contractor.name ?? null;

    if (contractor.default_carrier_payment_form) {
        row.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
    }

    row.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
    setAdditionalCostSearchValue(index, contractor.name ?? '');
    setAdditionalCostResultsVisible(index, false);
}

function syncCarrierNormsByLegFromPerformers() {
    const existingRows = Array.isArray(form.financial_term.carrier_norms_by_leg)
        ? form.financial_term.carrier_norms_by_leg
        : [];

    form.financial_term.carrier_norms_by_leg = form.performers.map((performer) => {
        const existingRow = existingRows.find((row) => stageMatches(row.stage, performer.stage));

        return normalizePartyNormsPenaltiesWithStage({
            ...existingRow,
            stage: performer.stage,
        });
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
    props.order.performers.forEach((p, legIndex) => {
        const registerCarrier = (id, name) => {
            const normalizedId = normalizeNullableNumber(id);
            const normalizedName = name ? String(name).trim() : '';

            if (normalizedId !== null && normalizedName !== '') {
                ensureContractorInLocalList({
                    id: normalizedId,
                    name: normalizedName,
                    type: 'carrier',
                    inn: null,
                    phone: null,
                    email: null,
                    is_own_company: false,
                });
            }
        };

        if (p.carrier_mode === CARRIER_MODE_SPLIT && Array.isArray(p.split_carriers)) {
            p.split_carriers.forEach((slot, slotIndex) => {
                registerCarrier(slot.contractor_id, slot.contractor_name);
                setCarrierSearchValue(
                    'performer-slot',
                    `${legIndex}-${slotIndex}`,
                    splitCarrierSearchLabel(legIndex, slotIndex, slot.contractor_id),
                );
            });

            return;
        }

        registerCarrier(p.contractor_id, p.contractor_name);
    });
}

watch(
    () => form.performers.map((performer) => ({
        stage: performer.stage,
        mode: performer.carrier_mode,
        contractor_id: performer.contractor_id,
        contractor_name: performer.contractor_name,
        split_carriers: (performer.split_carriers ?? []).map((slot) => [
            slot.slot,
            slot.contractor_id,
            slot.contractor_name,
            slot.fleet_vehicle_id,
            slot.fleet_driver_id,
        ]),
    })),
    (performers, prev) => {
        performers.forEach((row, index) => {
            const performer = form.performers[index];
            if (!performer) {
                return;
            }

            if (isPerformerSplit(performer)) {
                performer.split_carriers.forEach((slot, slotIndex) => {
                    setCarrierSearchValue(
                        'performer-slot',
                        `${index}-${slotIndex}`,
                        splitCarrierSearchLabel(index, slotIndex, slot.contractor_id),
                    );

                    const prevSlot = prev?.[index]?.split_carriers?.[slotIndex];
                    const contractorChanged = prevSlot != null && prevSlot[1] !== slot.contractor_id;
                    if (contractorChanged) {
                        slot.fleet_vehicle_id = null;
                        slot.fleet_driver_id = null;
                    }
                    if (prevSlot == null || contractorChanged) {
                        loadFleetOptionsForLeg(index, slotIndex);
                    }
                });

                return;
            }

            setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, row.contractor_id));
            const costIndex = form.financial_term.contractors_costs.findIndex((cost) => stageMatches(cost.stage, row.stage));

            if (costIndex !== -1) {
                setCarrierSearchValue('cost', costIndex, performerCarrierSearchLabel(index, row.contractor_id));
            }

            const prevRow = prev?.[index];
            if (prevRow && prevRow.contractor_id !== row.contractor_id) {
                performer.fleet_vehicle_id = null;
                performer.fleet_driver_id = null;
            }

            if (!prevRow || prevRow.contractor_id !== row.contractor_id) {
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

const ORDER_DOCUMENT_UPLOAD_EXTENSIONS = new Set(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp']);

const showOrderDocumentAttachModal = ref(false);
const orderDocumentAttachPendingFile = ref(null);
const orderDocumentAttachPresetIndex = ref(null);
const orderDocumentAttachNewDocType = ref('request');
const orderDocumentAttachTargetKind = ref('customer');
const orderDocumentAttachStage = ref(null);
const orderDocumentGlobalDropActive = ref(false);
let orderDocumentGlobalDropDepth = 0;
const orderDocumentGlobalFileInputRef = ref(null);
const orderDocumentAttachModalFileInputRef = ref(null);

const orderDocumentAttachModalTitle = computed(() => (orderDocumentAttachPresetIndex.value !== null ? 'Заменить файл' : 'Прикрепить файл'));

watch(orderDocumentAttachTargetKind, (kind) => {
    if (kind === 'carrier' && form.performers.length === 0) {
        orderDocumentAttachTargetKind.value = 'customer';

        return;
    }
    if (kind === 'carrier' && form.performers.length > 0) {
        const stages = form.performers.map((p) => p.stage);
        if (orderDocumentAttachStage.value === null || orderDocumentAttachStage.value === '' || !stages.some((s) => stageMatches(s, orderDocumentAttachStage.value))) {
            orderDocumentAttachStage.value = form.performers[0].stage;
        }
    }
});

function documentStatusLabel(status) {
    return props.documentStatusOptions.find((o) => o.value === status)?.label ?? status;
}

function orderDocumentUploadExtension(file) {
    return (file.name.split('.').pop() || '').toLowerCase();
}

function onOrderDocumentGlobalDragEnter() {
    if (!isOrderFormEditable.value) {
        return;
    }
    orderDocumentGlobalDropDepth += 1;
    orderDocumentGlobalDropActive.value = true;
}

function onOrderDocumentGlobalDragLeave() {
    orderDocumentGlobalDropDepth = Math.max(0, orderDocumentGlobalDropDepth - 1);
    if (orderDocumentGlobalDropDepth === 0) {
        orderDocumentGlobalDropActive.value = false;
    }
}

function onOrderDocumentGlobalDragOver(event) {
    if (!isOrderFormEditable.value) {
        return;
    }
    const dt = event.dataTransfer;
    if (dt) {
        dt.dropEffect = 'copy';
    }
}

async function onOrderDocumentGlobalDrop(event) {
    orderDocumentGlobalDropDepth = 0;
    orderDocumentGlobalDropActive.value = false;
    if (!isOrderFormEditable.value) {
        return;
    }
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (!file) {
        return;
    }
    await openOrderDocumentAttachModal({ file });
}

function triggerOrderDocumentGlobalFilePick() {
    if (!isOrderFormEditable.value) {
        return;
    }
    orderDocumentGlobalFileInputRef.value?.click();
}

async function onOrderDocumentGlobalFileInputChange(event) {
    const file = event.target.files?.[0] ?? null;
    const input = event.target;
    if (input && 'value' in input) {
        input.value = '';
    }
    if (!file) {
        return;
    }
    await openOrderDocumentAttachModal({ file });
}

async function setOrderDocumentAttachPendingFile(file) {
    if (!file) {
        orderDocumentAttachPendingFile.value = null;

        return;
    }
    const ext = orderDocumentUploadExtension(file);
    if (!ORDER_DOCUMENT_UPLOAD_EXTENSIONS.has(ext)) {
        window.alert(
            'Недопустимый тип файла. Разрешены: PDF, Word, Excel, изображения (JPG, PNG, WebP).',
        );

        return;
    }
    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    orderDocumentAttachPendingFile.value = file;
}

async function openOrderDocumentAttachModal(options = {}) {
    const file = options.file ?? null;
    const rawPreset = options.presetIndex;
    const presetIndex = rawPreset !== undefined && rawPreset !== null && form.documents[rawPreset]
        ? rawPreset
        : null;

    orderDocumentAttachPresetIndex.value = presetIndex;
    orderDocumentAttachPendingFile.value = null;

    if (presetIndex !== null) {
        const doc = form.documents[presetIndex];
        orderDocumentAttachTargetKind.value = doc.party === 'carrier' ? 'carrier' : 'customer';
        orderDocumentAttachStage.value = doc.party === 'carrier' ? doc.stage : null;
    } else {
        orderDocumentAttachTargetKind.value = 'customer';
        orderDocumentAttachStage.value = form.performers[0]?.stage ?? null;
        orderDocumentAttachNewDocType.value = props.documentTypeOptions[0]?.value ?? 'request';
    }

    if (file) {
        await setOrderDocumentAttachPendingFile(file);
    }

    showOrderDocumentAttachModal.value = true;
}

function closeOrderDocumentAttachModal() {
    showOrderDocumentAttachModal.value = false;
    orderDocumentAttachPendingFile.value = null;
    orderDocumentAttachPresetIndex.value = null;
    if (orderDocumentAttachModalFileInputRef.value) {
        orderDocumentAttachModalFileInputRef.value.value = '';
    }
    if (orderDocumentGlobalFileInputRef.value) {
        orderDocumentGlobalFileInputRef.value.value = '';
    }
}

async function onOrderDocumentAttachModalFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    await setOrderDocumentAttachPendingFile(file);
    const input = event.target;
    if (input && 'value' in input) {
        input.value = '';
    }
}

async function confirmOrderDocumentAttach() {
    const file = orderDocumentAttachPendingFile.value;
    if (!file) {
        window.alert('Выберите файл.');

        return;
    }
    const presetIdx = orderDocumentAttachPresetIndex.value;
    let index;
    if (presetIdx !== null) {
        if (!form.documents[presetIdx]) {
            window.alert('Документ не найден.');

            return;
        }
        index = presetIdx;
    } else {
        const party = orderDocumentAttachTargetKind.value === 'carrier' ? 'carrier' : 'customer';
        const stage = party === 'carrier' ? orderDocumentAttachStage.value : null;
        if (party === 'carrier' && (stage === null || stage === '')) {
            window.alert('Выберите плечо маршрута.');

            return;
        }
        const docType = orderDocumentAttachNewDocType.value;
        addDocumentFor(party, stage, { type: docType, flow: 'uploaded' });
        index = form.documents.length - 1;
    }
    await assignDocumentFileAtIndex(index, file);
    closeOrderDocumentAttachModal();
}

async function assignDocumentFileAtIndex(index, file) {
    if (!file) {
        return;
    }
    const ext = orderDocumentUploadExtension(file);
    if (!ORDER_DOCUMENT_UPLOAD_EXTENSIONS.has(ext)) {
        window.alert(
            'Недопустимый тип файла. Разрешены: PDF, Word, Excel, изображения (JPG, PNG, WebP).',
        );

        return;
    }
    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    form.documents[index].file = file;
    form.documents[index].original_name = file.name;
}

function documentTypeLabel(type) {
    return props.documentTypeOptions.find((option) => option.value === type)?.label ?? type;
}

const orderDocumentAttachPresetSummary = computed(() => {
    const idx = orderDocumentAttachPresetIndex.value;
    if (idx === null || !form.documents[idx]) {
        return '';
    }
    const d = form.documents[idx];
    const partyLabel = d.party === 'carrier' ? 'Перевозчик' : 'Заказчик';
    const leg = d.party === 'carrier' && d.stage !== null && d.stage !== undefined && String(d.stage).length > 0
        ? ` · ${stageLabel(d.stage)}`
        : '';

    return `${partyLabel}${leg} · ${documentTypeLabel(d.type)} · ${documentStatusLabel(d.status)}${d.number ? ` · № ${d.number}` : ''}`;
});

function documentRequirementLabel(key) {
    return effectiveRequiredDocumentRules.value.find((rule) => rule.key === key)?.label ?? '';
}

function paymentFormLabel(value) {
    return paymentFormOptions.value.find((option) => option.value === value)?.label ?? value;
}

function paymentBasisLabel(value) {
    return orderPs.PAYMENT_BASIS_OPTIONS.find((option) => option.value === value)?.label ?? value;
}

function onRoutePointAddressInput(index) {
    const point = form.route_points[index];
    if (point) {
        syncRoutePointCityFromAddress(point);
    }
    queueAddressLookup(index);
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
    const point = form.route_points[index];
    const existing = point.normalized_data || {};
    point.address = suggestion.value ?? '';
    const suggestedCity = suggestion.data?.city
        ?? suggestion.data?.settlement
        ?? suggestion.data?.city_with_type
        ?? existing.city
        ?? null;
    point.normalized_data = {
        ...existing,
        city: suggestedCity,
        region: suggestion.data?.region_with_type ?? suggestion.data?.region ?? existing.region ?? null,
        street: suggestion.data?.street_with_type ?? suggestion.data?.street ?? existing.street ?? null,
        house: suggestion.data?.house ?? existing.house ?? null,
        coordinates: {
            lat: suggestion.data?.geo_lat ?? existing.coordinates?.lat ?? null,
            lng: suggestion.data?.geo_lon ?? existing.coordinates?.lng ?? null,
        },
        kladr_id: suggestion.data?.kladr_id ?? existing.kladr_id ?? null,
        fias_id: suggestion.data?.fias_id ?? existing.fias_id ?? null,
    };
    syncRoutePointCityFromAddress(point);
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
        if (counterpartyTarget.value.kind === 'performer-slot' && counterpartyTarget.value.index !== null) {
            const target = parsePerformerCarrierTarget('performer-slot', counterpartyTarget.value.index);
            selectSplitPerformerContractor(target.legIndex, target.slotIndex, contractor);
        } else if (counterpartyTarget.value.kind === 'performer' && counterpartyTarget.value.index !== null) {
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

function normsPenaltiesForSubmit(row) {
    const n = normalizePartyNormsPenalties(row && typeof row === 'object' ? row : {});

    return {
        ...(n.stage ? { stage: n.stage } : {}),
        miss_amount: n.miss_amount,
        miss_currency: n.miss_currency,
        downtime_amount: n.downtime_amount,
        downtime_currency: n.downtime_currency,
        fine_amount: n.fine_amount,
        fine_currency: n.fine_currency,
        penalty_terms: n.penalty_terms,
        norm_loading_hours: n.norm_loading_hours,
        norm_customs_hours: n.norm_customs_hours,
        norm_unloading_hours: n.norm_unloading_hours,
    };
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
        svh_address: form.svh_address,
        customs_post_code: form.customs_post_code,
        cargo_declared_sum: form.cargo_declared_sum,
        is_international_transport: Boolean(form.is_international_transport),
        additional_expenses: sumAdditionalCostsAmount(form.financial_term.additional_costs),
        additional_expenses_payment_date: form.financial_term.additional_costs[0]?.service_date || form.order_date || null,
        insurance: form.insurance,
        bonus: form.bonus,

        print_form_template_selection: documentsTabRef.value?.exportPrintFormTemplateSelection?.()
            ?? props.order?.print_form_template_selection
            ?? {},

        // Performers: полный снимок (split_carriers / carrier_mode), иначе «Несколько исполнителей» не доходит до сервера.
        performers: form.performers.map((performer) => {
            const carrierMode = performer.carrier_mode === CARRIER_MODE_SPLIT ? CARRIER_MODE_SPLIT : CARRIER_MODE_SINGLE;

            if (carrierMode === CARRIER_MODE_SPLIT) {
                return {
                    stage: toStageKey(performer.stage) || 'leg_1',
                    carrier_mode: CARRIER_MODE_SPLIT,
                    contractor_id: null,
                    contractor_name: null,
                    fleet_vehicle_id: null,
                    fleet_driver_id: null,
                    loading_actual: null,
                    unloading_actual: null,
                    split_carriers: (performer.split_carriers ?? []).map((slot, index) => ({
                        slot: Number(slot?.slot ?? index + 1),
                        contractor_id: normalizeNullableNumber(slot.contractor_id),
                        contractor_name: slot.contractor_name ? String(slot.contractor_name).trim() || null : null,
                        fleet_vehicle_id: normalizeNullableNumber(slot.fleet_vehicle_id),
                        fleet_driver_id: normalizeNullableNumber(slot.fleet_driver_id),
                        execution_mode: isOwnFleetExecutionMode(slot?.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
                        fleet_trip_id: normalizeNullableNumber(slot?.fleet_trip_id),
                        loading_actual: slot.loading_actual || null,
                        unloading_actual: slot.unloading_actual || null,
                    })),
                };
            }

            return {
                stage: toStageKey(performer.stage) || 'leg_1',
                carrier_mode: CARRIER_MODE_SINGLE,
                contractor_id: normalizeNullableNumber(performer.contractor_id),
                contractor_name: performer.contractor_name ? String(performer.contractor_name).trim() || null : null,
                fleet_vehicle_id: normalizeNullableNumber(performer.fleet_vehicle_id),
                fleet_driver_id: normalizeNullableNumber(performer.fleet_driver_id),
                execution_mode: isOwnFleetExecutionMode(performer?.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
                fleet_trip_id: normalizeNullableNumber(performer?.fleet_trip_id),
                loading_actual: performer.loading_actual || null,
                unloading_actual: performer.unloading_actual || null,
                split_carriers: [],
            };
        }),

        // Route points
        route_points: form.route_points.map((point) => ({
            stage: toStageKey(point.stage) || 'leg_1',
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

        // Cargo items: глубокий снимок без proxy, иначе performer_allocations часто не попадают в POST.
        cargo_items: serializeCargoItemsForSubmit(),

        // Financial term
        financial_term: {
            client_price: rawFinancial.client_price,
            client_currency: rawFinancial.client_currency,
            client_payment_form: normalizePaymentFormCode(rawFinancial.client_payment_form, defaultClientPaymentForm()),
            client_request_mode: rawFinancial.client_request_mode,
            client_payment_schedule: rawFinancial.client_payment_schedule || {},
            client_payment_terms: rawFinancial.client_payment_terms ?? '',
            contractors_costs: (rawFinancial.contractors_costs || [])
                .filter((cost) => !cost?.is_additional && !String(cost?.stage ?? '').startsWith('additional'))
                .map((cost) => ({
                stage: cost.stage,
                carrier_slot: cost.carrier_slot != null && cost.carrier_slot !== '' ? Number(cost.carrier_slot) : null,
                contractor_id: normalizeNullableNumber(cost.contractor_id),
                amount: cost.amount,
                currency: cost.currency || 'RUB',
                payment_form: normalizePaymentFormCode(cost.payment_form, 'no_vat'),
                payment_schedule: cost.payment_schedule || {},
                payment_terms: cost.payment_terms ?? '',
                execution_mode: isOwnFleetExecutionMode(cost.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
            })),
            additional_costs: serializeAdditionalCostsForSubmit(rawFinancial.additional_costs || []),
            kpi_percent: rawFinancial.kpi_percent,
            client_norms_penalties: normsPenaltiesForSubmit(rawFinancial.client_norms_penalties),
            carrier_norms_by_leg: Array.isArray(rawFinancial.carrier_norms_by_leg)
                ? rawFinancial.carrier_norms_by_leg.map((row) => normsPenaltiesForSubmit(row))
                : [],
        },

        // Documents
        documents: form.documents
            .filter((document) => !document.is_print_workflow && document.flow !== 'print_template_workflow')
            .map((document) => ({
                id: document.id ?? null,
                type: document.type,
                flow: 'uploaded',
                party: document.party,
                stage: document.stage,
                contractor_id: document.contractor_id ?? null,
                carrier_contractor_id: document.carrier_contractor_id ?? null,
                requirement_key: document.requirement_key,
                number: document.number,
                document_date: document.document_date && String(document.document_date).trim() !== ''
                    ? document.document_date
                    : null,
                status: 'signed',
                template_id: document.template_id,
                file: document.file instanceof File ? document.file : null,
            })),
        ...(activeIntakeDraftId.value && !isEditing.value
            ? { intake_draft_id: activeIntakeDraftId.value }
            : {}),
    };
}

function markIntakeDraftCommitted() {
    intakeDraftCommitted.value = true;
    activeIntakeDraftId.value = null;
}

function buildWizardSubmitOptions(onError, extra = {}) {
    return {
        preserveScroll: true,
        preserveState: true,
        onError,
        ...extra,
    };
}

function postWizardPayload(url, payload, onError, extraOptions = {}) {
    form.processing = true;

    router.post(url, payload, {
        ...buildWizardSubmitOptions(onError),
        ...extraOptions,
        onFinish: () => {
            form.processing = false;
            extraOptions.onFinish?.();
        },
    });
}

function markOrderDisruption() {
    if (!canShowMarkDisruptionButton.value || !props.order?.id) {
        return;
    }

    if (! window.confirm('Установить статус «Срыв»? Убедитесь, что по плечам ещё не указана фактическая дата погрузки.')) {
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

    // Источник перевозчика — вкладка «Маршрут» (performers). Синхронизируем costs перед отправкой;
    // не перезаписываем performer пустым contractor_id из устаревшего wizard_state.
    syncContractorCostsFromPerformers();

    if (!skipCoreValidation && needsCargoPerformerAllocationUi.value) {
        const allocationErrors = validateCargoPerformerAllocations(
            form.cargo_items,
            cargoPerformerAllocationColumns.value,
            true,
            stageLabel,
        );
        if (allocationErrors.length > 0) {
            activeTab.value = 'cargo';
            window.alert(allocationErrors.join('\n'));

            return;
        }
    }

    const hasNewDocumentFiles = form.documents.some((document) => document.file instanceof File);

    const handleRequestError = (errors) => {
        if (revertStatusOnError !== null) {
            form.status = revertStatusOnError;
        }

        const fieldErrors = errors && typeof errors === 'object' ? errors : {};
        const hasFieldErrors = Object.keys(fieldErrors).length > 0;

        if (hasFieldErrors) {
            form.clearErrors().setError(fieldErrors);

            return;
        }

        form.clearErrors();
        window.alert('Не удалось сохранить заказ. Обновите страницу и попробуйте снова.');
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

        const url = isEditing.value ? route('orders.save', props.order.id) : route('orders.store');
        postWizardPayload(url, formData, handleRequestError, {
            forceFormData: true,
            onSuccess: () => {
                if (!isEditing.value) {
                    markIntakeDraftCommitted();
                }
            },
        });

        return;
    }

    const payload = buildSubmitPayload();

    if (isEditing.value) {
        if (!props.order?.id) {
            return;
        }

        postWizardPayload(route('orders.save', props.order.id), payload, handleRequestError);

        return;
    }

    postWizardPayload(route('orders.store'), payload, handleRequestError, {
        onSuccess: markIntakeDraftCommitted,
    });
}

onBeforeUnmount(() => {
    discardActiveIntakeLearning();
});

function goBack() {
    discardActiveIntakeLearning();
    // Всегда запрашиваем реестр с сервера: history.back() отдаёт старый снимок Inertia без свежих строк.
    router.get(route('orders.index'), {}, { preserveScroll: true });
}

</script>
