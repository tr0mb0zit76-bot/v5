<template>
  <div ref="gridSection" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
      <div class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
        <input
          v-model="quickSearch"
          type="text"
          placeholder="Поиск по ФИО, телефону, перевозчику"
          class="w-72 rounded-xl border border-zinc-200 bg-white py-1.5 pl-10 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
        />
      </div>
      <button type="button" class="toolbar-button" @click="$emit('create')">
        <Plus class="h-4 w-4" />
        Добавить водителя
      </button>
    </div>

    <div ref="gridPanel" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
      <div class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 overflow-hidden" :class="densityClass" :style="gridContainerStyle">
        <AgGridVue
          ref="agGrid"
          :gridOptions="gridOptions"
          :rowData="rows"
          :columnDefs="columnDefs"
          :defaultColDef="defaultColDef"
          domLayout="normal"
          :pagination="false"
          :animateRows="true"
          :suppressCellFocus="true"
          :suppressMovableColumns="true"
          :alwaysShowVerticalScroll="true"
          style="height: 100%; width: 100%;"
          @grid-ready="onGridReady"
          @first-data-rendered="onFirstDataRendered"
          @cell-double-clicked="onCellDoubleClicked"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Plus, Search } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity } from '@/Components/Grid/grid-density';
import '@/Components/Grid/grid-theme.css';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
  rows: { type: Array, default: () => [] },
  userId: { type: [String, Number], default: 'guest' },
});

const emit = defineEmits(['create', 'row-dblclick']);

const agGrid = ref(null);
const gridApi = ref(null);
const quickSearch = ref('');
const gridSection = ref(null);
const gridPanel = ref(null);
const gridViewportHeight = ref(360);
const currentDensity = ref(defaultGridDensity);
let removeResizeObserver = null;

const densityStorageKey = computed(() => `fleet_drivers_grid_density_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const gridContainerStyle = computed(() => ({
  height: `${gridViewportHeight.value}px`,
  minHeight: `${gridViewportHeight.value}px`,
  width: '100%',
}));

const gridOptions = {
  theme: 'legacy',
  getRowId: (params) => String(params.data?.id ?? ''),
  isExternalFilterPresent: () => quickSearch.value.trim().length > 0,
  doesExternalFilterPass: (node) => {
    const q = quickSearch.value.trim().toLowerCase();
    if (!q) {
      return true;
    }
    const d = node.data ?? {};
    const hay = [d.full_name, d.phone, d.carrier_name, d.passport_series, d.passport_number, String(d.id)]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return hay.includes(q);
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

const columnDefs = [
  { field: 'id', headerName: 'ID', width: 72, maxWidth: 90 },
  { field: 'carrier_name', headerName: 'Перевозчик', flex: 1, minWidth: 160 },
  { field: 'full_name', headerName: 'ФИО', width: 200, minWidth: 140 },
  { field: 'phone', headerName: 'Телефон', width: 130 },
  {
    headerName: 'Паспорт',
    width: 140,
    valueGetter: (p) => [p.data?.passport_series, p.data?.passport_number].filter(Boolean).join(' ') || '—',
  },
  { field: 'documents_count', headerName: 'Док.', width: 72 },
];

function onGridReady(params) {
  gridApi.value = params.api;
}

function onCellDoubleClicked(event) {
  const id = event?.data?.id;
  if (id) {
    emit('row-dblclick', event.data);
  }
}

function updateGridViewportHeight() {
  if (!gridSection.value || !gridPanel.value) {
    return;
  }
  const sectionRect = gridSection.value.getBoundingClientRect();
  const panelTop = gridPanel.value.getBoundingClientRect().top;
  gridViewportHeight.value = Math.max(280, Math.floor(sectionRect.bottom - panelTop - 24));
}

watch(quickSearch, () => {
  gridApi.value?.onFilterChanged();
});

onMounted(() => {
  try {
    const raw = localStorage.getItem(densityStorageKey.value);
    if (raw) {
      currentDensity.value = raw;
    }
  } catch {
    /* ignore */
  }
  updateGridViewportHeight();
  removeResizeObserver = new ResizeObserver(() => updateGridViewportHeight());
  if (gridSection.value) {
    removeResizeObserver.observe(gridSection.value);
  }
  window.addEventListener('resize', updateGridViewportHeight);
});

onUnmounted(() => {
  removeResizeObserver?.disconnect();
  window.removeEventListener('resize', updateGridViewportHeight);
});

function onFirstDataRendered() {
  nextTick(() => updateGridViewportHeight());
}
</script>
