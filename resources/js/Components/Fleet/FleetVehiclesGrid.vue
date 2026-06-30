<template>
  <div ref="gridSection" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
          <input
            v-model="quickSearch"
            type="text"
            :class="crmGridSearchField"
          />
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

        <button type="button" :class="crmGridToolbarBtn" @click="resetFilters">
          <RotateCcw class="h-4 w-4" />
          Сбросить
        </button>
      </div>

      <div class="text-xs text-zinc-500 dark:text-zinc-400">
        Двойной клик по строке открывает карточку ТС
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
          domLayout="normal"
          :pagination="false"
          :animateRows="false"
          :suppressCellFocus="true"
          :alwaysShowVerticalScroll="true"
          style="height: 100%; width: 100%;"
          @grid-ready="onGridReady"
          @first-data-rendered="onFirstDataRendered"
          @cell-double-clicked="onCellDoubleClicked"
          @column-resized="onColumnResized"
          @column-moved="persistColumnState"
          @column-visible="persistColumnState"
          @sort-changed="persistColumnState"
          @filter-changed="onGridFilterChanged"
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
      @close="closeRowContextMenu"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { RotateCcw, Rows3, Search } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import { applySavedToColDef, buildLayoutIndex, readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import {
  agGridFilterModelStorageKey,
  createAgGridFilterModelPersister,
  loadAgGridFilterModel,
} from '@/support/agGridFilterModelPersistence.js';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import { crmGridInnerPanel, crmGridSearchField, crmGridToolbarBtn } from '@/support/crmUi.js';
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
});

const page = usePage();

const emit = defineEmits(['row-dblclick', 'create-request']);

const agGrid = ref(null);
const gridApi = ref(null);
const quickSearch = ref('');
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const currentDensity = ref(defaultGridDensity);
const showDensityMenu = ref(false);
const persistFilterModel = createAgGridFilterModelPersister();
const filterModelStorageKey = computed(() => agGridFilterModelStorageKey('fleet_vehicles', props.userId));

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
  gridPanel,
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

function onGridPanelEmptyContextMenu(event) {
  openEmptyContextMenu(event, [
    {
      label: 'Добавить ТС',
      run: () => emit('create-request'),
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

  if (row?.id) {
    items.push({
      label: 'Открыть карточку',
      run: () => emit('row-dblclick', row),
    });
  }

  items.push({
    label: 'Добавить ТС',
    run: () => emit('create-request'),
  });

  openContextMenu(ev, items);
}

const columnStorageKey = computed(() => `fleet_vehicles_grid_columns_v2_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const gridOptions = {
  theme: 'legacy',
  localeText: agGridLocaleRu,
  animateRows: false,
  preventDefaultOnContextMenu: true,
  onCellContextMenu,
  getRowId: (params) => String(params.data?.id ?? ''),
  isExternalFilterPresent: () => quickSearch.value.trim().length > 0,
  doesExternalFilterPass: (node) => {
    const query = quickSearch.value.trim().toLowerCase();
    if (!query) {
      return true;
    }

    const data = node.data ?? {};
    const haystack = [data.owner_name, data.tractor_brand, data.trailer_brand, data.tractor_plate, data.trailer_plate]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return haystack.includes(query);
  },
};

const defaultColDef = {
  sortable: true,
  filter: true,
  resizable: true,
  floatingFilter: false,
  minWidth: 80,
  suppressSizeToFit: true,
};

function buildBaseVehicleColumnDefs() {
  return [
    { field: 'id', headerName: 'ID', width: 56, minWidth: 48, maxWidth: 72, filter: false, floatingFilter: false, suppressHeaderFilterButton: true, getQuickFilterText: () => '' },
    { field: 'owner_name', headerName: 'Владелец', flex: 1, minWidth: 160, floatingFilter: true },
    { field: 'tractor_brand', headerName: 'Марка тягача', width: 130 },
    { field: 'trailer_brand', headerName: 'Марка прицепа', width: 130 },
    { field: 'tractor_plate', headerName: 'Номер тягача', width: 120 },
    { field: 'trailer_plate', headerName: 'Номер прицепа', width: 120 },
    { field: 'documents_count', headerName: 'Док.', width: 72, filter: 'agNumberColumnFilter' },
  ];
}

let columnSaveTimeout = null;

/** Ignore flex / grid init sizing — they fire columnResized and would overwrite saved widths in localStorage. */
function onColumnResized(event) {
  if (event?.finished === false) {
    return;
  }

  if (event?.source === 'flex' || event?.source === 'gridInitializing') {
    return;
  }

  persistColumnState();
}

function persistColumnState() {
  if (!gridApi.value) {
    return;
  }

  if (columnSaveTimeout) {
    clearTimeout(columnSaveTimeout);
  }

  columnSaveTimeout = setTimeout(() => {
    const state = gridApi.value.getColumnState().map((column, index) => {
      const width = typeof column.width === 'number' && column.width > 0
        ? column.width
        : (typeof column.actualWidth === 'number' && column.actualWidth > 0 ? column.actualWidth : null);

      return {
        colId: column.colId,
        hide: column.hide,
        width,
        order: index,
        sort: column.sort ?? null,
        sortIndex: column.sortIndex ?? null,
      };
    });
    localStorage.setItem(columnStorageKey.value, JSON.stringify(state));
    columnSaveTimeout = null;
  }, 200);
}

const columnDefs = computed(() => {
  const saved = readPersistedAgGridColumnState(columnStorageKey.value);
  const raw = buildBaseVehicleColumnDefs();

  if (!saved?.length) {
    return raw;
  }

  const fields = raw.map((c) => c.field);
  const { orderedFields, byColId } = buildLayoutIndex(fields, saved);
  const byField = new Map(raw.map((d) => [d.field, d]));

  return orderedFields
    .map((field) => {
      const def = byField.get(field);
      return def ? applySavedToColDef({ ...def }, byColId.get(field)) : null;
    })
    .filter(Boolean);
});

function onGridReady(params) {
  gridApi.value = params.api;
  loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);
  refreshAgGridPanelLayout();
}

function onGridFilterChanged() {
  persistFilterModel(gridApi.value, filterModelStorageKey.value);
}

function reapplyPersistedFilters() {
  nextTick(() => {
    loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);
    refreshAgGridPanelLayout();
  });
}

function onCellDoubleClicked(event) {
  const id = event?.data?.id;
  if (id) {
    emit('row-dblclick', event.data);
  }
}

function applyDensity(densityKey) {
  currentDensity.value = resolveGridDensity(densityKey).key;
  writeLocalAgGridDensity(props.userId, currentDensity.value);
  schedulePersistAgGridDensityToProfile(currentDensity.value);
  showDensityMenu.value = false;

  nextTick(() => {
    gridApi.value?.resetRowHeights();
    gridApi.value?.refreshCells({ force: true });
    refreshAgGridPanelLayout();
  });
}

function toggleDensityMenu() {
  showDensityMenu.value = !showDensityMenu.value;
}

function resetFilters() {
  quickSearch.value = '';
  gridApi.value?.setFilterModel(null);
  gridApi.value?.onFilterChanged();
  localStorage.removeItem(filterModelStorageKey.value);
}

watch(quickSearch, () => {
  gridApi.value?.onFilterChanged();
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
    gridApi.value?.resetRowHeights();
    gridApi.value?.refreshCells({ force: true });
    refreshAgGridPanelLayout();
  });
}

onMounted(() => {
  currentDensity.value = readPersistedAgGridDensity(props.userId, page.props.auth?.user);
  window.addEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});

onUnmounted(() => {
  if (columnSaveTimeout) {
    clearTimeout(columnSaveTimeout);
  }

  window.removeEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});

function onFirstDataRendered() {
  nextTick(() => {
    refreshAgGridPanelLayout();
  });
}
</script>

