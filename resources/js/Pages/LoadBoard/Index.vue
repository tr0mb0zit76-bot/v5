<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            :lead="`Внутренний спрос продаж на закупку перевозчиков. Активных грузов: ${activePostsCount}`"
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

        <section v-if="allPosts.length === 0" :class="`${crmPanel} p-6 text-sm text-zinc-500 dark:text-zinc-400`">
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
                        Показано {{ allPosts.length }} из {{ postsMeta.total }} · выбрано #{{ selectedPost?.id ?? '—' }}
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

                <p v-if="loadingMorePosts" class="text-center text-xs text-zinc-500 dark:text-zinc-400">
                    Загружаем ещё грузы…
                </p>

                <GridContextMenu
                    :open="contextMenu.open"
                    :x="contextMenu.x"
                    :y="contextMenu.y"
                    :items="contextMenu.items"
                    @close="closeRowContextMenu"
                />
            </div>

            <LoadBoardPostCard
                v-for="post in selectedPost ? [selectedPost] : []"
                :key="post.id"
                :post="post"
                :users="users"
                :contractors="contractors"
                :status-labels="statusLabels"
                :priority-labels="priorityLabels"
                :offer-source-options="offerSourceOptions"
                :current-user-id="currentUserId"
                :ati-preview="atiPreview"
                :order-options="orderOptions"
                :lead-options="leadOptions"
            />
        </section>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { AgGridVue } from 'ag-grid-vue3';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import LoadBoardPostCard from '@/Components/LoadBoard/LoadBoardPostCard.vue';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { useAgGridInfiniteScroll } from '@/composables/useAgGridInfiniteScroll.js';
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
    posts: { type: Object, default: () => ({ data: [], meta: { current_page: 1, last_page: 1, per_page: 50, total: 0, has_more: false } }) },
    activePostsCount: { type: Number, default: 0 },
    filter: { type: String, default: 'active' },
    statusLabels: { type: Object, default: () => ({}) },
    priorityLabels: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    contractors: { type: Array, default: () => [] },
    leadOptions: { type: Array, default: () => [] },
    orderOptions: { type: Array, default: () => [] },
    atiDictionaries: { type: Object, default: () => ({}) },
    offerSourceOptions: { type: Object, default: () => ({}) },
    prefill: { type: Object, default: null },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const flash = computed(() => page.props.flash ?? {});
const atiPreview = computed(() => flash.value.load_board_ati_preview ?? null);
const createOpen = ref(Boolean(props.prefill));

const filterItems = [
    { value: 'active', label: 'Активные' },
    { value: 'my', label: 'Мои продажи' },
    { value: 'buyer', label: 'Моя закупка' },
    { value: 'has_offers', label: 'Есть офферы' },
    { value: 'closed', label: 'Закрытые' },
    { value: 'all', label: 'Все' },
];

function normalizePostsPayload(posts) {
    if (Array.isArray(posts)) {
        return {
            data: posts,
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: posts.length,
                total: posts.length,
                has_more: false,
            },
        };
    }

    return {
        data: Array.isArray(posts?.data) ? posts.data : [],
        meta: posts?.meta ?? {
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 0,
            has_more: false,
        },
    };
}

function syncPostsFromProps() {
    const payload = normalizePostsPayload(props.posts);
    allPosts.value = [...payload.data];
    postsMeta.value = payload.meta;
}

const allPosts = ref([]);
const postsMeta = ref(normalizePostsPayload(props.posts).meta);
syncPostsFromProps();

const activePostsCount = computed(() => Number(props.activePostsCount ?? 0));
const prefillSourceLabel = computed(() => props.prefill?.source_label ?? '');
const cargoTypeOptions = computed(() => props.atiDictionaries?.cargoTypes ?? []);
const packageTypeOptions = computed(() => props.atiDictionaries?.packageTypes ?? []);
const loadingTypeOptions = computed(() => props.atiDictionaries?.loadingTypes ?? []);
const truckBodyTypeOptions = computed(() => props.atiDictionaries?.truckBodyTypes ?? []);
const trailerTypeOptions = computed(() => props.atiDictionaries?.trailerTypes ?? []);
const selectedPostId = ref(allPosts.value[0]?.id ?? null);
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
    const current = allPosts.value.find((post) => Number(post.id) === Number(selectedPostId.value));

    return current ?? allPosts.value[0] ?? null;
});

const hasMorePosts = computed(() => Boolean(postsMeta.value?.has_more));

async function loadMorePosts() {
    if (!hasMorePosts.value) {
        return;
    }

    const nextPage = Number(postsMeta.value.current_page ?? 1) + 1;
    const response = await axios.get(route('load-board.rows'), {
        params: {
            filter: props.filter,
            page: nextPage,
        },
    });

    const existingIds = new Set(allPosts.value.map((post) => Number(post.id)));
    const incoming = (response.data?.data ?? []).filter((post) => !existingIds.has(Number(post.id)));

    allPosts.value = [...allPosts.value, ...incoming];
    postsMeta.value = response.data?.meta ?? postsMeta.value;
}

const { loading: loadingMorePosts } = useAgGridInfiniteScroll({
    gridApi,
    hasMore: hasMorePosts,
    loadMore: loadMorePosts,
});

const gridRows = computed(() => allPosts.value.map((post) => {
    const offers = Array.isArray(post.offers) ? post.offers : [];
    const selectedOffer = offers.find((offer) => ['selected', 'approved'].includes(offer.status)) ?? null;
    const preview = atiPreviewForPost(post);
    const summary = post.offers_summary ?? {};
    const bestOffer = offers.reduce((best, offer) => {
        if (!best || Number(offer.carrier_rate) < Number(best.carrier_rate)) {
            return offer;
        }

        return best;
    }, null);
    const currency = post.customer_rate_currency || 'RUB';

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
        best_offer_rate_label: summary.best_rate != null
            ? money(summary.best_rate, currency)
            : (bestOffer ? money(bestOffer.carrier_rate, bestOffer.carrier_rate_currency) : '—'),
        best_margin_label: summary.best_margin_abs != null && summary.best_margin_pct != null
            ? marginLabelFromSummary(summary, currency)
            : marginLabel(bestOffer, currency),
        offer_sources_label: summary.sources_label
            ?? ([...new Set(offers.map((offer) => offer.source_label).filter(Boolean))].join(', ') || '—'),
        selected_offer_rate: selectedOffer ? money(selectedOffer.carrier_rate, selectedOffer.carrier_rate_currency) : '—',
        selected_margin_label: marginLabel(selectedOffer, currency),
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
        field: 'best_offer_rate_label',
        headerName: 'Лучшая ставка',
        width: 150,
        minWidth: 125,
    },
    {
        field: 'best_margin_label',
        headerName: 'Маржа (лучш.)',
        width: 165,
        minWidth: 140,
    },
    setListColumn({
        field: 'offer_sources_label',
        headerName: 'Источники',
        width: 180,
        minWidth: 140,
    }),
    {
        field: 'selected_offer_rate',
        headerName: 'Выбранная ставка',
        width: 165,
        minWidth: 140,
    },
    {
        field: 'selected_margin_label',
        headerName: 'Маржа (выбр.)',
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


function takePost(post) {
    router.post(route('load-board.take', post.id), {}, { preserveScroll: true });
}

function releasePost(post) {
    router.post(route('load-board.release', post.id), {}, { preserveScroll: true });
}

function prepareAti(post) {
    router.post(route('load-board.ati.prepare', post.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
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

function marginLabel(offer, currency = 'RUB') {
    if (!offer || offer.margin_abs == null || offer.margin_pct == null) {
        return '—';
    }

    return `${money(offer.margin_abs, currency)} · ${offer.margin_pct}%`;
}

function marginLabelFromSummary(summary, currency = 'RUB') {
    if (!summary || summary.best_margin_abs == null || summary.best_margin_pct == null) {
        return '—';
    }

    return `${money(summary.best_margin_abs, currency)} · ${summary.best_margin_pct}%`;
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

watch(quickSearch, (value) => {
    if (gridApi.value) {
        gridApi.value.setGridOption('quickFilterText', value);
    }
});

watch(
    () => [props.posts, props.filter],
    () => {
        syncPostsFromProps();

        if (!allPosts.value.some((post) => Number(post.id) === Number(selectedPostId.value))) {
            selectedPostId.value = allPosts.value[0]?.id ?? null;
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
