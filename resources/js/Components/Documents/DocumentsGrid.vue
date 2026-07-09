<template>
    <div ref="gridSection" class="flex min-h-0 flex-1 flex-col gap-2">
        <div class="flex shrink-0 items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="quickSearch"
                        type="text"
                        :class="crmGridSearchFieldWide"
                        placeholder="Фильтр по реестру"
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

                    <div
                        v-if="showDensityMenu"
                        :class="crmGridDropdown"
                    >
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
                    grid-key="documents"
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

            <div class="flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                <span v-if="copyNotice" class="font-medium text-emerald-600 dark:text-emerald-400">{{ copyNotice }}</span>
                <span>Двойной клик — документы заказа</span>
            </div>
        </div>

        <div
            ref="gridPanel"
            :class="crmGridInnerPanel"
            @contextmenu.capture="suppressNativeContextMenuCapture"
            @contextmenu="onGridPanelEmptyContextMenu"
        >
            <div class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden" :class="densityClass" :style="gridContainerStyle">
                <AgGridVue
                    ref="agGrid"
                    :gridOptions="gridOptions"
                    :rowData="rows"
                    :columnDefs="columnDefs"
                    :defaultColDef="defaultColDef"
                    :domLayout="'normal'"
                    :pagination="false"
                    :animateRows="false"
                    :suppressCellFocus="false"
                    :alwaysShowVerticalScroll="true"
                    style="height: 100%; width: 100%;"
                    @grid-ready="onGridReady"
                    @first-data-rendered="onFirstDataRendered"
                    @cell-double-clicked="onCellDoubleClicked"
                    @cell-value-changed="onCellValueChanged"
                    @column-visible="saveColumnState"
                    @column-resized="saveColumnState"
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
                        <button type="button" class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="closeColumnModal">
                            Закрыть
                        </button>
                        <button type="button" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200" @click="applyColumnModalChanges">
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
import { usePage, router } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
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
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmGridDropdown,
    crmGridInnerPanel,
    crmGridSearchFieldWide,
    crmGridToolbarBtn,
    crmModalPanel,
} from '@/support/crmUi.js';
import { copyTextToClipboard } from '@/support/copyTextToClipboard.js';
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
    canEditTrackReceivedDates: { type: Boolean, default: false },
});

const page = usePage();

const emit = defineEmits(['open-create', 'row-dblclick', 'open-order-documents']);

const CLOSING_EDO_COLUMNS = new Set([
    'customer_upd',
    'customer_act',
    'customer_invoice_factura',
    'carrier_upd',
    'carrier_act',
    'carrier_invoice_factura',
]);

const fallbackColumns = [
    { field: 'order_number', headerName: 'Номер заказа', width: 160, minWidth: 140 },
    { field: 'entered_in_1c', headerName: 'Внесено в 1С', width: 140, minWidth: 120, kind: 'entered-in-1c' },
    { field: 'track_received_date_customer', headerName: 'Оригиналы заказчика', width: 180, minWidth: 150, kind: 'track-received-date' },
    { field: 'track_received_date_carrier', headerName: 'Оригиналы перевозчика', width: 190, minWidth: 160, kind: 'track-received-date' },
    { field: 'customer_invoice', headerName: 'Счёт заказчику', width: 220, minWidth: 180 },
    { field: 'customer_upd', headerName: 'УПД с заказчиком', width: 220, minWidth: 180 },
    { field: 'customer_act', headerName: 'Акт с заказчиком', width: 220, minWidth: 180 },
    { field: 'customer_invoice_factura', headerName: 'Счёт-фактура с заказчиком', width: 260, minWidth: 220 },
    { field: 'customer_request', headerName: 'Заявка заказчика', width: 220, minWidth: 180 },
    { field: 'customer_contract_request', headerName: 'Договор с заказчиком', width: 240, minWidth: 200 },
    { field: 'carrier_invoice', headerName: 'Счёт перевозчику', width: 220, minWidth: 180 },
    { field: 'carrier_upd', headerName: 'УПД с перевозчиком', width: 220, minWidth: 180 },
    { field: 'carrier_act', headerName: 'Акт с перевозчиком', width: 220, minWidth: 180 },
    { field: 'carrier_invoice_factura', headerName: 'Счёт-фактура с перевозчиком', width: 270, minWidth: 230 },
    { field: 'carrier_request', headerName: 'Заявка перевозчика', width: 220, minWidth: 180 },
    { field: 'carrier_contract_request', headerName: 'Договор с перевозчиком', width: 240, minWidth: 200 },
    { field: 'transport_docs', headerName: 'Транспортные документы', width: 250, minWidth: 200 },
    { field: 'etrn_status', headerName: 'Статус ЭТрН', width: 170, minWidth: 150, kind: 'etrn-status' },
    { field: 'etrn_external_id', headerName: 'External ID ЭТрН', width: 220, minWidth: 190, kind: 'text' },
    { field: 'other_docs', headerName: 'Прочее', width: 220, minWidth: 180 },
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
const copyNotice = ref('');

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
    gridPanel,
    bottomScrollbar,
    agGrid,
    gridApi,
});

let copyNoticeTimeout = null;
let saveTimeout = null;
let filterModelSaveTimeout = null;

const {
    contextMenu,
    closeContextMenu: closeRowContextMenu,
    openContextMenu,
    openEmptyContextMenu,
} = useGridContextMenu();

async function copyOrderSummary(row) {
    const copied = await copyTextToClipboard(row?.clipboard_summary ?? '');

    if (!copied) {
        copyNotice.value = 'Нет данных для сводки';
    } else {
        copyNotice.value = 'Сводка скопирована в буфер обмена';
    }

    if (copyNoticeTimeout) {
        clearTimeout(copyNoticeTimeout);
    }

    copyNoticeTimeout = setTimeout(() => {
        copyNotice.value = '';
        copyNoticeTimeout = null;
    }, 2500);
}

/** ПКМ по пустой области грида (не по ячейке) — добавить документ без привязки к строке. */
function onGridPanelEmptyContextMenu(event) {
    openEmptyContextMenu(event, [
        {
            label: 'Добавить документ',
            run: () => {
                emit('open-create', null);
            },
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

    if (row?.order_id) {
        items.push(
            {
                label: 'Открыть заказ',
                run: () => {
                    emit('row-dblclick', row);
                    window.open(route('orders.edit', row.order_id), '_blank', 'noopener,noreferrer');
                },
            },
            {
                label: 'Документы и печатные формы…',
                run: () => {
                    window.open(`${route('orders.edit', row.order_id)}?tab=documents`, '_blank', 'noopener,noreferrer');
                },
            },
            {
                label: 'Сводка по перевозке',
                disabled: !row?.clipboard_summary,
                run: () => {
                    void copyOrderSummary(row);
                },
            },
        );
    }

    items.push({
        label: 'Добавить документ',
        run: () => {
            emit('open-create', row?.order_id ?? null);
        },
    });

    openContextMenu(ev, items);
}

const documentColumnFields = fallbackColumns
    .filter((column) => column.field !== 'order_number' && !column.kind)
    .map((column) => column.field);

function isDocumentColumnNotRequired(row, field) {
    return row?.column_applicable?.[field] === false;
}

function createNotRequiredLabel() {
    const span = document.createElement('span');
    span.className = 'text-xs italic text-zinc-400 dark:text-zinc-500';
    span.textContent = 'не требуется';

    return span;
}

function maxDocumentItemsInRow(row) {
    if (!row) {
        return 1;
    }

    return Math.max(
        1,
        ...documentColumnFields.map((field) => normalizeItems(row[field]).length),
    );
}

function resolveDocumentRowHeight(row) {
    const itemCount = maxDocumentItemsInRow(row);
    const base = Number(resolveGridDensity(currentDensity.value).rowHeight) || 40;

    if (itemCount <= 1) {
        return base;
    }

    return Math.max(base, 14 + itemCount * 22);
}

const gridOptions = {
    theme: 'legacy',
    localeText: agGridLocaleRu,
    animateRows: false,
    preventDefaultOnContextMenu: true,
    onCellContextMenu,
    onDisplayedColumnsChanged: () => scheduleLayoutRefresh(),
    getRowHeight: (params) => resolveDocumentRowHeight(params.data),
};

function scheduleLayoutRefresh() {
    requestAnimationFrame(() => {
        refreshAgGridPanelLayout();
        requestAnimationFrame(() => {
            refreshAgGridPanelLayout();
        });
    });
}

const storageKey = computed(() => `documents_grid_state_v2_${props.userId}`);
const quickSearchStorageKey = computed(() => `documents_grid_quick_search_${props.userId}`);
const filterModelStorageKey = computed(() => `documents_grid_filter_model_v1_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const defaultColDef = {
    sortable: true,
    filter: true,
    resizable: true,
    floatingFilter: true,
    suppressSizeToFit: true,
    minWidth: 120,
};

class DateInputEditor {
    init(params) {
        this.input = document.createElement('input');
        this.input.type = 'date';
        this.input.className = 'orders-grid-date-editor';
        this.input.value = params.value ?? '';
    }

    getGui() {
        return this.input;
    }

    afterGuiAttached() {
        this.input.focus();
        this.input.showPicker?.();
    }

    getValue() {
        return this.input.value || null;
    }

    destroy() {}

    isPopup() {
        return false;
    }
}

function trackReceivedNeedsField(columnField) {
    return columnField === 'track_received_date_customer'
        ? 'needs_track_received_date_customer'
        : 'needs_track_received_date_carrier';
}

function isTrackReceivedEditable(columnField, row) {
    if (!props.canEditTrackReceivedDates || !row?.order_id) {
        return false;
    }

    return Boolean(row[trackReceivedNeedsField(columnField)]);
}

function formatTrackReceivedDate(value) {
    if (value == null || value === '') {
        return '';
    }

    const iso = String(value).slice(0, 10);
    const [year, month, day] = iso.split('-');

    if (!year || !month || !day) {
        return iso;
    }

    return `${day}.${month}.${year}`;
}

const columnDefs = computed(() => {
    void gridViewsRevision.value;
    const saved = readPersistedAgGridColumnState(storageKey.value);

    const baseColumns = fallbackColumns.map((column) => {
        const colDef = {
            field: column.field,
            headerName: column.headerName,
            width: column.width,
            minWidth: column.minWidth,
            valueGetter: (params) => valueForQuickFilter(params.data, column.field),
        };

        if (column.field === 'order_number') {
            colDef.pinned = 'left';
            colDef.lockPinned = true;
            colDef.cellClass = 'orders-grid-order-number-cell';
            colDef.headerClass = 'orders-grid-order-number-header';
            colDef.cellRenderer = orderCellRenderer;
            return colDef;
        }

        if (column.kind === 'text') {
            colDef.cellRenderer = (params) => textCellRenderer(params.data, column.field);
            return colDef;
        }
        if (column.kind === 'entered-in-1c') {
            colDef.editable = true;
            colDef.singleClickEdit = true;
            colDef.cellEditor = 'agSelectCellEditor';
            colDef.cellEditorParams = { values: ['да', 'нет'] };
            colDef.cellClass = () => ['orders-grid-editable-cell'];
            colDef.valueGetter = (params) => params.data?.entered_in_1c ?? 'нет';
            applyAgSetListColumn(colDef, {
                values: ['да', 'нет'],
                filterValueGetter: (params) => params.data?.entered_in_1c ?? 'нет',
                floatingFilterRow: true,
            });
            return colDef;
        }
        if (column.kind === 'track-received-date') {
            const needsField = trackReceivedNeedsField(column.field);

            colDef.editable = (params) => isTrackReceivedEditable(column.field, params.data);
            colDef.singleClickEdit = true;
            colDef.cellEditor = DateInputEditor;
            colDef.valueGetter = (params) => {
                if (!params.data?.[needsField]) {
                    return null;
                }

                return params.data?.[column.field] ?? null;
            };
            colDef.valueFormatter = (params) => {
                if (!params.data?.[needsField]) {
                    return 'не требуется';
                }

                return formatTrackReceivedDate(params.value);
            };
            colDef.cellClass = (params) => {
                const classes = [];

                if (!params.data?.[needsField]) {
                    return classes;
                }

                if (isTrackReceivedEditable(column.field, params.data)) {
                    classes.push('orders-grid-editable-cell');
                }

                if (!params.value) {
                    classes.push('documents-grid-track-received--pending');
                }

                return classes;
            };
            colDef.filter = 'agDateColumnFilter';

            return colDef;
        }
        if (column.kind === 'etrn-status') {
            colDef.cellRenderer = (params) => etrnStatusCellRenderer(params.data, column.field);
            return colDef;
        }

        colDef.cellRenderer = (params) => documentsCellRenderer(params.data, column.field);

        if (CLOSING_EDO_COLUMNS.has(column.field)) {
            colDef.cellClass = (params) => (
                params.data?.column_edo_fulfilment?.[column.field]
                    ? 'documents-grid-edo-fulfilment'
                    : ''
            );
        }

        return colDef;
    });

    if (!saved?.length) {
        return baseColumns;
    }

    const savedFiltered = saved.filter((r) => r.colId !== '__actions');
    const dataCols = baseColumns.filter((c) => c.field);
    const fields = dataCols.map((c) => c.field);
    const { orderedFields, byColId } = buildLayoutIndex(fields, savedFiltered);
    const byField = new Map(dataCols.map((d) => [d.field, d]));

    return orderedFields
        .map((field) => {
            const def = byField.get(field);
            return def ? applySavedToColDef(def, byColId.get(field)) : null;
        })
        .filter(Boolean);
});

function normalizeItems(items) {
    return Array.isArray(items) ? items : [];
}

function valueForQuickFilter(row, field) {
    if (!row) {
        return '—';
    }

    if (field === 'order_number') {
        return row.order_number ?? '—';
    }

    const rawValue = row[field];
    if (typeof rawValue === 'string' || typeof rawValue === 'number') {
        return String(rawValue);
    }

    const items = normalizeItems(row[field]);
    if (items.length === 0) {
        if (isDocumentColumnNotRequired(row, field)) {
            return 'не требуется';
        }

        return '—';
    }

    return items.map((item) => item.label).join(', ');
}

function textCellRenderer(row, field) {
    const value = row?.[field];
    const span = document.createElement('span');
    span.className = 'text-xs text-zinc-700 dark:text-zinc-200';
    span.textContent = (typeof value === 'string' || typeof value === 'number') && String(value) !== ''
        ? String(value)
        : '—';

    return span;
}

function etrnStatusCellRenderer(row, field) {
    const value = row?.[field];
    const normalized = (typeof value === 'string' ? value : '').toLowerCase();
    const span = document.createElement('span');
    span.className = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium';
    span.textContent = value && String(value) !== '' ? String(value) : '—';

    if (normalized.includes('подписан')) {
        span.className += ' bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300';
    } else if (normalized.includes('отправлен') || normalized.includes('ожидает') || normalized.includes('готов')) {
        span.className += ' bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300';
    } else if (normalized.includes('отклон') || normalized.includes('отмен')) {
        span.className += ' bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300';
    } else {
        span.className += ' bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
    }

    return span;
}

function documentsCellRenderer(row, field) {
    const container = document.createElement('div');
    container.className = 'flex h-full min-h-0 flex-col items-start justify-center gap-0.5 py-1';

    const items = normalizeItems(row?.[field]);
    if (items.length === 0) {
        if (isDocumentColumnNotRequired(row, field)) {
            container.appendChild(createNotRequiredLabel());
            return container;
        }

        const empty = document.createElement('span');
        empty.className = 'text-xs text-zinc-400';
        empty.textContent = '—';
        container.appendChild(empty);
        return container;
    }

    if (items.length >= 3) {
        const summary = document.createElement('button');
        summary.type = 'button';
        summary.className = 'text-left text-xs font-medium text-sky-700 underline dark:text-sky-300';
        summary.textContent = `${items.length} док.`;
        summary.title = items.map((item) => item.label).join('\n');
        summary.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (row?.order_id) {
                emit('open-order-documents', row);
            }
        });
        container.appendChild(summary);

        items.slice(0, 2).forEach((item) => {
            const link = document.createElement('a');
            link.href = item.preview_url ?? item.order_url ?? '#';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'block max-w-full whitespace-normal break-words text-[11px] leading-4 text-sky-700 underline dark:text-sky-300';
            link.textContent = item.label;
            container.appendChild(link);
        });

        return container;
    }

    items.forEach((item) => {
        const link = document.createElement('a');
        link.href = item.preview_url ?? item.order_url ?? '#';
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'block max-w-full whitespace-normal break-words text-xs leading-5 text-sky-700 underline dark:text-sky-300';
        link.textContent = item.label;
        container.appendChild(link);
    });

    return container;
}

function orderCellRenderer(params) {
    const wrapper = document.createElement('div');
    wrapper.className = 'flex h-full items-center';
    const link = document.createElement('a');
    link.href = params.data?.order_edit_url ?? '#';
    link.target = '_blank';
    link.rel = 'noopener noreferrer';

    const needsAttention = params.data?.missing_documents_after_unloading === true;
    const missingLabels = Array.isArray(params.data?.missing_document_labels)
        ? params.data.missing_document_labels.filter(Boolean)
        : [];

    link.className = needsAttention
        ? 'font-semibold text-rose-700 underline decoration-rose-400/70 dark:text-rose-300 dark:decoration-rose-500/60'
        : 'font-medium text-sky-700 underline dark:text-sky-300';

    if (needsAttention) {
        link.title = missingLabels.length > 0
            ? `Не хватает документов: ${missingLabels.join(', ')}`
            : 'Не хватает документов';
    }

    link.textContent = params.data?.order_number ?? '—';
    wrapper.appendChild(link);

    return wrapper;
}

function syncModalColumnsWithGrid() {
    if (!gridApi.value) {
        modalColumns.value = fallbackColumns.map((column) => ({ ...column, visible: true }));
        return;
    }

    const available = new Map(fallbackColumns.map((column) => [column.field, column]));
    modalColumns.value = gridApi.value
        .getAllGridColumns()
        .map((column) => {
            const info = available.get(column.getColId());
            if (!info) {
                return null;
            }

            return {
                ...info,
                visible: column.isVisible(),
                width: column.getActualWidth(),
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

function resetColumns() {
    if (!gridApi.value) {
        return;
    }

    gridApi.value.resetColumnState();
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
    gridViewsRevision.value++;
}

function onGridViewApplied() {
    gridViewsRevision.value++;

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
    } catch (error) {
        console.error('Error loading documents grid filter model', error);
    }
}

function onFilterChanged() {
    persistFilterModel();
}

async function onCellValueChanged(event) {
    const field = event.colDef?.field;

    if (field === 'entered_in_1c') {
        if (event.newValue === event.oldValue) {
            return;
        }

        const orderId = event.data?.order_id;
        if (!orderId) {
            return;
        }

        try {
            const response = await window.axios.patch(route('documents.orders.entered-in-1c', orderId), {
                entered_in_1c: event.newValue,
            });
            event.node?.setDataValue('entered_in_1c', response.data?.entered_in_1c ?? event.newValue);
            router.reload({ only: ['rows'], preserveScroll: true });
        } catch (error) {
            event.node?.setDataValue('entered_in_1c', event.oldValue ?? 'нет');
            console.error('Failed to update entered_in_1c', error);
        }

        return;
    }

    if (field === 'track_received_date_customer' || field === 'track_received_date_carrier') {
        if (event.newValue === event.oldValue) {
            return;
        }

        const orderId = event.data?.order_id;
        if (!orderId || !props.canEditTrackReceivedDates) {
            return;
        }

        try {
            const response = await window.axios.patch(route('documents.orders.track-received', orderId), {
                field,
                value: event.newValue,
            });
            event.node?.setDataValue(field, response.data?.value ?? null);
        } catch (error) {
            event.node?.setDataValue(field, event.oldValue ?? null);
            console.error('Failed to update track received date', error);
        }
    }
}

function onCellDoubleClicked(event) {
    if (event.data?.order_id) {
        emit('open-order-documents', event.data);
    }
}

async function onGridReady(params) {
    gridApi.value = params.api;
    const savedQuickSearch = localStorage.getItem(quickSearchStorageKey.value);
    quickSearch.value = typeof savedQuickSearch === 'string' ? savedQuickSearch : '';

    if (quickSearch.value.trim() !== '') {
        gridApi.value.setGridOption('quickFilterText', quickSearch.value);
    }

    if (!readPersistedAgGridColumnState(storageKey.value)?.length) {
        resetColumns();
    }

    const savedDensity = readPersistedAgGridDensity(props.userId, page.props.auth?.user);
    currentDensity.value = savedDensity;

    loadFilterModel();

    await nextTick();
    refreshAgGridPanelLayout();
}

function onFirstDataRendered() {
    scheduleLayoutRefresh();
}

watch(quickSearch, (value) => {
    localStorage.setItem(quickSearchStorageKey.value, value ?? '');
    if (gridApi.value) {
        gridApi.value.setGridOption('quickFilterText', value ?? '');
    }
});

watch(() => props.rows, async () => {
    await nextTick();
    gridApi.value?.resetRowHeights();
    refreshAgGridPanelLayout();
}, { deep: true });

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

onMounted(() => {
    window.addEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});

onUnmounted(() => {
    window.removeEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    if (filterModelSaveTimeout) {
        clearTimeout(filterModelSaveTimeout);
    }
});
</script>

