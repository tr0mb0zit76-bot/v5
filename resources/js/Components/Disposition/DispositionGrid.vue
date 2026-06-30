<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <div class="flex shrink-0 items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="quickSearch"
                        type="text"
                        :class="crmGridSearchField"
                    >
                </div>

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
                            <span
                                v-if="currentDensity === option.key"
                                class="text-xs text-zinc-500 dark:text-zinc-400"
                            >Текущая</span>
                        </button>
                    </div>
                </div>

                <GridViewsBar
                    grid-key="disposition"
                    :user-id="userId"
                    :get-grid-api="() => gridApi"
                    :quick-search-storage-key="quickSearchStorageKey"
                    :quick-search="quickSearch"
                    :on-reset-defaults="resetGridViewState"
                    @update:quick-search="quickSearch = $event"
                    @pinned-changed="onGridViewsPinnedChanged"
                />
            </div>

            <div class="flex flex-col items-end gap-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                <span>Показано: {{ displayedRowCount }} из {{ rows.length }} · «В пути»</span>
                <span class="hidden text-[11px] sm:inline">
                    Клик — правка · Tab — следующая ячейка · Enter — сохранить
                </span>
            </div>
        </div>

        <p
            v-if="lastSaveError"
            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200"
        >
            {{ lastSaveError }}
        </p>

        <div
            ref="gridPanel"
            :class="crmGridInnerPanel"
            @contextmenu.capture="suppressNativeContextMenuCapture"
        >
            <div
                class="ag-theme-alpine orders-grid-theme disposition-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden"
                :style="gridContainerStyle"
                :class="densityClass"
            >
                <AgGridVue
                    ref="agGrid"
                    :grid-options="gridOptions"
                    :row-data="rows"
                    :column-defs="columnDefs"
                    :default-col-def="defaultColDef"
                    dom-layout="normal"
                    :pagination="false"
                    :animate-rows="false"
                    :suppress-cell-focus="false"
                    :suppress-horizontal-scroll="false"
                    :always-show-vertical-scroll="true"
                    :stop-editing-when-cells-lose-focus="true"
                    style="height: 100%; width: 100%;"
                    @grid-ready="onGridReady"
                    @first-data-rendered="onFirstDataRendered"
                    @filter-changed="onFilterChanged"
                    @cell-context-menu="onCellContextMenu"
                    @cell-value-changed="onCellValueChanged"
                />
            </div>

            <div
                ref="bottomScrollbar"
                class="orders-grid-bottom-scroll"
                @scroll="onBottomScrollbarScroll"
            >
                <div
                    class="orders-grid-bottom-scroll-inner"
                    :style="{ width: `${bottomScrollbarWidth}px` }"
                />
            </div>
        </div>

        <GridContextMenu
            :open="contextMenu.open"
            :x="contextMenu.x"
            :y="contextMenu.y"
            :items="contextMenu.items"
            @close="closeContextMenu"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';
import { Rows3, Search } from 'lucide-vue-next';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { crmGridDropdown, crmGridInnerPanel, crmGridSearchField, crmGridToolbarBtn } from '@/support/crmUi.js';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import {
    CRM_AG_GRID_DENSITY_CHANGED,
    readPersistedAgGridDensity,
    schedulePersistAgGridDensityToProfile,
    writeLocalAgGridDensity,
} from '@/support/agGridUserDensity.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import {
    agGridFilterModelStorageKey,
    createAgGridFilterModelPersister,
    loadAgGridFilterModel,
} from '@/support/agGridFilterModelPersistence.js';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
    dates: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    userId: { type: [String, Number], default: 'guest' },
});

const page = usePage();

const gridApi = ref(null);
const agGrid = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const displayedRowCount = ref(0);

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
    gridPanel,
    bottomScrollbar,
    agGrid,
    gridApi,
});
const {
    contextMenu,
    closeContextMenu,
    openContextMenu,
} = useGridContextMenu();
const savingCells = ref(new Set());
const lastSaveError = ref('');
const currentDensity = ref(defaultGridDensity);
const showDensityMenu = ref(false);
const quickSearch = ref('');
const persistFilterModel = createAgGridFilterModelPersister();
const filterModelStorageKey = computed(() => agGridFilterModelStorageKey('disposition', props.userId));

const quickSearchStorageKey = computed(() => `disposition_grid_quick_search_v1_${props.userId}`);

const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const textFilterParams = {
    filterOptions: ['contains'],
    defaultOption: 'contains',
    suppressAndOrCondition: true,
};

const defaultColDef = {
    sortable: false,
    filter: false,
    floatingFilter: false,
    resizable: true,
    editable: false,
    suppressHeaderMenuButton: true,
    suppressSizeToFit: true,
};

const dispositionCellClassRules = {
    'disposition-slot-empty-today': (params) => isEmptyTodayLocationCell(params),
    'disposition-cell-saving': (params) => isSavingCell(params),
};

const gridOptions = {
    theme: 'legacy',
    localeText: agGridLocaleRu,
    animateRows: false,
    enterNavigatesHorizontallyAfterEdit: true,
    preventDefaultOnContextMenu: true,
    getRowClass: (params) => (isPlannedArrivalPast(params.data) ? 'ag-row-disposition-planned-overdue' : ''),
};

function onCellContextMenu(params) {
    const row = params.node?.data;
    const items = [];

    if (row?.order_id) {
        items.push({
            label: 'Открыть заказ',
            run: () => router.visit(route('orders.edit', row.order_id)),
        });
    }

    items.push({
        label: 'Сбросить фильтры',
        run: resetGridViewState,
    });

    openContextMenu(params.event, items);
}

function todayIso() {
    if (props.today) {
        return props.today;
    }

    return new Date().toISOString().slice(0, 10);
}

function isPlannedArrivalPast(row) {
    const planned = row?.planned_arrival_date;

    if (!planned) {
        return false;
    }

    return String(planned) < todayIso();
}

function refreshDisplayedRowCount() {
    displayedRowCount.value = gridApi.value?.getDisplayedRowCount() ?? props.rows.length;
}

function buildDispositionQuickFilterHaystack(row) {
    if (!row) {
        return '';
    }

    const parts = [
        row.order_number,
        row.customer_name,
        row.transport_type_label,
        row.route_label,
        row.planned_arrival_date,
    ];

    for (const [key, value] of Object.entries(row)) {
        if (!key.startsWith('cell_')) {
            continue;
        }

        if (!key.endsWith('_location') && !key.endsWith('_comment')) {
            continue;
        }

        if (value == null || value === '') {
            continue;
        }

        parts.push(String(value));
    }

    return parts
        .filter((part) => part != null && String(part).trim() !== '')
        .map((part) => String(part))
        .join(' ');
}

function loadQuickSearch() {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const saved = window.localStorage.getItem(quickSearchStorageKey.value);
        quickSearch.value = typeof saved === 'string' ? saved : '';
    } catch {
        quickSearch.value = '';
    }
}

function applyQuickSearchToGrid() {
    if (!gridApi.value) {
        return;
    }

    gridApi.value.setGridOption('quickFilterText', quickSearch.value ?? '');
}

function fieldPrefix(date, slot) {
    return `cell_${String(date).replace(/-/g, '')}_${slot}`;
}

function formatDateHeader(date) {
    const parts = String(date).split('-');
    if (parts.length !== 3) {
        return date;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function isEmptyTodayLocationCell(params) {
    const parsed = parseCellField(params.colDef?.field);

    if (!parsed || parsed.part !== 'location' || parsed.date !== todayIso()) {
        return false;
    }

    const value = params.value;

    return value == null || String(value).trim() === '';
}

function isSavingCell(params) {
    const parsed = parseCellField(params.colDef?.field);
    const orderId = params.data?.order_id;

    if (!parsed || !orderId) {
        return false;
    }

    return savingCells.value.has(`${orderId}|${parsed.date}|${parsed.slot}|${parsed.part}`);
}

function dispositionEditableCol(definition) {
    return {
        ...definition,
        editable: true,
        singleClickEdit: true,
        cellClass: () => ['orders-grid-editable-cell'],
        cellClassRules: dispositionCellClassRules,
    };
}

function refreshDispositionEditableCells(rowNode = null) {
    if (!gridApi.value) {
        return;
    }

    const refreshParams = { force: true, columns: editableDispositionColumnIds() };

    if (rowNode) {
        gridApi.value.refreshCells({ ...refreshParams, rowNodes: [rowNode] });
    } else {
        gridApi.value.refreshCells(refreshParams);
    }
}

function editableDispositionColumnIds() {
    return (props.dates ?? []).flatMap((date) => [
        `${fieldPrefix(date, 'morning')}_location`,
        `${fieldPrefix(date, 'morning')}_comment`,
        `${fieldPrefix(date, 'evening')}_location`,
        `${fieldPrefix(date, 'evening')}_comment`,
    ]);
}

const columnDefs = computed(() => {
    const pinnedLeft = [
        {
            field: 'order_number',
            headerName: 'Заказ',
            pinned: 'left',
            width: 120,
            minWidth: 100,
            editable: false,
            filter: 'agTextColumnFilter',
            floatingFilter: true,
            filterParams: textFilterParams,
            getQuickFilterText: (params) => buildDispositionQuickFilterHaystack(params.data),
        },
        {
            field: 'customer_name',
            headerName: 'Клиент',
            pinned: 'left',
            width: 160,
            minWidth: 120,
            editable: false,
            filter: 'agTextColumnFilter',
            floatingFilter: true,
            filterParams: textFilterParams,
            valueFormatter: (params) => params.value || '—',
        },
        {
            field: 'transport_type_label',
            headerName: 'Парк',
            pinned: 'left',
            width: 110,
            minWidth: 96,
            editable: false,
            filter: 'agSetColumnFilter',
            filterParams: {
                values: ['Свой парк', 'Наёмный', 'Смешанный', '—'],
            },
        },
        {
            field: 'route_label',
            headerName: 'Маршрут',
            pinned: 'left',
            width: 200,
            minWidth: 160,
            editable: false,
            headerClass: 'disposition-pinned-boundary-header',
            cellClass: 'disposition-pinned-boundary-cell',
        },
    ];

    const dayGroups = (props.dates ?? []).map((date) => ({
        headerName: formatDateHeader(date),
        headerClass: 'disposition-date-group-header',
        children: [
            dispositionEditableCol({
                field: `${fieldPrefix(date, 'morning')}_location`,
                headerName: 'Утро — место',
                width: 140,
                minWidth: 110,
            }),
            dispositionEditableCol({
                field: `${fieldPrefix(date, 'morning')}_comment`,
                headerName: 'Утро — комм.',
                width: 140,
                minWidth: 110,
            }),
            dispositionEditableCol({
                field: `${fieldPrefix(date, 'evening')}_location`,
                headerName: 'Вечер — место',
                width: 140,
                minWidth: 110,
            }),
            dispositionEditableCol({
                field: `${fieldPrefix(date, 'evening')}_comment`,
                headerName: 'Вечер — комм.',
                width: 140,
                minWidth: 110,
                headerClass: 'disposition-day-boundary-header',
                cellClass: () => ['orders-grid-editable-cell', 'disposition-day-boundary-cell'],
            }),
        ],
    }));

    const pinnedRight = [
        {
            field: 'planned_arrival_date',
            headerName: 'План прибытия',
            pinned: 'right',
            width: 120,
            minWidth: 100,
            editable: false,
            valueFormatter: (params) => {
                if (!params.value) {
                    return '—';
                }

                const p = String(params.value).split('-');

                return p.length === 3 ? `${p[2]}.${p[1]}.${p[0]}` : params.value;
            },
        },
    ];

    return [...pinnedLeft, ...dayGroups, ...pinnedRight];
});

function parseCellField(field) {
    const match = /^cell_(\d{8})_(morning|evening)_(location|comment)$/.exec(field ?? '');

    if (!match) {
        return null;
    }

    const ymd = match[1];
    const date = `${ymd.slice(0, 4)}-${ymd.slice(4, 6)}-${ymd.slice(6, 8)}`;

    return {
        date,
        slot: match[2],
        part: match[3],
    };
}

async function saveCell(row, parsed, newValue, rowNode = null) {
    const key = `${row.order_id}|${parsed.date}|${parsed.slot}|${parsed.part}`;
    if (savingCells.value.has(key)) {
        return;
    }

    savingCells.value.add(key);
    refreshDispositionEditableCells(rowNode);

    const prefix = fieldPrefix(parsed.date, parsed.slot);
    const payload = {
        order_id: row.order_id,
        date: parsed.date,
        slot: parsed.slot,
        location: parsed.part === 'location' ? newValue : (row[`${prefix}_location`] ?? null),
        comment: parsed.part === 'comment' ? newValue : (row[`${prefix}_comment`] ?? null),
    };

    try {
        await window.axios.post(route('disposition.entries.upsert'), payload);
        row[`${prefix}_${parsed.part}`] = newValue;
        lastSaveError.value = '';
    } catch (error) {
        const message = error?.response?.data?.message
            ?? error?.response?.data?.errors?.location?.[0]
            ?? 'Не удалось сохранить ячейку диспозиции.';

        lastSaveError.value = typeof message === 'string' ? message : 'Не удалось сохранить ячейку диспозиции.';
    } finally {
        savingCells.value.delete(key);
        refreshDispositionEditableCells(rowNode);
    }
}

async function onCellValueChanged(event) {
    const parsed = parseCellField(event.colDef?.field);
    const row = event.data;

    if (!parsed || !row?.order_id) {
        return;
    }

    await saveCell(row, parsed, event.newValue ?? '', event.node ?? null);

    if (!lastSaveError.value && gridApi.value) {
        gridApi.value.tabToNextCell();
    }
}

function resetGridViewState() {
    if (gridApi.value) {
        gridApi.value.setFilterModel({});
    }

    quickSearch.value = '';
    localStorage.removeItem(quickSearchStorageKey.value);
    localStorage.removeItem(filterModelStorageKey.value);
}

function onGridViewsPinnedChanged() {
    router.reload({ preserveScroll: true });
}

function onFilterChanged() {
    persistFilterModel(gridApi.value, filterModelStorageKey.value);
    refreshDisplayedRowCount();
    nextTick(() => {
        refreshAgGridPanelLayout();
    });
}

function reapplyPersistedFilters() {
    nextTick(() => {
        loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);
        refreshDisplayedRowCount();
        refreshAgGridPanelLayout();
    });
}

function loadDensity() {
    currentDensity.value = readPersistedAgGridDensity(props.userId, page.props.auth?.user);
}

function syncRowMetricsFromDensity() {
    if (!gridApi.value) {
        return;
    }

    const option = resolveGridDensity(currentDensity.value);
    gridApi.value.setGridOption('rowHeight', Number.parseInt(option.rowHeight, 10));
    gridApi.value.setGridOption('headerHeight', Number.parseInt(option.headerHeight, 10));
    gridApi.value.resetRowHeights();
}

function applyDensity(densityKey) {
    currentDensity.value = resolveGridDensity(densityKey).key;
    writeLocalAgGridDensity(props.userId, currentDensity.value);
    schedulePersistAgGridDensityToProfile(currentDensity.value);
    showDensityMenu.value = false;

    nextTick(() => {
        syncRowMetricsFromDensity();
        refreshAgGridPanelLayout();
    });
}

function toggleDensityMenu() {
    showDensityMenu.value = !showDensityMenu.value;
}

watch(quickSearch, (value) => {
    if (typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(quickSearchStorageKey.value, value ?? '');
        } catch {
            /* ignore */
        }
    }

    applyQuickSearchToGrid();
});

watch(
    () => props.rows,
    () => {
        reapplyPersistedFilters();
    },
    { deep: true },
);

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
        syncRowMetricsFromDensity();
        refreshAgGridPanelLayout();
    });
}

async function onGridReady(params) {
    gridApi.value = params.api;
    loadDensity();
    loadQuickSearch();
    loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);

    if (quickSearch.value.trim() !== '') {
        applyQuickSearchToGrid();
    }

    syncRowMetricsFromDensity();
    refreshDisplayedRowCount();

    await nextTick();
    refreshAgGridPanelLayout();
}

function resolveScrollColumnDate() {
    const today = todayIso();
    const dates = props.dates ?? [];

    if (dates.length === 0) {
        return '';
    }

    if (dates.includes(today)) {
        return today;
    }

    if (today < dates[0]) {
        return dates[0];
    }

    return dates[dates.length - 1];
}

function scrollToAnchorColumn() {
    const anchor = resolveScrollColumnDate();

    if (!anchor || !gridApi.value) {
        return;
    }

    const colId = `${fieldPrefix(anchor, 'morning')}_location`;
    gridApi.value.ensureColumnVisible(colId, 'start');
}

const onFirstDataRendered = () => {
    requestAnimationFrame(() => {
        scrollToAnchorColumn();
        refreshDisplayedRowCount();
        refreshAgGridPanelLayout();
    });
};

watch(
    [() => props.rows, () => props.dates, () => props.today],
    async () => {
        await nextTick();
        scrollToAnchorColumn();
        refreshDisplayedRowCount();
        refreshAgGridPanelLayout();
    },
);

onMounted(() => {
    loadDensity();
    loadQuickSearch();
    displayedRowCount.value = props.rows.length;
    window.addEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});

onUnmounted(() => {
    window.removeEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});
</script>
