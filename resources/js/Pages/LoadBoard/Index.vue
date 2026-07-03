<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            :lead="`Внутренний спрос продаж на закупку перевозчиков. Активных грузов: ${activeCount}`"
            title="Биржа грузов"
        >
            <template #actions>
                <div v-if="prefillSourceLabel" class="border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
                    Черновик из: {{ prefillSourceLabel }}
                </div>
                <button type="button" :class="crmBtnCreate" @click="createOpen = !createOpen">
                    {{ createOpen ? 'Скрыть форму' : 'Выставить груз' }}
                </button>
            </template>
        </CrmPageHeader>

        <section v-if="createOpen" :class="`${crmPanel} space-y-4 p-5`">
            <div class="grid gap-3 md:grid-cols-3">
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Название груза</span>
                    <input v-model="postForm.title" :class="crmFieldFluid" placeholder="Москва → Казань, 20 т" />
                    <InputError :message="postForm.errors.title" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Приоритет</span>
                    <select v-model="postForm.priority" :class="crmFieldFluid">
                        <option v-for="(label, value) in priorityLabels" :key="value" :value="value">{{ label }}</option>
                    </select>
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Клиент</span>
                    <select v-model="postForm.customer_id" :class="crmFieldFluid">
                        <option :value="null">Не указан</option>
                        <option v-for="contractor in contractors" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option>
                    </select>
                </label>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Откуда</span>
                    <input v-model="postForm.loading_location" :class="crmFieldFluid" placeholder="Город / адрес" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Куда</span>
                    <input v-model="postForm.unloading_location" :class="crmFieldFluid" placeholder="Город / адрес" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Погрузка</span>
                    <input v-model="postForm.loading_date" type="date" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Выгрузка</span>
                    <input v-model="postForm.unloading_date" type="date" :class="crmFieldFluid" />
                </label>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Груз</span>
                    <input v-model="postForm.cargo_name" :class="crmFieldFluid" placeholder="Что везём" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Вес, т</span>
                    <input v-model="postForm.cargo_weight" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Объём, м³</span>
                    <input v-model="postForm.cargo_volume" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Тип транспорта</span>
                    <input v-model="postForm.transport_type" :class="crmFieldFluid" placeholder="Тент, реф, контейнер…" />
                </label>
            </div>

            <div class="space-y-3 border border-sky-100 bg-sky-50/60 p-3 dark:border-sky-900/60 dark:bg-sky-950/20">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-sky-900 dark:text-sky-100">ATI-справочники для последующей публикации</h3>
                        <p class="text-xs text-sky-700/80 dark:text-sky-200/70">Эти поля сохраняются вместе с грузом, чтобы закупщик мог отправить его на ATI без повторного подбора справочников.</p>
                    </div>
                    <span class="text-xs text-sky-700 dark:text-sky-200">Синхронизировано с `ati_dictionary_items`</span>
                </div>

                <div class="grid gap-3 md:grid-cols-4">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Название для ATI</span>
                        <input v-model="postForm.ati_cargo_name" :class="crmFieldFluid" placeholder="Если отличается от груза" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Тип груза ATI</span>
                        <select v-model="postForm.cargo_type_id" :class="crmFieldFluid" @change="applyCargoTypeOption(postForm)">
                            <option :value="null">Не выбран</option>
                            <option v-for="option in cargoTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Упаковка ATI</span>
                        <select v-model="postForm.pack_type_id" :class="crmFieldFluid" @change="applyPackageTypeOption(postForm)">
                            <option :value="null">Не выбрана</option>
                            <option v-for="option in packageTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Мест</span>
                        <input v-model="postForm.package_count" type="number" min="0" step="1" :class="crmFieldFluid" />
                    </label>
                </div>

                <div class="grid gap-3 lg:grid-cols-3">
                    <div :class="crmFilterField">
                        <span :class="crmLabelCompact">Погрузка ATI</span>
                        <details class="relative">
                            <summary class="flex h-9 cursor-pointer list-none items-center justify-between gap-2 border border-zinc-200 bg-white px-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <span class="truncate">{{ dictionarySelectionLabel(postForm.loading_type_items) }}</span>
                                <span class="text-zinc-400">▾</span>
                            </summary>
                            <div class="absolute z-30 mt-1 max-h-48 w-full space-y-1 overflow-y-auto border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                <label v-for="option in loadingTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                    <input v-model="postForm.loading_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyLoadingTypeOption(postForm)" />
                                    <span class="leading-tight">{{ option.label }}</span>
                                </label>
                            </div>
                        </details>
                    </div>
                    <div :class="crmFilterField">
                        <span :class="crmLabelCompact">Кузов ATI</span>
                        <details class="relative">
                            <summary class="flex h-9 cursor-pointer list-none items-center justify-between gap-2 border border-zinc-200 bg-white px-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <span class="truncate">{{ dictionarySelectionLabel(postForm.truck_body_type_items) }}</span>
                                <span class="text-zinc-400">▾</span>
                            </summary>
                            <div class="absolute z-30 mt-1 max-h-48 w-full space-y-1 overflow-y-auto border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                <label v-for="option in truckBodyTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                    <input v-model="postForm.truck_body_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyTruckBodyTypeOption(postForm)" />
                                    <span class="leading-tight">{{ option.label }}</span>
                                </label>
                            </div>
                        </details>
                    </div>
                    <div :class="crmFilterField">
                        <span :class="crmLabelCompact">Прицеп ATI</span>
                        <details class="relative">
                            <summary class="flex h-9 cursor-pointer list-none items-center justify-between gap-2 border border-zinc-200 bg-white px-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <span class="truncate">{{ dictionarySelectionLabel(postForm.trailer_type_items) }}</span>
                                <span class="text-zinc-400">▾</span>
                            </summary>
                            <div class="absolute z-30 mt-1 max-h-48 w-full space-y-1 overflow-y-auto border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                <label v-for="option in trailerTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                    <input v-model="postForm.trailer_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyTrailerTypeOption(postForm)" />
                                    <span class="leading-tight">{{ option.label }}</span>
                                </label>
                            </div>
                        </details>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-4 lg:grid-cols-8">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Длина, м</span>
                        <input v-model="postForm.length" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Ширина, м</span>
                        <input v-model="postForm.width" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Высота, м</span>
                        <input v-model="postForm.height" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Диаметр, м</span>
                        <input v-model="postForm.diameter" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">ТН ВЭД</span>
                        <input v-model="postForm.hs_code" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Класс опасн.</span>
                        <input v-model="postForm.hazard_class" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Темп. мин</span>
                        <input v-model="postForm.temp_min" type="number" step="0.1" :class="crmFieldFluid" />
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Темп. макс</span>
                        <input v-model="postForm.temp_max" type="number" step="0.1" :class="crmFieldFluid" />
                    </label>
                </div>

                <div class="flex flex-wrap gap-3 text-sm text-zinc-700 dark:text-zinc-200">
                    <label class="inline-flex items-center gap-2">
                        <input v-model="postForm.is_hazardous" type="checkbox" class="rounded border-zinc-300" />
                        Опасный груз
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="postForm.needs_temperature" type="checkbox" class="rounded border-zinc-300" />
                        Температурный режим
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="postForm.is_oversized" type="checkbox" class="rounded border-zinc-300" />
                        Негабарит
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input v-model="postForm.is_fragile" type="checkbox" class="rounded border-zinc-300" />
                        Хрупкий
                    </label>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Ставка клиента</span>
                    <input v-model="postForm.customer_rate" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Валюта</span>
                    <input v-model="postForm.customer_rate_currency" maxlength="3" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Макс. ставка перевозчика</span>
                    <input v-model="postForm.target_carrier_rate" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Форма оплаты</span>
                    <input v-model="postForm.payment_form" :class="crmFieldFluid" placeholder="Нал / безнал / НДС" />
                </label>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Требования</span>
                    <textarea v-model="postForm.requirements" rows="3" :class="crmFieldFluid" placeholder="Температура, пропуска, документы, режим погрузки" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Комментарий продавца</span>
                    <textarea v-model="postForm.seller_comment" rows="3" :class="crmFieldFluid" placeholder="Что важно для закупщика" />
                </label>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Лид</span>
                    <select v-model="postForm.lead_id" :class="crmFieldFluid">
                        <option :value="null">Без лида</option>
                        <option v-for="lead in leadOptions" :key="lead.id" :value="lead.id">
                            #{{ lead.number ?? lead.id }} · {{ lead.title ?? 'без названия' }}
                        </option>
                    </select>
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Заказ</span>
                    <select v-model="postForm.order_id" :class="crmFieldFluid">
                        <option :value="null">Без заказа</option>
                        <option v-for="order in orderOptions" :key="order.id" :value="order.id">
                            {{ order.order_number ?? `#${order.id}` }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" :class="crmBtnNeutral" @click="resetPostForm">Очистить</button>
                <button type="button" :class="crmBtnCreate" :disabled="postForm.processing" @click="submitPost">
                    {{ postForm.processing ? 'Публикуем…' : 'Опубликовать' }}
                </button>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            <Link
                v-for="item in filterItems"
                :key="item.value"
                :href="route('load-board.index', { filter: item.value })"
                class="border px-3 py-1.5 text-sm font-medium transition"
                :class="filter === item.value
                    ? 'border-sky-700 bg-sky-700 text-white dark:border-sky-500 dark:bg-sky-500'
                    : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200'"
            >
                {{ item.label }}
            </Link>
        </div>

        <section v-if="posts.length === 0" :class="`${crmPanel} p-6 text-sm text-zinc-500 dark:text-zinc-400`">
            Грузов по выбранному фильтру пока нет.
        </section>

        <section v-else class="grid gap-4">
            <div :class="`${crmPanel} space-y-3 p-3`">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <input
                                v-model="quickSearch"
                                type="text"
                                :class="crmGridSearchField"
                                placeholder="Быстрый поиск по бирже"
                            />
                        </div>

                        <GridViewsBar
                            grid-key="load_board"
                            :user-id="gridUserId"
                            :get-grid-api="() => gridApi"
                            :column-storage-key="columnStorageKey"
                            :filter-storage-key="filterModelStorageKey"
                            :quick-search="quickSearch"
                            :on-reset-defaults="resetGridViewState"
                            @update:quick-search="quickSearch = $event"
                            @applied="onGridViewApplied"
                            @pinned-changed="onGridViewsPinnedChanged"
                        />
                    </div>

                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        Выбрано: #{{ selectedPost?.id ?? '—' }}
                    </div>
                </div>

                <div
                    :class="crmGridInnerPanel"
                    class="h-[34rem]"
                    @contextmenu.capture="suppressNativeContextMenuCapture"
                >
                    <div class="ag-theme-alpine orders-grid-theme h-full min-h-0 min-w-0 overflow-hidden">
                        <AgGridVue
                            :grid-options="gridOptions"
                            :row-data="gridRows"
                            :column-defs="dynamicColumnDefs"
                            :default-col-def="defaultColDef"
                            :enable-cell-text-selection="true"
                            :maintain-column-order="true"
                            :suppress-drag-leave-hides-columns="true"
                            style="height: 100%; width: 100%;"
                            @grid-ready="onGridReady"
                            @row-clicked="onGridRowClicked"
                            @filter-changed="onFilterChanged"
                            @column-visible="saveColumnState"
                            @column-resized="saveColumnState"
                            @column-moved="saveColumnState"
                            @column-pinned="saveColumnState"
                            @sort-changed="saveColumnState"
                        />
                    </div>
                </div>

                <GridContextMenu
                    :open="contextMenu.open"
                    :x="contextMenu.x"
                    :y="contextMenu.y"
                    :items="contextMenu.items"
                    @close="closeRowContextMenu"
                />
            </div>

            <article
                v-for="post in selectedPost ? [selectedPost] : []"
                :key="post.id"
                :class="`${crmPanel} overflow-hidden`"
            >
                <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,28rem)]">
                    <div class="min-w-0 space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="border px-2 py-1 text-xs font-semibold uppercase tracking-wide" :class="statusClass(post.status)">
                                        {{ statusLabels[post.status] ?? post.status }}
                                    </span>
                                    <span class="border px-2 py-1 text-xs font-semibold uppercase tracking-wide" :class="priorityClass(post.priority)">
                                        {{ priorityLabels[post.priority] ?? post.priority }}
                                    </span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">#{{ post.id }}</span>
                                </div>
                                <h2 class="mt-2 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ post.title }}</h2>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ routeSummary(post) }}
                                </p>
                            </div>
                            <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
                                <div>Продавец: {{ post.seller?.name ?? '—' }}</div>
                                <div>Закупщик: {{ post.buyer?.name ?? 'не назначен' }}</div>
                                <div v-if="post.accepted_at">Принял: {{ post.accepter?.name ?? '—' }}</div>
                            </div>
                        </div>

                        <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-zinc-500">Даты</dt>
                                <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ dateRange(post) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-zinc-500">Груз</dt>
                                <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ cargoSummary(post) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-zinc-500">Ставка клиента</dt>
                                <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ money(post.customer_rate, post.customer_rate_currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-zinc-500">Макс. перевозчик</dt>
                                <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ money(post.target_carrier_rate, post.customer_rate_currency) }}</dd>
                            </div>
                        </dl>

                        <div v-if="atiSummary(post)" class="border border-sky-100 bg-sky-50/70 p-3 text-sm text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/20 dark:text-sky-100">
                            <div class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">ATI</div>
                            <p class="mt-1">{{ atiSummary(post) }}</p>
                        </div>

                        <div v-if="atiPreviewForPost(post)" class="space-y-3 border border-indigo-200 bg-indigo-50/70 p-3 text-sm text-indigo-950 dark:border-indigo-900/60 dark:bg-indigo-950/20 dark:text-indigo-100">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Подготовка к ATI</div>
                                    <p class="mt-1 font-medium">
                                        {{ atiPreviewForPost(post).ready ? 'Готов к внешней публикации' : 'Нужно заполнить обязательные поля' }}
                                    </p>
                                </div>
                                <span
                                    class="border px-2 py-1 text-xs font-semibold uppercase tracking-wide"
                                    :class="atiPreviewForPost(post).ready
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
                                        : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200'"
                                >
                                    {{ atiPreviewForPost(post).ready ? 'ready' : 'draft' }}
                                </span>
                            </div>
                            <div v-if="atiPreviewForPost(post).missing.length" class="space-y-1">
                                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Обязательные поля</div>
                                <ul class="list-disc space-y-0.5 pl-5">
                                    <li v-for="item in atiPreviewForPost(post).missing" :key="item.field">{{ item.label }}</li>
                                </ul>
                            </div>
                            <div v-if="atiPreviewForPost(post).warnings.length" class="space-y-1">
                                <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Рекомендации</div>
                                <ul class="list-disc space-y-0.5 pl-5">
                                    <li v-for="item in atiPreviewForPost(post).warnings" :key="item.field">{{ item.label }}</li>
                                </ul>
                            </div>
                            <details class="border border-indigo-200 bg-white/70 p-2 dark:border-indigo-900/60 dark:bg-zinc-950/60">
                                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Payload для ATI</summary>
                                <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs text-zinc-800 dark:text-zinc-100">{{ atiPayloadJson(atiPreviewForPost(post)) }}</pre>
                            </details>
                        </div>

                        <div class="grid gap-3 text-sm md:grid-cols-2">
                            <div v-if="post.requirements" class="border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Требования</div>
                                <p class="mt-1 whitespace-pre-wrap text-zinc-800 dark:text-zinc-200">{{ post.requirements }}</p>
                            </div>
                            <div v-if="post.seller_comment" class="border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Комментарий продавца</div>
                                <p class="mt-1 whitespace-pre-wrap text-zinc-800 dark:text-zinc-200">{{ post.seller_comment }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <label v-if="!isClosed(post)" class="flex items-center gap-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                <span>Закупщик</span>
                                <select
                                    :value="post.buyer_id ?? ''"
                                    class="border border-zinc-200 bg-white px-2 py-1.5 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                                    @change="assignBuyer(post, $event.target.value)"
                                >
                                    <option value="">Не назначен</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </label>
                            <button v-if="!isClosed(post) && post.buyer_id !== currentUserId" type="button" :class="crmBtnNeutral" @click="takePost(post)">
                                Взять в работу
                            </button>
                            <button v-if="post.buyer_id === currentUserId && !isClosed(post)" type="button" :class="crmBtnNeutral" @click="releasePost(post)">
                                Вернуть в общий список
                            </button>
                            <button v-if="!isClosed(post)" type="button" :class="crmBtnNeutral" @click="openOfferForm(post)">
                                Добавить вариант
                            </button>
                            <button v-if="!isClosed(post)" type="button" :class="crmBtnNeutral" @click="prepareAti(post)">
                                Подготовить к ATI
                            </button>
                            <button v-if="!isClosed(post)" type="button" :class="crmBtnCreate" @click="setStatus(post, 'closed')">
                                Закрыть
                            </button>
                            <button v-if="!isClosed(post)" type="button" :class="crmBtnNeutral" @click="setStatus(post, 'no_options')">
                                Без вариантов
                            </button>
                            <button v-if="!isClosed(post)" type="button" :class="crmBtnDangerMuted" @click="setStatus(post, 'cancelled')">
                                Отменить
                            </button>
                        </div>
                    </div>

                    <aside class="space-y-3 border-t border-zinc-200 pt-4 xl:border-l xl:border-t-0 xl:pl-4 xl:pt-0 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                Варианты перевозчиков · {{ post.offers.length }}
                            </h3>
                        </div>

                        <form v-if="offerFormPostId === post.id" class="space-y-3 border border-sky-200 bg-sky-50 p-3 dark:border-sky-900/60 dark:bg-sky-950/30" @submit.prevent="submitOffer(post)">
                            <select v-model="offerForm.carrier_id" :class="crmFieldFluid">
                                <option :value="null">Перевозчик не указан</option>
                                <option v-for="contractor in contractors" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option>
                            </select>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input v-model="offerForm.carrier_rate" type="number" min="0" step="0.01" :class="crmFieldFluid" placeholder="Ставка" />
                                <input v-model="offerForm.carrier_rate_currency" maxlength="3" :class="crmFieldFluid" placeholder="RUB" />
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input v-model="offerForm.payment_form" :class="crmFieldFluid" placeholder="Форма оплаты" />
                                <input v-model="offerForm.available_date" type="date" :class="crmFieldFluid" />
                            </div>
                            <input v-model="offerForm.carrier_contact" :class="crmFieldFluid" placeholder="Контакт перевозчика" />
                            <textarea v-model="offerForm.conditions" rows="2" :class="crmFieldFluid" placeholder="Условия" />
                            <textarea v-model="offerForm.comment" rows="2" :class="crmFieldFluid" placeholder="Комментарий закупщика" />
                            <InputError :message="offerForm.errors.carrier_rate" />
                            <div class="flex justify-end gap-2">
                                <button type="button" :class="crmBtnNeutral" @click="offerFormPostId = null">Отмена</button>
                                <button type="submit" :class="crmBtnCreate" :disabled="offerForm.processing">
                                    Добавить
                                </button>
                            </div>
                        </form>

                        <div v-if="post.offers.length === 0" class="border border-dashed border-zinc-200 p-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            Вариантов пока нет.
                        </div>
                        <div v-for="offer in post.offers" :key="offer.id" class="border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-950">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-50">
                                        {{ offer.carrier?.name ?? 'Перевозчик не указан' }}
                                    </div>
                                    <div class="mt-0.5 text-zinc-600 dark:text-zinc-300">
                                        {{ money(offer.carrier_rate, offer.carrier_rate_currency) }}
                                        <template v-if="offer.payment_form"> · {{ offer.payment_form }}</template>
                                    </div>
                                </div>
                                <span class="text-xs uppercase tracking-wide" :class="offerStatusClass(offer.status)">
                                    {{ offerStatusLabel(offer.status) }}
                                </span>
                            </div>
                            <p v-if="offer.available_date" class="mt-2 text-xs text-zinc-500">Подача: {{ formatDate(offer.available_date) }}</p>
                            <p v-if="offer.carrier_contact" class="mt-1 text-xs text-zinc-500">Контакт: {{ offer.carrier_contact }}</p>
                            <p v-if="offer.conditions" class="mt-2 whitespace-pre-wrap text-zinc-700 dark:text-zinc-200">{{ offer.conditions }}</p>
                            <p v-if="offer.comment" class="mt-2 whitespace-pre-wrap text-xs text-zinc-500 dark:text-zinc-400">{{ offer.comment }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button v-if="!['selected', 'approved'].includes(offer.status) && !isClosed(post)" type="button" class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-300" @click="selectOffer(post, offer)">
                                    Выбрать этот вариант
                                </button>
                                <button v-if="post.status === 'seller_review' && offer.status === 'selected'" type="button" class="text-sm font-medium text-emerald-700 hover:underline dark:text-emerald-300" @click="approveOffer(post, offer)">
                                    Принять вариант
                                </button>
                            </div>
                        </div>
                    </aside>
                </div>
            </article>
        </section>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import InputError from '@/Components/InputError.vue';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import { applyAgSetListColumn } from '@/Components/Grid/agSetListFilter.js';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import { applySavedToColDef, buildLayoutIndex, readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';
import { createAgGridFilterModelPersister, loadAgGridFilterModel } from '@/support/agGridFilterModelPersistence.js';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import '@/Components/Grid/grid-theme.css';
import {
    applyDictionaryItems,
    dictionaryOptionByValue,
    dictionarySelectionLabel,
    normalizeNullableNumber,
} from '@/support/wizardDictionaryHelpers.js';
import {
    crmBtnCreate,
    crmBtnDangerMuted,
    crmBtnNeutral,
    crmFieldFluid,
    crmFilterField,
    crmGridInnerPanel,
    crmGridSearchField,
    crmGridToolbarBtn,
    crmLabelCompact,
    crmPanel,
} from '@/support/crmUi.js';

ModuleRegistry.registerModules([AllCommunityModule]);

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'load-board' }, () => page),
});

const props = defineProps({
    posts: { type: Array, default: () => [] },
    filter: { type: String, default: 'active' },
    statusLabels: { type: Object, default: () => ({}) },
    priorityLabels: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    contractors: { type: Array, default: () => [] },
    leadOptions: { type: Array, default: () => [] },
    orderOptions: { type: Array, default: () => [] },
    atiDictionaries: { type: Object, default: () => ({}) },
    prefill: { type: Object, default: null },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const flash = computed(() => page.props.flash ?? {});
const atiPreview = computed(() => flash.value.load_board_ati_preview ?? null);
const createOpen = ref(Boolean(props.prefill));
const offerFormPostId = ref(null);

const filterItems = [
    { value: 'active', label: 'Активные' },
    { value: 'my', label: 'Мои продажи' },
    { value: 'buyer', label: 'Моя закупка' },
    { value: 'closed', label: 'Закрытые' },
    { value: 'all', label: 'Все' },
];

const activeCount = computed(() => props.posts.filter((post) => !isClosed(post)).length);
const prefillSourceLabel = computed(() => props.prefill?.source_label ?? '');
const cargoTypeOptions = computed(() => props.atiDictionaries?.cargoTypes ?? []);
const packageTypeOptions = computed(() => props.atiDictionaries?.packageTypes ?? []);
const loadingTypeOptions = computed(() => props.atiDictionaries?.loadingTypes ?? []);
const truckBodyTypeOptions = computed(() => props.atiDictionaries?.truckBodyTypes ?? []);
const trailerTypeOptions = computed(() => props.atiDictionaries?.trailerTypes ?? []);
const selectedPostId = ref(props.posts[0]?.id ?? null);
const quickSearch = ref('');
const gridApi = ref(null);
const gridViewsRevision = ref(0);
const columnStateSaveTimer = ref(null);
const { contextMenu, closeContextMenu, openCellContextMenu } = useGridContextMenu();
const persistFilterModel = createAgGridFilterModelPersister();

const loadBoardGridKey = 'load_board';
const gridUserId = computed(() => currentUserId.value ?? 'guest');
const columnStorageKey = computed(() => `${loadBoardGridKey}_grid_columns_v1_${gridUserId.value}_${gridViewsRevision.value}`);
const filterModelStorageKey = computed(() => `${loadBoardGridKey}_grid_filter_model_v1_${gridUserId.value}`);

const selectedPost = computed(() => {
    const current = props.posts.find((post) => Number(post.id) === Number(selectedPostId.value));

    return current ?? props.posts[0] ?? null;
});

const gridRows = computed(() => props.posts.map((post) => {
    const offers = Array.isArray(post.offers) ? post.offers : [];
    const selectedOffer = offers.find((offer) => ['selected', 'approved'].includes(offer.status)) ?? null;
    const preview = atiPreviewForPost(post);

    return {
        ...post,
        status_label: props.statusLabels[post.status] ?? post.status,
        priority_label: props.priorityLabels[post.priority] ?? post.priority,
        route: routeSummary(post),
        dates: dateRange(post),
        cargo_summary: cargoSummary(post),
        ati_summary: atiSummary(post) || '—',
        ati_ready_label: preview ? (preview.ready ? 'Готов' : 'Не готов') : (post.ati_cargo_payload ? 'Payload есть' : 'Не проверяли'),
        seller_name: post.seller?.name ?? '—',
        buyer_name: post.buyer?.name ?? '—',
        offers_count: offers.length,
        selected_offer_rate: selectedOffer ? money(selectedOffer.carrier_rate, selectedOffer.carrier_rate_currency) : '—',
        customer_rate_label: money(post.customer_rate, post.customer_rate_currency),
        target_rate_label: money(post.target_carrier_rate, post.customer_rate_currency),
        accepted_label: post.accepted_offer_id ? 'Принят' : '—',
    };
}));

const defaultColDef = {
    sortable: true,
    filter: true,
    resizable: true,
    minWidth: 110,
};

function setListColumn(column) {
    applyAgSetListColumn(column, {
        values: [...new Set(gridRows.value
            .map((row) => row[column.field])
            .filter((value) => value !== null && value !== undefined && value !== '')
            .map(String))],
    });

    return column;
}

const baseColumnDefs = computed(() => [
    {
        field: 'id',
        headerName: 'ID',
        width: 82,
        minWidth: 72,
        filter: 'agNumberColumnFilter',
        pinned: 'left',
    },
    setListColumn({
        field: 'status_label',
        headerName: 'Статус',
        width: 150,
        minWidth: 130,
    }),
    setListColumn({
        field: 'priority_label',
        headerName: 'Приоритет',
        width: 130,
        minWidth: 115,
    }),
    {
        field: 'title',
        headerName: 'Заявка',
        width: 240,
        minWidth: 180,
    },
    {
        field: 'route',
        headerName: 'Маршрут',
        width: 320,
        minWidth: 220,
    },
    {
        field: 'dates',
        headerName: 'Даты',
        width: 160,
        minWidth: 140,
    },
    {
        field: 'cargo_summary',
        headerName: 'Груз',
        width: 260,
        minWidth: 190,
    },
    {
        field: 'customer_rate_label',
        headerName: 'Ставка клиента',
        width: 155,
        minWidth: 130,
    },
    {
        field: 'target_rate_label',
        headerName: 'Цель закупки',
        width: 150,
        minWidth: 125,
    },
    {
        field: 'offers_count',
        headerName: 'Предл.',
        width: 105,
        minWidth: 92,
        filter: 'agNumberColumnFilter',
    },
    {
        field: 'selected_offer_rate',
        headerName: 'Выбранная ставка',
        width: 165,
        minWidth: 140,
    },
    setListColumn({
        field: 'buyer_name',
        headerName: 'Закупщик',
        width: 150,
        minWidth: 130,
    }),
    setListColumn({
        field: 'seller_name',
        headerName: 'Продавец',
        width: 150,
        minWidth: 130,
    }),
    {
        field: 'ati_ready_label',
        headerName: 'ATI readiness',
        width: 155,
        minWidth: 135,
    },
    {
        field: 'ati_summary',
        headerName: 'ATI справочники',
        width: 300,
        minWidth: 220,
    },
    {
        field: 'accepted_label',
        headerName: 'Принято',
        width: 120,
        minWidth: 105,
    },
]);

const dynamicColumnDefs = computed(() => {
    const fields = baseColumnDefs.value.map((column) => column.field);
    const savedState = readPersistedAgGridColumnState(columnStorageKey.value);
    const { orderedFields, byColId } = buildLayoutIndex(fields, savedState);
    const byField = new Map(baseColumnDefs.value.map((column) => [column.field, column]));

    return orderedFields
        .map((field) => {
            const column = byField.get(field);

            return column ? applySavedToColDef(column, byColId.get(field)) : null;
        })
        .filter(Boolean);
});

const gridOptions = computed(() => ({
    localeText: agGridLocaleRu,
    rowHeight: 44,
    headerHeight: 42,
    suppressCellFocus: true,
    suppressDragLeaveHidesColumns: true,
    maintainColumnOrder: true,
    getRowId: (params) => String(params.data.id),
    rowClassRules: {
        'ag-row-selected-by-card': (params) => Number(params.data?.id) === Number(selectedPostId.value),
    },
    onCellContextMenu: (params) => openCellContextMenu(params, buildRowContextMenuItems),
}));

function buildDefaultColumnState() {
    return baseColumnDefs.value.map((column, index) => ({
        colId: column.field,
        hide: false,
        width: column.width,
        order: index,
    }));
}

function saveColumnState() {
    if (!gridApi.value || typeof window === 'undefined') {
        return;
    }

    if (columnStateSaveTimer.value) {
        clearTimeout(columnStateSaveTimer.value);
    }

    columnStateSaveTimer.value = setTimeout(() => {
        const state = gridApi.value.getColumnState().map((column, index) => ({
            colId: column.colId,
            hide: column.hide,
            width: column.width,
            order: index,
            sort: column.sort ?? null,
            sortIndex: column.sortIndex ?? null,
        }));

        localStorage.setItem(columnStorageKey.value, JSON.stringify(state));
    }, 250);
}

function resetGridViewState() {
    if (gridApi.value) {
        gridApi.value.applyColumnState({ state: buildDefaultColumnState(), applyOrder: true });
        gridApi.value.setFilterModel({});
    }

    if (typeof window !== 'undefined') {
        localStorage.removeItem(columnStorageKey.value);
        localStorage.removeItem(filterModelStorageKey.value);
    }

    quickSearch.value = '';
    gridViewsRevision.value++;
}

function onGridReady(params) {
    gridApi.value = params.api;

    if (!readPersistedAgGridColumnState(columnStorageKey.value)?.length) {
        gridApi.value.applyColumnState({ state: buildDefaultColumnState(), applyOrder: true });
    }

    loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);

    if (quickSearch.value.trim() !== '') {
        gridApi.value.setGridOption('quickFilterText', quickSearch.value);
    }
}

function onFilterChanged() {
    persistFilterModel(gridApi.value, filterModelStorageKey.value);
}

function onGridRowClicked(event) {
    if (event.data?.id) {
        selectedPostId.value = event.data.id;
    }
}

function onGridViewApplied() {
    gridViewsRevision.value++;

    nextTick(() => {
        if (gridApi.value) {
            gridApi.value.refreshCells({ force: true });
        }
    });
}

function onGridViewsPinnedChanged() {
    router.reload({ preserveScroll: true });
}

function closeRowContextMenu() {
    closeContextMenu();
}

function buildRowContextMenuItems(post) {
    if (!post) {
        return [];
    }

    return [
        {
            label: 'Открыть детали',
            run: () => {
                selectedPostId.value = post.id;
            },
        },
        {
            label: 'Взять в работу',
            disabled: isClosed(post) || Number(post.buyer?.id) === Number(currentUserId.value),
            run: () => takePost(post),
        },
        {
            label: 'Снять с себя',
            disabled: isClosed(post) || Number(post.buyer?.id) !== Number(currentUserId.value),
            run: () => releasePost(post),
        },
        {
            label: 'Подготовить к ATI',
            run: () => prepareAti(post),
        },
        {
            label: 'Нет вариантов',
            disabled: isClosed(post),
            run: () => setStatus(post, 'no_options'),
        },
        {
            label: 'Отменить',
            disabled: isClosed(post),
            danger: true,
            run: () => setStatus(post, 'cancelled'),
        },
    ];
}

const blankPostForm = {
    lead_id: null,
    order_id: null,
    customer_id: null,
    priority: 'normal',
    title: '',
    loading_location: '',
    unloading_location: '',
    loading_date: '',
    unloading_date: '',
    cargo_name: '',
    ati_cargo_name: '',
    cargo_weight: '',
    cargo_volume: '',
    cargo_type_id: null,
    cargo_type: null,
    cargo_type_label: '',
    pack_type_id: null,
    package_type: null,
    pack_type_label: '',
    package_count: '',
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
    length: '',
    width: '',
    height: '',
    diameter: '',
    is_hazardous: false,
    hazard_class: '',
    needs_temperature: false,
    temp_min: '',
    temp_max: '',
    is_oversized: false,
    is_fragile: false,
    hs_code: '',
    ati_cargo_payload: {},
    transport_type: '',
    customer_rate: '',
    customer_rate_currency: 'RUB',
    target_carrier_rate: '',
    payment_form: '',
    requirements: '',
    seller_comment: '',
};

const postForm = useForm({
    ...blankPostForm,
    ...(props.prefill ?? {}),
});

normalizeAtiSelections(postForm);

const offerForm = useForm({
    carrier_id: null,
    carrier_rate: '',
    carrier_rate_currency: 'RUB',
    payment_form: '',
    available_date: '',
    carrier_contact: '',
    conditions: '',
    comment: '',
});

function resetPostForm() {
    postForm.defaults({ ...blankPostForm });
    postForm.reset();
    normalizeAtiSelections(postForm);
    postForm.clearErrors();
}

function submitPost() {
    normalizeAtiSelections(postForm);
    postForm.post(route('load-board.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetPostForm();
            createOpen.value = false;
        },
    });
}

function normalizeAtiSelections(form) {
    form.loading_type_ids = idsFromItems(form.loading_type_items);
    form.truck_body_type_ids = idsFromItems(form.truck_body_type_items);
    form.trailer_type_ids = idsFromItems(form.trailer_type_items);
    applyCargoTypeOption(form);
    applyPackageTypeOption(form);
    applyLoadingTypeOption(form);
    applyTruckBodyTypeOption(form);
    applyTrailerTypeOption(form);
}

function idsFromItems(items) {
    if (!Array.isArray(items)) {
        return [];
    }

    return items
        .map((item) => normalizeNullableNumber(item?.id))
        .filter((id) => id !== null);
}

function applyCargoTypeOption(form = postForm) {
    const option = dictionaryOptionByValue(cargoTypeOptions.value, form.cargo_type_id);
    form.cargo_type_id = option ? normalizeNullableNumber(option.value) : null;
    form.cargo_type = option?.code ?? null;
    form.cargo_type_label = option?.label ?? '';
    form.is_hazardous = form.cargo_type === 'dangerous' || Boolean(form.is_hazardous);
    form.is_oversized = form.cargo_type === 'oversized' || Boolean(form.is_oversized);
    form.is_fragile = form.cargo_type === 'fragile' || Boolean(form.is_fragile);
}

function applyPackageTypeOption(form = postForm) {
    const option = dictionaryOptionByValue(packageTypeOptions.value, form.pack_type_id);
    form.pack_type_id = option ? normalizeNullableNumber(option.value) : null;
    form.package_type = option?.code ?? null;
    form.pack_type_label = option?.label ?? '';
}

function applyLoadingTypeOption(form = postForm) {
    applyDictionaryItems(form, loadingTypeOptions.value, 'loading_type_ids', 'loading_type_id', 'loading_type_code', 'loading_type_label', 'loading_type_items');
}

function applyTruckBodyTypeOption(form = postForm) {
    applyDictionaryItems(form, truckBodyTypeOptions.value, 'truck_body_type_ids', 'truck_body_type_id', 'truck_body_type_code', 'truck_body_type_label', 'truck_body_type_items');
}

function applyTrailerTypeOption(form = postForm) {
    applyDictionaryItems(form, trailerTypeOptions.value, 'trailer_type_ids', 'trailer_type_id', 'trailer_type_code', 'trailer_type_label', 'trailer_type_items');
}


function openOfferForm(post) {
    offerFormPostId.value = post.id;
    offerForm.reset();
    offerForm.clearErrors();
}

function submitOffer(post) {
    offerForm.post(route('load-board.offers.store', post.id), {
        preserveScroll: true,
        onSuccess: () => {
            offerFormPostId.value = null;
            offerForm.reset();
        },
    });
}

function takePost(post) {
    router.post(route('load-board.take', post.id), {}, { preserveScroll: true });
}

function releasePost(post) {
    router.post(route('load-board.release', post.id), {}, { preserveScroll: true });
}

function assignBuyer(post, buyerId) {
    router.patch(route('load-board.buyer.update', post.id), {
        buyer_id: buyerId === '' ? null : Number(buyerId),
    }, { preserveScroll: true });
}

function prepareAti(post) {
    router.post(route('load-board.ati.prepare', post.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function selectOffer(post, offer) {
    router.post(route('load-board.offers.select', { post: post.id, offer: offer.id }), {}, { preserveScroll: true });
}

function approveOffer(post, offer) {
    router.post(route('load-board.offers.approve', { post: post.id, offer: offer.id }), {}, { preserveScroll: true });
}

function setStatus(post, status) {
    router.patch(route('load-board.status.update', post.id), { status }, { preserveScroll: true });
}

function isClosed(post) {
    return ['closed', 'cancelled', 'no_options'].includes(post.status);
}

function routeSummary(post) {
    const from = post.loading_location || 'откуда не указано';
    const to = post.unloading_location || 'куда не указано';

    return `${from} → ${to}`;
}

function dateRange(post) {
    const from = formatDate(post.loading_date);
    const to = formatDate(post.unloading_date);

    if (from === '—' && to === '—') {
        return '—';
    }

    return `${from} → ${to}`;
}

function cargoSummary(post) {
    const parts = [
        post.cargo_name,
        post.cargo_weight ? `${post.cargo_weight} т` : null,
        post.cargo_volume ? `${post.cargo_volume} м³` : null,
        post.transport_type,
    ].filter(Boolean);

    return parts.length ? parts.join(' · ') : '—';
}

function atiSummary(post) {
    const parts = [
        post.ati_cargo_name ? `ATI: ${post.ati_cargo_name}` : null,
        post.cargo_type_label,
        post.pack_type_label,
        dictionarySelectionLabel(post.loading_type_items),
        dictionarySelectionLabel(post.truck_body_type_items),
        dictionarySelectionLabel(post.trailer_type_items),
        post.package_count ? `${post.package_count} мест` : null,
        post.hs_code ? `ТН ВЭД ${post.hs_code}` : null,
    ].filter((part) => part && part !== 'Выберите');

    return parts.join(' · ');
}

function atiPreviewForPost(post) {
    return Number(atiPreview.value?.post_id) === Number(post.id) ? atiPreview.value : null;
}

function atiPayloadJson(preview) {
    return JSON.stringify(preview?.payload ?? {}, null, 2);
}

function offerStatusLabel(status) {
    const labels = {
        proposed: 'предложен',
        selected: 'выбран',
        approved: 'принят',
        rejected: 'отклонён',
    };

    return labels[status] ?? status;
}

function offerStatusClass(status) {
    const classes = {
        selected: 'text-violet-700 dark:text-violet-300',
        approved: 'text-emerald-700 dark:text-emerald-300',
        rejected: 'text-rose-600 dark:text-rose-300',
        proposed: 'text-zinc-500',
    };

    return classes[status] ?? 'text-zinc-500';
}

function money(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numeric = Number(value);
    if (Number.isNaN(numeric)) {
        return `${value} ${currency}`;
    }

    return `${numeric.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${currency || 'RUB'}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const parts = String(value).split('-');
    if (parts.length !== 3) {
        return value;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function statusClass(status) {
    const map = {
        new: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        in_work: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        has_offers: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200',
        seller_review: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-200',
        closed: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
        no_options: 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200',
        cancelled: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200',
    };

    return map[status] ?? map.new;
}

function priorityClass(priority) {
    const map = {
        low: 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300',
        normal: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        high: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        urgent: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200',
    };

    return map[priority] ?? map.normal;
}

watch(quickSearch, (value) => {
    if (gridApi.value) {
        gridApi.value.setGridOption('quickFilterText', value);
    }
});

watch(
    () => props.posts,
    (posts) => {
        if (!posts.some((post) => Number(post.id) === Number(selectedPostId.value))) {
            selectedPostId.value = posts[0]?.id ?? null;
        }

        nextTick(() => {
            if (gridApi.value) {
                loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);
                gridApi.value.refreshCells({ force: true });
            }
        });
    },
);

watch(selectedPostId, () => {
    if (gridApi.value) {
        gridApi.value.redrawRows();
    }
});
</script>
