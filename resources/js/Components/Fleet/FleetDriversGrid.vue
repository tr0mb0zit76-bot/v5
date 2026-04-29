<template>
  <div ref="gridSection" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
          <input
            v-model="quickSearch"
            type="text"
            placeholder="Поиск по ФИО, телефону, перевозчику"
            class="w-72 rounded-xl border border-zinc-200 bg-white py-1.5 pl-10 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
          />
        </div>

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

        <button type="button" class="toolbar-button" @click="resetFilters">
          <RotateCcw class="h-4 w-4" />
          Сбросить
        </button>
      </div>

      <div class="text-xs text-zinc-500 dark:text-zinc-400">
        Двойной клик по строке открывает карточку водителя
      </div>
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
import { RotateCcw, Rows3, Search } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import '@/Components/Grid/grid-theme.css';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
  rows: { type: Array, default: () => [] },
  userId: { type: [String, Number], default: 'guest' },
});

const emit = defineEmits(['row-dblclick']);

const agGrid = ref(null);
const gridApi = ref(null);
const quickSearch = ref('');
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const bottomScrollbarWidth = ref(0);
const gridViewportHeight = ref(360);
const currentDensity = ref(defaultGridDensity);
const showDensityMenu = ref(false);

let removeCenterViewportListener = null;
let isSyncingHorizontalScroll = false;

const densityStorageKey = computed(() => `fleet_drivers_grid_density_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);
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
    const query = quickSearch.value.trim().toLowerCase();
    if (!query) {
      return true;
    }

    const data = node.data ?? {};
    const haystack = [data.full_name, data.phone, data.carrier_name, data.passport_series, data.passport_number, String(data.id)]
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

const columnDefs = [
  { field: 'id', headerName: 'ID', width: 72, maxWidth: 90 },
  { field: 'carrier_name', headerName: 'Перевозчик', flex: 1, minWidth: 160 },
  { field: 'full_name', headerName: 'ФИО', width: 200, minWidth: 140 },
  { field: 'phone', headerName: 'Телефон', width: 130 },
  {
    headerName: 'Паспорт',
    width: 140,
    valueGetter: (params) => [params.data?.passport_series, params.data?.passport_number].filter(Boolean).join(' ') || '—',
  },
  { field: 'documents_count', headerName: 'Док.', width: 72 },
];

function onGridReady(params) {
  gridApi.value = params.api;
  attachCenterViewportListener();
  syncBottomScrollbar();
}

function onCellDoubleClicked(event) {
  const id = event?.data?.id;
  if (id) {
    emit('row-dblclick', event.data);
  }
}

function updateGridViewportHeight() {
  if (!gridPanel.value) {
    return;
  }

  const sectionTop = gridPanel.value.getBoundingClientRect().top;
  const bottomScrollbarHeight = bottomScrollbar.value?.offsetHeight ?? 16;
  const commandBarFooter = document.querySelector('footer');
  const footerTop = commandBarFooter?.getBoundingClientRect().top ?? window.innerHeight;
  const footerReserve = 60;

  gridViewportHeight.value = Math.max(
    280,
    Math.floor(footerTop - sectionTop - bottomScrollbarHeight - footerReserve),
  );
}

function applyDensity(densityKey) {
  currentDensity.value = resolveGridDensity(densityKey).key;
  localStorage.setItem(densityStorageKey.value, currentDensity.value);
  showDensityMenu.value = false;

  nextTick(() => {
    gridApi.value?.resetRowHeights();
    gridApi.value?.refreshCells({ force: true });
    syncBottomScrollbar();
  });
}

function toggleDensityMenu() {
  showDensityMenu.value = !showDensityMenu.value;
}

function resetFilters() {
  quickSearch.value = '';
  gridApi.value?.onFilterChanged();
}

function getCenterViewport() {
  return agGrid.value?.$el?.querySelector('.ag-viewport.ag-center-cols-viewport') ?? null;
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

  const handleCenterViewportScroll = () => {
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

  centerViewport.addEventListener('scroll', handleCenterViewportScroll, { passive: true });
  removeCenterViewportListener = () => {
    centerViewport.removeEventListener('scroll', handleCenterViewportScroll);
  };
}

watch(quickSearch, () => {
  gridApi.value?.onFilterChanged();
});

onMounted(() => {
  try {
    const raw = localStorage.getItem(densityStorageKey.value);
    if (raw) {
      currentDensity.value = resolveGridDensity(raw).key;
    }
  } catch {
    /* ignore */
  }

  updateGridViewportHeight();
  window.addEventListener('resize', updateGridViewportHeight);
  window.addEventListener('resize', syncBottomScrollbar);
});

onUnmounted(() => {
  window.removeEventListener('resize', updateGridViewportHeight);
  window.removeEventListener('resize', syncBottomScrollbar);
  removeCenterViewportListener?.();
});

function onFirstDataRendered() {
  nextTick(() => {
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
  });
}
</script>

<style scoped>
.toolbar-button {
  @apply inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800;
}
</style>
