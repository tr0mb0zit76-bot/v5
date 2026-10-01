<template>
    <div ref="gridSection" class="flex min-h-0 flex-1 flex-col gap-2">
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="quickSearch"
                        type="text"
                        :class="crmGridSearchFieldWide"
                        placeholder="Номер, ИНН, контрагент…"
                    >
                </div>

                <button type="button" :class="crmGridToolbarBtn" @click="openColumnModal">
                    <Settings2 class="h-4 w-4" />
                    Колонки
                </button>

                <div class="relative">
                    <button
                        type="button"
                        :class="`${crmGridToolbarBtn} px-2`"
                        :title="`Плотность таблицы: ${currentDensityLabel}`"
                        @click="toggleDensityMenu"
                    >
                        <Rows3 class="h-4 w-4" />
                    </button>

                    <div v-if="showDensityMenu" :class="crmGridDropdown">
                        <button
                            v-for="option in gridDensityOptions"
                            :key="option.key"
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            @click="applyDensity(option.key)"
                        >
                            <span>{{ option.label }}</span>
                            <span v-if="currentDensity === option.key" class="text-xs text-zinc-500 dark:text-zinc-400">Текущая</span>
                        </button>
                    </div>
                </div>

                <GridViewsBar
                    grid-key="epd"
                    :user-id="userId"
                    :get-grid-api="() => gridApi"
                    :column-storage-key="storageKey"
                    :filter-storage-key="filterModelStorageKey"
                    :quick-search-storage-key="quickSearchStorageKey"
                    :quick-search="quickSearch"
                    :on-reset-defaults="resetGridViewState"
                    @update:quick-search="quickSearch = $event"
                    @applied="onGridViewApplied"
                    @pinned-changed="onGridViewsPinnedChanged"
                />
            </div>

            <div class="flex shrink-0 items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                <GridRowStatus
                    :get-grid-api="() => gridApi"
                    :total-count="displayRows.length"
                    :quick-search="quickSearch"
                />
            </div>
        </div>

        <div
            ref="gridPanel"
            :class="crmGridInnerPanel"
            @contextmenu.capture="suppressNativeContextMenuCapture"
            @contextmenu="onGridPanelEmptyContextMenu"
        >
            <div
                class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden"
                :class="densityClass"
                :style="gridContainerStyle"
            >
                <AgGridVue
                    ref="agGrid"
                    :gridOptions="gridOptions"
                    :rowData="displayRows"
                    :columnDefs="columnDefs"
                    :defaultColDef="defaultColDef"
                    domLayout="normal"
                    :pagination="false"
                    :animateRows="false"
                    :suppressCellFocus="false"
                    :alwaysShowVerticalScroll="true"
                    style="height: 100%; width: 100%;"
                    @grid-ready="onGridReady"
                    @first-data-rendered="onFirstDataRendered"
                    @cell-double-clicked="onCellDoubleClicked"
                    @column-visible="saveColumnState"
                    @column-resized="onColumnResized"
                    @column-moved="saveColumnState"
                    @sort-changed="saveColumnState"
                    @filter-changed="onFilterChanged"
                />
            </div>

            <div ref="bottomScrollbar" class="orders-grid-bottom-scroll" @scroll="onBottomScrollbarScroll">
                <div class="orders-grid-bottom-scroll-inner" :style="{ width: `${bottomScrollbarWidth}px` }" />
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showColumnModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeColumnModal"
            >
                <div :class="`${crmModalPanel} w-full max-w-2xl shadow-2xl`">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <div class="text-lg font-semibold">Настройка колонок</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">Видимость и порядок колонок</div>
                        </div>
                        <button type="button" class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closeColumnModal">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="grid max-h-[60vh] grid-cols-1 gap-3 overflow-y-auto p-5 md:grid-cols-2">
                        <label
                            v-for="column in modalColumns"
                            :key="column.field"
                            class="flex cursor-pointer items-start gap-3 rounded-2xl border border-zinc-200 px-4 py-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60"
                            draggable="true"
                            @dragstart="onColumnDragStart(column.field)"
                            @dragover.prevent
                            @drop="onColumnDrop(column.field)"
                        >
                            <button type="button" class="mt-0.5 cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200" @click.prevent>
                                ⋮⋮
                            </button>
                            <input
                                type="checkbox"
                                class="mt-1 rounded border-zinc-300"
                                :checked="column.visible"
                                @change="toggleColumnVisibility(column.field)"
                            >
                            <div class="min-w-0">
                                <div class="text-sm font-medium">{{ column.headerName }}</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <button
                            type="button"
                            class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            @click="closeColumnModal"
                        >
                            Закрыть
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            @click="applyColumnModalChanges"
                        >
                            Применить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <GridContextMenu
            :open="contextMenu.open"
            :x="contextMenu.x"
            :y="contextMenu.y"
            :items="contextMenu.items"
            @close="closeRowContextMenu"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';
import { Rows3, Search, Settings2, X } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import { applyAgSetListColumn } from '@/Components/Grid/agSetListFilter.js';
import { applySavedToColDef, buildLayoutIndex, readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridRowStatus from '@/Components/Grid/GridRowStatus.vue';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import {
    crmGridDropdown,
    crmGridInnerPanel,
    crmGridSearchFieldWide,
    crmGridToolbarBtn,
    crmModalPanel,
} from '@/support/crmUi.js';
import {
    CRM_AG_GRID_DENSITY_CHANGED,
    readPersistedAgGridDensity,
    schedulePersistAgGridDensityToProfile,
    writeLocalAgGridDensity,
} from '@/support/agGridUserDensity.js';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
    rows: { type: Array, default: () => [] },
    userId: { type: [String, Number], default: 'guest' },
    /** documents = плоский реестр; by_order = заказ → документы */
    viewMode: { type: String, default: 'by_order' },
});

const emit = defineEmits(['link-request', 'unlink-request', 'open-order']);

const page = usePage();

const fallbackColumns = [
    { field: '_expand', headerName: '', width: 44, minWidth: 44, maxWidth: 48, kind: 'expand' },
    { field: 'order_number', headerName: 'Заказ CRM', width: 140, minWidth: 120 },
    { field: 'document_type_label', headerName: 'Тип', width: 180, minWidth: 140 },
    { field: 'epd_number', headerName: '№ ЭПД', width: 120, minWidth: 100 },
    { field: 'epd_date', headerName: 'Дата', width: 110, minWidth: 100 },
    { field: 'current_step', headerName: 'Шаг', width: 160, minWidth: 120 },
    { field: 'shipper_name', headerName: 'ГО', width: 180, minWidth: 140 },
    { field: 'shipper_inn', headerName: 'ИНН ГО', width: 120, minWidth: 100 },
    { field: 'carrier_name', headerName: 'Перевозчик', width: 180, minWidth: 140 },
    { field: 'carrier_inn', headerName: 'ИНН перевозчика', width: 130, minWidth: 110 },
    { field: 'consignee_name', headerName: 'ГП', width: 160, minWidth: 120 },
    { field: 'link_status', headerName: 'Связь', width: 110, minWidth: 90 },
    { field: '_actions', headerName: '', width: 100, minWidth: 90, maxWidth: 120, kind: 'actions' },
];

const agGrid = ref(null);
const gridApi = ref(null);
const showColumnModal = ref(false);
const showDensityMenu = ref(false);
const modalColumns = ref([]);
const draggedColumnField = ref(null);
const quickSearch = ref('');
const gridViewsRevision = ref(0);
const currentDensity = ref(defaultGridDensity);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const expandedOrderKeys = ref(new Set(['unlinked']));

let saveTimeout = null;
let filterModelSaveTimeout = null;

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
    gridPanel,
    gridSection,
    bottomScrollbar,
    agGrid,
    gridApi,
});

const {
    contextMenu,
    closeContextMenu: closeRowContextMenu,
    openContextMenu,
    openEmptyContextMenu,
} = useGridContextMenu();

const storageKey = computed(() => `epd_grid_state_v1_${props.userId}`);
const quickSearchStorageKey = computed(() => `epd_grid_quick_search_${props.userId}`);
const filterModelStorageKey = computed(() => `epd_grid_filter_model_v1_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const normalizedRows = computed(() => props.rows.map((row) => ({
    ...row,
    link_status: row.order_id ? 'связан' : 'без заказа',
    order_number: row.order_number || (row.order_id ? `#${row.order_id}` : ''),
    _rowKind: 'doc',
})));

const orderGroups = computed(() => {
    const linked = new Map();
    const unlinked = [];

    for (const row of normalizedRows.value) {
        if (!row.order_id) {
            unlinked.push(row);
            continue;
        }

        const key = String(row.order_id);
        if (!linked.has(key)) {
            linked.set(key, {
                key,
                order_id: row.order_id,
                order_number: row.order_number,
                docs: [],
                types: new Set(),
                latest_date: row.epd_date || null,
            });
        }

        const group = linked.get(key);
        group.docs.push(row);
        if (row.document_type_label) {
            group.types.add(row.document_type_label);
        }
        if (row.epd_date && (!group.latest_date || String(row.epd_date) > String(group.latest_date))) {
            group.latest_date = row.epd_date;
        }
    }

    const groups = [...linked.values()]
        .map((group) => ({
            ...group,
            types_label: [...group.types].join(' · '),
            docs_count: group.docs.length,
        }))
        .sort((a, b) => String(b.latest_date || '').localeCompare(String(a.latest_date || '')));

    return { groups, unlinked };
});

const displayRows = computed(() => {
    if (props.viewMode !== 'by_order') {
        return normalizedRows.value;
    }

    const out = [];

    if (orderGroups.value.unlinked.length > 0) {
        out.push({
            id: 'group-unlinked',
            _rowKind: 'group',
            _groupKey: 'unlinked',
            order_id: null,
            order_number: 'Без заказа CRM',
            document_type_label: 'входящие без связи',
            epd_number: String(orderGroups.value.unlinked.length),
            epd_date: null,
            current_step: '',
            shipper_name: '',
            shipper_inn: '',
            carrier_name: '',
            carrier_inn: '',
            consignee_name: '',
            link_status: 'без заказа',
            docs_count: orderGroups.value.unlinked.length,
        });

        if (expandedOrderKeys.value.has('unlinked')) {
            for (const doc of orderGroups.value.unlinked) {
                out.push({ ...doc, _rowKind: 'doc', _childOf: 'unlinked' });
            }
        }
    }

    for (const group of orderGroups.value.groups) {
        out.push({
            id: `group-${group.key}`,
            _rowKind: 'group',
            _groupKey: group.key,
            order_id: group.order_id,
            order_number: group.order_number,
            document_type_label: group.types_label,
            epd_number: String(group.docs_count),
            epd_date: group.latest_date,
            current_step: '',
            shipper_name: '',
            shipper_inn: '',
            carrier_name: '',
            carrier_inn: '',
            consignee_name: '',
            link_status: 'связан',
            docs_count: group.docs_count,
        });

        if (expandedOrderKeys.value.has(group.key)) {
            for (const doc of group.docs) {
                out.push({ ...doc, _rowKind: 'doc', _childOf: group.key });
            }
        }
    }

    return out;
});

function uniqueValues(field) {
    const values = new Set();
    for (const row of normalizedRows.value) {
        const value = row?.[field];
        if (value !== null && value !== undefined && String(value).trim() !== '') {
            values.add(String(value));
        }
    }

    return [...values].sort((a, b) => a.localeCompare(b, 'ru'));
}

function formatDate(value) {
    if (!value) {
        return '';
    }
    const raw = String(value).slice(0, 10);
    const [y, m, d] = raw.split('-');
    if (!y || !m || !d) {
        return raw;
    }

    return `${d}.${m}.${y}`;
}

function isGroupExpanded(key) {
    return expandedOrderKeys.value.has(String(key));
}

function toggleGroup(key) {
    const next = new Set(expandedOrderKeys.value);
    const normalized = String(key);
    if (next.has(normalized)) {
        next.delete(normalized);
    } else {
        next.add(normalized);
    }
    expandedOrderKeys.value = next;
}

function expandCellRenderer(params) {
    const wrap = document.createElement('div');
    wrap.className = 'flex h-full items-center justify-center';

    if (params.data?._rowKind !== 'group' || props.viewMode !== 'by_order') {
        if (params.data?._childOf) {
            wrap.className = 'flex h-full items-center justify-center text-zinc-300 dark:text-zinc-600';
            wrap.textContent = '·';
        }

        return wrap;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'inline-flex h-7 w-7 items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800';
    btn.title = isGroupExpanded(params.data._groupKey) ? 'Свернуть' : 'Раскрыть';
    btn.textContent = isGroupExpanded(params.data._groupKey) ? '▾' : '▸';
    btn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        toggleGroup(params.data._groupKey);
    });
    wrap.appendChild(btn);

    return wrap;
}

function actionsCellRenderer(params) {
    const wrap = document.createElement('div');
    wrap.className = 'flex h-full items-center justify-center gap-1';

    if (params.data?._rowKind === 'group') {
        if (params.data.order_id) {
            const openBtn = document.createElement('button');
            openBtn.type = 'button';
            openBtn.className = 'rounded-lg px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-950/40';
            openBtn.textContent = 'Заказ';
            openBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                emit('open-order', params.data);
            });
            wrap.appendChild(openBtn);
        }

        return wrap;
    }

    if (!params.data?.id || String(params.data.id).startsWith('group-')) {
        return wrap;
    }

    if (!params.data.order_id) {
        const linkBtn = document.createElement('button');
        linkBtn.type = 'button';
        linkBtn.className = 'rounded-lg bg-emerald-700 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-800';
        linkBtn.textContent = 'Связать';
        linkBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            emit('link-request', params.data);
        });
        wrap.appendChild(linkBtn);
    } else {
        const unlinkBtn = document.createElement('button');
        unlinkBtn.type = 'button';
        unlinkBtn.className = 'rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800';
        unlinkBtn.textContent = 'Отвязать';
        unlinkBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            emit('unlink-request', params.data);
        });
        wrap.appendChild(unlinkBtn);
    }

    return wrap;
}

function orderCellRenderer(params) {
    const wrap = document.createElement('div');
    wrap.className = 'flex h-full items-center gap-1';

    if (params.data?._rowKind === 'group') {
        const label = document.createElement('span');
        label.className = params.data._groupKey === 'unlinked'
            ? 'font-semibold text-amber-800 dark:text-amber-200'
            : 'font-semibold text-sky-700 dark:text-sky-300';
        label.textContent = params.value || '—';
        wrap.appendChild(label);

        return wrap;
    }

    if (params.data?._childOf) {
        wrap.className = 'flex h-full items-center gap-1 pl-3 text-zinc-500';
    }

    if (params.data?.order_id) {
        const link = document.createElement('button');
        link.type = 'button';
        link.className = 'font-medium text-sky-700 hover:underline dark:text-sky-300';
        link.textContent = params.value || `#${params.data.order_id}`;
        link.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            emit('open-order', params.data);
        });
        wrap.appendChild(link);
    } else {
        const span = document.createElement('span');
        span.className = 'text-zinc-400';
        span.textContent = 'не связан';
        wrap.appendChild(span);
    }

    return wrap;
}

function onGridPanelEmptyContextMenu(event) {
    openEmptyContextMenu(event, [
        {
            label: 'Сбросить фильтры',
            run: () => resetGridViewState(),
        },
    ]);
}

function onCellContextMenu(params) {
    const ev = params.event;
    if (ev?.preventDefault) {
        ev.preventDefault();
    }

    const row = params.node?.data;
    const items = [];

    if (row?._rowKind === 'group' && row.order_id) {
        items.push({
            label: 'Открыть заказ',
            run: () => emit('open-order', row),
        });
        items.push({
            label: isGroupExpanded(row._groupKey) ? 'Свернуть' : 'Раскрыть документы',
            run: () => toggleGroup(row._groupKey),
        });
    }

    if (row?._rowKind === 'doc') {
        if (row.order_id) {
            items.push({
                label: 'Открыть заказ',
                run: () => emit('open-order', row),
            });
            items.push({
                label: 'Отвязать от заказа',
                run: () => emit('unlink-request', row),
            });
        } else {
            items.push({
                label: 'Связать с заказом…',
                run: () => emit('link-request', row),
            });
        }
    }

    if (items.length === 0) {
        return;
    }

    openContextMenu(ev, items);
}

const gridOptions = {
    theme: 'legacy',
    localeText: agGridLocaleRu,
    animateRows: false,
    preventDefaultOnContextMenu: true,
    onCellContextMenu,
    onDisplayedColumnsChanged: () => scheduleLayoutRefresh(),
    getRowId: (params) => String(params.data?.id ?? ''),
    getRowClass: (params) => {
        if (params.data?._rowKind === 'group') {
            return params.data._groupKey === 'unlinked'
                ? 'epd-grid-row--group-unlinked'
                : 'epd-grid-row--group';
        }
        if (params.data?._childOf) {
            return 'epd-grid-row--child';
        }

        return null;
    },
    isExternalFilterPresent: () => quickSearch.value.trim().length > 0,
    doesExternalFilterPass: (node) => {
        const query = quickSearch.value.trim().toLowerCase();
        if (!query) {
            return true;
        }

        const data = node.data ?? {};
        const haystack = [
            data.order_number,
            data.document_type_label,
            data.epd_number,
            data.ib_number,
            data.current_step,
            data.shipper_name,
            data.shipper_inn,
            data.carrier_name,
            data.carrier_inn,
            data.consignee_name,
            data.link_status,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    },
};

function scheduleLayoutRefresh() {
    requestAnimationFrame(() => {
        refreshAgGridPanelLayout();
        requestAnimationFrame(() => {
            refreshAgGridPanelLayout();
        });
    });
}

const defaultColDef = {
    sortable: true,
    filter: true,
    resizable: true,
    floatingFilter: true,
    suppressSizeToFit: true,
    minWidth: 90,
};

const columnDefs = computed(() => {
    void gridViewsRevision.value;
    void props.viewMode;
    void expandedOrderKeys.value;
    void normalizedRows.value;

    const saved = readPersistedAgGridColumnState(storageKey.value);
    const typeValues = uniqueValues('document_type_label');
    const stepValues = uniqueValues('current_step');
    const linkValues = ['связан', 'без заказа'];

    const baseColumns = fallbackColumns.map((column) => {
        const colDef = {
            field: column.field,
            headerName: column.headerName,
            width: column.width,
            minWidth: column.minWidth,
            maxWidth: column.maxWidth,
        };

        if (column.kind === 'expand') {
            colDef.sortable = false;
            colDef.filter = false;
            colDef.floatingFilter = false;
            colDef.resizable = false;
            colDef.pinned = 'left';
            colDef.lockPinned = true;
            colDef.suppressHeaderMenuButton = true;
            colDef.suppressColumnsToolPanel = true;
            colDef.cellRenderer = expandCellRenderer;
            colDef.hide = props.viewMode !== 'by_order';

            return colDef;
        }

        if (column.kind === 'actions') {
            colDef.sortable = false;
            colDef.filter = false;
            colDef.floatingFilter = false;
            colDef.resizable = false;
            colDef.pinned = 'right';
            colDef.lockPinned = true;
            colDef.suppressHeaderMenuButton = true;
            colDef.cellRenderer = actionsCellRenderer;

            return colDef;
        }

        if (column.field === 'order_number') {
            colDef.pinned = 'left';
            colDef.lockPinned = true;
            colDef.cellClass = 'orders-grid-order-number-cell';
            colDef.headerClass = 'orders-grid-order-number-header';
            colDef.cellRenderer = orderCellRenderer;
            applyAgSetListColumn(colDef, {
                values: uniqueValues('order_number').length
                    ? [...uniqueValues('order_number'), 'не связан']
                    : ['не связан'],
                filterValueGetter: (params) => {
                    if (params.data?._rowKind === 'group') {
                        return params.data.order_number || '';
                    }

                    return params.data?.order_id
                        ? (params.data.order_number || `#${params.data.order_id}`)
                        : 'не связан';
                },
                floatingFilterRow: true,
            });

            return colDef;
        }

        if (column.field === 'document_type_label') {
            applyAgSetListColumn(colDef, {
                values: typeValues,
                floatingFilterRow: true,
            });

            return colDef;
        }

        if (column.field === 'current_step') {
            applyAgSetListColumn(colDef, {
                values: stepValues.length ? stepValues : ['—'],
                filterValueGetter: (params) => params.data?.current_step || '—',
                floatingFilterRow: true,
            });

            return colDef;
        }

        if (column.field === 'link_status') {
            applyAgSetListColumn(colDef, {
                values: linkValues,
                floatingFilterRow: true,
            });

            return colDef;
        }

        if (column.field === 'epd_date') {
            colDef.filter = 'agDateColumnFilter';
            colDef.valueFormatter = (params) => formatDate(params.value);

            return colDef;
        }

        return colDef;
    });

    if (!saved?.length) {
        return baseColumns;
    }

    const savedFiltered = saved.filter((row) => row.colId !== '_actions' && row.colId !== '_expand');
    const dataCols = baseColumns.filter((col) => col.field && col.field !== '_actions' && col.field !== '_expand');
    const specialCols = baseColumns.filter((col) => col.field === '_expand' || col.field === '_actions');
    const fields = dataCols.map((col) => col.field);
    const { orderedFields, byColId } = buildLayoutIndex(fields, savedFiltered);
    const byField = new Map(dataCols.map((def) => [def.field, def]));

    const ordered = orderedFields
        .map((field) => {
            const def = byField.get(field);

            return def ? applySavedToColDef({ ...def }, byColId.get(field)) : null;
        })
        .filter(Boolean);

    const expandCol = specialCols.find((col) => col.field === '_expand');
    const actionsCol = specialCols.find((col) => col.field === '_actions');

    return [
        ...(expandCol ? [expandCol] : []),
        ...ordered,
        ...(actionsCol ? [actionsCol] : []),
    ];
});

function syncModalColumnsWithGrid() {
    if (!gridApi.value) {
        modalColumns.value = fallbackColumns
            .filter((column) => column.kind !== 'expand' && column.kind !== 'actions')
            .map((column) => ({
                field: column.field,
                headerName: column.headerName,
                visible: true,
                width: column.width,
            }));

        return;
    }

    const infoByField = new Map(
        fallbackColumns
            .filter((column) => column.kind !== 'expand' && column.kind !== 'actions')
            .map((column) => [column.field, column]),
    );

    modalColumns.value = gridApi.value
        .getColumnState()
        .map((state) => {
            const info = infoByField.get(state.colId);
            if (!info) {
                return null;
            }

            const column = gridApi.value.getColumn(state.colId);

            return {
                field: info.field,
                headerName: info.headerName,
                visible: column ? column.isVisible() : !state.hide,
                width: column ? column.getActualWidth() : state.width,
            };
        })
        .filter(Boolean);
}

function saveColumnState() {
    if (!gridApi.value) {
        return;
    }

    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }

    saveTimeout = setTimeout(() => {
        const state = gridApi.value.getColumnState().map((column, order) => ({
            colId: column.colId,
            hide: column.hide,
            width: column.width,
            order,
            sort: column.sort ?? null,
            sortIndex: column.sortIndex ?? null,
        }));
        localStorage.setItem(storageKey.value, JSON.stringify(state));
        syncModalColumnsWithGrid();
        refreshAgGridPanelLayout();
    }, 250);
}

function onColumnResized(event) {
    if (event?.finished === false) {
        return;
    }

    if (event?.source === 'flex' || event?.source === 'gridInitializing') {
        return;
    }

    saveColumnState();
}

function resetColumns() {
    if (!gridApi.value) {
        return;
    }

    gridApi.value.resetColumnState();
    localStorage.removeItem(storageKey.value);
    saveColumnState();
}

function resetGridViewState() {
    resetColumns();

    if (gridApi.value) {
        gridApi.value.setFilterModel({});
    }

    localStorage.removeItem(filterModelStorageKey.value);
    quickSearch.value = '';
    localStorage.setItem(quickSearchStorageKey.value, '');
    gridViewsRevision.value += 1;
}

function onGridViewApplied() {
    gridViewsRevision.value += 1;
    nextTick(() => {
        refreshAgGridPanelLayout();
    });
}

function onGridViewsPinnedChanged() {
    router.reload({ preserveScroll: true });
}

function applyDensity(densityKey) {
    currentDensity.value = resolveGridDensity(densityKey).key;
    writeLocalAgGridDensity(props.userId, currentDensity.value);
    schedulePersistAgGridDensityToProfile(currentDensity.value);
    showDensityMenu.value = false;
    nextTick(() => {
        gridApi.value?.resetRowHeights();
        refreshAgGridPanelLayout();
    });
}

function toggleDensityMenu() {
    showDensityMenu.value = !showDensityMenu.value;
}

function openColumnModal() {
    showDensityMenu.value = false;
    syncModalColumnsWithGrid();
    showColumnModal.value = true;
}

function closeColumnModal() {
    showColumnModal.value = false;
    draggedColumnField.value = null;
}

function toggleColumnVisibility(field) {
    modalColumns.value = modalColumns.value.map((column) => (
        column.field === field
            ? { ...column, visible: !column.visible }
            : column
    ));
}

function onColumnDragStart(field) {
    draggedColumnField.value = field;
}

function onColumnDrop(targetField) {
    if (!draggedColumnField.value || draggedColumnField.value === targetField) {
        draggedColumnField.value = null;

        return;
    }

    const reordered = [...modalColumns.value];
    const from = reordered.findIndex((column) => column.field === draggedColumnField.value);
    const to = reordered.findIndex((column) => column.field === targetField);
    if (from === -1 || to === -1) {
        draggedColumnField.value = null;

        return;
    }

    const [dragged] = reordered.splice(from, 1);
    reordered.splice(to, 0, dragged);
    modalColumns.value = reordered;
    draggedColumnField.value = null;
}

function applyColumnModalChanges() {
    if (!gridApi.value) {
        showColumnModal.value = false;

        return;
    }

    gridApi.value.applyColumnState({
        state: modalColumns.value.map((column) => ({
            colId: column.field,
            hide: !column.visible,
            width: column.width,
        })),
        applyOrder: true,
    });
    saveColumnState();
    showColumnModal.value = false;
}

function persistFilterModel() {
    if (!gridApi.value) {
        return;
    }

    if (filterModelSaveTimeout) {
        clearTimeout(filterModelSaveTimeout);
    }

    filterModelSaveTimeout = setTimeout(() => {
        const model = gridApi.value.getFilterModel();
        localStorage.setItem(filterModelStorageKey.value, JSON.stringify(model ?? {}));
    }, 250);
}

function loadFilterModel() {
    if (!gridApi.value) {
        return;
    }

    const raw = localStorage.getItem(filterModelStorageKey.value);
    if (!raw) {
        return;
    }

    try {
        const model = JSON.parse(raw);
        if (model && typeof model === 'object') {
            gridApi.value.setFilterModel(model);
        }
    } catch {
        // ignore corrupt local state
    }
}

function onFilterChanged() {
    persistFilterModel();
}

function onGridReady(params) {
    gridApi.value = params.api;
    loadFilterModel();

    const savedQuick = localStorage.getItem(quickSearchStorageKey.value);
    if (savedQuick) {
        quickSearch.value = savedQuick;
    }

    refreshAgGridPanelLayout();
}

function onFirstDataRendered() {
    nextTick(() => {
        refreshAgGridPanelLayout();
    });
}

function onCellDoubleClicked(event) {
    const row = event?.data;
    if (!row) {
        return;
    }

    if (row._rowKind === 'group') {
        toggleGroup(row._groupKey);

        return;
    }

    if (row.order_id) {
        emit('open-order', row);
    } else if (row._rowKind === 'doc') {
        emit('link-request', row);
    }
}

function onExternalAgGridDensityChange(event) {
    const detail = event?.detail;
    if (!detail) {
        return;
    }

    const key = resolveGridDensity(detail).key;
    if (key === currentDensity.value) {
        return;
    }

    currentDensity.value = key;
    nextTick(() => {
        gridApi.value?.resetRowHeights();
        refreshAgGridPanelLayout();
    });
}

watch(quickSearch, (value) => {
    localStorage.setItem(quickSearchStorageKey.value, value ?? '');
    gridApi.value?.onFilterChanged();
});

watch(
    () => props.rows,
    () => {
        nextTick(() => {
            loadFilterModel();
            refreshAgGridPanelLayout();
        });
    },
);

watch(
    () => props.viewMode,
    () => {
        nextTick(() => {
            gridApi.value?.refreshCells({ force: true });
            refreshAgGridPanelLayout();
        });
    },
);

onMounted(() => {
    currentDensity.value = readPersistedAgGridDensity(props.userId, page.props.auth?.user);
    window.addEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});

onUnmounted(() => {
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    if (filterModelSaveTimeout) {
        clearTimeout(filterModelSaveTimeout);
    }
    window.removeEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});
</script>

<style>
.epd-grid-row--group .ag-cell {
    font-weight: 600;
    background: rgb(250 250 250 / 0.9);
}

.epd-grid-row--group-unlinked .ag-cell {
    background: rgb(255 251 235 / 0.85);
}

.dark .epd-grid-row--group .ag-cell {
    background: rgb(24 24 27 / 0.55);
}

.dark .epd-grid-row--group-unlinked .ag-cell {
    background: rgb(69 26 3 / 0.25);
}

.epd-grid-row--child .ag-cell {
    background: rgb(255 255 255 / 0.6);
}

.dark .epd-grid-row--child .ag-cell {
    background: transparent;
}
</style>
