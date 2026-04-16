<template>
  <div ref="gridSection" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
          <input
            v-model="quickSearch"
            type="text"
            placeholder="Поиск по задачам"
            class="w-72 rounded-xl border border-zinc-200 bg-white py-1.5 pl-10 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
          />
        </div>

        <div class="relative">
          <button
            type="button"
            class="toolbar-button px-2"
            :title="`Плотность таблицы: ${currentDensityLabel}`"
            @click="showDensityMenu = !showDensityMenu"
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
      </div>

      <button type="button" class="toolbar-button" @click="$emit('create')">
        <Plus class="h-4 w-4" />
        Создать задачу
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
          :suppressHorizontalScroll="false"
          :alwaysShowVerticalScroll="true"
          style="height: 100%; width: 100%;"
          @grid-ready="onGridReady"
          @first-data-rendered="onFirstDataRendered"
          @cell-double-clicked="onCellDoubleClicked"
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
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Plus, Rows3, Search } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import '@/Components/Grid/grid-theme.css';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
  userId: {
    type: [String, Number],
    default: 'guest',
  },
});

const emit = defineEmits(['create', 'row-dblclick', 'selection-changed']);

const agGrid = ref(null);
const gridApi = ref(null);
const quickSearch = ref('');
const showDensityMenu = ref(false);
const currentDensity = ref(defaultGridDensity);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const bottomScrollbarWidth = ref(0);
const gridViewportHeight = ref(360);

let isSyncingHorizontalScroll = false;
let removeResizeObserver = null;

const densityStorageKey = computed(() => `tasks_grid_density_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);
const gridContainerStyle = computed(() => ({
  height: `${gridViewportHeight.value}px`,
  minHeight: `${gridViewportHeight.value}px`,
  width: '100%',
}));

const priorityLabels = {
  low: 'Низкий',
  medium: 'Средний',
  high: 'Высокий',
  critical: 'Критичный',
};

const gridOptions = {
  theme: 'legacy',
  getRowId: (params) => String(params.data?.id ?? ''),
  rowSelection: {
    mode: 'multiRow',
    checkboxes: true,
    headerCheckbox: true,
    enableClickSelection: true,
  },
  selectionColumnDef: {
    sortable: false,
    resizable: false,
    suppressHeaderMenuButton: true,
    maxWidth: 52,
    minWidth: 52,
  },
  onSelectionChanged: (event) => {
    const ids = event.api
      .getSelectedRows()
      .map((r) => r?.id)
      .filter((id) => id !== undefined && id !== null);
    emit('selection-changed', ids);
  },
  isExternalFilterPresent: () => quickSearch.value.trim().length > 0,
  doesExternalFilterPass: (node) => {
    const q = quickSearch.value.trim().toLowerCase();
    if (!q) {
      return true;
    }
    const d = node.data ?? {};
    const hay = [
      d.number,
      d.title,
      d.status_label,
      d.responsible_name,
      d.lead_number,
      d.lead_title,
      priorityLabels[d.priority] ?? d.priority,
    ]
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
  { field: 'number', headerName: 'Номер', width: 110, minWidth: 90 },
  { field: 'title', headerName: 'Название', flex: 1, minWidth: 180 },
  { field: 'status_label', headerName: 'Статус', width: 130, minWidth: 110 },
  {
    field: 'priority',
    headerName: 'Приоритет',
    width: 120,
    minWidth: 100,
    valueFormatter: (p) => priorityLabels[p.value] ?? p.value ?? '—',
  },
  { field: 'responsible_name', headerName: 'Ответственный', width: 160, minWidth: 130 },
  {
    field: 'due_at',
    headerName: 'Срок',
    width: 150,
    minWidth: 130,
    valueFormatter: (p) => formatDue(p.value),
  },
  {
    field: 'lead_number',
    headerName: 'Лид',
    width: 120,
    minWidth: 100,
    valueFormatter: (p) => p.data?.lead_number || '—',
  },
  {
    headerName: 'Чеклист',
    width: 110,
    minWidth: 100,
    valueGetter: (p) => {
      const items = p.data?.checklist_items ?? [];
      const done = items.filter((i) => i.is_done).length;

      return `${done}/${items.length}`;
    },
  },
];

function formatDue(value) {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function onGridReady(params) {
  gridApi.value = params.api;
}

function onCellDoubleClicked(event) {
  const id = event?.data?.id;
  if (id) {
    emit('row-dblclick', event.data);
  }
}

function applyDensity(key) {
  currentDensity.value = key;
  showDensityMenu.value = false;
  try {
    localStorage.setItem(densityStorageKey.value, key);
  } catch {
    /* ignore */
  }
}

function readDensityFromStorage() {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    const raw = localStorage.getItem(densityStorageKey.value);
    if (raw && gridDensityOptions.some((o) => o.key === raw)) {
      currentDensity.value = raw;
    }
  } catch {
    /* ignore */
  }
}

function updateGridViewportHeight() {
  if (!gridSection.value || !gridPanel.value) {
    return;
  }
  const sectionRect = gridSection.value.getBoundingClientRect();
  const panelTop = gridPanel.value.getBoundingClientRect().top;
  const reserve = 24;
  const h = Math.max(280, Math.floor(sectionRect.bottom - panelTop - reserve));
  gridViewportHeight.value = h;
}

function syncBottomScrollbar() {
  const api = gridApi.value;
  if (!api) {
    return;
  }
  const centerViewport = document.querySelector('.ag-body-horizontal-scroll-viewport');
  if (!centerViewport) {
    bottomScrollbarWidth.value = 0;

    return;
  }
  bottomScrollbarWidth.value = Math.max(centerViewport.scrollWidth, centerViewport.clientWidth);
  if (bottomScrollbar.value && !isSyncingHorizontalScroll) {
    bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
  }
}

function onBottomScrollbarScroll() {
  const centerViewport = document.querySelector('.ag-body-horizontal-scroll-viewport');
  if (!centerViewport || !bottomScrollbar.value) {
    return;
  }
  isSyncingHorizontalScroll = true;
  centerViewport.scrollLeft = bottomScrollbar.value.scrollLeft;
  requestAnimationFrame(() => {
    isSyncingHorizontalScroll = false;
  });
}

function onFirstDataRendered() {
  nextTick(() => {
    syncBottomScrollbar();
  });
}

watch(quickSearch, () => {
  gridApi.value?.onFilterChanged();
});

watch(
  () => props.rows,
  () => {
    nextTick(() => syncBottomScrollbar());
  },
  { deep: true },
);

onMounted(() => {
  readDensityFromStorage();
  updateGridViewportHeight();
  removeResizeObserver = new ResizeObserver(() => {
    updateGridViewportHeight();
    nextTick(() => syncBottomScrollbar());
  });
  if (gridSection.value) {
    removeResizeObserver.observe(gridSection.value);
  }
  window.addEventListener('resize', updateGridViewportHeight);
});

onUnmounted(() => {
  removeResizeObserver?.disconnect();
  window.removeEventListener('resize', updateGridViewportHeight);
});
</script>
