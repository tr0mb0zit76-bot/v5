<template>
    <div ref="gridSection" class="flex min-h-0 flex-1 flex-col gap-2">
        <div class="flex shrink-0 items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="quickSearch"
                        type="text"
                        class="w-80 rounded-xl border border-zinc-200 bg-white py-1.5 pl-10 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                        placeholder="Фильтр по реестру"
                    >
                </div>

                <button type="button" class="toolbar-button" @click="openColumnModal">
                    <Settings2 class="h-4 w-4" />
                    Колонки
                </button>

                <div class="relative">
                    <button
                        type="button"
                        class="toolbar-button px-2"
                        :title="`Плотность таблицы: ${currentDensityLabel}`"
                        @click="toggleDensityMenu"
                    >
                        <Rows3 class="h-4 w-4" />
                    </button>

                    <div
                        v-if="showDensityMenu"
                        class="absolute left-0 top-full z-20 mt-2 w-40 rounded-2xl border border-zinc-200 bg-white p-1.5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
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

                <button type="button" class="toolbar-button" @click="resetColumns">
                    <RotateCcw class="h-4 w-4" />
                    Сбросить
                </button>
            </div>

            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                Двойной клик по строке открывает заказ
            </div>
        </div>

        <div ref="gridPanel" class="flex min-h-0 flex-1 flex-col overflow-hidden border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="ag-theme-alpine orders-grid-theme" :class="densityClass" :style="gridContainerStyle">
                <AgGridVue
                    ref="agGrid"
                    :gridOptions="gridOptions"
                    :rowData="rows"
                    :columnDefs="columnDefs"
                    :defaultColDef="defaultColDef"
                    :domLayout="'normal'"
                    :pagination="false"
                    :animateRows="true"
                    :suppressCellFocus="true"
                    :alwaysShowVerticalScroll="true"
                    style="height: 100%; width: 100%;"
                    @grid-ready="onGridReady"
                    @first-data-rendered="onFirstDataRendered"
                    @cell-double-clicked="onCellDoubleClicked"
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
                <div class="w-full max-w-2xl rounded-3xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
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
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { RotateCcw, Rows3, Search, Settings2, X } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import '@/Components/Grid/grid-theme.css';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
    rows: { type: Array, default: () => [] },
    userId: { type: [String, Number], default: 'guest' },
});

const emit = defineEmits(['open-create', 'row-dblclick']);

const fallbackColumns = [
    { field: 'order_number', headerName: 'Номер заказа', width: 160, minWidth: 140 },
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
    { field: 'other_docs', headerName: 'Прочее', width: 220, minWidth: 180 },
];

const agGrid = ref(null);
const gridApi = ref(null);
const showColumnModal = ref(false);
const showDensityMenu = ref(false);
const modalColumns = ref([]);
const draggedColumnField = ref(null);
const quickSearch = ref('');
const currentDensity = ref(defaultGridDensity);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const bottomScrollbarWidth = ref(0);
const gridViewportHeight = ref(280);

let isSyncingHorizontalScroll = false;
let saveTimeout = null;
let filterModelSaveTimeout = null;
let removeCenterViewportListener = null;

const gridOptions = { theme: 'legacy' };

const storageKey = computed(() => `documents_grid_state_v2_${props.userId}`);
const densityStorageKey = computed(() => `documents_grid_density_${props.userId}`);
const quickSearchStorageKey = computed(() => `documents_grid_quick_search_${props.userId}`);
const filterModelStorageKey = computed(() => `documents_grid_filter_model_v1_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);
const gridContainerStyle = computed(() => ({
    height: `${gridViewportHeight.value}px`,
    minHeight: `${gridViewportHeight.value}px`,
    width: '100%',
}));

const defaultColDef = {
    sortable: true,
    filter: true,
    resizable: true,
    floatingFilter: true,
    suppressSizeToFit: true,
    minWidth: 120,
};

const columnDefs = computed(() => {
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

        colDef.cellRenderer = (params) => documentsCellRenderer(params.data, column.field);

        return colDef;
    });

    baseColumns.push({
        colId: '__actions',
        headerName: 'Действие',
        width: 120,
        minWidth: 110,
        maxWidth: 130,
        pinned: 'right',
        sortable: false,
        filter: false,
        resizable: false,
        cellRenderer: (params) => actionCellRenderer(params.data),
    });

    return baseColumns;
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

    const items = normalizeItems(row[field]);
    if (items.length === 0) {
        return '—';
    }

    return items.map((item) => item.label).join(', ');
}

function documentsCellRenderer(row, field) {
    const container = document.createElement('div');
    container.className = 'flex h-full min-h-[46px] flex-col justify-center gap-0.5 py-1';

    const items = normalizeItems(row?.[field]);
    if (items.length === 0) {
        const empty = document.createElement('span');
        empty.className = 'text-xs text-zinc-400';
        empty.textContent = '—';
        container.appendChild(empty);
        return container;
    }

    items.forEach((item) => {
        const link = document.createElement('a');
        link.href = item.order_url;
        link.className = 'truncate text-xs text-sky-700 underline dark:text-sky-300';
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
    link.className = 'font-medium text-sky-700 underline dark:text-sky-300';
    link.textContent = params.data?.order_number ?? '—';
    wrapper.appendChild(link);

    return wrapper;
}

function actionCellRenderer(row) {
    const wrapper = document.createElement('div');
    wrapper.className = 'flex h-full items-center justify-center';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800';
    button.textContent = 'Добавить';
    button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        emit('open-create', row?.order_id ?? null);
    });
    wrapper.appendChild(button);

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

function loadColumnState() {
    if (!gridApi.value) {
        return false;
    }

    const raw = localStorage.getItem(storageKey.value);
    if (!raw) {
        return false;
    }

    try {
        const state = JSON.parse(raw);
        gridApi.value.applyColumnState({
            state,
            applyOrder: true,
        });
        syncModalColumnsWithGrid();
        return true;
    } catch (error) {
        console.error('Error loading documents grid state', error);
        return false;
    }
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
        syncBottomScrollbar();
    }, 250);
}

function resetColumns() {
    if (!gridApi.value) {
        return;
    }

    gridApi.value.resetColumnState();
    saveColumnState();
}

function applyDensity(densityKey) {
    currentDensity.value = resolveGridDensity(densityKey).key;
    localStorage.setItem(densityStorageKey.value, currentDensity.value);
    showDensityMenu.value = false;
    nextTick(syncBottomScrollbar);
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

function onCellDoubleClicked(event) {
    if (event.data?.order_id) {
        emit('row-dblclick', event.data);
        router.visit(route('orders.edit', event.data.order_id));
    }
}

const getCenterViewport = () => agGrid.value?.$el?.querySelector('.ag-viewport.ag-center-cols-viewport') ?? null;

function updateGridViewportHeight() {
    const panel = gridPanel.value;
    if (!panel) {
        return;
    }

    const sectionTop = panel.getBoundingClientRect().top;
    const bottomScrollbarHeight = bottomScrollbar.value?.offsetHeight ?? 16;
    const footerTop = document.querySelector('footer')?.getBoundingClientRect().top ?? window.innerHeight;
    const footerReserve = 60;

    gridViewportHeight.value = Math.max(280, Math.floor(footerTop - sectionTop - bottomScrollbarHeight - footerReserve));
}

function syncBottomScrollbar() {
    const centerViewport = getCenterViewport();
    if (!centerViewport) {
        return;
    }

    bottomScrollbarWidth.value = Math.max(centerViewport.scrollWidth, centerViewport.clientWidth);
    updateGridViewportHeight();

    if (bottomScrollbar.value && !isSyncingHorizontalScroll) {
        bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
    }
}

function onBottomScrollbarScroll() {
    if (isSyncingHorizontalScroll) {
        return;
    }

    const centerViewport = getCenterViewport();
    if (!centerViewport) {
        return;
    }

    isSyncingHorizontalScroll = true;
    centerViewport.scrollLeft = bottomScrollbar.value?.scrollLeft ?? 0;

    requestAnimationFrame(() => {
        isSyncingHorizontalScroll = false;
    });
}

function attachCenterViewportListener() {
    removeCenterViewportListener?.();

    const centerViewport = getCenterViewport();
    if (!centerViewport) {
        return;
    }

    const onScroll = () => {
        if (isSyncingHorizontalScroll) {
            return;
        }

        isSyncingHorizontalScroll = true;
        if (bottomScrollbar.value) {
            bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
        }

        requestAnimationFrame(() => {
            isSyncingHorizontalScroll = false;
        });
    };

    centerViewport.addEventListener('scroll', onScroll, { passive: true });
    removeCenterViewportListener = () => {
        centerViewport.removeEventListener('scroll', onScroll);
    };
}

async function onGridReady(params) {
    gridApi.value = params.api;
    const savedQuickSearch = localStorage.getItem(quickSearchStorageKey.value);
    quickSearch.value = typeof savedQuickSearch === 'string' ? savedQuickSearch : '';

    if (quickSearch.value.trim() !== '') {
        gridApi.value.setGridOption('quickFilterText', quickSearch.value);
    }

    if (!loadColumnState()) {
        resetColumns();
    }

    const savedDensity = localStorage.getItem(densityStorageKey.value);
    currentDensity.value = savedDensity ? resolveGridDensity(savedDensity).key : defaultGridDensity;

    loadFilterModel();

    await nextTick();
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
}

function onFirstDataRendered() {
    requestAnimationFrame(() => {
        updateGridViewportHeight();
        attachCenterViewportListener();
        syncBottomScrollbar();
    });
}

watch(quickSearch, (value) => {
    localStorage.setItem(quickSearchStorageKey.value, value ?? '');
    if (gridApi.value) {
        gridApi.value.setGridOption('quickFilterText', value ?? '');
    }
});

watch(() => props.rows, async () => {
    await nextTick();
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
}, { deep: true });

onMounted(() => {
    updateGridViewportHeight();
    window.addEventListener('resize', updateGridViewportHeight);
    window.addEventListener('resize', syncBottomScrollbar);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateGridViewportHeight);
    window.removeEventListener('resize', syncBottomScrollbar);
    removeCenterViewportListener?.();
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    if (filterModelSaveTimeout) {
        clearTimeout(filterModelSaveTimeout);
    }
});
</script>

<style scoped>
.toolbar-button {
    @apply inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800;
}
</style>
