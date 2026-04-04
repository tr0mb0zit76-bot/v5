<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-zinc-200 text-lg leading-none hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    aria-label="К списку"
                    title="К списку"
                    @click="goBack"
                >
                    ×
                </button>

                <div>
                <h1 class="text-2xl font-semibold">
                    {{ isEditing ? `Заказ ${form.order_number || `#${order.id}`}` : 'Новый заказ' }}
                </h1>
                <p class="text-sm text-zinc-500">
                    Экспедиторская заявка с маршрутом, грузами, финансами и документами
                </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="goBack"
                >
                    К списку
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Сохранение...' : 'Сохранить заказ' }}
                </button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl border px-4 py-2 text-sm transition"
                :class="activeTab === tab.key
                    ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                    : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="min-h-0 overflow-auto border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
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

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Особые отметки</label>
                        <textarea v-model="form.special_notes" rows="4" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                    </div>

                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div class="mb-3 text-sm font-semibold">Сводка по финансам</div>
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
                            <p class="text-sm text-zinc-500">Каждый этап формирует leg в маршруте заказа</p>
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
                                    <input v-model="performer.stage" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-xs font-medium uppercase tracking-wide text-zinc-500">Исполнитель</label>
                                    <select v-model="performer.contractor_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option :value="null">Не выбран</option>
                                        <option v-for="contractor in carrierOptions" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option>
                                    </select>
                                </div>
                                <div class="flex items-end justify-end">
                                    <button type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.performers, index)">
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
                            <div class="text-sm font-medium">
                                {{ routePointTitle(point, index) }}
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

            <div v-else-if="activeTab === 'finance'" class="space-y-4">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <h2 class="text-base font-semibold">Клиент</h2>
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
                                <label class="text-sm font-medium">Форма оплаты клиента</label>
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
                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Срок, дней</label>
                                <input v-model="form.financial_term.client_payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Тип документов</label>
                                <select v-model="form.financial_term.client_payment_schedule.postpayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <label class="inline-flex items-center gap-2 pt-8 text-sm">
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
                                <label class="text-sm font-medium">Тип документов для предоплаты</label>
                                <select v-model="form.financial_term.client_payment_schedule.prepayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Постоплата, %</label>
                                <input :value="100 - Number(form.financial_term.client_payment_schedule.prepayment_ratio || 0)" type="number" disabled class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">KPI %</label>
                            <input v-model="form.financial_term.kpi_percent" :disabled="isManager" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm disabled:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-950 dark:disabled:bg-zinc-800" />
                            <p v-if="isManager" class="text-xs text-zinc-500">Для менеджера KPI рассчитывается и задаётся без ручного редактирования.</p>
                        </div>
                    </div>

                    <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold">Дополнительные расходы</h2>
                            <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addAdditionalCost">
                                Добавить
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(cost, index) in form.financial_term.additional_costs" :key="`extra-${index}`" class="grid gap-3 md:grid-cols-4">
                                <input v-model="cost.label" type="text" placeholder="Статья" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 md:col-span-2" />
                                <input v-model="cost.amount" type="number" min="0" step="0.01" placeholder="Сумма" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                <div class="flex gap-2">
                                    <select v-model="cost.currency" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <button type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.financial_term.additional_costs, index)">
                                        ×
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold">Затраты по исполнителям</h2>
                            <p class="text-xs text-zinc-500">Здесь задаются стоимость, форма оплаты и условия оплаты перевозчика по каждому этапу.</p>
                        </div>
                        <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="syncContractorCostsFromPerformers">
                            Подтянуть из этапов
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(cost, index) in form.financial_term.contractors_costs" :key="`contractor-cost-${index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="grid gap-3 md:grid-cols-4">
                            <input v-model="cost.stage" type="text" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="Этап" />
                            <select v-model="cost.contractor_id" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <option :value="null">Исполнитель</option>
                                <option v-for="contractor in carrierOptions" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option>
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
                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Срок, дней</label>
                                    <input v-model="cost.payment_schedule.postpayment_days" type="number" min="0" step="1" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Тип документов</label>
                                    <select v-model="cost.payment_schedule.postpayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <label class="inline-flex items-center gap-2 pt-8 text-sm">
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
                                    <label class="text-sm font-medium">Тип документов для предоплаты</label>
                                    <select v-model="cost.payment_schedule.prepayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option v-for="option in paymentBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
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
                                <label class="text-sm font-medium">Шаблон PDF</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="document.template_id" type="number" min="0" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="ID шаблона" />
                                    <button type="button" class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="document.generated_pdf_path = 'pending'">
                                        Сгенерировать
                                    </button>
                                </div>
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
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
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
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    currentUser: { type: Object, default: () => ({}) },
    cargoTitleSuggestions: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'main', label: 'Основное' },
    { key: 'route', label: 'Маршрут' },
    { key: 'cargo', label: 'Груз' },
    { key: 'finance', label: 'Финансы' },
    { key: 'documents', label: 'Документы' },
];

const activeTab = ref('main');
const contractors = ref([...props.contractors]);
const ownCompanyOptions = ref([...props.ownCompanies]);
const clientSearch = ref('');
const showClientResults = ref(false);
const showCounterpartyModal = ref(false);
const inlineContractorSaving = ref(false);
const addressSuggestions = ref({});
const addressTimers = {};
const draggedRoutePointIndex = ref(null);
const dragOverRoutePointIndex = ref(null);
const page = usePage();
const paymentFormOptions = [
    { value: 'vat', label: 'С НДС' },
    { value: 'no_vat', label: 'Без НДС' },
    { value: 'cash', label: 'Нал' },
];
const paymentBasisOptions = [
    { value: 'fttn', label: 'ФТТН' },
    { value: 'ottn', label: 'ОТТН' },
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
    return {
        ...blankPaymentSchedule(),
        ...schedule,
        has_prepayment: Boolean(schedule?.has_prepayment),
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

function blankOrder() {
    return {
        status: 'new',
        own_company_id: null,
        client_id: null,
        order_date: new Date().toISOString().slice(0, 10),
        order_number: '',
        payment_terms: '',
        special_notes: '',
        performers: [
            { stage: 'leg_1', contractor_id: null },
        ],
        route_points: [
            { type: 'loading', sequence: 1, address: '', normalized_data: {}, planned_date: '', actual_date: '', contact_person: '', contact_phone: '' },
            { type: 'unloading', sequence: 2, address: '', normalized_data: {}, planned_date: '', actual_date: '', contact_person: '', contact_phone: '' },
        ],
        cargo_items: [
            { name: '', description: '', weight_kg: null, volume_m3: null, package_type: null, package_count: null, dangerous_goods: false, dangerous_class: '', hs_code: '', cargo_type: 'general' },
        ],
        financial_term: {
            client_price: null,
            client_currency: 'RUB',
            client_payment_form: 'vat',
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
            stage: performer.stage ?? 'leg_1',
            contractor_id: performer.contractor_id ?? null,
        }))
        : blankOrder().performers,
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

const isEditing = computed(() => props.order !== null);
const currentRoleKey = computed(() => page.props.auth?.user?.role?.name ?? 'manager');
const isManager = computed(() => currentRoleKey.value === 'manager');
const selectedClient = computed(() => contractors.value.find((contractor) => contractor.id === form.client_id) ?? null);
const carrierOptions = computed(() => contractors.value.filter((contractor) => contractor.type === 'carrier' || contractor.type === 'both'));

const filteredClients = computed(() => {
    const query = clientSearch.value.trim().toLowerCase();

    if (query === '') {
        return contractors.value.slice(0, 8);
    }

    return contractors.value
        .filter((contractor) => [contractor.name, contractor.inn, contractor.phone, contractor.email].filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .slice(0, 8);
});

if (selectedClient.value) {
    clientSearch.value = selectedClient.value.name;
}

function selectClient(contractor) {
    form.client_id = contractor.id;
    clientSearch.value = contractor.name;
    showClientResults.value = false;
}

function addPerformer() {
    form.performers.push({
        stage: `leg_${form.performers.length + 1}`,
        contractor_id: null,
    });
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
    form.route_points.push({
        type,
        sequence: form.route_points.length + 1,
        address: '',
        normalized_data: {},
        planned_date: '',
        actual_date: '',
        contact_person: '',
        contact_phone: '',
    });
}

function normalizeRoutePointSequences() {
    form.route_points = form.route_points.map((point, index) => ({
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

        return normalizeContractorCost({
            ...existingRow,
            stage: performer.stage,
            contractor_id: performer.contractor_id,
        });
    });
}

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
