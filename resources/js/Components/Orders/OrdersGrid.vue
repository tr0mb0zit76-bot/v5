<template>
  <div ref="gridSection" class="space-y-2">
    <div class="flex items-center justify-between gap-2">
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
          class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
          @click="openColumnModal"
        >
          <Settings2 class="h-4 w-4" />
          Колонки
        </button>

        <div class="relative">
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-xl border border-zinc-200 bg-white p-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
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
          class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-2.5 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
          @click="resetToRoleDefaults"
        >
          <RotateCcw class="h-4 w-4" />
          Сбросить
        </button>
      </div>

      <div class="text-xs text-zinc-500 dark:text-zinc-400">
        Перетаскивай элементы в модалке, чтобы менять порядок колонок
      </div>
    </div>

    <div class="overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
      <div class="ag-theme-alpine orders-grid-theme" :class="densityClass" :style="gridContainerStyle">
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
          :ensureDomOrder="true"
          :suppressScrollOnNewData="false"
          :suppressHorizontalScroll="false"
          :domLayout="'normal'"
          :pagination="false"
          :alwaysShowVerticalScroll="true"
          :maintainColumnOrder="true"
          :suppressDragLeaveHidesColumns="true"
          @cell-double-clicked="onCellDoubleClicked"
          @cell-value-changed="onCellValueChanged"
          @grid-ready="onGridReady"
          @first-data-rendered="onFirstDataRendered"
          @column-visible="saveColumnState"
          @column-resized="saveColumnState"
          @column-moved="saveColumnState"
          @column-pinned="saveColumnState"
          @sort-changed="saveColumnState"
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
import { RotateCcw, Rows3, Search, Settings2, X } from 'lucide-vue-next';

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
});

const emit = defineEmits(['cell-save', 'row-dblclick', 'row-delete', 'columns-changed']);

const fallbackColumns = [
  { field: 'order_number', label: '№ заказа', width: 110, minWidth: 95, type: null },
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
  { field: 'kpi_percent', label: 'KPI %', width: 100, minWidth: 80, type: 'numeric' },
  { field: 'salary_paid', label: 'ЗП выпл.', width: 120, minWidth: 100, type: 'numeric' },
  { field: 'status_text', label: 'Статус', width: 130, minWidth: 110, type: null },
  { field: 'invoice_number', label: 'Счёт', width: 130, minWidth: 100, type: null },
  { field: 'upd_number', label: 'УПД', width: 120, minWidth: 90, type: null },
  { field: 'waybill_number', label: 'ТТН', width: 120, minWidth: 90, type: null },
];

const baseVisibleFields = [
  'order_number',
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
  'status_text',
  'invoice_number',
  'upd_number',
  'waybill_number',
];

const agGrid = ref(null);
const gridApi = ref(null);
const showColumnModal = ref(false);
const showDensityMenu = ref(false);
const modalColumns = ref([]);
const draggedColumnField = ref(null);
const quickSearch = ref('');
const currentDensity = ref(defaultGridDensity);
const gridSection = ref(null);
const bottomScrollbar = ref(null);
const bottomScrollbarWidth = ref(0);
const gridViewportHeight = ref(440);

let isSyncingHorizontalScroll = false;
let saveTimeout = null;
let removeCenterViewportListener = null;

const gridOptions = {
  theme: 'legacy',
};

const gridContainerStyle = computed(() => ({
  height: `${gridViewportHeight.value}px`,
  minHeight: `${gridViewportHeight.value}px`,
  width: '100%',
}));

const storageKey = computed(() => `orders_grid_state_v4_${props.userId}`);
const densityStorageKey = computed(() => `orders_grid_density_${props.userId}`);
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);

const displayData = computed(() => props.rows?.length ? props.rows : props.data ?? []);

const defaultColDef = {
  sortable: true,
  filter: true,
  resizable: true,
  editable: false,
  floatingFilter: true,
  minWidth: 50,
  suppressSizeToFit: true,
  singleClickEdit: true,
};

const getAllColumns = () => {
  const sourceColumns = props.availableColumns?.length ? props.availableColumns : fallbackColumns;

  return sourceColumns.map((column) => ({
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

const getDefaultEditableFields = () => roleDefaults[getRoleKey()]?.editable ?? [];

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
    syncBottomScrollbar();
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
    console.error('Error loading orders grid state', error);
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
    .replace(/\bFTTN\b/gi, 'ФТТН')
    .replace(/\bOTTN\b/gi, 'ОТТН')
    .replace(/\bLOADING\b/gi, 'погрузка')
    .replace(/\bUNLOADING\b/gi, 'выгрузка')
    .replace(/\bdays\b/gi, 'дн');
};

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Подписи из мастера / старые текстовые значения в БД → код статуса для иконки.
 * Ключи в нижнем регистре (см. normalizeRuStatusKey).
 */
const ORDER_STATUS_RU_LABEL_TO_CODE = {
  'новый заказ': 'new',
  'выполняется': 'in_progress',
  документы: 'documents',
  оплата: 'payment',
  закрыта: 'closed',
  отменена: 'cancelled',
  черновик: 'draft',
  'черновик (legacy)': 'draft',
  'на согласовании (legacy)': 'pending',
  'подтвержден (legacy)': 'confirmed',
  'завершен (legacy)': 'completed',
  'на согласовании': 'pending',
  подтвержден: 'confirmed',
  завершен: 'completed',
  завершён: 'completed',
  'завершён (legacy)': 'completed',
};

function normalizeRuStatusKey(raw) {
  if (raw === null || raw === undefined || raw === '') {
    return null;
  }

  const k = String(raw).trim().toLowerCase();

  return ORDER_STATUS_RU_LABEL_TO_CODE[k] ?? null;
}

/** Иконки Lucide (stroke), цвет через currentColor на родителе */
const ORDER_STATUS_ICON_META = {
  new: {
    label: 'Новый заказ',
    colorClass: 'text-sky-600 dark:text-sky-400',
    paths: [
      'm12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z',
      'M5 3v4',
      'M3 5h4',
      'M19 17v4',
      'M17 19h4',
    ],
  },
  in_progress: {
    label: 'Выполняется',
    colorClass: 'text-amber-600 dark:text-amber-400',
    paths: [
      'M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2',
      'M15 18H9',
      'M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14',
    ],
    circles: [{ cx: '17', cy: '18', r: '2' }, { cx: '7', cy: '18', r: '2' }],
  },
  documents: {
    label: 'Документы',
    colorClass: 'text-violet-600 dark:text-violet-400',
    paths: [
      'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z',
      'M14 2v6h6',
      'M16 13H8',
      'M16 17H8',
      'M10 9H8',
    ],
  },
  payment: {
    label: 'Оплата',
    colorClass: 'text-emerald-600 dark:text-emerald-400',
    paths: [
      'M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1',
      'M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4',
    ],
  },
  closed: {
    label: 'Закрыта',
    colorClass: 'text-green-700 dark:text-green-400',
    paths: [
      'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z',
      'm9 12 2 2 4-4',
    ],
  },
  cancelled: {
    label: 'Отменена',
    colorClass: 'text-rose-600 dark:text-rose-400',
    paths: ['M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z', 'm15 9-6 6', 'm9 9 6 6'],
  },
  draft: {
    label: 'Черновик',
    colorClass: 'text-zinc-500 dark:text-zinc-400',
    paths: ['M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z', 'm15 5 4 4'],
  },
  pending: {
    label: 'На согласовании (legacy)',
    colorClass: 'text-orange-600 dark:text-orange-400',
    paths: [
      'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z',
      'M12 6v6l4 2',
    ],
  },
  confirmed: {
    label: 'Подтвержден (legacy)',
    colorClass: 'text-blue-600 dark:text-blue-400',
    paths: [
      'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z',
      'm9 12 2 2 4-4',
    ],
  },
  completed: {
    label: 'Завершен (legacy)',
    colorClass: 'text-green-600 dark:text-green-400',
    /** Простая зелёная галочка (завершение / согласовано) */
    paths: ['M20 6 L9 17 l-5 -5'],
  },
};

function buildOrderStatusIconSvg(meta) {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('width', '20');
  svg.setAttribute('height', '20');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '2');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('class', 'shrink-0');
  meta.paths.forEach((d) => {
    const p = document.createElementNS(SVG_NS, 'path');
    p.setAttribute('d', d);
    svg.appendChild(p);
  });
  (meta.circles ?? []).forEach((c) => {
    const circle = document.createElementNS(SVG_NS, 'circle');
    circle.setAttribute('cx', c.cx);
    circle.setAttribute('cy', c.cy);
    circle.setAttribute('r', c.r);
    svg.appendChild(circle);
  });

  return svg;
}

function pickStatusString(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  return String(value).trim();
}

/**
 * Код для иконки: учитывает status_text (COALESCE(manual, status)), а также сырые поля строки.
 * Если manual_status — не код (например русская подпись), для иконки берём orders.status.
 */
function resolveOrderStatusIconKey(row, statusTextCell) {
  const text = pickStatusString(statusTextCell);
  const manual = row ? pickStatusString(row.manual_status) : null;
  const machine = row ? pickStatusString(row.status) : null;

  const hasMeta = (k) => Boolean(k && ORDER_STATUS_ICON_META[k]);

  if (hasMeta(manual)) {
    return manual;
  }

  if (manual && !hasMeta(manual) && hasMeta(machine)) {
    return machine;
  }

  if (hasMeta(machine)) {
    return machine;
  }

  if (hasMeta(text)) {
    return text;
  }

  const fromRu =
    normalizeRuStatusKey(text) ?? normalizeRuStatusKey(manual) ?? normalizeRuStatusKey(machine);

  if (fromRu && hasMeta(fromRu)) {
    return fromRu;
  }

  return text ?? manual ?? machine ?? '';
}

function resolveOrderStatusLabel(cellValue, row) {
  if (cellValue === null || cellValue === undefined || cellValue === '') {
    return '—';
  }

  const key = resolveOrderStatusIconKey(row ?? null, cellValue);
  const meta = key && ORDER_STATUS_ICON_META[key];

  if (meta) {
    return meta.label;
  }

  return String(cellValue);
}

function renderOrderStatusTextCell(params) {
  const displayText = pickStatusString(params.value);
  const wrap = document.createElement('div');
  wrap.className = 'flex h-full w-full items-center justify-center';
  wrap.setAttribute('role', 'presentation');

  if (!displayText) {
    wrap.textContent = '—';
    wrap.classList.add('text-zinc-400');
    wrap.title = '';

    return wrap;
  }

  const iconKey = resolveOrderStatusIconKey(params.data ?? null, params.value);
  const meta = iconKey && ORDER_STATUS_ICON_META[iconKey];

  if (!meta) {
    wrap.textContent = displayText;
    wrap.title = displayText;
    wrap.classList.add('max-w-full', 'truncate', 'px-1', 'text-xs', 'text-zinc-600', 'dark:text-zinc-300');
    wrap.setAttribute('role', 'img');
    wrap.setAttribute('aria-label', displayText);

    return wrap;
  }

  const manual = pickStatusString(params.data?.manual_status);
  const machine = pickStatusString(params.data?.status);
  const titleParts = [meta.label];
  if (manual && manual !== iconKey && manual !== machine) {
    titleParts.push(manual);
  }
  const title = titleParts.filter(Boolean).join(' · ');

  wrap.title = title;
  wrap.setAttribute('role', 'img');
  wrap.setAttribute('aria-label', title);
  const span = document.createElement('span');
  span.className = `inline-flex ${meta.colorClass}`;
  span.appendChild(buildOrderStatusIconSvg(meta));
  wrap.appendChild(span);

  return wrap;
}

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
  const editableFields = getDefaultEditableFields();

  const columns = getAllowedColumns().map((column) => {
    const isEditable = editableFields.includes(column.field) && props.editable;
    const columnDefinition = {
      field: column.field,
      headerName: column.headerName,
      width: column.width,
      minWidth: column.minWidth || 50,
      sortable: true,
      filter: true,
      resizable: true,
      suppressSizeToFit: true,
      editable: isEditable,
      cellClass: (params) => {
        const classes = [];

        if (isEditable) {
          classes.push('orders-grid-editable-cell');
        }

        if (column.field === 'order_number') {
          classes.push('orders-grid-order-number-cell');
        }

        if (column.field === 'status_text') {
          classes.push('orders-grid-status-cell');
        }

        if (column.field === 'loading_date') {
          const k = params.data?.loading_date_route_kind;
          if (k === 'planned') {
            classes.push('orders-grid-route-date-planned');
          }
          if (k === 'actual') {
            classes.push('orders-grid-route-date-actual');
          }
        }

        if (column.field === 'unloading_date') {
          const k = params.data?.unloading_date_route_kind;
          if (k === 'planned') {
            classes.push('orders-grid-route-date-planned');
          }
          if (k === 'actual') {
            classes.push('orders-grid-route-date-actual');
          }
        }

        return classes;
      },
    };

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

    if (column.type === 'numeric') {
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
      if (columnDefinition.editable) {
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
        values: ['vat', 'no_vat', 'cash'],
      };
      columnDefinition.valueFormatter = (params) => ({
        vat: 'С НДС',
        no_vat: 'Без НДС',
        cash: 'Нал',
      }[params.value] ?? formatEmpty(params.value));
    }

    if (['customer_payment_term', 'carrier_payment_term'].includes(column.field)) {
      columnDefinition.valueFormatter = (params) => formatPaymentTermsDisplay(params.value);
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
        closed: 'Закрыта',
        cancelled: 'Отменена',
        draft: 'Черновик (legacy)',
        pending: 'На согласовании (legacy)',
        confirmed: 'Подтвержден (legacy)',
        completed: 'Завершен (legacy)',
      }[params.value] ?? formatEmpty(params.value));
    }

    return columnDefinition;
  });

  if (['admin', 'supervisor', 'manager'].includes(getRoleKey())) {
    columns.push({
      colId: '__actions',
      headerName: 'Действия',
      width: 110,
      minWidth: 96,
      maxWidth: 120,
      pinned: 'right',
      sortable: false,
      filter: false,
      resizable: false,
      editable: false,
      suppressSizeToFit: true,
      cellRenderer: (params) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex h-full items-center justify-center';

        if (!params.data?.can_delete) {
          return wrapper;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40';
        button.title = 'Удалить заказ';
        button.innerHTML = '<span aria-hidden="true">×</span>';
        button.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          emit('row-delete', params.data);
        });

        wrapper.appendChild(button);

        return wrapper;
      },
    });
  }

  return columns;
});

const onCellDoubleClicked = (params) => {
  if (params.data) {
    emit('row-dblclick', params.data);
  }
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

const getCenterViewport = () => agGrid.value?.$el?.querySelector('.ag-viewport.ag-center-cols-viewport') ?? null;

const updateGridViewportHeight = () => {
  const sectionElement = gridSection.value;

  if (!sectionElement) {
    return;
  }

  const sectionTop = sectionElement.getBoundingClientRect().top;
  const bottomScrollbarHeight = bottomScrollbar.value?.offsetHeight ?? 16;
  const commandBarFooter = document.querySelector('footer');
  const footerTop = commandBarFooter?.getBoundingClientRect().top ?? window.innerHeight;
  const footerReserve = 60;

  gridViewportHeight.value = Math.max(
    440,
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

const openColumnModal = () => {
  showDensityMenu.value = false;
  syncModalColumnsWithGrid();
  showColumnModal.value = true;
};

const closeColumnModal = () => {
  showColumnModal.value = false;
  draggedColumnField.value = null;
  syncModalColumnsWithGrid();
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
    return;
  }

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
};

const exportData = () => {
  if (gridApi.value) {
    gridApi.value.exportDataAsCsv({
      fileName: `orders_export_${new Date().toISOString().slice(0, 10)}.csv`,
      allColumns: true,
    });
  }
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

const onGridReady = async (params) => {
  gridApi.value = params.api;

  if (quickSearch.value.trim() !== '') {
    gridApi.value.setGridOption('quickFilterText', quickSearch.value);
  }

  if (!loadColumnState()) {
    resetToRoleDefaults();
  }

  await nextTick();
  updateGridViewportHeight();
  attachCenterViewportListener();
  syncBottomScrollbar();
};

const onFirstDataRendered = () => {
  requestAnimationFrame(() => {
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
  });
};

watch(
  displayData,
  async () => {
    await nextTick();
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
  },
  { deep: true },
);

watch(quickSearch, (value) => {
  if (!gridApi.value) {
    return;
  }

  gridApi.value.setGridOption('quickFilterText', value);
});

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

  if (saveTimeout) {
    clearTimeout(saveTimeout);
  }
});

defineExpose({
  exportData,
  refreshGrid,
  resetToRoleDefaults,
});
</script>
