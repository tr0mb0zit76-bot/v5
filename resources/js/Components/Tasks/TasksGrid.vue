<template>
  <div ref="gridSection" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
    <div class="flex shrink-0 flex-wrap items-center gap-2">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
          <input
            v-model="quickSearch"
            type="text"
            placeholder="Поиск по задачам"
            :class="crmGridSearchField"
          />
        </div>

        <div class="relative">
          <button
            type="button"
            :class="`${crmGridToolbarBtn} px-2`"
            :title="`Плотность таблицы: ${currentDensityLabel}`"
            @click="showDensityMenu = !showDensityMenu"
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

        <GridBulkIconActions
          :selected-count="selectedTaskIds.length"
          :actions="bulkActions"
          @action="onBulkAction"
        />
    </div>

    <GridRowStatus
      :get-grid-api="() => gridApi"
      :total-count="rows.length"
      :selected-count="selectedTaskIds.length"
      :quick-search="quickSearch"
    />

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
          @cell-context-menu="onCellContextMenu"
          @cell-value-changed="onCellValueChanged"
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
              Выбрано задач: {{ selectedTaskIds.length }}
            </div>
          </div>

          <div class="space-y-3 p-5">
            <select
              v-if="activeBulkModal === 'assign'"
              v-model="bulkResponsibleId"
              class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >
              <option value="">Выберите ответственного</option>
              <option v-for="user in users" :key="user.id" :value="String(user.id)">
                {{ user.name }}
              </option>
            </select>

            <select
              v-else-if="activeBulkModal === 'status'"
              v-model="bulkStatusValue"
              class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >
              <option value="">Выберите статус</option>
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <input
              v-else-if="activeBulkModal === 'reschedule'"
              v-model="bulkDueAt"
              type="datetime-local"
              class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >

            <p v-else-if="activeBulkModal === 'close'" class="text-sm text-zinc-600 dark:text-zinc-300">
              Закрыть выбранные задачи (статус «Готово»)?
            </p>

            <p v-else-if="activeBulkModal === 'delete'" class="text-sm text-zinc-600 dark:text-zinc-300">
              Удалить выбранные задачи? Это действие необратимо.
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
              :class="activeBulkModal === 'delete' || activeBulkModal === 'close' ? crmBtnDangerMuted : crmBtnCreate"
              :disabled="bulkProcessing || selectedTaskIds.length === 0 || !canSubmitBulkModal"
              @click="submitBulkModal"
            >
              {{ bulkProcessing ? 'Сохранение…' : bulkModalSubmitLabel }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { CalendarClock, CheckCircle2, Rows3, Search, Trash2, UserRound, Workflow } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import {
  CRM_AG_GRID_DENSITY_CHANGED,
  readPersistedAgGridDensity,
  schedulePersistAgGridDensityToProfile,
  writeLocalAgGridDensity,
} from '@/support/agGridUserDensity.js';
import GridBulkIconActions from '@/Components/Grid/GridBulkIconActions.vue';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridRowStatus from '@/Components/Grid/GridRowStatus.vue';
import { AgSetListFilter, setListFilterParams } from '@/Components/Grid/agSetListFilter.js';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import {
  crmBtnCreate,
  crmBtnDangerMuted,
  crmBtnNeutral,
  crmGridDropdown,
  crmGridInnerPanel,
  crmGridSearchField,
  crmGridToolbarBtn,
  crmModalPanel,
} from '@/support/crmUi.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import {
  agGridFilteredMultiRowSelection,
  pruneAgGridSelectionToDisplayed,
} from '@/support/agGridRowSelection.js';
import { confirmLargeBulkGridAction } from '@/support/gridBulkConfirm.js';
import {
  agGridFilterModelStorageKey,
  createAgGridFilterModelPersister,
  loadAgGridFilterModel,
} from '@/support/agGridFilterModelPersistence.js';

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
  users: {
    type: Array,
    default: () => [],
  },
  canBulkMutateTasks: {
    type: Boolean,
    default: false,
  },
  canDeleteTasks: {
    type: Boolean,
    default: false,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();

const emit = defineEmits([
  'row-dblclick',
  'selection-changed',
  'quick-status',
  'quick-reschedule-due',
  'assign-request',
  'delete-task',
  'cell-save',
]);

const agGrid = ref(null);
const gridApi = ref(null);
const quickSearch = ref('');
const showDensityMenu = ref(false);
const currentDensity = ref(defaultGridDensity);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const persistFilterModel = createAgGridFilterModelPersister();
const filterModelStorageKey = computed(() => agGridFilterModelStorageKey('tasks', props.userId));
const quickSearchStorageKey = computed(() => `tasks_grid_quick_search_v1_${props.userId}`);

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
  gridPanel,
  bottomScrollbar,
  agGrid,
  gridApi,
});

const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const priorityLabels = {
  low: 'Низкий',
  medium: 'Средний',
  high: 'Высокий',
  critical: 'Критичный',
};

const priorityCodes = Object.keys(priorityLabels);

const statusLabelByValue = (value) => {
  const option = props.statusOptions.find((item) => item.value === value);

  return option?.label ?? value ?? '—';
};

const responsibleFilterValues = computed(() => {
  const names = new Set();

  for (const row of props.rows ?? []) {
    const name = String(row?.responsible_name ?? '').trim();
    names.add(name === '' ? '—' : name);
  }

  return [...names].sort((a, b) => a.localeCompare(b, 'ru'));
});

const creatorFilterValues = computed(() => {
  const names = new Set();

  for (const row of props.rows ?? []) {
    const name = String(row?.creator_name ?? '').trim();
    names.add(name === '' ? '—' : name);
  }

  return [...names].sort((a, b) => a.localeCompare(b, 'ru'));
});

const statusFilterValues = computed(() =>
  props.statusOptions.map((option) => option.label),
);

const priorityFilterValues = computed(() => Object.values(priorityLabels));

const userIdByName = computed(() => {
  const map = new Map();

  for (const user of props.users ?? []) {
    map.set(user.name, Number(user.id));
  }

  return map;
});

function canEditResponsible(row) {
  if (!row) {
    return false;
  }

  return Boolean(row.can_mutate) || props.canBulkMutateTasks;
}

const selectedTaskIds = ref([]);
const activeBulkModal = ref(null);
const bulkResponsibleId = ref('');
const bulkStatusValue = ref('');
const bulkDueAt = ref('');
const bulkError = ref('');
const bulkProcessing = ref(false);

const bulkActions = computed(() => {
  const actions = [];

  if (props.canBulkMutateTasks) {
    actions.push({
      key: 'assign',
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
      key: 'reschedule',
      icon: CalendarClock,
      title: 'Перенести срок',
      label: 'Перенести срок',
    },
    {
      key: 'close',
      icon: CheckCircle2,
      title: 'Закрыть выбранные',
      label: 'Закрыть',
    },
  );

  if (props.canDeleteTasks) {
    actions.push({
      key: 'delete',
      icon: Trash2,
      title: 'Удалить выбранные',
      label: 'Удалить',
      danger: true,
    });
  }

  return actions;
});

const bulkModalTitle = computed(() => {
  if (activeBulkModal.value === 'assign') {
    return 'Сменить ответственного';
  }
  if (activeBulkModal.value === 'status') {
    return 'Сменить статус';
  }
  if (activeBulkModal.value === 'reschedule') {
    return 'Перенести срок';
  }
  if (activeBulkModal.value === 'close') {
    return 'Закрыть задачи';
  }
  if (activeBulkModal.value === 'delete') {
    return 'Удалить задачи';
  }

  return '';
});

const bulkModalSubmitLabel = computed(() => {
  if (activeBulkModal.value === 'delete') {
    return 'Удалить';
  }
  if (activeBulkModal.value === 'close') {
    return 'Закрыть';
  }

  return 'Применить';
});

const canSubmitBulkModal = computed(() => {
  if (activeBulkModal.value === 'assign') {
    return bulkResponsibleId.value !== '';
  }
  if (activeBulkModal.value === 'status') {
    return bulkStatusValue.value !== '';
  }
  if (activeBulkModal.value === 'reschedule') {
    return bulkDueAt.value !== '';
  }
  if (activeBulkModal.value === 'close' || activeBulkModal.value === 'delete') {
    return true;
  }

  return false;
});

function onBulkAction(key) {
  if (selectedTaskIds.value.length === 0) {
    return;
  }

  bulkError.value = '';
  bulkResponsibleId.value = '';
  bulkStatusValue.value = '';
  bulkDueAt.value = '';
  activeBulkModal.value = key;
}

function closeBulkModal() {
  if (bulkProcessing.value) {
    return;
  }

  activeBulkModal.value = null;
  bulkError.value = '';
}

function clearGridSelection() {
  gridApi.value?.deselectAll();
  selectedTaskIds.value = [];
  emit('selection-changed', []);
}

function submitBulkModal() {
  if (!activeBulkModal.value || selectedTaskIds.value.length === 0 || !canSubmitBulkModal.value) {
    return;
  }

  const action = activeBulkModal.value;
  const count = selectedTaskIds.value.length;

  if (action === 'assign'
    && !confirmLargeBulkGridAction(count, 'сменить ответственного у задач')) {
    return;
  }

  if (action === 'close'
    && !confirmLargeBulkGridAction(count, 'закрыть задачи')) {
    return;
  }

  if (action === 'delete'
    && !confirmLargeBulkGridAction(count, 'удалить задачи')) {
    return;
  }

  const payload = {
    task_ids: selectedTaskIds.value,
    action: action === 'close' ? 'close' : action,
  };

  if (action === 'assign') {
    payload.responsible_id = Number(bulkResponsibleId.value);
  } else if (action === 'status') {
    payload.status = bulkStatusValue.value;
  } else if (action === 'reschedule') {
    payload.due_at = bulkDueAt.value;
  }

  bulkProcessing.value = true;
  bulkError.value = '';

  router.post(route('tasks.bulk'), payload, {
    preserveScroll: true,
    only: ['tasks', 'quickFilters', 'selectedTask'],
    onSuccess: () => {
      activeBulkModal.value = null;
      clearGridSelection();
    },
    onError: (errors) => {
      const first = Object.values(errors ?? {})[0];
      bulkError.value = Array.isArray(first) ? first[0] : (first || 'Не удалось выполнить массовое действие.');
    },
    onFinish: () => {
      bulkProcessing.value = false;
    },
  });
}

const {
  contextMenu,
  closeContextMenu: closeRowContextMenu,
  openContextMenu,
} = useGridContextMenu();

const INLINE_EDITABLE_COL_IDS = new Set(['status', 'priority', 'responsible']);

const gridOptions = {
  theme: 'legacy',
  localeText: agGridLocaleRu,
  singleClickEdit: true,
  stopEditingWhenCellsLoseFocus: true,
  getRowClass: (params) => {
    const row = params.data;
    if (!row || row.status === 'done') {
      return '';
    }
    if (row.is_due_overdue || row.is_overdue) {
      return 'ag-row-task-due-overdue';
    }

    return '';
  },
  getRowId: (params) => String(params.data?.id ?? ''),
  rowSelection: agGridFilteredMultiRowSelection({
    enableClickSelection: true,
  }),
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
    selectedTaskIds.value = ids;
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
      d.creator_name,
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

const columnDefs = computed(() => [
  { field: 'number', headerName: 'Номер', width: 110, minWidth: 90, filter: 'agTextColumnFilter' },
  { field: 'title', headerName: 'Название', flex: 1, minWidth: 180, filter: 'agTextColumnFilter' },
  {
    colId: 'status',
    headerName: 'Статус',
    width: 140,
    minWidth: 120,
    filter: AgSetListFilter,
    filterParams: setListFilterParams(statusFilterValues.value),
    filterValueGetter: (params) => statusLabelByValue(params.data?.status),
    valueGetter: (params) => statusLabelByValue(params.data?.status),
    valueSetter: (params) => {
      const option = props.statusOptions.find((item) => item.label === params.newValue);
      if (!option) {
        return false;
      }
      params.data.status = option.value;
      params.data.status_label = option.label;

      return true;
    },
    editable: (params) => Boolean(params.data?.can_mutate),
    cellEditor: 'agSelectCellEditor',
    cellEditorParams: {
      values: props.statusOptions.map((option) => option.label),
    },
    cellClass: (params) => (params.data?.can_mutate ? 'tasks-grid-editable-cell' : ''),
  },
  {
    field: 'priority',
    headerName: 'Приоритет',
    width: 130,
    minWidth: 110,
    filter: AgSetListFilter,
    filterParams: setListFilterParams(priorityFilterValues.value),
    filterValueGetter: (params) => priorityLabels[params.data?.priority] ?? '—',
    valueFormatter: (params) => priorityLabels[params.value] ?? params.value ?? '—',
    editable: (params) => Boolean(params.data?.can_mutate),
    cellEditor: 'agSelectCellEditor',
    cellEditorParams: { values: priorityCodes },
    cellClass: (params) => (params.data?.can_mutate ? 'tasks-grid-editable-cell' : ''),
  },
  {
    colId: 'responsible',
    headerName: 'Ответственный',
    width: 170,
    minWidth: 140,
    filter: AgSetListFilter,
    filterParams: setListFilterParams(responsibleFilterValues.value),
    filterValueGetter: (params) => {
      const name = String(params.data?.responsible_name ?? '').trim();

      return name === '' ? '—' : name;
    },
    valueGetter: (params) => {
      const name = String(params.data?.responsible_name ?? '').trim();

      return name === '' ? '—' : name;
    },
    valueSetter: (params) => {
      const userId = userIdByName.value.get(params.newValue);
      if (!userId) {
        return false;
      }
      params.data.responsible_id = userId;
      params.data.responsible_name = params.newValue;

      return true;
    },
    editable: (params) => canEditResponsible(params.data),
    cellEditor: 'agSelectCellEditor',
    cellEditorParams: {
      values: (props.users ?? []).map((user) => user.name),
    },
    cellClass: (params) => (canEditResponsible(params.data) ? 'tasks-grid-editable-cell' : ''),
  },
  {
    colId: 'creator',
    headerName: 'Создатель',
    width: 160,
    minWidth: 130,
    filter: AgSetListFilter,
    filterParams: setListFilterParams(creatorFilterValues.value),
    filterValueGetter: (params) => {
      const name = String(params.data?.creator_name ?? '').trim();

      return name === '' ? '—' : name;
    },
    valueGetter: (params) => {
      const name = String(params.data?.creator_name ?? '').trim();

      return name === '' ? '—' : name;
    },
  },
  {
    field: 'due_at',
    headerName: 'Срок',
    width: 150,
    minWidth: 130,
    filter: 'agDateColumnFilter',
    valueFormatter: (p) => formatDue(p.value),
    cellClass: (params) => (params.data?.is_due_overdue ? 'tasks-grid-cell-due-overdue' : ''),
  },
  {
    field: 'lead_number',
    headerName: 'Лид',
    width: 120,
    minWidth: 100,
    filter: 'agTextColumnFilter',
    valueFormatter: (p) => p.data?.lead_number || '—',
    cellRenderer: leadLinkCellRenderer,
  },
  {
    headerName: 'Чеклист',
    width: 110,
    minWidth: 100,
    sortable: false,
    filter: false,
    valueGetter: (p) => {
      const items = p.data?.checklist_items ?? [];
      const done = items.filter((i) => i.is_done).length;

      return `${done}/${items.length}`;
    },
  },
]);

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

function openTaskLead(row) {
  const url = row?.lead_show_url;
  if (url) {
    router.visit(url);
    return;
  }

  if (!row?.lead_id) {
    return;
  }

  router.get(route('leads.show', row.lead_id), { standalone: 1 });
}

function leadLinkCellRenderer(params) {
  const wrap = document.createElement('div');
  wrap.className = 'flex h-full items-center';

  const leadId = params.data?.lead_id;
  const label = params.data?.lead_number || (leadId ? `#${leadId}` : '');

  if (!leadId || !label) {
    wrap.textContent = '—';
    return wrap;
  }

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className =
    'text-left font-medium text-sky-700 underline decoration-sky-300 underline-offset-2 hover:decoration-sky-700 dark:text-sky-300 dark:decoration-sky-700 dark:hover:decoration-sky-300';
  btn.textContent = label;
  btn.title = params.data?.lead_title ? `Открыть лид: ${params.data.lead_title}` : 'Открыть лид';
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    openTaskLead(params.data);
  });
  wrap.appendChild(btn);

  return wrap;
}

function onGridReady(params) {
  gridApi.value = params.api;

  try {
    const savedQuickSearch = localStorage.getItem(quickSearchStorageKey.value);
    if (typeof savedQuickSearch === 'string') {
      quickSearch.value = savedQuickSearch;
    }
  } catch {
    /* ignore */
  }

  loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);

  nextTick(() => {
    refreshAgGridPanelLayout();
  });
}

function onGridFilterChanged() {
  persistFilterModel(gridApi.value, filterModelStorageKey.value);
  pruneAgGridSelectionToDisplayed(gridApi.value);
  const ids = (gridApi.value?.getSelectedRows() ?? [])
    .map((r) => r?.id)
    .filter((id) => id !== undefined && id !== null);
  selectedTaskIds.value = ids;
  emit('selection-changed', ids);
}

function reapplyPersistedFilters() {
  nextTick(() => {
    loadAgGridFilterModel(gridApi.value, filterModelStorageKey.value);
    refreshAgGridPanelLayout();
  });
}

function isInlineEditableColumn(event) {
  const colId = event?.colDef?.colId ?? event?.colDef?.field;
  if (!INLINE_EDITABLE_COL_IDS.has(colId) || !event?.data) {
    return false;
  }

  const editable = event.colDef?.editable;

  if (typeof editable === 'function') {
    return Boolean(editable(event));
  }

  return Boolean(editable);
}

function onCellDoubleClicked(event) {
  if (isInlineEditableColumn(event)) {
    const colKey = event.column?.getColId?.() ?? event.colDef?.colId ?? event.colDef?.field;
    if (colKey != null && event.rowIndex != null) {
      event.api?.startEditingCell({
        rowIndex: event.rowIndex,
        colKey,
      });
    }

    return;
  }

  const id = event?.data?.id;
  if (id) {
    emit('row-dblclick', event.data);
  }
}

function onCellValueChanged(params) {
  if (params.newValue === params.oldValue) {
    return;
  }

  const field = params.colDef.field ?? params.colDef.colId;
  let value = params.newValue;

  if (params.colDef.colId === 'responsible') {
    emit('cell-save', {
      row: params.data,
      field: 'responsible_id',
      value: params.data.responsible_id,
    });

    return;
  }

  if (params.colDef.colId === 'status') {
    emit('cell-save', {
      row: params.data,
      field: 'status',
      value: params.data.status,
    });

    return;
  }

  emit('cell-save', {
    row: params.data,
    field,
    value,
  });
}

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
      run: () => emit('row-dblclick', row),
    },
  ];

  if (row.lead_id) {
    items.push({
      label: 'Открыть лид',
      run: () => openTaskLead(row),
    });
  }

  if (row.can_mutate && row.status !== 'done') {
    if (row.status !== 'in_progress') {
      items.push({
        label: 'В работу',
        run: () => emit('quick-status', { row, status: 'in_progress' }),
      });
    }
    if (row.status !== 'review') {
      items.push({
        label: 'На проверку',
        run: () => emit('quick-status', { row, status: 'review' }),
      });
    }
    items.push({
      label: 'Перенести срок…',
      run: () => emit('quick-reschedule-due', row),
    });
    items.push({
      label: 'Завершить',
      run: () => emit('quick-status', { row, status: 'done' }),
    });
  }

  if (canEditResponsible(row)) {
    items.push({
      label: 'Назначить ответственного…',
      run: () => emit('assign-request', row),
    });
  }

  if (props.canDeleteTasks) {
    items.push({
      label: 'Удалить',
      run: () => emit('delete-task', row),
    });
  }

  openContextMenu(ev, items);
}

function applyDensity(key) {
  currentDensity.value = resolveGridDensity(key).key;
  showDensityMenu.value = false;
  try {
    writeLocalAgGridDensity(props.userId, currentDensity.value);
    schedulePersistAgGridDensityToProfile(currentDensity.value);
  } catch {
    /* ignore */
  }
  nextTick(() => {
    gridApi.value?.resetRowHeights();
    gridApi.value?.refreshCells({ force: true });
    refreshAgGridPanelLayout();
  });
}

function readDensityFromStorage() {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    currentDensity.value = readPersistedAgGridDensity(props.userId, page.props.auth?.user);
  } catch {
    /* ignore */
  }
}

function onFirstDataRendered() {
  nextTick(() => {
    refreshAgGridPanelLayout();
  });
}

watch(quickSearch, (value) => {
  try {
    localStorage.setItem(quickSearchStorageKey.value, value ?? '');
  } catch {
    /* ignore */
  }

  gridApi.value?.onFilterChanged();
  pruneAgGridSelectionToDisplayed(gridApi.value);
  const ids = (gridApi.value?.getSelectedRows() ?? [])
    .map((r) => r?.id)
    .filter((id) => id !== undefined && id !== null);
  selectedTaskIds.value = ids;
  emit('selection-changed', ids);
});

watch(
  () => props.rows,
  () => {
    reapplyPersistedFilters();
    nextTick(() => {
      pruneAgGridSelectionToDisplayed(gridApi.value);
      const ids = (gridApi.value?.getSelectedRows() ?? [])
        .map((r) => r?.id)
        .filter((id) => id !== undefined && id !== null);
      selectedTaskIds.value = ids;
      emit('selection-changed', ids);
    });
  },
  { deep: true },
);

watch(
  () => props.userId,
  () => {
    reapplyPersistedFilters();
  },
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
  readDensityFromStorage();
  window.addEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});

onUnmounted(() => {
  window.removeEventListener(CRM_AG_GRID_DENSITY_CHANGED, onExternalAgGridDensityChange);
});
</script>

<style scoped>
.toolbar-button {
  @apply inline-flex items-center justify-center gap-2 rounded-none border border-zinc-200 bg-white px-2.5 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800;
}

/* Подсветка строк/ячеек — в grid-theme.css (scoped .dark не видит class на html) */

:deep(.tasks-grid-editable-cell) {
  cursor: pointer;
}
</style>
