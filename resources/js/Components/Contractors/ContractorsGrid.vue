<template>
  <div ref="gridSection" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
          <input
            v-model="quickSearch"
            type="text"
            placeholder="Поиск по реестру"
            class="w-72 rounded-xl border border-zinc-200 bg-white py-1.5 pl-10 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
          />
        </div>

        <button
          type="button"
          class="toolbar-button"
          @click="openColumnModal"
        >
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

        <button
          type="button"
          class="toolbar-button"
          @click="resetToRoleDefaults"
        >
          <RotateCcw class="h-4 w-4" />
          Сбросить
        </button>
      </div>

      <button type="button" class="toolbar-button" @click="$emit('create')">
        <Plus class="h-4 w-4" />
        Новый контрагент
      </button>
    </div>

    <div ref="gridPanel" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
      <div class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 overflow-hidden" :class="densityClass" :style="gridContainerStyle">
        <AgGridVue
          ref="agGrid"
          :gridOptions="gridOptions"
          :rowData="rows"
          :columnDefs="dynamicColumnDefs"
          :defaultColDef="defaultColDef"
          :domLayout="'normal'"
          :pagination="false"
          :animateRows="true"
          :suppressCellFocus="true"
          :suppressMovableColumns="true"
          :suppressHorizontalScroll="false"
          :alwaysShowVerticalScroll="true"
          style="height: 100%; width: 100%;"
          @grid-ready="onGridReady"
          @first-data-rendered="onFirstDataRendered"
          @cell-clicked="onCellClicked"
          @cell-double-clicked="onCellDoubleClicked"
          @column-visible="saveColumnState"
          @column-resized="saveColumnState"
          @column-moved="saveColumnState"
          @column-pinned="saveColumnState"
          @sort-changed="saveColumnState"
          @filter-changed="onFilterChanged"
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
              <div class="text-sm text-zinc-500 dark:text-zinc-400">
                Видимость, порядок и ширина сохраняются для текущего пользователя
              </div>
            </div>
            <button
              type="button"
              class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800"
              @click="closeColumnModal"
            >
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
              <button
                type="button"
                class="mt-0.5 cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                @click.prevent
              >
                ⋮⋮
              </button>
              <input
                type="checkbox"
                class="mt-1 rounded border-zinc-300"
                :checked="column.visible"
                @change="toggleColumnVisibility(column.field)"
              />
              <div class="min-w-0">
                <div class="text-sm font-medium">{{ column.headerName }}</div>
              </div>
            </label>
          </div>

          <div class="flex items-center justify-between border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <button
              type="button"
              class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
              @click="stageRoleDefaults"
            >
              Сбросить по роли
            </button>

            <div class="flex items-center gap-3">
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
                Сохранить
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Plus, RotateCcw, Rows3, Search, Settings2, X } from 'lucide-vue-next';

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
  availableColumns: {
    type: Array,
    default: () => [],
  },
  roleColumnsConfig: {
    type: Object,
    default: () => ({}),
  },
  userId: {
    type: [String, Number],
    default: 'guest',
  },
});

const emit = defineEmits(['create', 'row-select', 'columns-changed']);

const fallbackColumns = [
  { field: 'name', label: 'Название', width: 240, minWidth: 190, type: null },
  { field: 'status_text', label: 'Статус', width: 130, minWidth: 110, type: null },
  { field: 'activity_types_label', label: 'Вид деятельности', width: 220, minWidth: 180, type: null },
  { field: 'inn', label: 'ИНН', width: 140, minWidth: 120, type: null },
  { field: 'primary_contact', label: 'Основной контакт', width: 220, minWidth: 180, type: null },
];

const defaultVisibleFields = [
  'name',
  'status_text',
  'activity_types_label',
  'inn',
  'primary_contact',
  'phone',
  'email',
  'orders_count',
  'current_debt',
];

/** Без плавающей строки фильтра (как в реестре заказов — меньше DOM). */
const CONTRACTORS_NO_FLOATING_FILTER = new Set([
  'primary_contact',
  'phone',
  'email',
  'orders_count',
]);

const agGrid = ref(null);
const gridApi = ref(null);
const showColumnModal = ref(false);
const columnModalFilterSnapshot = ref(null);
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
/** Снять подписки AG Grid на пересчёт нижнего скролла (gridSizeChanged и т.д.) */
let removeGridScrollbarSyncListeners = null;

const gridOptions = {
  theme: 'legacy',
  getRowId: (params) => String(params.data?.id ?? ''),
};

const storageKey = computed(() => `contractors_grid_state_v1_${props.userId}`);
const filterModelStorageKey = computed(() => `contractors_grid_filter_model_v1_${props.userId}`);
const densityStorageKey = computed(() => `contractors_grid_density_${props.userId}`);
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
  floatingFilter: false,
  minWidth: 90,
  suppressSizeToFit: true,
};

const getAllColumns = () => {
  const sourceColumns = props.availableColumns?.length ? props.availableColumns : fallbackColumns;

  return sourceColumns.map((column) => ({
    field: column.field,
    headerName: column.headerName ?? column.label ?? column.field,
    width: column.width ?? 160,
    minWidth: column.minWidth ?? 100,
    type: column.type ?? null,
  }));
};

const getRoleColumnPreset = () => {
  const preset = props.roleColumnsConfig?.contractors;

  if (!Array.isArray(preset) || preset.length === 0) {
    return null;
  }

  const columnsByField = new Map(getAllColumns().map((column) => [column.field, column]));

  return preset
    .filter((column) => columnsByField.has(column?.colId))
    .map((column, index) => ({
      colId: column.colId,
      hide: Boolean(column.hide),
      width: Number(column.width) > 0 ? Number(column.width) : (columnsByField.get(column.colId)?.width ?? 140),
      order: Number.isInteger(column.order) ? column.order : index,
    }))
    .sort((left, right) => left.order - right.order);
};

const getAllowedColumns = () => {
  const roleColumnPreset = getRoleColumnPreset();

  if (!roleColumnPreset) {
    return getAllColumns();
  }

  const allowedColumnIds = new Set(
    roleColumnPreset
      .filter((column) => !column.hide)
      .map((column) => column.colId),
  );

  return getAllColumns().filter((column) => allowedColumnIds.has(column.field));
};

const buildRoleDefaultState = () => {
  const roleColumnPreset = getRoleColumnPreset();

  if (roleColumnPreset) {
    return roleColumnPreset
      .map((column) => ({
        colId: column.colId,
        hide: false,
        width: column.width,
      }))
      .filter((column) => getAllowedColumns().some((allowedColumn) => allowedColumn.field === column.colId));
  }

  return getAllowedColumns().map((column) => ({
    colId: column.field,
    hide: !defaultVisibleFields.includes(column.field),
    width: column.width,
  }));
};

const dynamicColumnDefs = computed(() => {
  return getAllowedColumns().map((column) => {
    const columnDefinition = {
      field: column.field,
      headerName: column.headerName,
      width: column.width,
      minWidth: column.minWidth,
      sortable: true,
      filter: true,
      resizable: true,
      suppressSizeToFit: true,
      floatingFilter: !CONTRACTORS_NO_FLOATING_FILTER.has(column.field),
      valueFormatter: (params) => formatValue(params.value, column.type),
    };

    if (column.field === 'name') {
      columnDefinition.pinned = 'left';
      columnDefinition.lockPinned = true;
      columnDefinition.cellClass = 'orders-grid-order-number-cell';
      columnDefinition.headerClass = 'orders-grid-order-number-header';
    }

    if (column.type === 'numeric') {
      columnDefinition.filter = 'agNumberColumnFilter';
    }

    return columnDefinition;
  });
});

const saveColumnState = () => {
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
      sort: column.sort ?? null,
      sortIndex: column.sortIndex ?? null,
    }));

    localStorage.setItem(storageKey.value, JSON.stringify(columnState));
    syncModalColumnsWithGrid();
    syncBottomScrollbar();
    requestAnimationFrame(() => {
      syncBottomScrollbar();
    });
  }, 250);
};

const loadColumnState = () => {
  if (!gridApi.value) {
    return false;
  }

  const savedState = localStorage.getItem(storageKey.value);

  if (!savedState) {
    return false;
  }

  try {
    const parsedState = JSON.parse(savedState);
    const allowedColumnIds = new Set(getAllowedColumns().map((column) => column.field));

    gridApi.value.applyColumnState({
      state: parsedState
        .filter((column) => allowedColumnIds.has(column.colId))
        .map((column) => ({
          colId: column.colId,
          hide: column.hide,
          width: column.width,
          sort: column.sort ?? null,
          sortIndex: column.sortIndex ?? null,
        })),
      applyOrder: true,
    });

    syncModalColumnsWithGrid();

    return true;
  } catch (error) {
    console.error('Error loading contractors grid state', error);

    return false;
  }
};

const resetToRoleDefaults = () => {
  if (!gridApi.value) {
    return;
  }

  gridApi.value.applyColumnState({
    state: buildRoleDefaultState(),
    applyOrder: true,
  });

  saveColumnState();
};

const loadDensity = () => {
  const savedDensity = localStorage.getItem(densityStorageKey.value);
  currentDensity.value = savedDensity ? resolveGridDensity(savedDensity).key : defaultGridDensity;
};

const applyDensity = (densityKey) => {
  currentDensity.value = resolveGridDensity(densityKey).key;
  localStorage.setItem(densityStorageKey.value, currentDensity.value);
  showDensityMenu.value = false;

  nextTick(() => {
    refreshGrid();
  });
};

const toggleDensityMenu = () => {
  showDensityMenu.value = !showDensityMenu.value;
};

const syncModalColumnsWithGrid = () => {
  const allColumns = getAllowedColumns();

  if (!gridApi.value) {
    modalColumns.value = allColumns.map((column) => ({
      ...column,
      visible: true,
    }));

    return;
  }

  const columnsByField = new Map(allColumns.map((column) => [column.field, column]));

  modalColumns.value = gridApi.value
    .getAllGridColumns()
    .map((gridColumn) => {
      const column = columnsByField.get(gridColumn.getColId());

      if (!column) {
        return null;
      }

      return {
        ...column,
        width: gridColumn.getActualWidth(),
        visible: gridColumn.isVisible(),
      };
    })
    .filter(Boolean);
};

function cloneAgFilterModel(model) {
  try {
    return model ? JSON.parse(JSON.stringify(model)) : null;
  } catch {
    return null;
  }
}

function restoreColumnModalFilters() {
  if (!gridApi.value || columnModalFilterSnapshot.value === null) {
    return;
  }

  gridApi.value.setFilterModel(columnModalFilterSnapshot.value);
}

const openColumnModal = () => {
  showDensityMenu.value = false;
  columnModalFilterSnapshot.value = gridApi.value ? cloneAgFilterModel(gridApi.value.getFilterModel()) : null;
  syncModalColumnsWithGrid();
  showColumnModal.value = true;
  nextTick(() => {
    restoreColumnModalFilters();
  });
};

const closeColumnModal = () => {
  showColumnModal.value = false;
  draggedColumnField.value = null;
  syncModalColumnsWithGrid();
  nextTick(() => {
    restoreColumnModalFilters();
    columnModalFilterSnapshot.value = null;
  });
};

const toggleColumnVisibility = (field) => {
  modalColumns.value = modalColumns.value.map((column) => (
    column.field === field
      ? { ...column, visible: !column.visible }
      : column
  ));
};

const onColumnDragStart = (field) => {
  draggedColumnField.value = field;
};

const onColumnDrop = (targetField) => {
  if (!draggedColumnField.value || draggedColumnField.value === targetField) {
    draggedColumnField.value = null;

    return;
  }

  const reorderedColumns = [...modalColumns.value];
  const draggedIndex = reorderedColumns.findIndex((column) => column.field === draggedColumnField.value);
  const targetIndex = reorderedColumns.findIndex((column) => column.field === targetField);

  if (draggedIndex === -1 || targetIndex === -1) {
    draggedColumnField.value = null;

    return;
  }

  const [draggedColumn] = reorderedColumns.splice(draggedIndex, 1);
  reorderedColumns.splice(targetIndex, 0, draggedColumn);
  modalColumns.value = reorderedColumns;
  draggedColumnField.value = null;
};

const stageRoleDefaults = () => {
  const columnsByField = new Map(getAllowedColumns().map((column) => [column.field, column]));

  modalColumns.value = buildRoleDefaultState()
    .map((state) => {
      const column = columnsByField.get(state.colId);

      if (!column) {
        return null;
      }

      return {
        ...column,
        width: state.width,
        visible: !state.hide,
      };
    })
    .filter(Boolean);
};

const applyColumnModalChanges = () => {
  if (!gridApi.value) {
    showColumnModal.value = false;
    columnModalFilterSnapshot.value = null;

    return;
  }

  const snapshot = columnModalFilterSnapshot.value;

  gridApi.value.applyColumnState({
    state: modalColumns.value.map((column) => ({
      colId: column.field,
      hide: !column.visible,
      width: column.width,
    })),
    applyOrder: true,
  });

  saveColumnState();
  emit('columns-changed', modalColumns.value);
  showColumnModal.value = false;
  columnModalFilterSnapshot.value = null;

  nextTick(() => {
    if (snapshot !== null && gridApi.value) {
      gridApi.value.setFilterModel(snapshot);
    }
  });
};

const onCellClicked = (params) => {
  if (params.data?.id) {
    emit('row-select', params.data.id);
  }
};

const onCellDoubleClicked = (params) => {
  if (params.data?.id) {
    emit('row-select', params.data.id);
  }
};

const persistFilterModel = () => {
  if (!gridApi.value) {
    return;
  }

  if (filterModelSaveTimeout) {
    clearTimeout(filterModelSaveTimeout);
  }

  filterModelSaveTimeout = setTimeout(() => {
    try {
      const model = gridApi.value.getFilterModel();
      localStorage.setItem(filterModelStorageKey.value, JSON.stringify(model ?? {}));
    } catch (error) {
      console.error('Error saving contractors grid filter model', error);
    }
  }, 250);
};

const loadPersistedFilterModel = () => {
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
    console.error('Error loading contractors grid filter model', error);
  }
};

const onFilterChanged = () => {
  persistFilterModel();
};

const registerGridScrollbarWidthSync = (api) => {
  removeGridScrollbarSyncListeners?.();

  const scheduleSync = () => {
    nextTick(() => {
      syncBottomScrollbar();
    });
  };

  api.addEventListener('gridSizeChanged', scheduleSync);
  api.addEventListener('displayedColumnsChanged', scheduleSync);

  removeGridScrollbarSyncListeners = () => {
    api.removeEventListener('gridSizeChanged', scheduleSync);
    api.removeEventListener('displayedColumnsChanged', scheduleSync);
    removeGridScrollbarSyncListeners = null;
  };
};

const onGridReady = async (params) => {
  gridApi.value = params.api;

  registerGridScrollbarWidthSync(params.api);

  if (quickSearch.value.trim() !== '') {
    gridApi.value.setGridOption('quickFilterText', quickSearch.value);
  }

  if (!loadColumnState()) {
    resetToRoleDefaults();
  }

  loadPersistedFilterModel();

  await nextTick();
  updateGridViewportHeight();
  attachCenterViewportListener();
  syncBottomScrollbar();
  requestAnimationFrame(() => {
    syncBottomScrollbar();
  });
};

const onFirstDataRendered = () => {
  requestAnimationFrame(() => {
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
    requestAnimationFrame(() => {
      syncBottomScrollbar();
    });
  });
};

watch(quickSearch, (value) => {
  if (!gridApi.value) {
    return;
  }

  gridApi.value.setGridOption('quickFilterText', value);
});

watch(
  () => props.rows,
  async () => {
    await nextTick();
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
  },
);

const getCenterViewport = () => agGrid.value?.$el?.querySelector('.ag-viewport.ag-center-cols-viewport') ?? null;

const updateGridViewportHeight = () => {
  const panelElement = gridPanel.value;

  if (!panelElement) {
    return;
  }

  const sectionTop = panelElement.getBoundingClientRect().top;
  const bottomScrollbarHeight = bottomScrollbar.value?.offsetHeight ?? 16;
  const commandBarFooter = document.querySelector('footer');
  const footerTop = commandBarFooter?.getBoundingClientRect().top ?? window.innerHeight;
  const footerReserve = 60;

  gridViewportHeight.value = Math.max(
    280,
    Math.floor(footerTop - sectionTop - bottomScrollbarHeight - footerReserve),
  );
};

const syncBottomScrollbar = () => {
  const centerViewport = getCenterViewport();

  if (!centerViewport) {
    return;
  }

  bottomScrollbarWidth.value = Math.max(centerViewport.scrollWidth, centerViewport.clientWidth);
  updateGridViewportHeight();

  if (bottomScrollbar.value && !isSyncingHorizontalScroll) {
    bottomScrollbar.value.scrollLeft = centerViewport.scrollLeft;
  }
};

const onBottomScrollbarScroll = () => {
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
};

const attachCenterViewportListener = () => {
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
};

const refreshGrid = () => {
  if (!gridApi.value) {
    return;
  }

  nextTick(() => {
    gridApi.value.resetRowHeights();
    gridApi.value.refreshCells({ force: true });
    syncBottomScrollbar();
  });
};

onMounted(() => {
  loadDensity();
  updateGridViewportHeight();
  window.addEventListener('resize', updateGridViewportHeight);
  window.addEventListener('resize', syncBottomScrollbar);
});

onUnmounted(() => {
  window.removeEventListener('resize', updateGridViewportHeight);
  window.removeEventListener('resize', syncBottomScrollbar);
  removeCenterViewportListener?.();
  removeGridScrollbarSyncListeners?.();

  if (saveTimeout) {
    clearTimeout(saveTimeout);
  }

  if (filterModelSaveTimeout) {
    clearTimeout(filterModelSaveTimeout);
  }
});

function formatValue(value, type) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (type === 'boolean') {
    return value ? 'Да' : 'Нет';
  }

  if (type === 'numeric') {
    return new Intl.NumberFormat('ru-RU').format(Number(value));
  }

  return value;
}
</script>

<style scoped>
.toolbar-button {
  @apply inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800;
}
</style>
