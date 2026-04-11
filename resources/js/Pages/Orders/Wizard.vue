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
                        <input v-model="form.order_date" type="date" :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('order_date', form.order_date)]" />
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
            </div>

            <div v-else-if="activeTab === 'route'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Маршрут</h2>
                        <p class="text-sm text-zinc-500">Этапы маршрута, точки погрузки и выгрузки</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addPerformer">
                            Добавить плечо
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    {{ routeChainLabel }}
                </div>

                <div class="space-y-6">
                    <div
                        v-for="(performer, legIndex) in form.performers"
                        :key="`leg-route-${legIndex}`"
                        class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div class="flex items-start justify-between gap-3 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Плечо</div>
                                <div class="text-base font-semibold">{{ stageLabel(performer.stage) }}</div>
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
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-zinc-500">
                                        {{ item.point.type === 'loading' ? 'Погрузка' : 'Выгрузка' }}
                                    </div>
                                    <div class="text-sm font-medium">
                                        {{ routePointTitle(item.point, item.globalIndex) }}
                                    </div>
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

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium">Адрес</label>
                                    <div class="relative">
                                        <input
                                            v-model="item.point.address"
                                            type="text"
                                            :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('route_point_address_' + item.globalIndex, item.point.address)]"
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
                                    <input v-model="item.point.planned_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Фактическая дата</label>
                                    <input v-model="item.point.actual_date" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контактное лицо</label>
                                    <input v-model="item.point.contact_person" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Телефон</label>
                                    <input v-model="item.point.contact_phone" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                            </div>

                            <div v-if="item.point.type === 'loading'" class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Отправитель</label>
                                    <input v-model="item.point.sender_name" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контакт отправителя</label>
                                    <input v-model="item.point.sender_contact" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium">Телефон отправителя</label>
                                    <input v-model="item.point.sender_phone" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                            </div>

                            <div v-if="item.point.type === 'unloading'" class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Получатель</label>
                                    <input v-model="item.point.recipient_name" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Контакт получателя</label>
                                    <input v-model="item.point.recipient_contact" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium">Телефон получателя</label>
                                    <input v-model="item.point.recipient_phone" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
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

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Наименование</label>
                                <input v-model="item.name" list="cargo-title-suggestions" type="text" :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('cargo_name_' + index, item.name)]" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Тип груза</label>
                                <select v-model="item.cargo_type" :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('cargo_type_' + index, item.cargo_type)]">
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
                                <select v-model="form.financial_term.client_currency" :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('client_currency', form.financial_term.client_currency, form.financial_term.client_price)]">
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
                            <div class="relative">
                                <input
                                    :value="carrierSearchValue('cost', index)"
                                    type="text"
                                    class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 pr-10 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    placeholder="Поиск перевозчика"
                                    @focus="setCarrierResultsVisible('cost', index, true)"
                                    @input="onCostCarrierInput(index, $event.target.value)"
                                    @blur="restoreCostCarrierSearch(index)"
                                />
                                <button
                                    v-if="form.financial_term.contractors_costs[index]?.contractor_id !== null"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                    title="Очистить перевозчика"
                                    @click="clearCostContractor(index)"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                                <div
                                    v-if="isCarrierResultsVisible('cost', index) && filteredCarrierResults('cost', index).length > 0"
                                    class="absolute left-0 top-full z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
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
                        <p class="text-sm text-zinc-500">Общий блок заказчика + отдельные блоки по каждому плечу перевозки</p>
                    </div>
                </div>

                <div
                    v-if="documentChecklist.length > 0"
                    class="rounded-2xl border border-amber-200/80 bg-amber-50/50 p-4 text-sm dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <div class="font-medium text-amber-950 dark:text-amber-100">Обязательные документы для этапов «Оплата» и «Закрыта»</div>
                    <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/80">
                        Пока не выполнены все пункты, после выгрузки статус заказа останется «Документы». Для загружаемых файлов — прикрепите файл и поставьте статус «Отправлен» или «Подписан». Для заявки из шаблона — завершите цепочку печатной формы (финальный PDF и подписи по шаблону).
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
                    v-if="page.props.flash?.message"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                    role="status"
                >
                    {{ page.props.flash.message }}
                </div>

                <div
                    v-if="!order?.id"
                    class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 text-sm dark:border-emerald-900/60 dark:bg-emerald-950/25"
                >
                    <h3 class="font-semibold text-emerald-950 dark:text-emerald-100">Создать документ из шаблона (заявка)</h3>
                    <p class="mt-1 text-xs text-emerald-900/80 dark:text-emerald-200/80">
                        Печатная форма привязана к заказу в базе. Сохраните заказ — на этой вкладке появится выбор шаблона и кнопка «Создать в карточке» (черновик DOCX и цепочка согласования).
                    </p>
                </div>

                <div
                    v-if="order?.id"
                    class="space-y-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/25"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">Заявка и согласование (печатная форма)</h3>
                        <p class="text-xs text-emerald-900/80 dark:text-emerald-200/80">
                            Цепочка: черновик DOCX → согласование руководителем → печать/подпись у нас → загрузка финального PDF. Если по шаблону нужна подпись клиента, после PDF приложите скан в «Документы заказчика».
                        </p>
                    </div>

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
                            :disabled="!workflowTemplateId || !isEditing"
                            @click="createPersistedPrintWorkflowDocument"
                        >
                            Создать в карточке
                        </button>
                    </div>

                    <div v-if="printWorkflowDocuments.length === 0" class="rounded-xl border border-dashed border-emerald-300/80 px-3 py-3 text-sm text-emerald-900/70 dark:border-emerald-800 dark:text-emerald-200/70">
                        Пока нет заявок по этому процессу.
                    </div>

                    <div v-for="doc in printWorkflowDocuments" :key="`print-wf-${doc.id}`" class="space-y-3 rounded-xl border border-zinc-200 bg-white/80 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="text-sm font-medium">
                                {{ doc.original_name || 'Документ' }}
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
                                    Скачать финальный PDF
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
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="doc.can_request_approval"
                                type="button"
                                class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                :disabled="!isEditing"
                                @click="postWorkflowAction('request-approval', doc.id)"
                            >
                                Отправить на согласование
                            </button>
                            <button
                                v-if="doc.can_regenerate_draft"
                                type="button"
                                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                                :disabled="!isEditing"
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
                                Согласовать
                            </button>
                            <button
                                v-if="doc.can_reject"
                                type="button"
                                class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                @click="toggleWorkflowReject(doc.id)"
                            >
                                Отклонить
                            </button>
                            <label
                                v-if="doc.can_finalize"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                            >
                                <span>Загрузить финальный PDF</span>
                                <input type="file" accept="application/pdf" class="hidden" @change="finalizeWorkflowPdf(doc, $event)" />
                            </label>
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
                </div>

                <div class="space-y-4">
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
                                    :disabled="!isEditing || !order?.id || !item.document.template_id"
                                    @click="previewDocumentDraft(item.document)"
                                >
                                    Предпросмотр
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    :disabled="!isEditing || !order?.id || !item.document.template_id"
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

                    <div v-for="(performer, performerIndex) in form.performers" :key="`carrier-doc-stage-${performerIndex}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold">Документы перевозчика — {{ stageLabel(performer.stage) }}</div>
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
                                    :disabled="!isEditing || !order?.id || !item.document.template_id"
                                    @click="previewDocumentDraft(item.document)"
                                >
                                    Предпросмотр
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                    :disabled="!isEditing || !order?.id || !item.document.template_id"
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
import { computed, nextTick, onMounted, ref, toRaw, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ClipboardList, FileText, MapPinned, Package, Paperclip, Save, Wallet, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

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
    currencyOptions: { type: Array, default: () => [] },
    documentTypeOptions: { type: Array, default: () => [] },
    documentPartyOptions: { type: Array, default: () => [] },
    orderStatusOptions: { type: Array, default: () => [] },
    documentStatusOptions: { type: Array, default: () => [] },
    printFormTemplateOptions: { type: Array, default: () => [] },
    orderDocumentWorkflow: { type: Object, default: () => ({ status_options: [] }) },
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

function finalizeWorkflowPdf(doc, event) {
    const target = event.target;
    const file = target?.files?.[0];

    if (!file || !props.order?.id) {
        return;
    }

    const formData = new FormData();
    formData.append('pdf', file);

    router.post(route('orders.documents.finalize', [props.order.id, doc.id]), formData, {
        forceFormData: true,
        preserveScroll: true,
    });

    target.value = '';
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

/** Коды vat/no_vat/cash — в данных заказа иногда приходят человекочитаемые подписи; без этого <select> не совпадает с option и кажется, что «сохраняется старое». */
function normalizePaymentFormCode(value, fallback = 'vat') {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }
    if (['vat', 'no_vat', 'cash'].includes(value)) {
        return value;
    }
    const lower = String(value).trim().toLowerCase();
    if (lower.includes('без') && lower.includes('ндс')) {
        return 'no_vat';
    }
    if (lower.includes('нал')) {
        return 'cash';
    }
    if (lower.includes('ндс')) {
        return 'vat';
    }

    return fallback;
}
const clientRequestModeOptions = [
    { value: 'single_request', label: 'Одна заявка', description: 'Все плечи включаются в одну клиентскую заявку.' },
    { value: 'split_by_leg', label: 'Разбить по плечам', description: 'Для каждого плеча оформляется отдельная клиентская заявка.' },
];
const paymentBasisOptions = [
    { value: 'fttn', label: 'ФТТН' },
    { value: 'ottn', label: 'ОТТН' },
    { value: 'loading', label: 'На загрузке' },
    { value: 'unloading', label: 'На выгрузке' },
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
    const merged = {
        stage: '',
        contractor_id: null,
        amount: null,
        currency: 'RUB',
        payment_form: 'no_vat',
        payment_schedule: blankPaymentSchedule(),
        ...cost,
        payment_schedule: normalizePaymentSchedule(cost.payment_schedule),
    };
    merged.payment_form = normalizePaymentFormCode(merged.payment_form, 'no_vat');

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

const form = useForm({
    ...blankOrder(),
    ...(props.order ?? {}),
    own_company_id: normalizeNullableNumber(props.order?.own_company_id),
    client_id: normalizeNullableNumber(props.order?.client_id),
    additional_expenses: props.order?.additional_expenses ?? null,
    insurance: props.order?.insurance ?? null,
    bonus: props.order?.bonus ?? null,
    performers: Array.isArray(props.order?.performers)
        ? props.order.performers.map((performer) => ({
            stage: stageLabel(performer.stage ?? 'leg_1'),
            contractor_id: normalizeNullableNumber(performer.contractor_id),
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
                additional_expenses: Number(form.additional_expenses || 0),
                insurance: Number(form.insurance || 0),
                bonus: Number(form.bonus || 0),
                manager_id: props.order?.responsible_id ?? props.currentUser?.id,
                order_date: form.order_date,
                customer_payment_form: normalizePaymentFormCode(form.financial_term.client_payment_form, 'vat'),
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

// Проверка обязательных полей
const requiredFieldsValid = computed(() => {
    // Основные обязательные поля
    const hasClient = !!form.client_id;
    const hasOrderDate = !!form.order_date;
    const hasStatus = !!form.status;
    
    // Обязательные поля в performers
    const performersValid = form.performers.every(performer => !!performer.stage);
    
    // Обязательные поля в route_points
    const routePointsValid = form.route_points.every(point => {
        return !!point.type && !!point.address;
    });
    
    // Обязательные поля в cargo_items
    const cargoItemsValid = form.cargo_items.every(item => {
        return !!item.name && item.dangerous_goods !== undefined && !!item.cargo_type;
    });
    
    // Обязательные поля в financial_term (если financial_term заполнен)
    const financialTermValid = !form.financial_term.client_price || !!form.financial_term.client_currency;
    
    return hasClient && hasOrderDate && hasStatus && performersValid && routePointsValid && cargoItemsValid && financialTermValid;
});

// Подсветка для конкретных полей
const highlightRequiredField = (fieldName, value, conditionValue = null) => {
    // Для поля client_currency проверяем, заполнена ли цена клиента
    if (fieldName === 'client_currency') {
        if (conditionValue && (!value || value === '' || value === null)) {
            return 'border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/30';
        }
        return 'border-zinc-200 dark:border-zinc-700';
    }
    
    // Для остальных полей
    if (!value || value === '' || value === null) {
        return 'border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/30';
    }
    return 'border-zinc-200 dark:border-zinc-700';
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
        form.performers = [{ stage: stageLabel('leg_1'), contractor_id: null }];
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
    const prepaymentMatch = normalized.match(/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+ДН\s+(FTTN|OTTN|LOADING|UNLOADING)\s*\/\s*(\d+)\s+ДН\s+(FTTN|OTTN|LOADING|UNLOADING)$/u);

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

    const postpaymentMatch = normalized.match(/^(\d+)\s+ДН\s+(FTTN|OTTN|LOADING|UNLOADING)$/u);

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
        form.financial_term.client_payment_form = normalizePaymentFormCode(contractor.default_customer_payment_form, 'vat');
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
    };
    form.performers = updatedPerformers;

    setCarrierSearchValue('performer', index, contractor.name);
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
    applyCarrierDefaultsByStage(form.performers[index].stage, contractor.id);
}

function clearPerformerContractor(index) {
    // Создаем новый массив performers с очищенным contractor_id
    const updatedPerformers = [...form.performers];
    updatedPerformers[index] = {
        ...updatedPerformers[index],
        contractor_id: null
    };
    form.performers = updatedPerformers;
    
    setCarrierSearchValue('performer', index, '');
    setCarrierResultsVisible('performer', index, false);
    syncContractorCostsFromPerformers();
}

function syncPerformerContractor(stage, contractorId) {
    const performer = form.performers.find((item) => stageMatches(item.stage, stage));

    if (!performer) {
        return;
    }

    performer.contractor_id = contractorId !== null ? Number(contractorId) : null;
}

function selectCostContractor(index, contractor) {
    ensureContractorInLocalList(contractor);

    form.financial_term.contractors_costs[index].contractor_id = Number(contractor.id);
    setCarrierSearchValue('cost', index, contractor.name);
    setCarrierResultsVisible('cost', index, false);
    syncPerformerContractor(form.financial_term.contractors_costs[index].stage, Number(contractor.id));
    applyCarrierDefaultsByStage(form.financial_term.contractors_costs[index].stage, Number(contractor.id));
}

function clearCostContractor(index) {
    const cost = form.financial_term.contractors_costs[index];

    if (!cost) {
        return;
    }

    cost.contractor_id = null;
    setCarrierSearchValue('cost', index, '');
    setCarrierResultsVisible('cost', index, false);
    syncPerformerContractor(cost.stage, null);
}

function onCostCarrierInput(index, value) {
    setCarrierSearchValue('cost', index, value);
    setCarrierResultsVisible('cost', index, true);

    const cost = form.financial_term.contractors_costs[index];
    if (!cost) {
        return;
    }

    const typed = String(value ?? '').trim().toLowerCase();
    const selectedContractor = getContractorById(cost.contractor_id);
    const selectedName = String(selectedContractor?.name ?? '').trim().toLowerCase();

    if (typed === '') {
        clearCostContractor(index);
        return;
    }

    // Если пользователь начал вводить другое имя, снимаем текущее привязанное contractor_id.
    if (cost.contractor_id !== null && selectedName !== '' && selectedName !== typed) {
        cost.contractor_id = null;
        syncPerformerContractor(cost.stage, null);
    }
}

function restoreCostCarrierSearch(index) {
    window.setTimeout(() => {
        const cost = form.financial_term.contractors_costs[index];
        if (!cost) {
            return;
        }

        const selectedContractor = getContractorById(cost.contractor_id);
        setCarrierSearchValue('cost', index, selectedContractor?.name ?? '');
        setCarrierResultsVisible('cost', index, false);
    }, 120);
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
    const additionalCosts =
        Number(form.additional_expenses || 0)
        + Number(form.insurance || 0)
        + Number(form.bonus || 0);
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

watch(
    () => form.performers.map((performer) => [performer.stage, performer.contractor_id]),
    (performers) => {
        performers.forEach(([stage, contractorId], index) => {
            const contractor = getContractorById(contractorId);

            setCarrierSearchValue('performer', index, contractor?.name ?? '');
            const costIndex = form.financial_term.contractors_costs.findIndex((row) => stageMatches(row.stage, stage));

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

function buildSubmitPayload() {
    // Снимок без Vue/Inertia proxy: иначе вложенные суммы иногда уходят в запросе «как при загрузке».
    const rawFinancial = JSON.parse(JSON.stringify(toRaw(form.financial_term)));

    return {
        // Basic order fields
        status: form.status,
        own_company_id: form.own_company_id,
        client_id: form.client_id,
        order_date: form.order_date,
        order_number: form.order_number,
        payment_terms: form.payment_terms,
        special_notes: form.special_notes,
        additional_expenses: form.additional_expenses,
        insurance: form.insurance,
        bonus: form.bonus,

        // Performers array (the server expects this field)
        performers: form.performers.map((performer) => ({
            stage: performer.stage,
            contractor_id: normalizeNullableNumber(performer.contractor_id),
        })),

        // Route points
        route_points: form.route_points.map((point) => ({
            stage: point.stage,
            type: point.type,
            sequence: point.sequence,
            address: point.address,
            normalized_data: point.normalized_data || {},
            planned_date: point.planned_date,
            actual_date: point.actual_date,
            contact_person: point.contact_person,
            contact_phone: point.contact_phone,
            sender_name: point.sender_name,
            sender_contact: point.sender_contact,
            sender_phone: point.sender_phone,
            recipient_name: point.recipient_name,
            recipient_contact: point.recipient_contact,
            recipient_phone: point.recipient_phone,
        })),

        // Cargo items
        cargo_items: form.cargo_items.map((item) => ({
            name: item.name,
            description: item.description,
            weight_kg: item.weight_kg,
            volume_m3: item.volume_m3,
            package_type: item.package_type,
            package_count: item.package_count,
            dangerous_goods: item.dangerous_goods,
            dangerous_class: item.dangerous_class,
            hs_code: item.hs_code,
            cargo_type: item.cargo_type,
        })),

        // Financial term
        financial_term: {
            client_price: rawFinancial.client_price,
            client_currency: rawFinancial.client_currency,
            client_payment_form: normalizePaymentFormCode(rawFinancial.client_payment_form, 'vat'),
            client_request_mode: rawFinancial.client_request_mode,
            client_payment_schedule: rawFinancial.client_payment_schedule || {},
            contractors_costs: (rawFinancial.contractors_costs || []).map((cost) => ({
                stage: cost.stage,
                contractor_id: normalizeNullableNumber(cost.contractor_id),
                amount: cost.amount,
                currency: cost.currency || 'RUB',
                payment_form: normalizePaymentFormCode(cost.payment_form, 'no_vat'),
                payment_schedule: cost.payment_schedule || {},
            })),
            additional_costs: [],
            kpi_percent: rawFinancial.kpi_percent,
        },

        // Documents
        documents: form.documents.map((document) => ({
            type: document.type,
            flow: document.flow,
            party: document.party,
            stage: document.stage,
            requirement_key: document.requirement_key,
            number: document.number,
            document_date: document.document_date,
            status: document.status,
            template_id: document.template_id,
            file: document.file instanceof File ? document.file : null,
            original_name: document.original_name,
            generated_pdf_path: document.generated_pdf_path,
        })),
    };
}

function submit() {
    const costsByStage = new Map(
        form.financial_term.contractors_costs.map((cost) => [toStageKey(cost.stage), cost]),
    );
    form.performers = form.performers.map((performer) => {
        const syncedCost = costsByStage.get(toStageKey(performer.stage));

        return {
            ...performer,
            contractor_id: syncedCost?.contractor_id ?? performer.contractor_id ?? null,
        };
    });

    // Multipart FormData глубоко вложенные массивы (financial_term, contractors_costs и т.д.) на бэкенде
    // часто приходят пустыми/обрезанными — без JSON сохранение не совпадает с тем, что в форме.
    // FormData нужен только при загрузке новых файлов документов.
    const hasNewDocumentFiles = form.documents.some((document) => document.file instanceof File);
    const submitOptions = {
        preserveScroll: true,
        forceFormData: hasNewDocumentFiles,
    };

    if (isEditing.value) {
        form.transform(() => buildSubmitPayload()).patch(route('orders.update', props.order.id), submitOptions);

        return;
    }

    form.transform(() => buildSubmitPayload()).post(route('orders.store'), submitOptions);
}

function goBack() {
    router.get(route('orders.index'));
}

</script>
