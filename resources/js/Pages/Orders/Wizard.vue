<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
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
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-11 shrink-0 items-center gap-2 rounded-2xl bg-zinc-900 px-4 text-sm font-medium text-white transition-colors hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    :disabled="form.processing || customerDebtBlocked"
                    @click="submit"
                >
                    <Save class="h-4 w-4" />
                    {{ form.processing ? '...' : 'Сохранить' }}
                </button>
            </div>

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

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        :disabled="form.processing || customerDebtBlocked"
                        @click="submit"
                    >
                        <Save class="h-4 w-4" />
                        {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 border border-zinc-200 bg-white px-5 py-3 dark:border-zinc-800 dark:bg-zinc-900">
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
        </template>

        <div class="min-h-0 overflow-auto border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 md:p-5">
            <div v-if="activeTab === 'main'" class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Своя компания</label>
                        <select v-model="form.own_company_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option :value="null">Не выбрано</option>
                            <option v-for="company in ownCompanyOptions" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm font-medium">Контрагент</label>
                            <button
                                type="button"
                                class="rounded-xl border border-zinc-200 px-3 py-1.5 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                @click="showCounterpartyModal = true"
                            >
                                Новый контрагент
                            </button>
                        </div>

                        <div class="relative">
                            <input
                                v-model="clientSearch"
                                type="text"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="Начни вводить название или ИНН"
                                @focus="showClientResults = true"
                            />

                            <div
                                v-if="showClientResults && filteredClients.length > 0"
                                class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <button
                                    v-for="contractor in filteredClients"
                                    :key="contractor.id"
                                    type="button"
                                    class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                    @click="selectClient(contractor)"
                                >
                                    <span class="text-sm font-medium">{{ contractor.name }}</span>
                                    <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="selectedClient" class="text-xs text-zinc-500">
                            Выбран: {{ selectedClient.name }}{{ selectedClient.inn ? `, ИНН ${selectedClient.inn}` : '' }}
                        </p>
                        <p v-if="customerDebtBlocked" class="text-xs text-rose-500">
                            Лимит задолженности контрагента достигнут: {{ selectedClient?.current_debt ?? 0 }} {{ selectedClient?.debt_limit_currency || 'RUB' }}. Новый заказ сохранить нельзя.
                        </p>
                        <p v-if="form.errors.client_id" class="text-xs text-rose-500">{{ form.errors.client_id }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Дата заказа</label>
                            <input v-model="form.order_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Номер</label>
                            <input v-model="form.order_number" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="Сгенерируется автоматически" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Статус заказа</label>
                            <select v-model="form.status" disabled class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <option v-for="option in orderStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p class="text-xs text-zinc-500">Статус рассчитывается автоматически по датам, документам и оплатам.</p>
                            <p v-if="missingRequiredDocumentsSummary" class="text-xs text-amber-600 dark:text-amber-300">{{ missingRequiredDocumentsSummary }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Ответственный</label>
                            <input :value="currentUser.name" type="text" disabled class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Расчётный период</label>
                            <input :value="orderPeriodPreview.label" type="text" disabled class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            <p class="text-xs text-zinc-500">Период определяется автоматически по дате заказа: 1-15 или 16-последний день месяца.</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Тип сделки</label>
                            <input :value="dealTypePreview.label" type="text" disabled class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            <p class="text-xs text-zinc-500">Тип сделки определяется по формам оплаты клиента и перевозчика.</p>
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

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Особые отметки</label>
                        <textarea v-model="form.special_notes" rows="4" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>

                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div class="mb-1 text-sm font-semibold">Предварительная сводка по финансам</div>
                        <p class="mb-3 text-xs text-zinc-500">Итоговые KPI, дельта и начисление пересчитываются на сервере после сохранения заказа.</p>
                        <div class="grid gap-3 md:grid-cols-3">
                            <div>Дельта: <span class="font-medium">{{ financialSummary.margin.toFixed(2) }}</span></div>
                            <div>Маржинальность: <span class="font-medium">{{ financialMarginPercent.toFixed(2) }}%</span></div>
                            <div>Себестоимость: <span class="font-medium">{{ financialSummary.totalCost.toFixed(2) }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold">Исполнители по этапам</h2>
                            <p class="text-sm text-zinc-500">Каждый этап формирует плечо в маршруте заказа</p>
                        </div>
                        <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addPerformer">
                            Добавить этап
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(performer, index) in form.performers" :key="`performer-${index}`" class="grid gap-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="grid gap-3 md:grid-cols-4">
                                <div class="space-y-2">
                                    <label class="text-xs font-medium uppercase tracking-wide text-zinc-500">Этап</label>
                                    <input
                                        type="text"
                                        :value="stageLabel(performer.stage)"
                                        readonly
                                        class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                                    />
                                    <input type="hidden" v-model="performer.stage" />
                                </div>
                                <div class="relative space-y-2 md:col-span-2">
                                    <label class="text-xs font-medium uppercase tracking-wide text-zinc-500">Исполнитель</label>
                                    <input
                                        :value="carrierSearchValue('performer', index)"
                                        type="text"
                                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                        placeholder="Начни вводить название или ИНН"
                                        @focus="setCarrierResultsVisible('performer', index, true)"
                                        @input="setCarrierSearchValue('performer', index, $event.target.value); setCarrierResultsVisible('performer', index, true)"
                                    />
                                    <div
                                        v-if="isCarrierResultsVisible('performer', index) && filteredCarrierResults('performer', index).length > 0"
                                        class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                    >
                                        <button
                                            v-for="contractor in filteredCarrierResults('performer', index)"
                                            :key="contractor.id"
                                            type="button"
                                            class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                            @click="selectPerformerContractor(index, contractor)"
                                        >
                                            <span class="text-sm font-medium">{{ contractor.name }}</span>
                                            <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-end justify-end">
                                    <button type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removePerformer(index)">
                                        Удалить
                                    </button>
                                </div>
                            </div>

                            <p class="text-xs text-zinc-500">
                                Стоимость, форма оплаты и условия для этапа задаются на вкладке «Финансы».
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'route'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Маршрут</h2>
                        <p class="text-sm text-zinc-500">Последовательность точек погрузки и выгрузки</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addRoutePoint('loading')">
                            Погрузка
                        </button>
                        <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addRoutePoint('unloading')">
                            Выгрузка
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    {{ routeChainLabel }}
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(point, index) in form.route_points"
                        :key="`point-${index}`"
                        draggable="true"
                        class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                        :class="[
                            draggedRoutePointIndex === index ? 'opacity-60 ring-2 ring-zinc-300 dark:ring-zinc-700' : '',
                            dragOverRoutePointIndex === index ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-200 dark:bg-zinc-800/60' : '',
                        ]"
                        @dragstart="handleRoutePointDragStart(index, $event)"
                        @dragover.prevent="handleRoutePointDragOver(index)"
                        @drop.prevent="handleRoutePointDrop(index)"
                        @dragend="handleRoutePointDragEnd"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">{{ stageLabel(point.stage) }}</div>
                                <div class="text-sm font-medium">
                                    {{ routePointTitle(point, index) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 cursor-grab items-center justify-center rounded-xl border border-zinc-200 text-zinc-400 dark:border-zinc-700 dark:text-zinc-500" title="Перетащить этап">
                                    ⋮⋮
                                </span>
                                <button type="button" class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.route_points, index)">
                                    Удалить
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div v-if="form.performers.length > 1" class="space-y-2">
                                <label class="text-sm font-medium">Плечо</label>
                                <select v-model="point.stage" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="performer in form.performers" :key="performer.stage" :value="performer.stage">
                                        {{ stageLabel(performer.stage) }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-sm font-medium">Адрес</label>
                                <div class="relative">
                                    <input
                                        v-model="point.address"
                                        type="text"
                                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                        placeholder="Начни вводить адрес"
                                        @input="queueAddressLookup(index)"
                                    />

                                    <div
                                        v-if="addressSuggestions[index]?.length"
                                        class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                    >
                                        <button
                                            v-for="suggestion in addressSuggestions[index]"
                                            :key="suggestion.value"
                                            type="button"
                                            class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                            @click="selectAddress(index, suggestion)"
                                        >
                                            <span class="text-sm font-medium">{{ suggestion.value }}</span>
                                            <span class="text-xs text-zinc-500">{{ suggestion.data?.region_with_type || suggestion.data?.region || '' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium">Плановая дата</label>
                                <input v-model="point.planned_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Фактическая дата</label>
                                <input v-model="point.actual_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Контактное лицо</label>
                                <input v-model="point.contact_person" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Телефон</label>
                                <input v-model="point.contact_phone" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                        </div>

                        <div v-if="point.type === 'loading'" class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Отправитель</label>
                                <input v-model="point.sender_name" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Контакт отправителя</label>
                                <input v-model="point.sender_contact" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-sm font-medium">Телефон отправителя</label>
                                <input v-model="point.sender_phone" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                        </div>

                        <div v-if="point.type === 'unloading'" class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Получатель</label>
                                <input v-model="point.recipient_name" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Контакт получателя</label>
                                <input v-model="point.recipient_contact" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-sm font-medium">Телефон получателя</label>
                                <input v-model="point.recipient_phone" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
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

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Наименование</label>
                                <input v-model="item.name" list="cargo-title-suggestions" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Тип груза</label>
                                <select v-model="item.cargo_type" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in cargoTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Описание</label>
                            <textarea v-model="item.description" rows="3" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Вес, кг</label>
                                <input v-model="item.weight_kg" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Объём, м³</label>
                                <input v-model="item.volume_m3" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Количество мест</label>
                                <input v-model="item.package_count" type="number" min="0" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Упаковка</label>
                                <select v-model="item.package_type" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option :value="null">Не выбрана</option>
                                    <option v-for="option in packageTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Код ТН ВЭД</label>
                                <input v-model="item.hs_code" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Класс опасности</label>
                                <input v-model="item.dangerous_class" :disabled="!item.dangerous_goods" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm disabled:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-950 dark:disabled:bg-zinc-800" />
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm">
                            <input v-model="item.dangerous_goods" type="checkbox" class="rounded border-zinc-300" />
                            Опасный груз
                        </label>
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
                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-sm font-medium">Цена клиента</label>
                                <input v-model="form.financial_term.client_price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Валюта</label>
                                <select v-model="form.financial_term.client_currency" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Форма оплаты</label>
                                <select v-model="form.financial_term.client_payment_form" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Условия оплаты клиента</label>
                                <p class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-300">
                                    {{ paymentScheduleSummary(form.financial_term.client_payment_schedule) }}
                                </p>
                            </div>
                        </div>
                        <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Срок, дней</label>
                                    <input v-model="form.financial_term.client_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Оплата по</label>
                                    <select v-model="form.financial_term.client_payment_schedule.postpayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <input v-model="form.financial_term.client_payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                    Предоплата
                                </label>
                            </div>
                            <div v-if="form.financial_term.client_payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Предоплата, %</label>
                                    <input v-model="form.financial_term.client_payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Срок предоплаты, дней</label>
                                    <input v-model="form.financial_term.client_payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Оплата по</label>
                                    <select v-model="form.financial_term.client_payment_schedule.prepayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Постоплата, %</label>
                                    <input :value="100 - Number(form.financial_term.client_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">KPI %</label>
                            <input :value="Number(form.financial_term.kpi_percent || 0).toFixed(2)" disabled type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            <p class="text-xs text-zinc-500">KPI рассчитывается автоматически по периоду и типу сделки после сохранения заказа.</p>
                        </div>
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
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold">{{ stageLabel(cost.stage) }}</div>
                                    <p class="text-xs text-zinc-500">Перевозчик и условия оплаты для этого плеча.</p>
                                </div>
                            </div>
                            <div class="grid gap-3 md:grid-cols-4">
                            <select v-model="cost.stage" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <option v-for="performer in form.performers" :key="performer.stage" :value="performer.stage">{{ stageLabel(performer.stage) }}</option>
                            </select>
                            <input
                                :value="carrierSearchValue('cost', index)"
                                type="text"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="Поиск перевозчика"
                                @focus="setCarrierResultsVisible('cost', index, true)"
                                @input="setCarrierSearchValue('cost', index, $event.target.value); setCarrierResultsVisible('cost', index, true)"
                            />
                            <div
                                v-if="isCarrierResultsVisible('cost', index) && filteredCarrierResults('cost', index).length > 0"
                                class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <button
                                    v-for="contractor in filteredCarrierResults('cost', index)"
                                    :key="contractor.id"
                                    type="button"
                                    class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                    @click="selectCostContractor(index, contractor)"
                                >
                                    <span class="text-sm font-medium">{{ contractor.name }}</span>
                                            <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                </button>
                            </div>
                            <select v-model="cost.contractor_id" class="hidden rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" @change="applyCarrierDefaultsByStage(cost.stage, cost.contractor_id); syncPerformerContractor(cost.stage, cost.contractor_id)">
                                <option :value="null">Исполнитель</option>
                                <option v-for="contractor in filteredCarrierResults('cost', index)" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option>
                            </select>
                            <input v-model="cost.amount" type="number" min="0" step="0.01" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="Сумма" />
                            <select v-model="cost.currency" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Форма оплаты</label>
                                    <select v-model="cost.payment_form" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Условия оплаты</label>
                                    <p class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-300">
                                        {{ paymentScheduleSummary(cost.payment_schedule) }}
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Срок, дней</label>
                                        <input v-model="cost.payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Оплата по</label>
                                        <select v-model="cost.payment_schedule.postpayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                            <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </div>
                                    <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <input v-model="cost.payment_schedule.has_prepayment" type="checkbox" class="rounded border-zinc-300" />
                                        Предоплата
                                    </label>
                                </div>
                                <div v-if="cost.payment_schedule.has_prepayment" class="grid gap-3 md:grid-cols-4">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Предоплата, %</label>
                                        <input v-model="cost.payment_schedule.prepayment_ratio" type="number" min="1" max="99" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Срок предоплаты, дней</label>
                                        <input v-model="cost.payment_schedule.prepayment_days" type="number" min="0" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Оплата по</label>
                                        <select v-model="cost.payment_schedule.prepayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                            <option v-for="option in paymentBasisOptions" :key="`${option.value}-${option.label}`" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Постоплата, %</label>
                                        <input :value="100 - Number(cost.payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Дополнительные затраты</h2>
                        <p class="text-xs text-zinc-500">Прочие расходы, не связанные с оплатой перевозчикам</p>
                    </div>
                    <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addAdditionalCost">
                        Добавить затрату
                    </button>
                </div>

                <div class="space-y-3">
                    <div v-for="(cost, index) in form.financial_term.additional_costs" :key="`additional-cost-${index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-sm font-medium">Наименование</label>
                                <input v-model="cost.label" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="Например: Страховка, Топливо, и т.д." />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Валюта</label>
                                <select v-model="cost.currency" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Сумма</label>
                                <input v-model="cost.amount" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.financial_term.additional_costs, index)">
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-800 md:grid-cols-4">
                <div>Цена клиента: <span class="font-medium">{{ financialSummary.clientPrice.toFixed(2) }}</span></div>
                <div>Себестоимость: <span class="font-medium">{{ financialSummary.totalCost.toFixed(2) }}</span></div>
                <div>Маржа: <span class="font-medium">{{ financialSummary.margin.toFixed(2) }}</span></div>
                <div>Доп. расходы: <span class="font-medium">{{ financialSummary.additionalCosts.toFixed(2) }}</span></div>
            </div>
            </div>

            <div v-else-if="activeTab === 'documents'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Документы</h2>
                        <p class="text-sm text-zinc-500">Тип, номер, дата, файл и статус документа</p>
                    </div>
                    <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addDocument">
                        Добавить документ
                    </button>
                </div>

                <div class="space-y-4">
                    <div v-for="(document, index) in form.documents" :key="`document-${index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium">Документ {{ index + 1 }}</div>
                            <button type="button" class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.documents, index)">
                                Удалить
                            </button>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Тип</label>
                                <select v-model="document.type" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Сторона</label>
                                <select v-model="document.party" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in documentPartyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Номер</label>
                                <input v-model="document.number" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Дата</label>
                                <input v-model="document.document_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Статус</label>
                                <select v-model="document.status" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in documentStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Файл</label>
                                <input type="file" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" @change="onDocumentFileChange(index, $event)" />
                                <p v-if="document.original_name" class="text-xs text-zinc-500">Текущий файл: {{ document.original_name }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Шаблон DOCX</label>
                                <div class="flex items-center gap-2">
                                    <select v-model="document.template_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option :value="null">Не выбран</option>
                                        <option v-for="template in printFormTemplateOptions" :key="template.id" :value="template.id">
                                            {{ templateOptionLabel(template) }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        :disabled="!isEditing || !order?.id || !document.template_id"
                                        @click="generateDocumentDraft(document)"
                                    >
                                        Скачать DOCX
                                    </button>
                                </div>
                                <p class="text-xs text-zinc-500">
                                    Доступны шаблоны, назначенные на контрагента заказа, и общие шаблоны по умолчанию.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="showCounterpartyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showCounterpartyModal = false">
            <div class="w-full max-w-xl rounded-3xl border border-zinc-200 bg-white p-5 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <div class="text-lg font-semibold">Новый контрагент</div>
                        <div class="text-sm text-zinc-500">Создаётся в справочнике и сразу подставляется в заказ</div>
                    </div>
                    <button type="button" class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="showCounterpartyModal = false">×</button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <input v-model="counterpartyForm.name" type="text" placeholder="Название" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 md:col-span-2" />
                    <input v-model="counterpartyForm.inn" type="text" placeholder="ИНН" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.kpp" type="text" placeholder="КПП" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.address" type="text" placeholder="Адрес" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 md:col-span-2" />
                    <input v-model="counterpartyForm.phone" type="text" placeholder="Телефон" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.email" type="email" placeholder="Email" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    <input v-model="counterpartyForm.contact_person" type="text" placeholder="Контактное лицо" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 md:col-span-2" />
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="showCounterpartyModal = false">
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
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ClipboardList, FileText, MapPinned, Package, Save, Wallet, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const props = defineProps({
    order: { type: Object, default: null },
    contractors: { type: Array, default: () => [] },
    ownCompanies: { type: Array, default: () => [] },
    cargoTypeOptions: { type: Array, default: () => [] },
    packageTypeOptions: { type: Array, default: () => [] },
    currencyOptions: { type: Array, default: () => [] },
    documentTypeOptions: { type: Array, default: () => [] },
    documentPartyOptions: { type: Array, default: () => [] },
    orderStatusOptions: { type: Array, default: () => [] },
    documentStatusOptions: { type: Array, default: () => [] },
    printFormTemplateOptions: { type: Array, default: () => [] },
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    currentUser: { type: Object, default: () => ({}) },
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
const contractors = ref([...props.contractors]);
const ownCompanyOptions = ref([...props.ownCompanies]);
const clientSearch = ref('');
const showClientResults = ref(false);
const carrierSearch = ref({});
const showCarrierResults = ref({});
const showCounterpartyModal = ref(false);
const inlineContractorSaving = ref(false);
const addressSuggestions = ref({});
const addressTimers = {};
const draggedRoutePointIndex = ref(null);
const dragOverRoutePointIndex = ref(null);
const paymentFormOptions = [
    { value: 'vat', label: 'С НДС' },
    { value: 'no_vat', label: 'Без НДС' },
    { value: 'cash', label: 'Нал' },
];
const clientRequestModeOptions = [
    { value: 'single_request', label: 'Одна заявка', description: 'Все плечи включаются в одну клиентскую заявку.' },
    { value: 'split_by_leg', label: 'Разбить по плечам', description: 'Для каждого плеча оформляется отдельная клиентская заявка.' },
];
const paymentBasisOptions = [
    { value: 'fttn', label: 'ФТТН' },
    { value: 'ottn', label: 'ОТТН' },
    { value: 'fttn', label: 'На загрузке' },
    { value: 'ottn', label: 'На выгрузке' },
];

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

function templateOptionLabel(template) {
    const suffix = [];

    if (template.contractor_name) {
        suffix.push(template.contractor_name);
    }

    if (template.is_default) {
        suffix.push('по умолчанию');
    }

    return suffix.length > 0 ? `${template.name} (${suffix.join(', ')})` : template.name;
}

function normalizeDocument(document = {}) {
    return {
        type: 'request',
        number: '',
        document_date: '',
        status: 'draft',
        template_id: null,
        file: null,
        original_name: '',
        generated_pdf_path: null,
        party: 'internal',
        requirement_key: null,
        ...document,
    };
}

function generateDocumentDraft(document) {
    if (!props.order?.id || !document?.template_id) {
        return;
    }

    window.location.href = route('orders.templates.generate-draft', {
        order: props.order.id,
        printFormTemplate: document.template_id,
    });
}

function blankPaymentSchedule() {
    return {
        has_prepayment: false,
        prepayment_ratio: 50,
        prepayment_days: 0,
        prepayment_mode: 'fttn',
        postpayment_days: 0,
        postpayment_mode: 'ottn',
    };
}

function normalizePaymentSchedule(schedule = {}) {
    const raw = schedule?.has_prepayment;
    const hasPrepayment = raw === true || raw === 1 || raw === '1';

    return {
        ...blankPaymentSchedule(),
        ...schedule,
        has_prepayment: hasPrepayment,
    };
}

function normalizeContractorCost(cost = {}) {
    return {
        stage: '',
        contractor_id: null,
        amount: null,
        currency: 'RUB',
        payment_form: 'no_vat',
        payment_schedule: blankPaymentSchedule(),
        ...cost,
        payment_schedule: normalizePaymentSchedule(cost.payment_schedule),
    };
}

function blankRoutePoint(type, sequence, stage) {
    return {
        stage,
        type,
        sequence,
        address: '',
        normalized_data: {},
        planned_date: '',
        actual_date: '',
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
        own_company_id: null,
        client_id: null,
        order_date: new Date().toISOString().slice(0, 10),
        order_number: '',
        payment_terms: '',
        special_notes: '',
        cargo_sender_name: '',
        cargo_sender_address: '',
        cargo_sender_contact: '',
        cargo_sender_phone: '',
        cargo_recipient_name: '',
        cargo_recipient_address: '',
        cargo_recipient_contact: '',
        cargo_recipient_phone: '',
        performers: [
            { stage: stageLabel('leg_1'), contractor_id: null },
        ],
        route_points: [
            blankRoutePoint('loading', 1, stageLabel('leg_1')),
            blankRoutePoint('unloading', 2, stageLabel('leg_1')),
        ],
        cargo_items: [
            { name: '', description: '', weight_kg: null, volume_m3: null, package_type: null, package_count: null, dangerous_goods: false, dangerous_class: '', hs_code: '', cargo_type: 'general' },
        ],
        financial_term: {
            client_price: null,
            client_currency: 'RUB',
            client_payment_form: 'vat',
            client_request_mode: 'single_request',
            client_payment_schedule: blankPaymentSchedule(),
            contractors_costs: [],
            additional_costs: [],
            kpi_percent: 0,
        },
        documents: [],
    };
}

const form = useForm({
    ...blankOrder(),
    ...(props.order ?? {}),
    performers: Array.isArray(props.order?.performers)
        ? props.order.performers.map((performer) => ({
            stage: stageLabel(performer.stage ?? 'leg_1'),
            contractor_id: performer.contractor_id ?? null,
        }))
        : blankOrder().performers,
    route_points: Array.isArray(props.order?.route_points)
        ? props.order.route_points.map((point, index) => ({
            ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), stageLabel(point.stage ?? 'leg_1')),
            ...point,
            stage: stageLabel(point.stage ?? 'leg_1'),
            sequence: Number(point.sequence ?? (index + 1)),
        }))
        : blankOrder().route_points,
    financial_term: {
        ...blankOrder().financial_term,
        ...(props.order?.financial_term ?? {}),
        client_payment_schedule: normalizePaymentSchedule(props.order?.financial_term?.client_payment_schedule),
        contractors_costs: Array.isArray(props.order?.financial_term?.contractors_costs)
            ? props.order.financial_term.contractors_costs.map((cost) => normalizeContractorCost(cost))
            : [],
    },
    documents: Array.isArray(props.order?.documents)
        ? props.order.documents.map((document) => normalizeDocument(document))
        : [],
});

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
                additional_expenses: form.financial_term.additional_costs.reduce((sum, cost) => sum + Number(cost.amount || 0), 0),
                insurance: 0, // TODO: add insurance field if needed
                bonus: 0, // TODO: add bonus field if needed
                manager_id: props.currentUser?.id,
                order_date: form.order_date,
                client_id: form.client_id,
                carrier_id: form.performers.find(p => p.contractor_id)?.contractor_id,
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
        () => form.financial_term.additional_costs,
        () => form.order_date,
        () => form.client_id,
        () => form.performers,
    ],
    () => {
        calculateCompensation();
    },
    { deep: true, immediate: false },
);

const isEditing = computed(() => props.order !== null);
const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});
const selectedClient = computed(() => contractors.value.find((contractor) => contractor.id === form.client_id) ?? null);
const carrierOptions = computed(() => contractors.value.filter((contractor) => contractor.type === 'carrier' || contractor.type === 'both'));
const customerDebtBlocked = computed(() => !isEditing.value && Boolean(selectedClient.value?.debt_limit_reached));
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

const filteredClients = computed(() => {
    const query = clientSearch.value.trim().toLowerCase();

    if (query === '') {
        return contractors.value.slice(0, 50); // Увеличено с 8 до 50
    }

    return contractors.value
        .filter((contractor) => [contractor.name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .slice(0, 50); // Увеличено с 8 до 50
});

if (selectedClient.value) {
    clientSearch.value = selectedClient.value.name;
}

function selectClient(contractor) {
    form.client_id = contractor.id;
    clientSearch.value = contractor.name;
    showClientResults.value = false;
    applyClientDefaults(contractor);
}

function addPerformer() {
    const stage = stageLabel(`leg_${form.performers.length + 1}`);

    form.performers.push({
        stage,
        contractor_id: null,
    });
    syncRoutePointsFromPerformers();
}

function removePerformer(index) {
    const performer = form.performers[index];

    if (!performer) {
        return;
    }

    form.performers.splice(index, 1);
    form.route_points = form.route_points.filter((point) => !stageMatches(point.stage, performer.stage));
    normalizeRoutePointSequences();
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

function getContractorById(contractorId) {
    return contractors.value.find((contractor) => contractor.id === contractorId) ?? null;
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
        ? form.performers[index]?.contractor_id
        : form.financial_term.contractors_costs[index]?.contractor_id;
    const selectedContractor = getContractorById(selectedContractorId);

    if (query === '') {
        const visibleContractors = carrierOptions.value.slice(0, 50); // Увеличено с 8 до 50

        if (!selectedContractor || visibleContractors.some((contractor) => contractor.id === selectedContractor.id)) {
            return visibleContractors;
        }

        return [selectedContractor, ...visibleContractors.slice(0, 49)]; // Увеличено с 7 до 49
    }

    return carrierOptions.value
        .filter((contractor) => [contractor.name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .slice(0, 50); // Увеличено с 8 до 50
}

function parsePaymentTermPreset(term) {
    if (!term) {
        return blankPaymentSchedule();
    }

    const normalized = String(term).trim().toUpperCase();
    const prepaymentMatch = normalized.match(/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+ДН\s+(FTTN|OTTN)\s*\/\s*(\d+)\s+ДН\s+(FTTN|OTTN)$/u);

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

    const postpaymentMatch = normalized.match(/^(\d+)\s+ДН\s+(FTTN|OTTN)$/u);

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
        form.financial_term.client_payment_form = contractor.default_customer_payment_form;
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

    const costRow = form.financial_term.contractors_costs.find((row) => row.stage === stage);

    if (!costRow) {
        return;
    }

    if (contractor.default_carrier_payment_form) {
        costRow.payment_form = contractor.default_carrier_payment_form;
    }

    costRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
}

function selectPerformerContractor(index, contractor) {
    form.performers[index].contractor_id = contractor.id;
    setCarrierSearchValue('performer', index, contractor.name);
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[index].stage, contractor.id);
}

function syncPerformerContractor(stage, contractorId) {
    const performer = form.performers.find((item) => item.stage === stage);

    if (!performer) {
        return;
    }

    performer.contractor_id = contractorId;
}

function selectCostContractor(index, contractor) {
    form.financial_term.contractors_costs[index].contractor_id = contractor.id;
    setCarrierSearchValue('cost', index, contractor.name);
    setCarrierResultsVisible('cost', index, false);
    syncPerformerContractor(form.financial_term.contractors_costs[index].stage, contractor.id);
    applyCarrierDefaultsByStage(form.financial_term.contractors_costs[index].stage, contractor.id);
}

const routeChainLabel = computed(() => {
    if (form.route_points.length === 0) {
        return 'Маршрут пока не задан';
    }

    return form.route_points
        .slice()
        .sort((left, right) => Number(left.sequence ?? 0) - Number(right.sequence ?? 0))
        .map((point) => `${point.type === 'loading' ? 'Погрузка' : 'Выгрузка'}: ${point.address || 'адрес не указан'}`)
        .join(' → ');
});

const cargoSummary = computed(() => {
    return form.cargo_items.reduce((summary, item) => {
        summary.totalWeight += Number(item.weight_kg || 0);
        summary.totalVolume += Number(item.volume_m3 || 0);
        summary.totalPackages += Number(item.package_count || 0);

        return summary;
    }, {
        totalWeight: 0,
        totalVolume: 0,
        totalPackages: 0,
    });
});

const financialSummary = computed(() => {
    const clientPrice = Number(form.financial_term.client_price || 0);
    const contractorCosts = form.financial_term.contractors_costs.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const additionalCosts = form.financial_term.additional_costs.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const totalCost = contractorCosts + additionalCosts;
    const margin = clientPrice * (1 - (Number(form.financial_term.kpi_percent || 0) / 100)) - totalCost;

    return {
        clientPrice,
        contractorCosts,
        additionalCosts,
        totalCost,
        margin,
    };
});

const financialMarginPercent = computed(() => {
    const clientPrice = Number(form.financial_term.client_price || 0);

    if (clientPrice <= 0) {
        return 0;
    }

    return (financialSummary.value.margin / clientPrice) * 100;
});

const documentChecklist = computed(() => {
    const documents = Array.isArray(form.documents) ? form.documents : [];

    return props.requiredDocumentRules.map((rule) => {
        const matchedDocument = documents.find((document) => {
            return Array.isArray(rule.accepted_types)
                && rule.accepted_types.includes(document.type)
                && String(document.party ?? 'internal') === rule.party;
        });

        return {
            ...rule,
            completed: matchedDocument !== undefined,
            matched_document_id: matchedDocument?.id ?? null,
        };
    });
});

const completedRequiredDocumentsCount = computed(() => {
    return documentChecklist.value.filter((item) => item.completed).length;
});

const missingRequiredDocumentsSummary = computed(() => {
    const missingItems = documentChecklist.value.filter((item) => !item.completed);

    if (missingItems.length === 0) {
        return '';
    }

    const groupedItems = missingItems.reduce((carry, item) => {
        const key = item.party;

        if (!carry[key]) {
            carry[key] = [];
        }

        carry[key].push(item.label);

        return carry;
    }, {});

    const summary = Object.entries(groupedItems)
        .map(([party, labels]) => `${partyLabel(party)}: ${labels.join(', ')}`)
        .join('; ');

    return `Не хватает документов. ${summary}`;
});

function addRoutePoint(type) {
    form.route_points.push(blankRoutePoint(
        type,
        form.route_points.length + 1,
        form.performers[0]?.stage ?? stageLabel('leg_1'),
    ));
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

    return point.type === 'loading'
        ? `Погрузка ${ordinal}`
        : `Выгрузка ${ordinal}`;
}

function handleRoutePointDragStart(index, event) {
    draggedRoutePointIndex.value = index;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
}

function handleRoutePointDragOver(index) {
    if (draggedRoutePointIndex.value === null || draggedRoutePointIndex.value === index) {
        return;
    }

    dragOverRoutePointIndex.value = index;
}

function handleRoutePointDrop(targetIndex) {
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
    draggedRoutePointIndex.value = null;
    dragOverRoutePointIndex.value = null;
}

function addCargoItem() {
    form.cargo_items.push({
        name: '',
        description: '',
        weight_kg: null,
        volume_m3: null,
        package_type: null,
        package_count: null,
        dangerous_goods: false,
        dangerous_class: '',
        hs_code: '',
        cargo_type: 'general',
    });
}

function addAdditionalCost() {
    form.financial_term.additional_costs.push({
        label: '',
        amount: null,
        currency: 'RUB',
    });
}

function addDocument() {
    form.documents.push(normalizeDocument());
}

function removeItem(collection, index) {
    collection.splice(index, 1);

    if (collection === form.route_points) {
        normalizeRoutePointSequences();
    }
}

function syncContractorCostsFromPerformers() {
    const existingRows = Array.isArray(form.financial_term.contractors_costs)
        ? form.financial_term.contractors_costs
        : [];

    form.financial_term.contractors_costs = form.performers.map((performer) => {
        const existingRow = existingRows.find((row) => row.stage === performer.stage);

        const nextRow = normalizeContractorCost({
            ...existingRow,
            stage: performer.stage,
            contractor_id: performer.contractor_id,
        });

        if (!existingRow && performer.contractor_id) {
            const contractor = getContractorById(performer.contractor_id);

            if (contractor?.default_carrier_payment_form) {
                nextRow.payment_form = contractor.default_carrier_payment_form;
            }

            nextRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
        }

        return nextRow;
    });
}

watch(
    () => form.performers,
    () => {
        syncContractorCostsFromPerformers();
        syncRoutePointsFromPerformers();
    },
    { deep: true },
);

watch(
    () => form.performers.map((performer) => [performer.stage, performer.contractor_id]),
    (performers) => {
        performers.forEach(([stage, contractorId], index) => {
            const contractor = getContractorById(contractorId);

            setCarrierSearchValue('performer', index, contractor?.name ?? '');
            const costIndex = form.financial_term.contractors_costs.findIndex((row) => row.stage === stage);

            if (costIndex !== -1) {
                setCarrierSearchValue('cost', costIndex, contractor?.name ?? '');
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

function onDocumentFileChange(index, event) {
    form.documents[index].file = event.target.files?.[0] ?? null;
}

function documentTypeLabel(type) {
    return props.documentTypeOptions.find((option) => option.value === type)?.label ?? type;
}

function partyLabel(party) {
    return props.documentPartyOptions.find((option) => option.value === party)?.label ?? party;
}

function documentRequirementLabel(key) {
    return props.requiredDocumentRules.find((rule) => rule.key === key)?.label ?? '';
}

function paymentFormLabel(value) {
    return paymentFormOptions.find((option) => option.value === value)?.label ?? value;
}

function paymentBasisLabel(value) {
    return paymentBasisOptions.find((option) => option.value === value)?.label ?? value;
}

function paymentScheduleSummary(schedule) {
    const normalized = normalizePaymentSchedule(schedule);
    const postpaymentPart = `${normalized.has_prepayment ? 100 - Number(normalized.prepayment_ratio || 0) : 100}% ${normalized.postpayment_days || 0} дн ${paymentBasisLabel(normalized.postpayment_mode)}`;

    if (!normalized.has_prepayment) {
        return postpaymentPart;
    }

    return `${normalized.prepayment_ratio || 0}% ${normalized.prepayment_days || 0} дн ${paymentBasisLabel(normalized.prepayment_mode)} / ${postpaymentPart}`;
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
        selectClient(contractor);
        counterpartyForm.reset();
        showCounterpartyModal.value = false;
    } catch (error) {
        console.error(error);
    } finally {
        inlineContractorSaving.value = false;
    }
}

function submit() {
    syncContractorCostsFromPerformers();

    if (isEditing.value) {
        form.patch(route('orders.update', props.order.id), {
            forceFormData: true,
            preserveScroll: true,
        });

        return;
    }

    form.post(route('orders.store'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function goBack() {
    router.get(route('orders.index'));
}

</script>
