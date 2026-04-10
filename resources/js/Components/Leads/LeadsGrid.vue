<template>
  <div class="flex h-full min-h-0 flex-col gap-3">
    <div class="space-y-3 border-b border-zinc-200 p-4 dark:border-zinc-800">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold">Лиды</h1>
          <p class="text-sm text-zinc-500 dark:text-zinc-400">Предзаказы до конверсии в заказ.</p>
        </div>

        <button type="button" class="action-button" :disabled="!allowCreate" @click="$emit('create')">
          <Plus class="h-4 w-4" />
          Добавить
        </button>
      </div>

      <div class="grid gap-3" :class="props.canFilterResponsible ? 'md:grid-cols-[minmax(0,1fr),180px,180px]' : 'md:grid-cols-[minmax(0,1fr),180px]'">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
          <input
            v-model="quickSearch"
            type="text"
            class="w-72 rounded-xl border border-zinc-200 bg-white py-1.5 pl-10 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
            placeholder="Поиск по реестру"
          />
        </div>

        <select v-model="statusFilter" class="field">
          <option value="">Все статусы</option>
          <option v-for="option in statusFilterOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>

        <select v-if="props.canFilterResponsible" v-model="responsibleFilter" class="field">
          <option value="">Все ответственные</option>
          <option v-for="option in responsibleFilterOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <div class="min-h-0 flex-1 px-3 pb-3">
      <div class="ag-theme-alpine orders-grid-theme orders-grid-density--compact h-full border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <AgGridVue
          ref="agGrid"
          :gridOptions="gridOptions"
          :rowData="filteredRows"
          :columnDefs="columnDefs"
          :defaultColDef="defaultColDef"
          :domLayout="'normal'"
          :pagination="false"
          :animateRows="true"
          :rowSelection="{ mode: 'singleRow', enableClickSelection: true }"
          :suppressCellFocus="true"
          :suppressMovableColumns="true"
          style="height: 100%; width: 100%;"
          @grid-ready="onGridReady"
          @cell-double-clicked="onCellDoubleClicked"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Plus, Search } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import '@/Components/Grid/grid-theme.css';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
  allowCreate: {
    type: Boolean,
    default: true,
  },
  canFilterResponsible: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['create', 'row-dblclick']);

const gridApi = ref(null);
const quickSearch = ref('');
const statusFilter = ref('');
const responsibleFilter = ref('');

const defaultColDef = {
  sortable: true,
  filter: true,
  resizable: true,
  suppressSizeToFit: true,
  minWidth: 80,
};

const gridOptions = {
  theme: 'legacy',
};

const statusLabels = {
  new: 'Новый',
  qualification: 'Квалификация',
  calculation: 'Просчёт',
  proposal_ready: 'КП готово',
  proposal_sent: 'КП отправлено',
  negotiation: 'Переговоры',
  won: 'Конвертирован',
  lost: 'Закрыт',
  on_hold: 'Отложен',
};

const columnDefs = computed(() => [
  {
    field: 'number',
    headerName: '№',
    width: 120,
    minWidth: 110,
    pinned: 'left',
    lockPinned: true,
    cellClass: 'orders-grid-order-number-cell',
    headerClass: 'orders-grid-order-number-header',
  },
  {
    field: 'status',
    headerName: 'Статус',
    width: 135,
    minWidth: 120,
    valueFormatter: (params) => statusLabels[params.value] ?? params.value ?? '—',
  },
  {
    field: 'title',
    headerName: 'Тема',
    flex: 1,
    minWidth: 180,
  },
  {
    field: 'counterparty_name',
    headerName: 'Контрагент',
    width: 180,
    minWidth: 150,
    valueFormatter: (params) => params.value || '—',
  },
  {
    field: 'responsible_name',
    headerName: 'Ответственный',
    width: 160,
    minWidth: 140,
    valueFormatter: (params) => params.value || '—',
  },
  {
    field: 'planned_shipping_date',
    headerName: 'Отгрузка',
    width: 120,
    minWidth: 110,
    valueFormatter: (params) => formatDate(params.value),
  },
  {
    field: 'target_price',
    headerName: 'Цена',
    width: 130,
    minWidth: 120,
    valueFormatter: (params) => params.value ? formatMoney(params.value, params.data?.target_currency) : '—',
  },
]);

const statusFilterOptions = computed(() => {
  return Object.entries(statusLabels)
    .filter(([value]) => props.rows.some((row) => row.status === value))
    .map(([value, label]) => ({ value, label }));
});

const responsibleFilterOptions = computed(() => {
  return [...new Set(props.rows.map((row) => row.responsible_name).filter(Boolean))]
    .map((name) => ({ value: name, label: name }));
});

const filteredRows = computed(() => {
  return props.rows.filter((row) => {
    const matchesStatus = statusFilter.value === '' || row.status === statusFilter.value;
    const matchesResponsible = responsibleFilter.value === '' || row.responsible_name === responsibleFilter.value;

    return matchesStatus && matchesResponsible;
  });
});

function onGridReady(params) {
  gridApi.value = params.api;

  if (quickSearch.value.trim() !== '') {
    gridApi.value.setGridOption('quickFilterText', quickSearch.value);
  }
}

function onCellDoubleClicked(event) {
  if (event.data?.id) {
    emit('row-dblclick', event.data);
  }
}

watch(quickSearch, (value) => {
  if (gridApi.value) {
    gridApi.value.setGridOption('quickFilterText', value);
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

function formatMoney(value, currency = 'RUB') {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value));
}
</script>

<style scoped>
.field {
  @apply w-full border border-zinc-200 bg-white px-3 py-2 text-sm outline-none transition-colors focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400;
}

.action-button {
  @apply inline-flex items-center gap-2 border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800;
}
</style>
