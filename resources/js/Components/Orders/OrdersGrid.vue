<template>
  <div ref="gridSection" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2">
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
            :class="`${crmGridToolbarBtn} justify-center p-2`"
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

        <GridViewsBar
          grid-key="orders"
          :user-id="userId"
          :get-grid-api="() => gridApi"
          :column-storage-key="storageKey"
          :filter-storage-key="filterModelStorageKey"
          :quick-search-storage-key="quickSearchStorageKey"
          :quick-search="quickSearch"
          :on-reset-defaults="resetGridViewState"
          @update:quick-search="quickSearch = $event"
          @applied="onGridViewApplied"
          @pinned-changed="onGridViewsPinnedChanged"
        />
      </div>

      <span v-if="copyNotice" class="ml-auto text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ copyNotice }}</span>
    </div>

    <div
      ref="gridPanel"
      :class="crmGridInnerPanel"
      @contextmenu.capture="suppressNativeContextMenuCapture"
      @contextmenu="onGridPanelEmptyContextMenu"
    >
      <div
        class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden"
        :style="gridContainerStyle"
        :class="densityClass"
      >
        <AgGridVue
          ref="agGrid"
          :gridOptions="gridOptions"
          :rowData="displayData"
          :columnDefs="dynamicColumnDefs"
          :defaultColDef="defaultColDef"
          :loading="loading"
          style="height: 100%; width: 100%;"
          :stopEditingWhenCellsLoseFocus="true"
          :enableCellTextSelection="true"
          :suppressScrollOnNewData="false"
          :suppressHorizontalScroll="false"
          :domLayout="'normal'"
          :pagination="false"
          :alwaysShowVerticalScroll="true"
          :maintainColumnOrder="true"
          :suppressDragLeaveHidesColumns="true"
          @cell-double-clicked="onCellDoubleClicked"
          @cell-clicked="onCellClicked"
          @cell-value-changed="onCellValueChanged"
          @grid-ready="onGridReady"
          @first-data-rendered="onFirstDataRendered"
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

    <GridExportDialog
      :show="showExportModal"
      :columns="exportColumns"
      :responsible-options="exportResponsibleOptions"
      responsible-label="Менеджеры"
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
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Rows3, Search, Settings2, Download, X } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { defaultGridDensity, gridDensityOptions, resolveGridDensity } from '@/Components/Grid/grid-density';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import { applySavedToColDef, buildLayoutIndex, readPersistedAgGridColumnState } from '@/support/agGridColumnLayout.js';
import { applyAgGridIdColumnSizing, autoSizeIdColumnIfNotPersisted } from '@/support/agGridIdColumn.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridExportDialog from '@/Components/Grid/GridExportDialog.vue';
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
import { renderOrderOneCSummaryCell } from '@/support/orderOneCSummaryCell.js';
import { copyTextToClipboard } from '@/support/copyTextToClipboard.js';
import { renderOrderStatusTextCell, resolveOrderStatusLabel } from '@/support/orderStatusDisplay.js';
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
  data: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  editable: {
    type: Boolean,
    default: true,
  },
  canUseLoadBoard: {
    type: Boolean,
    default: false,
  },
  roleKey: {
    type: String,
    default: 'manager',
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
  paymentFormSelectOptions: {
    type: Array,
    default: () => [],
  },
  inlineEditableFields: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();

const emit = defineEmits(['cell-save', 'row-dblclick', 'row-delete', 'columns-changed', 'create-request', 'create-from-request', 'open-order-documents', 'publish-load-board']);

const paymentFormEditorValues = computed(() => {
    if (Array.isArray(props.paymentFormSelectOptions) && props.paymentFormSelectOptions.length > 0) {
        return props.paymentFormSelectOptions.map((o) => o.value);
    }

    return ['vat_22', 'vat_5', 'vat_0', 'no_vat', 'cash'];
});

const paymentFormValueLabels = computed(() => {
    const map = {
        vat: 'С НДС',
        no_vat: 'Без НДС',
        cash: 'Нал',
    };
    if (Array.isArray(props.paymentFormSelectOptions)) {
        for (const o of props.paymentFormSelectOptions) {
            if (o && o.value !== undefined && o.value !== null) {
                map[o.value] = o.label ?? o.value;
            }
        }
    }

    return map;
});

const fallbackColumns = [
  { field: 'id', label: 'ID', width: 56, minWidth: 48, type: 'numeric' },
  { field: 'order_number', label: '№ заказа', width: 110, minWidth: 95, type: null },
  { field: 'status_text', label: 'Статус', width: 130, minWidth: 110, type: null },
  { field: 'company_code', label: 'Компания', width: 110, minWidth: 80, type: null },
  { field: 'manager_name', label: 'Менеджер', width: 150, minWidth: 140, type: null },
  { field: 'order_date', label: 'Дата заявки', width: 130, minWidth: 110, type: 'date' },
  { field: 'loading_point', label: 'Погрузка', width: 190, minWidth: 140, type: null },
  { field: 'unloading_point', label: 'Выгрузка', width: 190, minWidth: 140, type: null },
  { field: 'loading_date', label: 'Дата погрузки', width: 140, minWidth: 120, type: 'date' },
  { field: 'unloading_date', label: 'Дата выгрузки', width: 140, minWidth: 120, type: 'date' },
  { field: 'cargo_description', label: 'Груз', width: 220, minWidth: 160, type: null },
  { field: 'customer_name', label: 'Заказчик', width: 180, minWidth: 140, type: null },
  { field: 'carrier_name', label: 'Перевозчик', width: 180, minWidth: 140, type: null },
  { field: 'customer_rate', label: 'Ставка клиента', width: 150, minWidth: 120, type: 'numeric' },
  { field: 'carrier_rate', label: 'Ставка перевозчика', width: 170, minWidth: 130, type: 'numeric' },
  { field: 'additional_expenses', label: 'Доп. расходы', width: 150, minWidth: 120, type: 'numeric' },
  { field: 'insurance', label: 'Страховка', width: 130, minWidth: 110, type: 'numeric' },
  { field: 'bonus', label: 'Бонус', width: 120, minWidth: 100, type: 'numeric' },
  { field: 'delta', label: 'Маржа', width: 120, minWidth: 100, type: 'numeric' },
  { field: 'kpi_percent', label: 'Вычет %', width: 100, minWidth: 80, type: 'numeric' },
  { field: 'salary_paid', label: 'ЗП выпл.', width: 120, minWidth: 100, type: 'numeric' },
  { field: 'invoice_number', label: 'Счёт', width: 130, minWidth: 100, type: null },
  { field: 'upd_number', label: 'УПД', width: 120, minWidth: 90, type: null },
  { field: 'waybill_number', label: 'ТТН', width: 120, minWidth: 90, type: null },
];

const baseVisibleFields = [
  'id',
  'order_number',
  'status_text',
  'company_code',
  'manager_name',
  'order_date',
  'loading_point',
  'unloading_point',
  'loading_date',
  'unloading_date',
  'cargo_description',
  'customer_name',
  'carrier_name',
  'customer_rate',
  'carrier_rate',
  'additional_expenses',
  'insurance',
  'bonus',
  'delta',
  'kpi_percent',
  'salary_paid',
  'invoice_number',
  'upd_number',
  'waybill_number',
  'track_number_customer',
  'track_number_carrier',
];

/** В реестре заказов не показываем колонки AI / ATI / metadata и служебную колонку действий. */
const ORDERS_GRID_EXCLUDED_FIELDS = new Set([
  'ai_draft_id',
  'ai_confidence',
  'ai_metadata',
  'ati_response',
  'ati_load_id',
  'ati_published_at',
  'metadata',
  'actions',
  '__actions',
  'order_actions',
]);

/** Плавающая строка фильтров только у «поисковых» полей; у ставок и дальше — выкл. */
/** Только выпадающий список значений (без плавающей строки фильтра). */
const ORDERS_SET_FILTER_FIELDS = new Set([
  'company_code',
  'manager_name',
  'status_text',
]);

const FLOATING_FILTER_FIELDS = new Set([
  'order_number',
  'order_date',
  'loading_point',
  'unloading_point',
  'loading_date',
  'unloading_date',
  'cargo_description',
  'customer_name',
  'carrier_name',
]);

const INLINE_TEXT_EDITABLE_FIELDS = new Set([
  'invoice_number',
  'upd_number',
  'waybill_number',
  'track_number_customer',
  'track_number_carrier',
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

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
  gridPanel,
  bottomScrollbar,
  agGrid,
  gridApi,
});

let saveTimeout = null;
let filterModelSaveTimeout = null;

const TERMINAL_ORDER_STATUSES = ['closed', 'cancelled', 'disruption'];

const GRID_FINANCIAL_FIELDS = new Set([
  'customer_rate',
  'carrier_rate',
  'additional_expenses',
  'insurance',
  'bonus',
  'customer_payment_form',
  'carrier_payment_form',
]);

function orderGridTodayIso() {
  return new Date().toISOString().slice(0, 10);
}

function orderGridDateDay(value) {
  return value == null || value === '' ? '' : String(value).slice(0, 10);
}

function isActiveOrderForDateAlert(data) {
  if (!data) {
    return false;
  }

  const status = data.manual_status || data.status || '';

  return !TERMINAL_ORDER_STATUSES.includes(status);
}

/** Плановая дата в прошлом, фактической по маршруту ещё нет (kind ≠ actual). */
function isOverduePlannedRouteDateCell(data, dateField) {
  if (!isActiveOrderForDateAlert(data)) {
    return false;
  }

  const kindField = dateField === 'loading_date' ? 'loading_date_route_kind' : 'unloading_date_route_kind';
  const kind = data[kindField] ?? 'none';

  if (kind === 'actual' || kind === 'none') {
    return false;
  }

  const day = orderGridDateDay(data[dateField]);
  if (!day) {
    return false;
  }

  return day < orderGridTodayIso();
}

function ordersGridStaleRowClass(data) {
  if (!data) {
    return '';
  }

  const status = data.manual_status || data.status || '';
  if (TERMINAL_ORDER_STATUSES.includes(status)) {
    return '';
  }

  const today = orderGridTodayIso();

  if (status === 'new') {
    const ld = orderGridDateDay(data.loading_date);
    if (ld && ld < today) {
      return 'ag-row-orders-stale';
    }

    return '';
  }

  if (status === 'in_progress') {
    const ud = orderGridDateDay(data.unloading_date);
    if (!ud || ud >= today) {
      return '';
    }
    const uk = data.unloading_date_route_kind ?? 'none';
    if (uk === 'actual') {
      return '';
    }

    return 'ag-row-orders-stale';
  }

  return '';
}

const {
  contextMenu,
  closeContextMenu: closeRowContextMenu,
  openContextMenu,
  openEmptyContextMenu,
} = useGridContextMenu();

const copyNotice = ref('');

let copyNoticeTimeout = null;

async function copyTransportSummary(row) {
  const summary = String(row?.clipboard_summary ?? row?.one_c_summary ?? '').trim();

  if (summary === '') {
    copyNotice.value = 'Нет данных для сводки';

    if (copyNoticeTimeout) {
      clearTimeout(copyNoticeTimeout);
    }

    copyNoticeTimeout = setTimeout(() => {
      copyNotice.value = '';
      copyNoticeTimeout = null;
    }, 2500);

    return;
  }

  const copied = await copyTextToClipboard(summary);
  copyNotice.value = copied ? 'Сводка скопирована в буфер обмена' : 'Не удалось скопировать сводку';

  if (copyNoticeTimeout) {
    clearTimeout(copyNoticeTimeout);
  }

  copyNoticeTimeout = setTimeout(() => {
    copyNotice.value = '';
    copyNoticeTimeout = null;
  }, 2500);
}

/** ПКМ по пустому месту таблицы (не по ячейке) — только быстрые действия без строки. */
function onGridPanelEmptyContextMenu(event) {
  openEmptyContextMenu(event, [
    {
      label: 'Новый заказ',
      run: () => {
        emit('create-request');
      },
    },
  ]);
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
      label: 'Открыть заказ',
      run: () => {
        emit('row-dblclick', row);
      },
    },
    {
      label: 'Документы и печатные формы…',
      run: () => {
        emit('open-order-documents', row);
      },
    },
    {
      label: 'Сводка по перевозке',
      disabled: !String(row?.clipboard_summary ?? row?.one_c_summary ?? '').trim(),
      run: () => {
        void copyTransportSummary(row);
      },
    },
    {
      label: 'Создать на основании',
      run: () => {
        emit('create-from-request', row);
      },
    },
    ...(props.canUseLoadBoard ? [{
      label: 'Выставить на биржу грузов',
      run: () => {
        emit('publish-load-board', row);
      },
    }] : []),
    {
      label: 'Новый заказ',
      run: () => {
        emit('create-request');
      },
    },
  ];

  if (row.can_delete) {
    items.push({
      label: 'Удалить заказ',
      danger: true,
      run: () => {
        emit('row-delete', row);
      },
    });
  }

  openContextMenu(ev, items);
}

const gridOptions = {
  theme: 'legacy',
  localeText: agGridLocaleRu,
  animateRows: false,
  /** Системное меню ПКМ: AG Grid сам вызывает preventDefault в теле таблицы (см. onBodyViewportContextMenu). */
  preventDefaultOnContextMenu: true,
  /** Стабильные id строк — быстрее обновление rowData и меньше лишних перерисовок. */
  getRowId: (params) => String(params.data?.id ?? ''),
  getRowClass: (params) => ordersGridStaleRowClass(params.data),
  onCellContextMenu,
};

const storageKey = computed(() => `orders_grid_state_v5_${props.userId}`);
const quickSearchStorageKey = computed(() => `orders_grid_quick_search_v1_${props.userId}`);
const filterModelStorageKey = computed(() => `orders_grid_filter_model_v1_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const displayData = computed(() => props.rows?.length ? props.rows : props.data ?? []);
const canExportGrid = computed(() => page.props.can_export_grid === true);

function orderSetFilterLabel(field, row) {
  if (!row) {
    return '—';
  }

  if (field === 'status_text') {
    return resolveOrderStatusLabel(row.status_text, row);
  }

  if (field === 'manager_name') {
    const name = String(row.manager_name ?? '').trim();

    return name === '' ? '—' : name;
  }

  if (field === 'company_code') {
    const code = String(row.company_code ?? '').trim();

    return code === '' ? '—' : code;
  }

  return '—';
}

function collectOrderSetFilterValues(field) {
  const values = new Set();

  for (const row of displayData.value ?? []) {
    values.add(orderSetFilterLabel(field, row));
  }

  return [...values].sort((left, right) => String(left).localeCompare(String(right), 'ru'));
}

const defaultColDef = {
  sortable: true,
  filter: true,
  resizable: true,
  editable: false,
  floatingFilter: false,
  minWidth: 50,
  suppressSizeToFit: true,
  singleClickEdit: true,
};

const getAllColumns = () => {
  const sourceColumns = props.availableColumns?.length ? props.availableColumns : fallbackColumns;

  return sourceColumns
    .filter((column) => !ORDERS_GRID_EXCLUDED_FIELDS.has(column.field))
    .map((column) => ({
      field: column.field,
      headerName: column.headerName ?? column.label ?? column.field,
      width: column.width ?? 140,
      minWidth: column.minWidth ?? 80,
      type: column.type ?? null,
    }));
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

const roleDefaults = {
  admin: {
    visible: baseVisibleFields,
    editable: [
      'customer_rate',
      'carrier_rate',
      'additional_expenses',
      'insurance',
      'bonus',
      'invoice_number',
      'upd_number',
      'waybill_number',
      'track_number_customer',
      'track_sent_date_customer',
      'track_received_date_customer',
      'track_number_carrier',
      'track_sent_date_carrier',
      'track_received_date_carrier',
      'customer_payment_form',
      'carrier_payment_form',
      'manual_status',
    ],
  },
  supervisor: {
    visible: baseVisibleFields,
    editable: [
      'customer_rate',
      'carrier_rate',
      'additional_expenses',
      'insurance',
      'bonus',
      'invoice_number',
      'upd_number',
      'waybill_number',
      'track_number_customer',
      'track_sent_date_customer',
      'track_received_date_customer',
      'track_number_carrier',
      'track_sent_date_carrier',
      'track_received_date_carrier',
      'customer_payment_form',
      'carrier_payment_form',
      'manual_status',
    ],
  },
  manager: {
    visible: baseVisibleFields.filter((field) => field !== 'salary_paid'),
    editable: [
      'customer_rate',
      'carrier_rate',
      'additional_expenses',
      'insurance',
      'bonus',
      'invoice_number',
      'upd_number',
      'waybill_number',
      'track_number_customer',
      'track_sent_date_customer',
      'track_received_date_customer',
      'track_number_carrier',
      'track_sent_date_carrier',
      'track_received_date_carrier',
      'customer_payment_form',
      'carrier_payment_form',
    ],
  },
  clerk: {
    visible: baseVisibleFields.filter((field) => field !== 'salary_paid'),
    editable: [
      'invoice_number',
      'upd_number',
      'waybill_number',
      'track_number_customer',
      'track_sent_date_customer',
      'track_received_date_customer',
      'track_number_carrier',
      'track_sent_date_carrier',
      'track_received_date_carrier',
    ],
  },
};

const getRoleKey = () => props.roleKey || 'manager';

const getRoleColumnPreset = () => {
  const preset = props.roleColumnsConfig?.orders;

  if (!Array.isArray(preset) || preset.length === 0) {
    return null;
  }

  const columnsByField = new Map(getAllColumns().map((column) => [column.field, column]));

  return preset
    .filter((column) => columnsByField.has(column?.colId))
    .map((column, index) => ({
      colId: column.colId,
      hide: Boolean(column.hide),
      width: Number(column.width) > 0 ? Number(column.width) : (columnsByField.get(column.colId)?.width ?? 120),
      order: Number.isInteger(column.order) ? column.order : index,
    }))
    .sort((left, right) => left.order - right.order);
};

const getDefaultVisibleFields = () => {
  const roleColumnPreset = getRoleColumnPreset();

  if (roleColumnPreset) {
    return roleColumnPreset.filter((column) => !column.hide).map((column) => column.colId);
  }

  return roleDefaults[getRoleKey()]?.visible ?? baseVisibleFields;
};

const getDefaultEditableFields = () => {
  if (Array.isArray(props.inlineEditableFields) && props.inlineEditableFields.length > 0) {
    return props.inlineEditableFields;
  }

  return roleDefaults[getRoleKey()]?.editable ?? [];
};

function isGridCellEditable(columnField, row) {
  if (!getDefaultEditableFields().includes(columnField) || !props.editable) {
    return false;
  }

  if (GRID_FINANCIAL_FIELDS.has(columnField) && row?.can_edit_financial_fields === false) {
    return false;
  }

  return true;
}

const buildRoleDefaultState = () => {
  const roleColumnPreset = getRoleColumnPreset();

  if (roleColumnPreset) {
    return roleColumnPreset.map((column) => ({
      colId: column.colId,
      hide: false,
      width: column.width,
    })).filter((column) => getAllowedColumns().some((allowedColumn) => allowedColumn.field === column.colId));
  }

  const visibleFields = getDefaultVisibleFields();

  return getAllowedColumns().map((column) => ({
    colId: column.field,
    hide: !visibleFields.includes(column.field),
    width: column.width,
  }));
};

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
  localStorage.setItem(quickSearchStorageKey.value, '');
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

const loadQuickSearch = () => {
  const savedQuickSearch = localStorage.getItem(quickSearchStorageKey.value);
  quickSearch.value = typeof savedQuickSearch === 'string' ? savedQuickSearch : '';
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

const formatEmpty = (value) => {
  return value === null || value === undefined || value === '' || value === 'null' ? '—' : value;
};

/** Короткие условия оплаты в БД хранятся латиницей (FTTN/OTTN); в таблице показываем кириллицей. */
const formatPaymentTermsDisplay = (value) => {
  const base = formatEmpty(value);

  if (base === '—') {
    return base;
  }

  return String(base)
    .replace(/\bFTTN_RECEIPT\b/gi, 'ФТТН + квиток')
    .replace(/\bFTTN\b/gi, 'ФТТН')
    .replace(/\bOTTN\b/gi, 'ОТТН')
    .replace(/\bLOADING\b/gi, 'погрузка')
    .replace(/\bUNLOADING\b/gi, 'выгрузка')
    .replace(/\bdays\b/gi, 'дн');
};

const moneyFormatter = (params) => {
  const value = formatEmpty(params.value);

  if (value === '—') {
    return value;
  }

  return new Intl.NumberFormat('ru-RU').format(Number(value));
};

const dateFormatter = (params) => {
  const value = formatEmpty(params.value);

  if (value === '—') {
    return value;
  }

  const parsedDate = new Date(value);

  if (Number.isNaN(parsedDate.getTime())) {
    return value;
  }

  return parsedDate.toLocaleDateString('ru-RU');
};

const dateTimeFormatter = (params) => {
  const value = formatEmpty(params.value);

  if (value === '—') {
    return value;
  }

  const parsedDate = new Date(value);

  if (Number.isNaN(parsedDate.getTime())) {
    return value;
  }

  return parsedDate.toLocaleString('ru-RU');
};

class DateInputEditor {
  init(params) {
    this.input = document.createElement('input');
    this.input.type = 'date';
    this.input.className = 'orders-grid-date-editor';
    this.input.value = params.value ?? '';
  }

  getGui() {
    return this.input;
  }

  afterGuiAttached() {
    this.input.focus();
    this.input.showPicker?.();
  }

  getValue() {
    return this.input.value || null;
  }

  destroy() {}

  isPopup() {
    return false;
  }
}

const dynamicColumnDefs = computed(() => {
  void gridViewsRevision.value;
  const editableFields = getDefaultEditableFields();
  const saved = readPersistedAgGridColumnState(storageKey.value);

  const columns = getAllowedColumns().map((column) => {
    const roleAllowsEdit = editableFields.includes(column.field) && props.editable;
    const columnDefinition = {
      field: column.field,
      headerName: column.headerName,
      width: column.width,
      minWidth: column.minWidth || 50,
      sortable: true,
      filter: true,
      resizable: true,
      suppressSizeToFit: true,
      floatingFilter: FLOATING_FILTER_FIELDS.has(column.field),
      editable: (params) => isGridCellEditable(column.field, params.data),
      cellClass: (params) => {
        const classes = [];
        const isEditable = isGridCellEditable(column.field, params.data);

        if (isEditable) {
          classes.push('orders-grid-editable-cell');
        }

        if (column.field === 'order_number') {
          classes.push('orders-grid-order-number-cell');
        }

        if (column.field === 'status_text') {
          classes.push('orders-grid-status-cell');
        }

        if (column.field === 'loading_date' || column.field === 'unloading_date') {
          const kindKey = column.field === 'loading_date' ? 'loading_date_route_kind' : 'unloading_date_route_kind';
          const k = params.data?.[kindKey];
          if (k === 'planned') {
            classes.push('orders-grid-route-date-planned');
          }
          if (k === 'actual') {
            classes.push('orders-grid-route-date-actual');
          }
          if (isOverduePlannedRouteDateCell(params.data, column.field)) {
            classes.push('orders-grid-route-date-overdue-planned');
          }
        }

        return classes;
      },
    };

    if (column.field === 'id') {
      columnDefinition.pinned = 'left';
      columnDefinition.width = 56;
      columnDefinition.minWidth = 48;
      columnDefinition.maxWidth = 72;
      columnDefinition.filter = false;
      columnDefinition.floatingFilter = false;
      columnDefinition.suppressHeaderFilterButton = true;
      columnDefinition.getQuickFilterText = () => '';
      columnDefinition.valueFormatter = (params) => formatEmpty(params.value);

      return applyAgGridIdColumnSizing(columnDefinition);
    }

    if (column.field === 'order_number') {
      columnDefinition.pinned = 'left';
      columnDefinition.lockPinned = true;
      columnDefinition.headerClass = 'orders-grid-order-number-header';
    }

    if (column.field === 'carrier_name') {
      columnDefinition.tooltipValueGetter = (params) => {
        const tip = params.data?.carrier_name_tooltip;

        return tip && String(tip).trim() !== '' ? String(tip) : null;
      };
    }

    if (column.type === 'numeric' && column.field !== 'id') {
      columnDefinition.valueFormatter = moneyFormatter;
      columnDefinition.valueParser = (params) => {
        if (params.newValue === null || params.newValue === undefined || params.newValue === '') {
          return null;
        }

        const parsedValue = parseFloat(params.newValue);
        return Number.isNaN(parsedValue) ? params.oldValue : parsedValue;
      };
      columnDefinition.filter = 'agNumberColumnFilter';
    } else if (column.type === 'date') {
      columnDefinition.valueFormatter = dateFormatter;
      if (roleAllowsEdit) {
        columnDefinition.cellEditor = DateInputEditor;
      }
    } else if (column.type === 'datetime') {
      columnDefinition.valueFormatter = dateTimeFormatter;
    } else if (column.type === 'boolean') {
      columnDefinition.valueFormatter = (params) => params.value ? 'Да' : 'Нет';
    } else if (column.type === 'json') {
      columnDefinition.valueFormatter = (params) => {
        const value = formatEmpty(params.value);

        if (value === '—') {
          return value;
        }

        return typeof value === 'string' ? value : JSON.stringify(value);
      };
    } else {
      columnDefinition.valueFormatter = (params) => formatEmpty(params.value);
    }

    if (['customer_payment_form', 'carrier_payment_form'].includes(column.field)) {
      columnDefinition.cellEditor = 'agSelectCellEditor';
      columnDefinition.cellEditorParams = {
        values: paymentFormEditorValues.value,
      };
      columnDefinition.valueFormatter = (params) => paymentFormValueLabels.value[params.value] ?? formatEmpty(params.value);
    }

    if (INLINE_TEXT_EDITABLE_FIELDS.has(column.field) && roleAllowsEdit) {
      columnDefinition.cellEditor = 'agTextCellEditor';
      columnDefinition.singleClickEdit = true;
    }

    if (['customer_payment_term', 'carrier_payment_term'].includes(column.field)) {
      columnDefinition.valueFormatter = (params) => formatPaymentTermsDisplay(params.value);
    }

    if (column.field === 'one_c_summary') {
      columnDefinition.cellRenderer = renderOrderOneCSummaryCell;
      columnDefinition.wrapText = true;
      columnDefinition.autoHeight = true;
      columnDefinition.sortable = false;
      columnDefinition.filter = 'agTextColumnFilter';
      columnDefinition.valueFormatter = (params) => formatEmpty(params.value);
      columnDefinition.getQuickFilterText = (params) => (params.value == null ? '' : String(params.value));
      columnDefinition.cellClass = 'orders-grid-summary-cell';
    }

    if (column.field === 'status_text') {
      columnDefinition.width = 72;
      columnDefinition.minWidth = 56;
      columnDefinition.maxWidth = 96;
      columnDefinition.cellRenderer = renderOrderStatusTextCell;
      columnDefinition.valueFormatter = (params) => resolveOrderStatusLabel(params.value, params.data);
      columnDefinition.getQuickFilterText = (params) => resolveOrderStatusLabel(params.value, params.data);
    }

    if (column.field === 'manual_status') {
      columnDefinition.cellEditor = 'agSelectCellEditor';
      columnDefinition.cellEditorParams = {
        values: [
          'new',
          'in_progress',
          'documents',
          'payment',
          'closed',
          'cancelled',
          'disruption',
          'draft',
          'pending',
          'confirmed',
          'completed',
        ],
      };
      columnDefinition.valueFormatter = (params) => ({
        new: 'Новый заказ',
        in_progress: 'Выполняется',
        documents: 'Документы',
        payment: 'Оплата',
        closed: 'Завершено',
        cancelled: 'Отменена',
        disruption: 'Срыв',
        draft: 'Черновик (legacy)',
        pending: 'На согласовании (legacy)',
        confirmed: 'Подтвержден (legacy)',
        completed: 'Завершен (legacy)',
      }[params.value] ?? formatEmpty(params.value));
    }

    if (ORDERS_SET_FILTER_FIELDS.has(column.field)) {
      applyAgSetListColumn(columnDefinition, {
        values: collectOrderSetFilterValues(column.field),
        filterValueGetter: (params) => orderSetFilterLabel(column.field, params.data),
        floatingFilterRow: true,
      });
    }

    return columnDefinition;
  });

  if (!saved?.length) {
    return columns;
  }

  const dataCols = columns.filter((c) => c.field);
  const fields = dataCols.map((c) => c.field);
  const { orderedFields, byColId } = buildLayoutIndex(fields, saved);
  const byField = new Map(dataCols.map((d) => [d.field, d]));

  return orderedFields
    .map((field) => {
      const def = byField.get(field);
      return def ? applySavedToColDef(def, byColId.get(field)) : null;
    })
    .filter(Boolean);
});

const onCellDoubleClicked = (params) => {
  if (!params.data) {
    return;
  }

  if (params.colDef?.field === 'status_text') {
    emit('open-order-documents', params.data);

    return;
  }

  const field = params.colDef?.field;
  if (field && isGridCellEditable(field, params.data)) {
    params.api.startEditingCell({
      rowIndex: params.node.rowIndex,
      colKey: field,
    });

    return;
  }

  emit('row-dblclick', params.data);
};

const onCellClicked = (params) => {
  const field = params.colDef?.field;

  if (!field || !params.data || !INLINE_TEXT_EDITABLE_FIELDS.has(field)) {
    return;
  }

  if (!isGridCellEditable(field, params.data)) {
    return;
  }

  if (params.api.getEditingCells().length > 0) {
    return;
  }

  params.api.startEditingCell({
    rowIndex: params.node.rowIndex,
    colKey: field,
  });
};

const onCellValueChanged = (params) => {
  if (params.newValue !== params.oldValue && props.editable) {
    emit('cell-save', {
      row: params.data,
      field: params.colDef.field,
      value: params.newValue,
    });
  }
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

  const gridColumnsByField = new Map(
    gridApi.value
      .getAllGridColumns()
      .map((gridColumn) => [gridColumn.getColId(), gridColumn]),
  );

  modalColumns.value = allColumns.map((column) => {
    const gridColumn = gridColumnsByField.get(column.field);

    return {
      ...column,
      width: gridColumn?.getActualWidth() ?? column.width,
      visible: gridColumn ? gridColumn.isVisible() : true,
    };
  });
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
  const columnsByField = new Map(getAllColumns().map((column) => [column.field, column]));

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

const exportData = () => {
  openExportModal();
};

function openExportModal() {
  if (!gridApi.value) {
    return;
  }

  exportColumns.value = buildExportColumnsFromGrid(gridApi.value, ORDERS_GRID_EXCLUDED_FIELDS);
  exportResponsibleOptions.value = buildResponsibleOptionsFromRows(displayData.value, {
    idField: 'manager_id',
    nameField: 'manager_name',
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
    fileName: defaultGridExportFileName('zakazy'),
    responsibleMode: payload.responsibleMode,
    responsibleIds: payload.responsibleIds,
    responsibleIdField: 'manager_id',
  });
  showExportModal.value = false;
}

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
      console.error('Error saving orders grid filter model', error);
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
    console.error('Error loading orders grid filter model', error);
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

watch(
  displayData,
  async (rows) => {
    await nextTick();
    if (gridApi.value) {
      gridApi.value.setGridOption('rowData', rows ?? []);
      loadPersistedFilterModel();
    }
    refreshAgGridPanelLayout();
  },
  { deep: true },
);

watch(quickSearch, (value) => {
  if (!gridApi.value) {
    localStorage.setItem(quickSearchStorageKey.value, value ?? '');
    return;
  }

  localStorage.setItem(quickSearchStorageKey.value, value ?? '');
  gridApi.value.setGridOption('quickFilterText', value);
});

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
  loadQuickSearch();
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

defineExpose({
  exportData,
  refreshGrid,
  resetToRoleDefaults,
});
</script>
