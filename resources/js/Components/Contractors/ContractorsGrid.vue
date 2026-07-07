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

        <button
          type="button"
          :class="crmGridToolbarBtn"
          @click="openColumnModal"
        >
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

        <button
          v-if="canExportGrid"
          type="button"
          :class="crmGridToolbarBtn"
          title="Экспорт в CSV"
          @click="openExportModal"
        >
          <Download class="h-4 w-4" />
          CSV
        </button>

        <GridBulkIconActions
          :selected-count="selectedContractorIds.length"
          :actions="bulkOwnerActions"
          @action="openBulkOwnerModal"
        />

        <GridViewsBar
          grid-key="contractors"
          :user-id="userId"
          :get-grid-api="() => gridApi"
          :column-storage-key="storageKey"
          :filter-storage-key="filterModelStorageKey"
          :quick-search="quickSearch"
          :on-reset-defaults="resetGridViewState"
          @update:quick-search="quickSearch = $event"
          @applied="onGridViewApplied"
          @pinned-changed="onGridViewsPinnedChanged"
        />
      </div>
    </div>

    <div
      ref="gridPanel"
      :class="crmGridInnerPanel"
      @contextmenu.capture="suppressNativeContextMenuCapture"
    >
      <div class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden" :class="densityClass" :style="gridContainerStyle">
        <AgGridVue
          ref="agGrid"
          :gridOptions="gridOptions"
          :rowData="rows"
          :columnDefs="dynamicColumnDefs"
          :defaultColDef="defaultColDef"
          :domLayout="'normal'"
          :pagination="false"
          :animateRows="false"
          :suppressCellFocus="true"
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
          @selection-changed="onSelectionChanged"
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
        <div :class="`${crmModalPanel} w-full max-w-2xl shadow-2xl`">
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
              :class="crmBtnNeutral"
              @click="stageRoleDefaults"
            >
              Сбросить по роли
            </button>

            <div class="flex items-center gap-3">
              <button
                type="button"
                :class="crmBtnNeutral"
                @click="closeColumnModal"
              >
                Закрыть
              </button>
              <button
                type="button"
                :class="crmBtnCreate"
                @click="applyColumnModalChanges"
              >
                Сохранить
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="showBulkOwnerModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="closeBulkOwnerModal"
      >
        <div :class="`${crmModalPanel} w-full max-w-md shadow-2xl`">
          <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="text-lg font-semibold">Сменить владельца</div>
            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
              Выбрано контрагентов: {{ selectedContractorIds.length }}
            </div>
          </div>

          <div class="space-y-3 p-5">
            <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">
              Новый владелец
            </label>
            <select
              v-model="bulkOwnerId"
              class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >
              <option value="">Не назначен</option>
              <option v-for="user in users" :key="user.id" :value="String(user.id)">
                {{ user.name }}
              </option>
            </select>

            <p v-if="bulkOwnerError" class="text-sm text-rose-600 dark:text-rose-400">
              {{ bulkOwnerError }}
            </p>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <button
              type="button"
              :class="crmBtnNeutral"
              :disabled="bulkOwnerProcessing"
              @click="closeBulkOwnerModal"
            >
              Отмена
            </button>
            <button
              type="button"
              :class="crmBtnCreate"
              :disabled="bulkOwnerProcessing || selectedContractorIds.length === 0"
              @click="submitBulkOwnerChange"
            >
              {{ bulkOwnerProcessing ? 'Сохранение…' : 'Сменить' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <GridExportDialog
      :show="showExportModal"
      :columns="exportColumns"
      :responsible-options="exportResponsibleOptions"
      responsible-label="Владельцы"
      @close="showExportModal = false"
      @export="handleGridExport"
    />

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
import axios from 'axios';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Rows3, Search, Settings2, Download, UserRound, X } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import { applySavedToColDef, buildLayoutIndex, readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';
import { applyAgGridIdColumnSizing, autoSizeIdColumnIfNotPersisted } from '@/support/agGridIdColumn.js';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridBulkIconActions from '@/Components/Grid/GridBulkIconActions.vue';
import GridExportDialog from '@/Components/Grid/GridExportDialog.vue';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { applyAgSetListColumn } from '@/Components/Grid/agSetListFilter.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmGridDropdown,
    crmGridInnerPanel,
    crmGridSearchField,
    crmGridToolbarBtn,
    crmModalPanel,
} from '@/support/crmUi.js';
import {
  CRM_AG_GRID_DENSITY_CHANGED,
  readPersistedAgGridDensity,
  schedulePersistAgGridDensityToProfile,
  writeLocalAgGridDensity,
} from '@/support/agGridUserDensity.js';
import {
    buildExportColumnsFromGrid,
    buildResponsibleOptionsFromRows,
    defaultGridExportFileName,
    exportAgGridToCsv,
} from '@/support/gridCsvExport.js';

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
  users: {
    type: Array,
    default: () => [],
  },
  userId: {
    type: [String, Number],
    default: 'guest',
  },
});

const page = usePage();

const emit = defineEmits(['row-select', 'columns-changed', 'create-request']);

const fallbackColumns = [
  { field: 'id', label: 'ID', width: 56, minWidth: 48, type: 'numeric' },
  { field: 'name', label: 'Название', width: 240, minWidth: 190, type: null },
  { field: 'status_text', label: 'Статус', width: 130, minWidth: 110, type: null },
  { field: 'activity_types_label', label: 'Вид деятельности', width: 220, minWidth: 180, type: null },
  { field: 'type_label', label: 'Тип контрагента', width: 160, minWidth: 140, type: null },
  { field: 'inn', label: 'ИНН', width: 140, minWidth: 120, type: null },
  { field: 'primary_contact', label: 'Основной контакт', width: 220, minWidth: 180, type: null },
  { field: 'owner_name', label: 'Владелец', width: 180, minWidth: 140, type: null },
];

const defaultVisibleFields = [
  'id',
  'name',
  'status_text',
  'activity_types_label',
  'type_label',
  'inn',
  'primary_contact',
  'owner_name',
  'phone',
  'email',
  'orders_count',
  'current_debt',
];

/** Только выпадающий список значений (как в гриде «Задачи»). */
const CONTRACTORS_SET_FILTER_FIELDS = new Set([
  'status_text',
  'activity_types_label',
  'type_label',
  'owner_name',
]);

const CONTRACTORS_GRID_EXCLUDED_FIELDS = new Set([
  'status_badge_class',
  'actions',
  '__actions',
]);

const agGrid = ref(null);
const gridApi = ref(null);
const showColumnModal = ref(false);
const showExportModal = ref(false);
const exportColumns = ref([]);
const exportResponsibleOptions = ref([]);
const columnModalFilterSnapshot = ref(null);
const showDensityMenu = ref(false);
const modalColumns = ref([]);
const draggedColumnField = ref(null);
const quickSearch = ref('');
const gridViewsRevision = ref(0);
const currentDensity = ref(defaultGridDensity);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const selectedRows = ref([]);
const showBulkOwnerModal = ref(false);
const bulkOwnerId = ref('');
const bulkOwnerError = ref('');
const bulkOwnerProcessing = ref(false);

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
  gridPanel,
  bottomScrollbar,
  agGrid,
  gridApi,
});

let saveTimeout = null;
let filterModelSaveTimeout = null;
let removeGridScrollbarSyncListeners = null;

const {
  contextMenu,
  closeContextMenu: closeRowContextMenu,
  openContextMenu,
} = useGridContextMenu();

function onCellContextMenu(params) {
  const ev = params.event;
  if (ev?.preventDefault) {
    ev.preventDefault();
  }

  const row = params.node?.data;
  if (!row?.id) {
    closeRowContextMenu();

    return;
  }

  const items = [
    {
      label: 'Открыть карточку',
      run: () => {
        emit('row-select', row.id);
      },
    },
    {
      label: 'Новый контрагент',
      run: () => {
        emit('create-request');
      },
    },
  ];

  openContextMenu(ev, items);
}

const gridOptions = {
  theme: 'legacy',
  localeText: agGridLocaleRu,
  animateRows: false,
  preventDefaultOnContextMenu: true,
  getRowId: (params) => String(params.data?.id ?? ''),
  rowSelection: {
    mode: 'multiRow',
    checkboxes: true,
    headerCheckbox: true,
    enableClickSelection: false,
  },
  selectionColumnDef: {
    pinned: 'left',
    sortable: false,
    resizable: false,
    suppressHeaderMenuButton: true,
    maxWidth: 48,
    minWidth: 48,
  },
  onCellContextMenu,
};

const storageKey = computed(() => `contractors_grid_state_v1_${props.userId}`);
const filterModelStorageKey = computed(() => `contractors_grid_filter_model_v1_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);
const displayData = computed(() => props.rows ?? []);
const canExportGrid = computed(() => page.props.can_export_grid === true);
const selectedContractorIds = computed(() => selectedRows.value
  .map((row) => Number(row?.id))
  .filter((id) => Number.isFinite(id) && id > 0));

const bulkOwnerActions = computed(() => ([
  {
    key: 'owner',
    icon: UserRound,
    title: 'Сменить владельца',
    label: 'Сменить владельца',
  },
]));

const defaultColDef = {
  sortable: true,
  filter: true,
  resizable: true,
  floatingFilter: false,
  minWidth: 90,
  suppressSizeToFit: true,
};

function contractorSetFilterLabel(field, row) {
  if (!row) {
    return '—';
  }

  const raw = row[field];

  return raw === null || raw === undefined || raw === '' ? '—' : String(raw);
}

function collectContractorSetFilterValues(field) {
  const values = new Set();

  if (field === 'owner_name' && Array.isArray(props.users)) {
    for (const user of props.users) {
      const name = String(user?.name ?? '').trim();

      if (name !== '') {
        values.add(name);
      }
    }
  }

  for (const row of props.rows ?? []) {
    values.add(contractorSetFilterLabel(field, row));
  }

  return [...values].sort((left, right) => String(left).localeCompare(String(right), 'ru'));
}

function sanitizeContractorsFilterModel(model) {
  if (!model || typeof model !== 'object') {
    return model;
  }

  const next = { ...model };
  const ownerFilter = next.owner_name;

  if (
    ownerFilter
    && (ownerFilter.filterType === 'text' || typeof ownerFilter.filter === 'string' || typeof ownerFilter.type === 'string')
  ) {
    delete next.owner_name;
  }

  return next;
}

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
  void gridViewsRevision.value;
  const saved = readPersistedAgGridColumnState(storageKey.value);

  const raw = getAllowedColumns().map((column) => {
    const columnDefinition = {
      field: column.field,
      headerName: column.headerName,
      width: column.width,
      minWidth: column.minWidth,
      sortable: true,
      filter: true,
      resizable: true,
      suppressSizeToFit: true,
      floatingFilter: false,
      valueFormatter: (params) => formatValue(params.value, column.type),
    };

    if (CONTRACTORS_SET_FILTER_FIELDS.has(column.field)) {
      applyAgSetListColumn(columnDefinition, {
        values: collectContractorSetFilterValues(column.field),
        filterValueGetter: (params) => contractorSetFilterLabel(column.field, params.data),
        floatingFilterRow: true,
      });
    } else if (column.type === 'numeric' && column.field !== 'id') {
      columnDefinition.filter = 'agNumberColumnFilter';
    } else if (column.field !== 'id') {
      columnDefinition.filter = 'agTextColumnFilter';
    }

    if (column.field === 'name') {
      columnDefinition.pinned = 'left';
      columnDefinition.lockPinned = true;
      columnDefinition.cellClass = 'orders-grid-order-number-cell';
      columnDefinition.headerClass = 'orders-grid-order-number-header';
    }

    if (column.field === 'id') {
      columnDefinition.pinned = 'left';
      columnDefinition.width = 56;
      columnDefinition.minWidth = 48;
      columnDefinition.maxWidth = 72;
      columnDefinition.filter = false;
      columnDefinition.floatingFilter = false;
      columnDefinition.suppressHeaderFilterButton = true;
      columnDefinition.getQuickFilterText = () => '';

      return applyAgGridIdColumnSizing(columnDefinition);
    }

    return columnDefinition;
  });

  if (!saved?.length) {
    return raw;
  }

  const byField = new Map(raw.map((d) => [d.field, d]));
  const { orderedFields, byColId } = buildLayoutIndex([...byField.keys()], saved);

  return orderedFields
    .map((field) => {
      const def = byField.get(field);
      return def ? applySavedToColDef(def, byColId.get(field)) : null;
    })
    .filter(Boolean);
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
    refreshAgGridPanelLayout();
    requestAnimationFrame(() => {
      refreshAgGridPanelLayout();
    });
  }, 250);
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

const resetGridViewState = () => {
  resetToRoleDefaults();

  if (gridApi.value) {
    gridApi.value.setFilterModel({});
  }

  localStorage.removeItem(filterModelStorageKey.value);
  quickSearch.value = '';
  gridViewsRevision.value++;
};

const onGridViewApplied = () => {
  gridViewsRevision.value++;

  nextTick(() => {
    refreshGrid();
    refreshAgGridPanelLayout();
  });
};

const onGridViewsPinnedChanged = () => {
  router.reload({ preserveScroll: true });
};

const loadDensity = () => {
  currentDensity.value = readPersistedAgGridDensity(props.userId, page.props.auth?.user);
};

const applyDensity = (densityKey) => {
  currentDensity.value = resolveGridDensity(densityKey).key;
  writeLocalAgGridDensity(props.userId, currentDensity.value);
  schedulePersistAgGridDensityToProfile(currentDensity.value);
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
  if (isSelectionColumnClick(params)) {
    return;
  }

  if (params.data?.id) {
    emit('row-select', params.data.id);
  }
};

const onCellDoubleClicked = (params) => {
  if (isSelectionColumnClick(params)) {
    return;
  }

  if (params.data?.id) {
    emit('row-select', params.data.id);
  }
};

function isSelectionColumnClick(params) {
  const columnId = params.column?.getColId?.() ?? '';

  return columnId === 'ag-Grid-SelectionColumn'
    || columnId === 'ag-Grid-AutoColumn'
    || params.event?.target?.closest?.('.ag-selection-checkbox');
}

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
    const model = sanitizeContractorsFilterModel(JSON.parse(raw));
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
      refreshAgGridPanelLayout();
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

  if (!readPersistedAgGridColumnState(storageKey.value)?.length) {
    resetToRoleDefaults();
  }

  loadPersistedFilterModel();

  await nextTick();
  refreshAgGridPanelLayout();
  requestAnimationFrame(() => {
    refreshAgGridPanelLayout();
  });
};

function onSelectionChanged(event) {
  selectedRows.value = event.api.getSelectedRows();
}

const onFirstDataRendered = () => {
  requestAnimationFrame(() => {
    autoSizeIdColumnIfNotPersisted(gridApi.value, storageKey.value);
    refreshAgGridPanelLayout();
    requestAnimationFrame(() => {
      refreshAgGridPanelLayout();
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
    loadPersistedFilterModel();
    refreshAgGridPanelLayout();
  },
);

const refreshGrid = () => {
  if (!gridApi.value) {
    return;
  }

  nextTick(() => {
    gridApi.value.resetRowHeights();
    gridApi.value.refreshCells({ force: true });
    refreshAgGridPanelLayout();
  });
};

function openExportModal() {
  if (!gridApi.value) {
    return;
  }

  exportColumns.value = buildExportColumnsFromGrid(gridApi.value, CONTRACTORS_GRID_EXCLUDED_FIELDS);
  exportResponsibleOptions.value = buildResponsibleOptionsFromRows(displayData.value, {
    idField: 'owner_id',
    nameField: 'owner_name',
  });
  showExportModal.value = true;
}

function handleGridExport(payload) {
  if (!gridApi.value) {
    return;
  }

  exportAgGridToCsv({
    gridApi: gridApi.value,
    columns: payload.columns,
    fileName: defaultGridExportFileName('kontragenty'),
    responsibleMode: payload.responsibleMode,
    responsibleIds: payload.responsibleIds,
    responsibleIdField: 'owner_id',
  });
  showExportModal.value = false;
}

function openBulkOwnerModal() {
  if (selectedContractorIds.value.length === 0) {
    return;
  }

  bulkOwnerError.value = '';
  bulkOwnerId.value = '';
  showBulkOwnerModal.value = true;
}

function closeBulkOwnerModal() {
  if (bulkOwnerProcessing.value) {
    return;
  }

  showBulkOwnerModal.value = false;
  bulkOwnerError.value = '';
}

function massUpdateOwnerUrl() {
  const query = typeof window === 'undefined' ? '' : window.location.search;

  return `${route('contractors.mass-update-owner')}${query}`;
}

async function submitBulkOwnerChange() {
  if (selectedContractorIds.value.length === 0) {
    return;
  }

  bulkOwnerProcessing.value = true;
  bulkOwnerError.value = '';

  try {
    await axios.post(massUpdateOwnerUrl(), {
      contractor_ids: selectedContractorIds.value,
      owner_id: bulkOwnerId.value === '' ? null : Number(bulkOwnerId.value),
    });

    gridApi.value?.deselectAll();
    selectedRows.value = [];
    showBulkOwnerModal.value = false;
    router.reload({
      only: ['contractors', 'selectedContractor'],
      preserveScroll: true,
    });
  } catch (error) {
    bulkOwnerError.value = error?.response?.data?.message ?? 'Не удалось сменить владельца.';
  } finally {
    bulkOwnerProcessing.value = false;
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
    refreshGrid();
  });
}

onMounted(() => {
  loadDensity();
  window.addEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});

onUnmounted(() => {
  window.removeEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
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
  @apply inline-flex items-center gap-2 rounded-none border border-zinc-200 bg-white px-2.5 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800;
}
</style>
