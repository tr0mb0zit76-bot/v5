<template>
  <div ref="gridSection" class="flex min-h-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 flex-wrap items-center gap-2">
      <div class="relative min-w-[220px] flex-1">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
        <input v-model="quickSearch" type="text" :class="crmGridSearchField" />
      </div>

      <GridViewsBar
        grid-key="company_planning"
        :user-id="userId"
        :get-grid-api="() => gridApi"
        :column-storage-key="storageKey"
        :filter-storage-key="filterModelStorageKey"
        :quick-search="quickSearch"
        :on-reset-defaults="resetGridViewState"
        @update:quick-search="quickSearch = $event"
        @applied="onGridViewApplied"
      />
    </div>

    <div ref="gridPanel" :class="crmGridInnerPanel">
      <div
        class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden"
        :class="densityClass"
        :style="gridContainerStyle"
      >
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
          @cell-clicked="onCellClicked"
          @cell-double-clicked="onCellDoubleClicked"
          @column-visible="saveColumnState"
          @column-resized="saveColumnState"
          @column-moved="saveColumnState"
          @filter-changed="onFilterChanged"
        />
      </div>

      <div ref="bottomScrollbar" class="orders-grid-bottom-scroll" @scroll="onBottomScrollbarScroll">
        <div class="orders-grid-bottom-scroll-inner" :style="{ width: `${bottomScrollbarWidth}px` }" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Search } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import '@/Components/Grid/grid-theme.css';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import { defaultGridDensity, resolveGridDensity } from '@/Components/Grid/grid-density';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { applyAgSetListColumn } from '@/Components/Grid/agSetListFilter.js';
import { readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import { loadAgGridFilterModel, createAgGridFilterModelPersister } from '@/support/agGridFilterModelPersistence.js';
import {
  crmGridInnerPanel,
  crmGridSearchField,
} from '@/support/crmUi.js';
import {
  readPersistedAgGridDensity,
  writeLocalAgGridDensity,
} from '@/support/agGridUserDensity.js';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
  rows: { type: Array, default: () => [] },
  userId: { type: [String, Number], default: 'guest' },
  statusLabels: { type: Object, default: () => ({}) },
  riskLabels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['row-click', 'row-dblclick']);

const agGrid = ref(null);
const gridApi = ref(null);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const quickSearch = ref('');
const gridViewsRevision = ref(0);
const currentDensity = ref(defaultGridDensity);

const storageKey = computed(() => `company_planning_grid_state_v1_${props.userId}`);
const filterModelStorageKey = computed(() => `company_planning_grid_filter_model_v1_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const persistFilterModel = createAgGridFilterModelPersister();

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
  gridPanel,
  bottomScrollbar,
  agGrid,
  gridApi,
});

const defaultColDef = {
  sortable: true,
  filter: true,
  resizable: true,
  floatingFilter: false,
  minWidth: 90,
  suppressSizeToFit: true,
};

const gridOptions = {
  theme: 'legacy',
  localeText: agGridLocaleRu,
  animateRows: false,
  getRowId: (params) => String(params.data?.id ?? ''),
  getRowClass: (params) => (params.data?.is_overdue ? 'ag-row-lead-stage-overdue' : ''),
};

function formatMoney(value, currency = 'RUB') {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  return `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ${currency}`;
}

function formatPeriod(start, end) {
  if (!start && !end) {
    return '—';
  }

  const fmt = (value) => {
    if (!value) {
      return '…';
    }
    const parts = String(value).split('-');

    return parts.length === 3 ? `${parts[2]}.${parts[1]}.${parts[0]}` : value;
  };

  return `${fmt(start)} → ${fmt(end)}`;
}

const columnDefs = computed(() => {
  void gridViewsRevision.value;

  const columns = [
    {
      field: 'title',
      headerName: 'Инициатива',
      flex: 1,
      minWidth: 220,
      filter: 'agTextColumnFilter',
    },
    {
      field: 'direction_label',
      headerName: 'Направление',
      width: 140,
      filter: 'agTextColumnFilter',
      valueFormatter: (params) => params.value || '—',
    },
    {
      field: 'owner_name',
      headerName: 'Владелец',
      width: 160,
      filter: 'agTextColumnFilter',
      valueFormatter: (params) => params.value || '—',
    },
    {
      colId: 'period',
      headerName: 'Сроки',
      width: 170,
      filter: false,
      valueGetter: (params) => formatPeriod(params.data?.starts_on, params.data?.ends_on),
    },
    {
      field: 'progress_percent',
      headerName: 'Прогресс',
      width: 110,
      filter: 'agNumberColumnFilter',
      valueFormatter: (params) => `${params.value ?? 0}%`,
    },
    {
      colId: 'budget_plan',
      headerName: 'План',
      width: 120,
      filter: 'agNumberColumnFilter',
      valueGetter: (params) => params.data?.planned_budget_amount,
      valueFormatter: (params) => formatMoney(params.value, params.data?.budget_currency),
    },
    {
      colId: 'budget_fact',
      headerName: 'Факт',
      width: 120,
      filter: 'agNumberColumnFilter',
      valueGetter: (params) => params.data?.budget_snapshot?.fact_out_amount,
      valueFormatter: (params) => formatMoney(params.value, params.data?.budget_currency),
    },
    {
      field: 'status_label',
      headerName: 'Статус',
      width: 130,
    },
    {
      field: 'risk_label',
      headerName: 'Риск',
      width: 120,
    },
    {
      field: 'overdue_milestones_count',
      headerName: 'Проср. этапы',
      width: 120,
      filter: 'agNumberColumnFilter',
    },
  ];

  applyAgSetListColumn(columns[7], {
    values: Object.values(props.statusLabels),
    filterValueGetter: (params) => params.data?.status_label ?? '—',
    floatingFilterRow: true,
  });
  applyAgSetListColumn(columns[8], {
    values: Object.values(props.riskLabels),
    filterValueGetter: (params) => params.data?.risk_label ?? '—',
    floatingFilterRow: true,
  });

  return columns;
});

let saveTimeout = null;

function saveColumnState() {
  if (!gridApi.value) {
    return;
  }

  if (saveTimeout) {
    clearTimeout(saveTimeout);
  }

  saveTimeout = setTimeout(() => {
    const columnState = gridApi.value.getColumnState().map((column, index) => ({
      colId: column.colId,
      hide: column.hide,
      width: column.width,
      order: index,
    }));

    localStorage.setItem(storageKey.value, JSON.stringify(columnState));
    refreshAgGridPanelLayout();
  }, 250);
}

function resetGridViewState() {
  if (gridApi.value) {
    gridApi.value.resetColumnState();
    gridApi.value.setFilterModel({});
  }

  localStorage.removeItem(storageKey.value);
  localStorage.removeItem(filterModelStorageKey.value);
  quickSearch.value = '';
  gridViewsRevision.value++;
}

function onGridViewApplied() {
  gridViewsRevision.value++;

  nextTick(() => {
    refreshAgGridPanelLayout();
  });
}

function onGridReady(params) {
  gridApi.value = params.api;

  const saved = readPersistedAgGridColumnState(storageKey.value);
  if (saved?.length) {
    gridApi.value.applyColumnState({ state: saved, applyOrder: true });
  }

  if (quickSearch.value.trim() !== '') {
    gridApi.value.setGridOption('quickFilterText', quickSearch.value);
  }

  loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);

  nextTick(() => {
    refreshAgGridPanelLayout();
  });
}

function onFirstDataRendered() {
  requestAnimationFrame(() => {
    refreshAgGridPanelLayout();
  });
}

function onFilterChanged() {
  persistFilterModel(gridApi.value, filterModelStorageKey.value);
}

function onCellClicked(event) {
  if (event?.data?.id) {
    emit('row-click', event.data);
  }
}

function onCellDoubleClicked(event) {
  if (event?.data?.id) {
    emit('row-dblclick', event.data);
  }
}

watch(quickSearch, (value) => {
  if (gridApi.value) {
    gridApi.value.setGridOption('quickFilterText', value);
  }
});

watch(
  () => props.rows,
  async () => {
    await nextTick();
    refreshAgGridPanelLayout();
  },
);

onMounted(() => {
  currentDensity.value = readPersistedAgGridDensity(props.userId, null)?.key
    ? resolveGridDensity(readPersistedAgGridDensity(props.userId, null)).key
    : defaultGridDensity;
  writeLocalAgGridDensity(props.userId, currentDensity.value);
});
</script>
