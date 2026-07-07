<template>
  <div ref="gridSection" class="flex min-h-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 items-center gap-2">
      <div class="flex flex-wrap items-center gap-2">
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

        <GridViewsBar
          grid-key="leads"
          :user-id="userId"
          :get-grid-api="() => gridApi"
          :column-storage-key="storageKey"
          :filter-storage-key="filterModelStorageKey"
          :quick-search-storage-key="filtersStorageKey"
          quick-search-json-wrapper
          :quick-search="quickSearch"
          :on-reset-defaults="resetGridViewState"
          @update:quick-search="quickSearch = $event"
          @applied="onGridViewApplied"
          @pinned-changed="onGridViewsPinnedChanged"
        />

        <GridBulkIconActions
          :selected-count="selectedLeadIds.length"
          :actions="bulkActions"
          @action="onBulkAction"
        />
      </div>
    </div>

    <div
      ref="gridPanel"
      :class="crmGridInnerPanel"
      @contextmenu.capture="suppressNativeContextMenuCapture"
    >
      <div
        class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden"
        :style="gridContainerStyle"
        :class="densityClass"
      >
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
          :suppressMovableColumns="true"
          :alwaysShowVerticalScroll="true"
          style="height: 100%; width: 100%;"
          @grid-ready="onGridReady"
          @first-data-rendered="onFirstDataRendered"
          @cell-double-clicked="onCellDoubleClicked"
          @cell-value-changed="onCellValueChanged"
          @selection-changed="onSelectionChanged"
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
        v-if="activeBulkModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="closeBulkModal"
      >
        <div :class="`${crmModalPanel} w-full max-w-md shadow-2xl`">
          <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="text-lg font-semibold">{{ bulkModalTitle }}</div>
            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
              Выбрано лидов: {{ selectedLeadIds.length }}
            </div>
          </div>

          <div class="space-y-3 p-5">
            <select
              v-if="activeBulkModal === 'source'"
              v-model="bulkSourceValue"
              class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >
              <option value="">Не указан</option>
              <option v-for="option in sourceOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <select
              v-else-if="activeBulkModal === 'responsible_id'"
              v-model="bulkResponsibleId"
              class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >
              <option value="">Выберите ответственного</option>
              <option v-for="user in responsibleUsers" :key="user.id" :value="String(user.id)">
                {{ user.name }}
              </option>
            </select>

            <select
              v-else-if="activeBulkModal === 'status'"
              v-model="bulkStatusValue"
              class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >
              <option value="">Выберите статус</option>
              <option v-for="option in inlineStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <p v-else-if="activeBulkModal === 'delete'" class="text-sm text-zinc-600 dark:text-zinc-300">
              Удалить выбранные лиды? Это действие необратимо.
            </p>

            <p v-if="bulkError" class="text-sm text-rose-600 dark:text-rose-400">
              {{ bulkError }}
            </p>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <button
              type="button"
              :class="crmBtnNeutral"
              :disabled="bulkProcessing"
              @click="closeBulkModal"
            >
              Отмена
            </button>
            <button
              type="button"
              :class="activeBulkModal === 'delete' ? 'inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50' : crmBtnCreate"
              :disabled="bulkProcessing || selectedLeadIds.length === 0 || !canSubmitBulkModal"
              @click="submitBulkModal"
            >
              {{ bulkProcessing ? 'Сохранение…' : bulkModalSubmitLabel }}
            </button>
          </div>
        </div>
      </div>

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
import { Megaphone, Rows3, Search, Settings2, Trash2, UserRound, Workflow, X } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import { applySavedToColDef, buildLayoutIndex, readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';
import { applyAgGridIdColumnSizing, autoSizeIdColumnIfNotPersisted } from '@/support/agGridIdColumn.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridBulkIconActions from '@/Components/Grid/GridBulkIconActions.vue';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { applyAgSetListColumn } from '@/Components/Grid/agSetListFilter.js';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
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
  allowCreate: {
    type: Boolean,
    default: true,
  },
  userId: {
    type: [String, Number],
    default: 'guest',
  },
  sourceOptions: {
    type: Array,
    default: () => [],
  },
  responsibleUsers: {
    type: Array,
    default: () => [],
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  canAssignResponsible: {
    type: Boolean,
    default: false,
  },
});

const page = usePage();

const sourceLabels = computed(() => {
  const labels = { ...fallbackSourceLabels };

  for (const option of props.sourceOptions ?? []) {
    if (option?.value) {
      labels[option.value] = option.label ?? option.value;
    }
  }

  return labels;
});

function sourceLabel(value) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  return sourceLabels.value[value] ?? value;
}

const emit = defineEmits(['create', 'create-from', 'row-dblclick', 'columns-changed', 'delete-request', 'grid-updated']);

const CLOSED_STATUSES = new Set(['won', 'lost']);

const inlineStatusOptions = computed(() => (props.statusOptions ?? []).filter((option) => !CLOSED_STATUSES.has(option?.value)));

const statusLabelByValue = computed(() => {
  const labels = { ...statusLabels };

  for (const option of props.statusOptions ?? []) {
    if (option?.value) {
      labels[option.value] = option.label ?? option.value;
    }
  }

  return labels;
});

const inlineStatusLabels = computed(() => inlineStatusOptions.value.map((option) => option.label));

const sourceLabelsByValue = computed(() => {
  const labels = { ...fallbackSourceLabels };

  for (const option of props.sourceOptions ?? []) {
    if (option?.value) {
      labels[option.value] = option.label ?? option.value;
    }
  }

  return labels;
});

const sourceLabelsForEditor = computed(() => Object.values(sourceLabelsByValue.value)
  .sort((left, right) => String(left).localeCompare(String(right), 'ru')));

const userIdByName = computed(() => {
  const map = new Map();

  for (const user of props.responsibleUsers ?? []) {
    if (user?.name && user?.id) {
      map.set(user.name, user.id);
    }
  }

  return map;
});

const responsibleNamesForEditor = computed(() => (props.responsibleUsers ?? [])
  .map((user) => user?.name)
  .filter(Boolean)
  .sort((left, right) => String(left).localeCompare(String(right), 'ru')));

const selectedRows = ref([]);
const selectedLeadIds = computed(() => selectedRows.value
  .map((row) => Number(row?.id))
  .filter((id) => Number.isFinite(id) && id > 0));

const activeBulkModal = ref(null);
const bulkSourceValue = ref('');
const bulkResponsibleId = ref('');
const bulkStatusValue = ref('');
const bulkError = ref('');
const bulkProcessing = ref(false);

const bulkActions = computed(() => {
  const actions = [
    {
      key: 'source',
      icon: Megaphone,
      title: 'Сменить источник',
      label: 'Сменить источник',
    },
  ];

  if (props.canAssignResponsible) {
    actions.push({
      key: 'responsible_id',
      icon: UserRound,
      title: 'Сменить ответственного',
      label: 'Сменить ответственного',
    });
  }

  actions.push(
    {
      key: 'status',
      icon: Workflow,
      title: 'Сменить статус',
      label: 'Сменить статус',
    },
    {
      key: 'delete',
      icon: Trash2,
      title: 'Удалить выбранные',
      label: 'Удалить',
      danger: true,
    },
  );

  return actions;
});

const bulkModalTitle = computed(() => {
  if (activeBulkModal.value === 'source') {
    return 'Сменить источник';
  }

  if (activeBulkModal.value === 'responsible_id') {
    return 'Сменить ответственного';
  }

  if (activeBulkModal.value === 'status') {
    return 'Сменить статус';
  }

  if (activeBulkModal.value === 'delete') {
    return 'Удалить лиды';
  }

  return '';
});

const bulkModalSubmitLabel = computed(() => {
  if (activeBulkModal.value === 'delete') {
    return 'Удалить';
  }

  return 'Применить';
});

const canSubmitBulkModal = computed(() => {
  if (activeBulkModal.value === 'source') {
    return true;
  }

  if (activeBulkModal.value === 'responsible_id') {
    return bulkResponsibleId.value !== '';
  }

  if (activeBulkModal.value === 'status') {
    return bulkStatusValue.value !== '';
  }

  if (activeBulkModal.value === 'delete') {
    return true;
  }

  return false;
});

function canEditGridField(row, field) {
  return Array.isArray(row?.inline_editable_fields) && row.inline_editable_fields.includes(field);
}

function canEditResponsibleCell(row) {
  return canEditGridField(row, 'responsible_id');
}

const statusLabels = {
  new: 'Новый',
  qualification: 'Квалификация',
  calculation: 'Просчёт',
  proposal_ready: 'КП готово',
  proposal_sent: 'КП отправлено',
  negotiation: 'Переговоры',
  won: 'Выигран',
  lost: 'Закрыт',
  on_hold: 'Отложен',
};

const fallbackSourceLabels = {
  inbound: 'Входящий',
  outbound: 'Исходящий',
  referral: 'Рекомендация',
  website: 'Сайт',
  existing_customer: 'Действующий клиент',
  base_reprocessing: 'Повторная обработка базы',
  other: 'Другое',
};

const fallbackColumns = [
  { field: 'id', label: 'ID', width: 56, minWidth: 48, type: 'numeric' },
  { field: 'number', label: '№ лида', width: 120, minWidth: 110, type: null },
  { field: 'status', label: 'Статус', width: 150, minWidth: 130, type: null },
  { field: 'title', label: 'Тема', width: 220, minWidth: 180, type: null },
  { field: 'source', label: 'Источник', width: 150, minWidth: 130, type: null },
  { field: 'counterparty_name', label: 'Контрагент', width: 200, minWidth: 160, type: null },
  { field: 'responsible_name', label: 'Ответственный', width: 180, minWidth: 150, type: null },
  { field: 'planned_shipping_date', label: 'План отгрузки', width: 140, minWidth: 120, type: 'date' },
  { field: 'target_price', label: 'Цена', width: 130, minWidth: 120, type: 'numeric' },
  { field: 'target_currency', label: 'Валюта', width: 100, minWidth: 90, type: null },
  { field: 'has_offer', label: 'Есть КП', width: 110, minWidth: 100, type: 'boolean' },
  { field: 'process_name', label: 'Процесс', width: 180, minWidth: 150, type: null },
  { field: 'current_stage_name', label: 'Этап', width: 170, minWidth: 140, type: null },
  { field: 'stage_due_at', label: 'Срок этапа', width: 150, minWidth: 130, type: 'datetime' },
  { field: 'is_stage_overdue', label: 'Этап просрочен', width: 130, minWidth: 110, type: 'boolean' },
  { field: 'created_at', label: 'Создан', width: 160, minWidth: 140, type: 'datetime' },
];

const defaultVisibleFields = [
  'id',
  'number',
  'status',
  'title',
  'source',
  'counterparty_name',
  'responsible_name',
  'planned_shipping_date',
  'target_price',
  'target_currency',
  'has_offer',
  'created_at',
];

/** Только выпадающий список значений (как в гриде «Задачи»). */
const LEADS_SET_FILTER_FIELDS = new Set([
  'status',
  'source',
  'responsible_name',
  'has_offer',
  'process_name',
]);

const agGrid = ref(null);
const gridApi = ref(null);
const showColumnModal = ref(false);
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

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
  gridPanel,
  bottomScrollbar,
  agGrid,
  gridApi,
});

let saveTimeout = null;
let filterModelSaveTimeout = null;

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
      label: 'Открыть лид',
      run: () => {
        emit('row-dblclick', row);
      },
    },
  ];

  if (props.allowCreate) {
    items.push({
      label: 'Создать на основании',
      run: () => {
        emit('create-from', row);
      },
    });
    items.push({
      label: 'Новый лид',
      run: () => {
        emit('create');
      },
    });
  }

  items.push({
    label: 'Удалить лид',
    danger: true,
    run: () => {
      emit('delete-request', row);
    },
  });

  openContextMenu(ev, items);
}

const gridOptions = {
  theme: 'legacy',
  localeText: agGridLocaleRu,
  animateRows: false,
  preventDefaultOnContextMenu: true,
  singleClickEdit: true,
  stopEditingWhenCellsLoseFocus: true,
  getRowId: (params) => String(params.data?.id ?? ''),
  getRowClass: (params) => (params.data?.is_stage_overdue ? 'ag-row-lead-stage-overdue' : ''),
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

const storageKey = computed(() => `leads_grid_state_v1_${props.userId}`);
const filtersStorageKey = computed(() => `leads_grid_filters_v1_${props.userId}`);
const filterModelStorageKey = computed(() => `leads_grid_filter_model_v1_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const defaultColDef = {
  sortable: true,
  filter: true,
  resizable: true,
  floatingFilter: false,
  minWidth: 80,
  suppressSizeToFit: true,
};

function leadSetFilterLabel(field, row) {
  if (!row) {
    return '—';
  }

  if (field === 'status') {
    return statusLabels[row.status] ?? row.status ?? '—';
  }

  if (field === 'source') {
    return sourceLabel(row.source);
  }

  if (field === 'has_offer') {
    return row.has_offer ? 'Да' : 'Нет';
  }

  if (field === 'responsible_name') {
    const name = String(row.responsible_name ?? '').trim();

    return name === '' ? '—' : name;
  }

  const raw = row[field];

  return raw === null || raw === undefined || raw === '' ? '—' : String(raw);
}

function collectLeadSetFilterValues(field) {
  if (field === 'status') {
    return Object.values(statusLabels).sort((left, right) => String(left).localeCompare(String(right), 'ru'));
  }

  if (field === 'source') {
    return Object.values(sourceLabels.value).sort((left, right) => String(left).localeCompare(String(right), 'ru'));
  }

  if (field === 'has_offer') {
    return ['Да', 'Нет'];
  }

  const values = new Set();

  for (const row of props.rows ?? []) {
    values.add(leadSetFilterLabel(field, row));
  }

  return [...values].sort((left, right) => String(left).localeCompare(String(right), 'ru'));
}

const getAllColumns = () => {
  const sourceColumns = props.availableColumns?.length ? props.availableColumns : fallbackColumns;

  return sourceColumns.map((column) => ({
    field: column.field,
    headerName: column.headerName ?? column.label ?? column.field,
    width: column.width ?? 140,
    minWidth: column.minWidth ?? 100,
    type: column.type ?? null,
  }));
};

const getRoleColumnPreset = () => {
  const preset = props.roleColumnsConfig?.leads;

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
      valueFormatter: (params) => formatValue(params.value, column.type, column.field, params.data),
    };

    if (LEADS_SET_FILTER_FIELDS.has(column.field)) {
      applyAgSetListColumn(columnDefinition, {
        values: collectLeadSetFilterValues(column.field),
        filterValueGetter: (params) => leadSetFilterLabel(column.field, params.data),
        floatingFilterRow: true,
      });
    } else if (column.type === 'numeric' && column.field !== 'id') {
      columnDefinition.filter = 'agNumberColumnFilter';
    } else if (column.type === 'date' || column.type === 'datetime') {
      columnDefinition.filter = 'agDateColumnFilter';
    } else if (column.field !== 'id') {
      columnDefinition.filter = 'agTextColumnFilter';
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

    if (column.field === 'number') {
      columnDefinition.pinned = 'left';
      columnDefinition.lockPinned = true;
      columnDefinition.cellClass = 'orders-grid-order-number-cell';
      columnDefinition.headerClass = 'orders-grid-order-number-header';
    } else if (column.field === 'status') {
      columnDefinition.colId = 'status';
      columnDefinition.valueGetter = (params) => statusLabelByValue.value[params.data?.status] ?? params.data?.status ?? '—';
      columnDefinition.valueSetter = (params) => {
        const option = inlineStatusOptions.value.find((item) => item.label === params.newValue);
        if (!option) {
          return false;
        }
        params.data.status = option.value;

        return true;
      };
      columnDefinition.editable = (params) => canEditGridField(params.data, 'status');
      columnDefinition.cellEditor = 'agSelectCellEditor';
      columnDefinition.cellEditorParams = {
        values: inlineStatusLabels.value,
      };
      columnDefinition.cellClass = (params) => (canEditGridField(params.data, 'status') ? 'orders-grid-editable-cell' : '');
    } else if (column.field === 'source') {
      columnDefinition.valueGetter = (params) => sourceLabel(params.data?.source);
      columnDefinition.valueSetter = (params) => {
        const entry = Object.entries(sourceLabelsByValue.value).find(([, label]) => label === params.newValue);
        if (!entry && params.newValue !== '—') {
          return false;
        }
        params.data.source = entry ? entry[0] : null;

        return true;
      };
      columnDefinition.editable = (params) => canEditGridField(params.data, 'source');
      columnDefinition.cellEditor = 'agSelectCellEditor';
      columnDefinition.cellEditorParams = {
        values: sourceLabelsForEditor.value,
      };
      columnDefinition.cellClass = (params) => (canEditGridField(params.data, 'source') ? 'orders-grid-editable-cell' : '');
    } else if (column.field === 'responsible_name') {
      columnDefinition.colId = 'responsible_name';
      columnDefinition.valueGetter = (params) => {
        const name = String(params.data?.responsible_name ?? '').trim();

        return name === '' ? '—' : name;
      };
      columnDefinition.valueSetter = (params) => {
        const userId = userIdByName.value.get(params.newValue);
        if (!userId) {
          return false;
        }
        params.data.responsible_id = userId;
        params.data.responsible_name = params.newValue;

        return true;
      };
      columnDefinition.editable = (params) => canEditResponsibleCell(params.data);
      columnDefinition.cellEditor = 'agSelectCellEditor';
      columnDefinition.cellEditorParams = {
        values: responsibleNamesForEditor.value,
      };
      columnDefinition.cellClass = (params) => (canEditResponsibleCell(params.data) ? 'orders-grid-editable-cell' : '');
    } else if (column.field === 'title') {
      columnDefinition.flex = 1;
    } else if (column.field === 'is_stage_overdue') {
      columnDefinition.cellClass = (params) => (params.data?.is_stage_overdue ? 'leads-grid-cell-stage-overdue' : '');
    } else if (column.field === 'stage_due_at') {
      columnDefinition.cellClass = (params) => (params.data?.is_stage_overdue ? 'leads-grid-cell-stage-overdue' : '');
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
  localStorage.removeItem(filtersStorageKey.value);
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

const loadFilters = () => {
  try {
    const savedFilters = localStorage.getItem(filtersStorageKey.value);

    if (!savedFilters) {
      return;
    }

    const parsedFilters = JSON.parse(savedFilters);
    quickSearch.value = typeof parsedFilters.quickSearch === 'string' ? parsedFilters.quickSearch : '';
  } catch (error) {
    console.error('Error loading leads grid filters', error);
  }
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
      console.error('Error saving leads grid filter model', error);
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
    console.error('Error loading leads grid filter model', error);
  }
};

const onFilterChanged = () => {
  persistFilterModel();
};

const onGridReady = async (params) => {
  gridApi.value = params.api;

  if (quickSearch.value.trim() !== '') {
    gridApi.value.setGridOption('quickFilterText', quickSearch.value);
  }

  if (!readPersistedAgGridColumnState(storageKey.value)?.length) {
    resetToRoleDefaults();
  }

  loadPersistedFilterModel();

  await nextTick();
  refreshAgGridPanelLayout();
};

const onFirstDataRendered = () => {
  requestAnimationFrame(() => {
    autoSizeIdColumnIfNotPersisted(gridApi.value, storageKey.value);
    refreshAgGridPanelLayout();
  });
};

const onCellDoubleClicked = (event) => {
  const field = event.colDef?.field ?? event.colDef?.colId;
  const inlineFields = new Set(['status', 'source', 'responsible_name']);

  if (inlineFields.has(field) && canEditGridField(event.data, field === 'responsible_name' ? 'responsible_id' : field)) {
    return;
  }

  if (event.data?.id) {
    emit('row-dblclick', event.data);
  }
};

function onSelectionChanged() {
  if (!gridApi.value) {
    selectedRows.value = [];

    return;
  }

  selectedRows.value = gridApi.value.getSelectedRows() ?? [];
}

async function saveGridField(row, field, value) {
  if (!row?.id || !field) {
    return;
  }

  try {
    const response = await axios.patch(route('leads.grid-field.update', row.id), {
      field,
      value,
    });

    const updatedLead = response.data?.lead;
    if (updatedLead) {
      Object.assign(row, updatedLead);
      gridApi.value?.refreshCells({ rowNodes: [gridApi.value.getRowNode(String(row.id))].filter(Boolean), force: true });
    }

    emit('grid-updated');
  } catch (error) {
    const message = error?.response?.data?.message ?? 'Не удалось сохранить изменение.';
    window.alert(message);
    router.reload({ only: ['leads'], preserveScroll: true });
  }
}

function onCellValueChanged(params) {
  if (params.newValue === params.oldValue) {
    return;
  }

  const colId = params.colDef.colId ?? params.colDef.field;

  if (colId === 'responsible_name') {
    saveGridField(params.data, 'responsible_id', params.data.responsible_id);

    return;
  }

  if (colId === 'status') {
    saveGridField(params.data, 'status', params.data.status);

    return;
  }

  if (colId === 'source' || params.colDef.field === 'source') {
    saveGridField(params.data, 'source', params.data.source);
  }
}

function onBulkAction(actionKey) {
  if (selectedLeadIds.value.length === 0) {
    return;
  }

  bulkError.value = '';

  if (actionKey === 'source') {
    bulkSourceValue.value = '';
  } else if (actionKey === 'responsible_id') {
    bulkResponsibleId.value = '';
  } else if (actionKey === 'status') {
    bulkStatusValue.value = '';
  }

  activeBulkModal.value = actionKey;
}

function closeBulkModal() {
  if (bulkProcessing.value) {
    return;
  }

  activeBulkModal.value = null;
  bulkError.value = '';
}

function bulkValueForAction(action) {
  if (action === 'source') {
    return bulkSourceValue.value === '' ? null : bulkSourceValue.value;
  }

  if (action === 'responsible_id') {
    return Number(bulkResponsibleId.value);
  }

  if (action === 'status') {
    return bulkStatusValue.value;
  }

  return null;
}

async function submitBulkModal() {
  if (!activeBulkModal.value || selectedLeadIds.value.length === 0) {
    return;
  }

  bulkProcessing.value = true;
  bulkError.value = '';

  try {
    const payload = {
      lead_ids: selectedLeadIds.value,
      action: activeBulkModal.value,
    };

    if (activeBulkModal.value !== 'delete') {
      payload.value = bulkValueForAction(activeBulkModal.value);
    }

    await axios.post(route('leads.mass-update'), payload);

    gridApi.value?.deselectAll();
    selectedRows.value = [];
    activeBulkModal.value = null;

    router.reload({
      only: ['leads', 'leadAttentionQueue', 'salesCoachingInsights'],
      preserveScroll: true,
    });
  } catch (error) {
    bulkError.value = error?.response?.data?.message ?? 'Не удалось выполнить массовое действие.';
  } finally {
    bulkProcessing.value = false;
  }
}

watch(quickSearch, (value) => {
  if (gridApi.value) {
    gridApi.value.setGridOption('quickFilterText', value);
  }

  localStorage.setItem(filtersStorageKey.value, JSON.stringify({
    quickSearch: value,
  }));
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
  loadFilters();
  loadDensity();
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

function formatDate(value) {
  if (!value) {
    return '—';
  }

  const parsedDate = new Date(value);

  if (Number.isNaN(parsedDate.getTime())) {
    return value;
  }

  return parsedDate.toLocaleDateString('ru-RU');
}

function formatDateTime(value) {
  if (!value) {
    return '—';
  }

  const parsedDate = new Date(value);

  if (Number.isNaN(parsedDate.getTime())) {
    return value;
  }

  return parsedDate.toLocaleString('ru-RU');
}

function formatMoney(value, currency = 'RUB') {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value));
}

function formatValue(value, type, field, row) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (field === 'status') {
    return statusLabels[value] ?? value;
  }

  if (field === 'source') {
    return sourceLabel(value);
  }

  if (field === 'planned_shipping_date') {
    return formatDate(value);
  }

  if (field === 'target_price') {
    return formatMoney(value, row?.target_currency ?? 'RUB');
  }

  if (field === 'stage_due_at' || field === 'created_at' || type === 'datetime') {
    return formatDateTime(value);
  }

  if (field === 'is_stage_overdue') {
    return value ? 'Да' : 'Нет';
  }

  if (type === 'boolean' || field === 'has_offer') {
    return value ? 'Да' : 'Нет';
  }

  return value;
}
</script>
