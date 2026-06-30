<template>
    <div ref="gridSection" class="flex min-h-0 flex-1 flex-col gap-2">
        <div class="shrink-0 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">График оплат</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        План и факт по строкам графика заказов. Источник данных — расписание платежей в заказе.
                    </p>
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 sm:pt-1 sm:text-right">
                    Записей: {{ props.rows.length }}
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="quickSearch"
                        type="text"
                        :class="crmGridSearchField"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Показать</span>
                    <select
                        v-model="currentWorkMode"
                        class="h-9 min-w-[14rem] rounded-xl border border-zinc-200 bg-white px-3 text-sm text-zinc-800 outline-none transition focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                        <option
                            v-for="option in workModeOptions"
                            :key="option.key"
                            :value="option.key"
                        >
                            {{ option.label }} ({{ countRowsForWorkMode(option.key) }})
                        </option>
                    </select>
                </div>

                <div class="relative">
                    <button
                        type="button"
                        :class="`${crmGridToolbarBtn} gap-1.5 px-3 py-2 text-xs`"
                        :disabled="!props.canRecordPayment || selectedPaymentScheduleIds.length === 0"
                        title="Групповые действия с выбранными строками"
                        @click="togglePaymentRunActionsMenu"
                    >
                        Действия
                        <span v-if="selectedPaymentScheduleIds.length > 0" class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ selectedPaymentScheduleIds.length }}
                        </span>
                    </button>
                    <div
                        v-if="showPaymentRunActionsMenu"
                        :class="crmGridDropdown"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-zinc-800"
                            :disabled="selectedPaymentScheduleIds.length === 0"
                            @click="markSelectedForToday"
                        >
                            <span>Поставить на сегодня</span>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-zinc-800"
                            :disabled="selectedPaymentScheduleIds.length === 0"
                            @click="openPaymentRunDateModal"
                        >
                            <span>Поставить на дату…</span>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                            :disabled="selectedPaymentScheduleIds.length === 0"
                            @click="clearSelectedPaymentRun"
                        >
                            <span>Снять план оплаты</span>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    :class="`${crmGridToolbarBtn} gap-1.5 px-3 py-2 text-xs`"
                    title="Печать просроченных и платежей с плановой датой на сегодня"
                    :disabled="operationalExportRows.length === 0"
                    @click="printOperationalPayments"
                >
                    <Printer class="h-4 w-4" />
                    Печать
                </button>
                <button
                    type="button"
                    :class="`${crmGridToolbarBtn} gap-1.5 px-3 py-2 text-xs`"
                    title="Скачать CSV: просрочено и план на сегодня"
                    :disabled="operationalExportRows.length === 0"
                    @click="downloadOperationalPaymentsCsv"
                >
                    <Download class="h-4 w-4" />
                    CSV
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

                <GridViewsBar
                    grid-key="payment_schedule"
                    :user-id="userId"
                    :get-grid-api="() => gridApi"
                    :filter-storage-key="filterModelStorageKey"
                    :quick-search="quickSearch"
                    :on-reset-defaults="resetGridViewState"
                    @update:quick-search="quickSearch = $event"
                    @pinned-changed="onGridViewsPinnedChanged"
                />
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showPaymentRunDateModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closePaymentRunDateModal"
            >
                <div class="w-full max-w-sm rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Поставить в оплату</div>
                        <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Выбрано строк: {{ selectedPaymentScheduleIds.length }}
                        </div>
                    </div>

                    <div class="space-y-3 p-5">
                        <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            Дата оплаты
                        </label>
                        <input
                            v-model="paymentRunDateInput"
                            type="date"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <button
                            type="button"
                            :class="crmGridToolbarBtn"
                            @click="closePaymentRunDateModal"
                        >
                            Отмена
                        </button>
                        <button
                            type="button"
                            :class="`${crmGridToolbarBtn} bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200`"
                            :disabled="selectedPaymentScheduleIds.length === 0 || paymentRunDateInput === ''"
                            @click="markSelectedForDate"
                        >
                            Поставить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <div v-if="props.rows.length === 0" class="shrink-0 px-1 py-10 text-sm text-zinc-500 dark:text-zinc-400">
            График оплат пока не заполнен — задайте платежи в финансовом блоке заказа.
        </div>
        <div
            v-else
            ref="gridPanel"
            :class="crmGridInnerPanel"
            @contextmenu.capture="suppressNativeContextMenuCapture"
        >
            <div class="ag-theme-alpine orders-grid-theme min-h-0 min-w-0 shrink-0 overflow-hidden" :class="densityClass" :style="gridContainerStyle">
                <AgGridVue
                    ref="agGrid"
                    :gridOptions="gridOptions"
                    :rowData="gridRows"
                    :columnDefs="dynamicColumnDefs"
                    :defaultColDef="defaultColDef"
                    :rowSelection="'multiple'"
                    domLayout="normal"
                    :pagination="false"
                    :animateRows="true"
                    :suppressCellFocus="false"
                    :alwaysShowVerticalScroll="true"
                    style="height: 100%; width: 100%;"
                    @grid-ready="onGridReady"
                    @first-data-rendered="onFirstDataRendered"
                    @cell-double-clicked="onCellDoubleClicked"
                    @cell-context-menu="onCellContextMenu"
                    @cell-value-changed="onCellValueChanged"
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

        <GridContextMenu
            :open="contextMenu.open"
            :x="contextMenu.x"
            :y="contextMenu.y"
            :items="contextMenu.items"
            @close="closeContextMenu"
        />
    </div>
</template>

<script setup>
import { createVNode, computed, markRaw, nextTick, onMounted, onUnmounted, ref, render, shallowRef, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Download, Printer, Rows3, Search } from 'lucide-vue-next';
import { gridDensityOptions, resolveGridDensity, defaultGridDensity } from '@/Components/Grid/grid-density.js';
import { applyAgSetListColumn } from '@/Components/Grid/agSetListFilter.js';
import {
    CRM_AG_GRID_DENSITY_CHANGED,
    readPersistedAgGridDensity,
    writeLocalAgGridDensity,
} from '@/support/agGridUserDensity.js';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import PaymentScheduleActions from '@/Components/PaymentScheduleActions.vue';
import { applyAgGridIdColumnSizing, autoSizeIdColumnIfNotPersisted } from '@/support/agGridIdColumn.js';
import { useAgGridHorizontalPanel } from '@/support/useAgGridHorizontalPanel.js';
import { crmGridDropdown, crmGridInnerPanel, crmGridSearchField, crmGridToolbarBtn } from '@/support/crmUi.js';
import { cashFlowRowDisplayAmount, cashFlowRowStatusLabel } from '@/support/cashFlowJournalStats.js';
import GridContextMenu from '@/Components/Grid/GridContextMenu.vue';
import GridViewsBar from '@/Components/Grid/GridViewsBar.vue';
import { suppressNativeContextMenuCapture } from '@/Components/Grid/suppressNativeContextMenuCapture.js';
import { useGridContextMenu } from '@/Components/Grid/useGridContextMenu.js';
import { PRINT_DOCUMENT_BASE_STYLES, printHtmlDocument } from '@/support/printHtmlDocument.js';

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
    availableColumns: {
        type: Array,
        default: () => [],
    },
    roleColumnsConfig: {
        type: Object,
        default: () => ({}),
    },
    canManageActions: {
        type: Boolean,
        default: false,
    },
    canShowActionsColumn: {
        type: Boolean,
        default: false,
    },
    canRecordPayment: {
        type: Boolean,
        default: false,
    },
    canCancelPaymentRow: {
        type: Boolean,
        default: false,
    },
    initialPreset: {
        type: String,
        default: null,
    },
});

const DIRECTION_FILTER_VALUES = ['Мы', 'Нам'];
const PAYMENT_TYPE_FILTER_VALUES = ['Предоплата', 'Финальный платёж'];
const STATUS_FILTER_VALUES = ['По плану', 'Частично оплачено', 'Оплачено', 'Просрочено', 'Отменено'];
const workModeOptions = [
    { key: 'due', label: 'К оплате' },
    { key: 'payment_run_today', label: 'Оплачиваем сегодня' },
    { key: 'overdue', label: 'Просрочено' },
    { key: 'today', label: 'План сегодня' },
    { key: 'upcoming', label: '7 дней' },
    { key: 'no_date', label: 'Без даты' },
    { key: 'all', label: 'Все' },
];

const presetFilterModels = {
    customer_overdue: {
        direction: {
            values: ['Нам'],
        },
        status: {
            values: ['Просрочено'],
        },
    },
};

const currentDensity = ref(defaultGridDensity);
const showDensityMenu = ref(false);
const showPaymentRunActionsMenu = ref(false);
const showPaymentRunDateModal = ref(false);
const paymentRunDateInput = ref('');
const densityClass = computed(() => `orders-grid-density--${currentDensity.value}`);
const currentDensityLabel = computed(() => resolveGridDensity(currentDensity.value).label);
const currentWorkMode = ref('due');
const selectedRows = ref([]);

const quickSearch = ref('');
const agGrid = ref(null);
const gridApi = ref(null);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);

const { bottomScrollbarWidth, gridContainerStyle, onBottomScrollbarScroll, refreshAgGridPanelLayout } = useAgGridHorizontalPanel({
    gridPanel,
    bottomScrollbar,
    agGrid,
    gridApi,
});
const {
    contextMenu,
    closeContextMenu,
    openContextMenu,
} = useGridContextMenu();

let filterModelSaveTimeout = null;

const filterModelStorageKey = computed(() => `cashflow_grid_filter_model_v1_${props.userId}`);

const allGridRows = shallowRef([]);

watch(
    () => props.rows,
    (rows) => {
        allGridRows.value = Array.isArray(rows) ? rows.map((row) => ({ ...row })) : [];
    },
    { immediate: true },
);

const todayIsoDate = () => new Date().toISOString().slice(0, 10);

const gridRows = computed(() => allGridRows.value.filter((row) => matchesPaymentWorkMode(row, currentWorkMode.value)));
const selectedPaymentScheduleIds = computed(() => selectedRows.value.map((row) => Number(row.id)).filter((id) => id > 0));

function normalizeIsoDate(value) {
    return value ? String(value).slice(0, 10) : '';
}

function addDaysIso(dateIso, days) {
    const date = new Date(`${dateIso}T00:00:00`);
    date.setDate(date.getDate() + days);

    return date.toISOString().slice(0, 10);
}

function isOpenPaymentRow(row) {
    if (!row || row.status === 'paid' || row.status === 'cancelled') {
        return false;
    }

    return Number(cashFlowRowDisplayAmount(row) || 0) > 0.009;
}

function matchesPaymentWorkMode(row, mode) {
    if (mode === 'all') {
        return true;
    }

    if (!isOpenPaymentRow(row)) {
        return false;
    }

    const today = todayIsoDate();
    const planned = normalizeIsoDate(row?.planned_date);
    const paymentRunDate = normalizeIsoDate(row?.payment_run_date);

    if (mode === 'payment_run_today') {
        return paymentRunDate === today;
    }

    if (mode === 'no_date') {
        return planned === '';
    }

    if (planned === '') {
        return false;
    }

    if (mode === 'overdue') {
        return planned < today || row?.status === 'overdue';
    }

    if (mode === 'today') {
        return planned === today;
    }

    if (mode === 'upcoming') {
        return planned > today && planned <= addDaysIso(today, 7);
    }

    if (mode === 'due') {
        return planned <= today || paymentRunDate === today || row?.status === 'overdue';
    }

    return true;
}

function countRowsForWorkMode(mode) {
    return allGridRows.value.filter((row) => matchesPaymentWorkMode(row, mode)).length;
}

function formatGridDate(value) {
    const raw = value ? String(value).slice(0, 10) : '';
    const parts = raw.split('-');

    if (parts.length !== 3) {
        return raw || '—';
    }

    const [year, month, day] = parts;

    return `${day.padStart(2, '0')}.${month.padStart(2, '0')}.${year}`;
}

const operationalExportRows = computed(() => {
    return gridRows.value.filter((row) => isOpenPaymentRow(row));
});

function operationalExportTableHtml(rows) {
    const head = `
        <tr>
            <th>ID</th>
            <th>Заказ</th>
            <th>Направление</th>
            <th>Контрагент</th>
            <th>Тип</th>
            <th>Номер счёта</th>
            <th>План</th>
            <th>Сумма</th>
            <th>Статус</th>
        </tr>
    `;

    const body = rows
        .map(
            (row) => `
        <tr>
            <td>${row.id ?? ''}</td>
            <td>${row.order_number ?? ''}</td>
            <td>${row.direction ?? ''}</td>
            <td>${row.counterparty_name ?? '—'}</td>
            <td>${row.payment_type ?? ''}</td>
            <td>${row.invoice_number ?? '—'}</td>
            <td>${formatGridDate(row.planned_date)}</td>
            <td>${formatMoneyValue(cashFlowRowDisplayAmount(row))}</td>
            <td>${cashFlowRowStatusLabel(row)}</td>
        </tr>
    `,
        )
        .join('');

    return `<table><thead>${head}</thead><tbody>${body}</tbody></table>`;
}

function printOperationalPayments() {
    const rows = operationalExportRows.value;

    if (rows.length === 0) {
        return;
    }

    const todayLabel = formatGridDate(todayIsoDate());

    printHtmlDocument(
        `
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="utf-8" />
            <title>График оплат — просрочено и на ${todayLabel}</title>
            <style>
                ${PRINT_DOCUMENT_BASE_STYLES}
                body { font-family: Arial, sans-serif; font-size: 12px; padding: 16px; }
                h1 { font-size: 16px; margin: 0 0 12px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
                th { background: #f4f4f5; }
            </style>
        </head>
        <body>
            <h1>График оплат: просрочено и план на ${todayLabel}</h1>
            <p>Строк: ${rows.length}</p>
            ${operationalExportTableHtml(rows)}
        </body>
        </html>
    `,
        `График оплат — ${todayLabel}`,
    );
}

function downloadOperationalPaymentsCsv() {
    const rows = operationalExportRows.value;

    if (rows.length === 0) {
        return;
    }

    const headers = ['ID', 'Заказ', 'Направление', 'Контрагент', 'Тип', 'Номер счёта', 'План', 'Сумма', 'Статус'];
    const escapeCsv = (value) => {
        const text = String(value ?? '');
        if (/[;"\n]/.test(text)) {
            return `"${text.replace(/"/g, '""')}"`;
        }

        return text;
    };

    const lines = [
        headers.join(';'),
        ...rows.map((row) =>
            [
                row.id,
                row.order_number,
                row.direction,
                row.counterparty_name,
                row.payment_type,
                row.invoice_number,
                formatGridDate(row.planned_date),
                cashFlowRowDisplayAmount(row),
                cashFlowRowStatusLabel(row),
            ]
                .map(escapeCsv)
                .join(';'),
        ),
    ];

    const blob = new Blob(['\uFEFF', lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `grafik-oplat-${todayIsoDate()}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

const gridOptions = markRaw({
    theme: 'legacy',
    localeText: agGridLocaleRu,
});

function formatMoneyValue(value) {
    if (typeof value !== 'number') {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(0);
    }

    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

function statusLabel(status, row = null) {
    if (row) {
        return cashFlowRowStatusLabel(row);
    }

    const labels = {
        pending: 'По плану',
        paid: 'Оплачено',
        overdue: 'Просрочено',
        cancelled: 'Отменено',
    };

    return labels[status] || status;
}

function statusClass(status, row = null) {
    if (row?.is_partially_settled) {
        return 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-200';
    }

    const classes = {
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
        paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200',
        overdue: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-200',
        cancelled: 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };

    return classes[status] || 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200';
}

function orderLinkCellRenderer(params) {
    const wrap = document.createElement('div');
    wrap.className = 'flex h-full items-center';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className =
        'text-left font-medium text-zinc-900 underline decoration-zinc-300 underline-offset-2 hover:decoration-zinc-900 dark:text-zinc-50 dark:decoration-zinc-600 dark:hover:decoration-zinc-200';
    btn.textContent = params.data?.order_number || `#${params.data?.order_id}`;
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        router.visit(route('orders.edit', params.data.order_id));
    });
    wrap.appendChild(btn);

    return wrap;
}

function statusCellRenderer(params) {
    const wrap = document.createElement('div');
    wrap.className = 'flex h-full items-center';
    const span = document.createElement('span');
    span.className = `px-2.5 py-1 text-xs font-medium ${statusClass(params.data?.status, params.data)}`;
    span.textContent = statusLabel(params.data?.status, params.data);
    wrap.appendChild(span);

    return wrap;
}

class PaymentScheduleCell {
    eGui = null;

    init(params) {
        this.eGui = document.createElement('div');
        this.eGui.className = 'flex items-center';
        const vnode = createVNode(PaymentScheduleActions, {
            payment: params.data,
            canRecordPayment: params.canRecordPayment !== false,
            canCancelPaymentRow: params.canCancelPaymentRow !== false,
        });
        render(vnode, this.eGui);
        this.vnode = vnode;
    }

    getGui() {
        return this.eGui;
    }

    refresh(params) {
        if (this.eGui && params?.data) {
            const vnode = createVNode(PaymentScheduleActions, {
                payment: params.data,
                canRecordPayment: params.canRecordPayment !== false,
                canCancelPaymentRow: params.canCancelPaymentRow !== false,
            });
            render(vnode, this.eGui);
            this.vnode = vnode;
        }

        return true;
    }

    destroy() {
        if (this.eGui) {
            render(null, this.eGui);
        }
    }
}

function buildBaseColumnDefs() {
    const paymentTypeCol = {
        colId: 'payment_type',
        field: 'payment_type',
        headerName: 'Тип',
        minWidth: 120,
        sortable: true,
    };
    const directionCol = {
        colId: 'direction',
        field: 'direction',
        headerName: 'Направление',
        minWidth: 110,
        sortable: true,
    };
    applyAgSetListColumn(directionCol, {
        values: DIRECTION_FILTER_VALUES,
        compact: true,
        floatingFilterRow: true,
    });

    applyAgSetListColumn(paymentTypeCol, {
        values: PAYMENT_TYPE_FILTER_VALUES,
        compact: true,
        floatingFilterRow: true,
    });

    const statusCol = {
        colId: 'status',
        headerName: 'Статус',
        minWidth: 120,
        sortable: true,
        valueGetter: (p) => statusLabel(p.data?.status, p.data),
        cellRenderer: statusCellRenderer,
    };
    applyAgSetListColumn(statusCol, {
        values: STATUS_FILTER_VALUES,
        filterValueGetter: (p) => statusLabel(p.data?.status, p.data),
        floatingFilterRow: true,
    });

    return [
    {
        colId: '__selection',
        headerName: '',
        width: 48,
        minWidth: 48,
        maxWidth: 48,
        pinned: 'left',
        sortable: false,
        filter: false,
        resizable: false,
        suppressSizeToFit: true,
        suppressMovable: true,
        suppressHeaderFilterButton: true,
        checkboxSelection: (params) => props.canRecordPayment && isOpenPaymentRow(params.data),
        headerCheckboxSelection: props.canRecordPayment,
        headerCheckboxSelectionFilteredOnly: true,
        getQuickFilterText: () => '',
        valueGetter: () => '',
    },
    applyAgGridIdColumnSizing({
        colId: 'id',
        field: 'id',
        headerName: 'ID',
        sortable: true,
        pinned: 'left',
        filter: false,
        floatingFilter: false,
        suppressHeaderFilterButton: true,
        getQuickFilterText: () => '',
        valueFormatter: (p) => (p.value === null || p.value === undefined || p.value === '' ? '—' : String(p.value)),
    }),
    {
        colId: 'order_number',
        headerName: 'Заказ',
        minWidth: 120,
        flex: 1,
        sortable: true,
        filter: 'agTextColumnFilter',
        floatingFilter: true,
        cellRenderer: orderLinkCellRenderer,
        valueGetter: (p) => p.data?.order_number || `#${p.data?.order_id}`,
    },
    directionCol,
    {
        colId: 'counterparty_name',
        field: 'counterparty_name',
        headerName: 'Контрагент',
        minWidth: 160,
        flex: 1,
        sortable: true,
        filter: 'agTextColumnFilter',
        floatingFilter: true,
        valueFormatter: (p) => p.value || '—',
    },
    paymentTypeCol,
    {
        colId: 'invoice_number',
        field: 'invoice_number',
        headerName: 'Номер счёта',
        minWidth: 140,
        flex: 1,
        sortable: true,
        filter: 'agTextColumnFilter',
        floatingFilter: true,
        editable: (p) => Boolean(props.canManageActions),
        valueFormatter: (p) => (p.value ? String(p.value) : '—'),
    },
    {
        colId: 'payment_run_date',
        field: 'payment_run_date',
        headerName: 'План оплаты',
        minWidth: 130,
        sortable: true,
        filter: 'agDateColumnFilter',
        floatingFilter: true,
        valueFormatter: (p) => formatGridDate(p.value),
        cellClass: (params) => (normalizeIsoDate(params.value) === todayIsoDate()
            ? ['bg-emerald-50', 'text-emerald-800', 'dark:bg-emerald-950/30', 'dark:text-emerald-200']
            : []),
    },
    {
        colId: 'planned_date',
        field: 'planned_date',
        headerName: 'План',
        minWidth: 120,
        sortable: true,
        filter: 'agDateColumnFilter',
        floatingFilter: true,
        valueFormatter: (p) => formatGridDate(p.value),
    },
    {
        colId: 'actual_date',
        field: 'actual_date',
        headerName: 'Факт',
        minWidth: 120,
        sortable: true,
        filter: 'agDateColumnFilter',
        floatingFilter: true,
        valueFormatter: (p) => formatGridDate(p.value),
    },
    {
        colId: 'amount',
        field: 'amount_due',
        headerName: 'К оплате',
        minWidth: 120,
        sortable: true,
        filter: 'agNumberColumnFilter',
        floatingFilter: true,
        valueGetter: (params) => cashFlowRowDisplayAmount(params.data),
        valueFormatter: (p) => formatMoneyValue(p.value),
    },
    statusCol,
    {
        colId: 'actions',
        headerName: 'Действия',
        width: 88,
        minWidth: 72,
        maxWidth: 112,
        pinned: 'right',
        sortable: false,
        filter: false,
        resizable: false,
        suppressSizeToFit: true,
        cellRenderer: PaymentScheduleCell,
    },
    ];
}

const baseColumnDefs = markRaw(buildBaseColumnDefs());

const dynamicColumnDefs = computed(() => {
    const baseById = new Map(baseColumnDefs.map((column) => [column.colId, { ...column }]));
    const roleState = Array.isArray(props.roleColumnsConfig?.payment_schedule)
        ? props.roleColumnsConfig.payment_schedule
        : [];

    let ordered = baseColumnDefs.map((column) => ({ ...column }));
    if (roleState.length > 0) {
        const sortedState = [...roleState].sort((left, right) => Number(left.order ?? 0) - Number(right.order ?? 0));
        const seen = new Set();
        ordered = sortedState
            .map((state) => {
                const colId = state.colId;
                if (!baseById.has(colId)) {
                    return null;
                }
                seen.add(colId);
                return {
                    ...baseById.get(colId),
                    hide: Boolean(state.hide),
                    width: Number(state.width) || baseById.get(colId).width,
                };
            })
            .filter(Boolean);

        baseColumnDefs.forEach((column) => {
            if (!seen.has(column.colId)) {
                ordered.push({ ...column });
            }
        });
    }

    const allowedColumnIds = props.availableColumns.map((column) => column.field);
    if (allowedColumnIds.length > 0) {
        ordered = ordered.filter((column) => column.colId === '__selection' || allowedColumnIds.includes(column.colId));
    }

    return ordered.map((column) => {
        if (column.colId === '__selection') {
            return {
                ...column,
                hide: !props.canRecordPayment,
            };
        }

        if (column.colId === 'actions') {
            const savedWidth = Number(column.width) || 88;

            return {
                ...column,
                width: Math.min(Math.max(savedWidth, 72), 112),
                minWidth: 72,
                maxWidth: 112,
                hide: Boolean(column.hide) || !props.canShowActionsColumn,
                cellRendererParams: {
                    canRecordPayment: props.canRecordPayment,
                    canCancelPaymentRow: props.canCancelPaymentRow,
                },
            };
        }

        return column;
    });
});

const defaultColDef = {
    sortable: true,
    filter: true,
    resizable: true,
    floatingFilter: false,
    minWidth: 80,
    suppressSizeToFit: true,
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
            console.error('Error saving cashflow grid filter model', error);
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
        console.error('Error loading cashflow grid filter model', error);
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

    const presetModel = props.initialPreset ? presetFilterModels[props.initialPreset] : null;
    if (presetModel) {
        gridApi.value.setFilterModel(presetModel);
    } else {
        loadPersistedFilterModel();
    }

    await nextTick();
    refreshAgGridPanelLayout();
};

const onFirstDataRendered = () => {
    requestAnimationFrame(() => {
        autoSizeIdColumnIfNotPersisted(gridApi.value, null);
        refreshAgGridPanelLayout();
    });
};

const onCellDoubleClicked = (event) => {
    if (event.colDef?.colId === 'invoice_number') {
        return;
    }

    const orderId = event.data?.order_id;

    if (orderId) {
        router.visit(route('orders.edit', orderId));
    }
};

function onCellContextMenu(params) {
    const row = params.node?.data;
    if (!row?.id) {
        closeContextMenu();

        return;
    }

    const items = [];
    if (row.order_id) {
        items.push({
            label: 'Открыть заказ',
            run: () => router.visit(route('orders.edit', row.order_id)),
        });
    }

    if (props.canRecordPayment && isOpenPaymentRow(row)) {
        items.push(
            {
                label: 'Поставить в оплату сегодня',
                run: () => applyPaymentRunPatch([Number(row.id)], { payment_run_date: todayIsoDate() }),
            },
            {
                label: 'Снять план оплаты',
                danger: true,
                disabled: !row.payment_run_date,
                run: () => applyPaymentRunPatch([Number(row.id)], { clear: true }),
            },
        );
    }

    openContextMenu(params.event, items);
}

const onCellValueChanged = (event) => {
    if (event.colDef.colId !== 'invoice_number' || !props.canManageActions) {
        return;
    }

    const id = event.data?.id;
    if (!id) {
        return;
    }

    const newVal = event.newValue != null ? String(event.newValue).trim() : '';
    const oldVal = event.oldValue != null ? String(event.oldValue).trim() : '';
    if (newVal === oldVal) {
        return;
    }

    axios
        .patch(route('payment-schedules.invoice-number', id), {
            invoice_number: newVal === '' ? null : newVal,
        })
        .catch(() => {
            event.api.refreshCells({ rowNodes: [event.node], columns: ['invoice_number'], force: true });
        });
};

function onSelectionChanged(event) {
    selectedRows.value = event.api.getSelectedRows().filter((row) => isOpenPaymentRow(row));
}

function applyPaymentRunPatch(ids, payload) {
    if (ids.length === 0) {
        return;
    }

    axios
        .patch(route('payment-schedules.payment-run'), {
            payment_schedule_ids: ids,
            ...payload,
        })
        .then((response) => {
            const updatedIds = new Set((response.data?.updated_ids ?? []).map((id) => Number(id)));
            allGridRows.value = allGridRows.value.map((row) => {
                if (!updatedIds.has(Number(row.id))) {
                    return row;
                }

                return {
                    ...row,
                    payment_run_date: response.data?.payment_run_date ?? null,
                    payment_run_by: response.data?.payment_run_date ? props.userId : null,
                    payment_run_note: payload.clear ? null : (payload.payment_run_note ?? null),
                };
            });
            selectedRows.value = [];
            gridApi.value?.deselectAll();
            router.reload({
                only: ['cashFlowJournal', 'cash_flow_stats', 'todays_cash_flow'],
                preserveScroll: true,
            });
        })
        .catch((error) => {
            console.error('Failed to update payment run', error);
        });
}

function markSelectedForToday() {
    showPaymentRunActionsMenu.value = false;
    applyPaymentRunPatch(selectedPaymentScheduleIds.value, {
        payment_run_date: todayIsoDate(),
    });
}

function openPaymentRunDateModal() {
    if (selectedPaymentScheduleIds.value.length === 0) {
        return;
    }

    showPaymentRunActionsMenu.value = false;
    paymentRunDateInput.value = todayIsoDate();
    showPaymentRunDateModal.value = true;
}

function closePaymentRunDateModal() {
    showPaymentRunDateModal.value = false;
}

function markSelectedForDate() {
    if (paymentRunDateInput.value === '') {
        return;
    }

    applyPaymentRunPatch(selectedPaymentScheduleIds.value, {
        payment_run_date: paymentRunDateInput.value,
    });
    showPaymentRunDateModal.value = false;
}

function clearSelectedPaymentRun() {
    showPaymentRunActionsMenu.value = false;
    applyPaymentRunPatch(selectedPaymentScheduleIds.value, {
        clear: true,
    });
}

function togglePaymentRunActionsMenu() {
    if (!props.canRecordPayment || selectedPaymentScheduleIds.value.length === 0) {
        return;
    }

    showPaymentRunActionsMenu.value = !showPaymentRunActionsMenu.value;
    showDensityMenu.value = false;
}

watch(quickSearch, (value) => {
    if (!gridApi.value) {
        return;
    }

    gridApi.value.setGridOption('quickFilterText', value ?? '');
});

watch(currentWorkMode, async () => {
    selectedRows.value = [];
    gridApi.value?.deselectAll();
    await nextTick();
    gridApi.value?.resetRowHeights();
    refreshAgGridPanelLayout();
});

function resetGridViewState() {
    if (gridApi.value) {
        gridApi.value.setFilterModel({});
    }

    localStorage.removeItem(filterModelStorageKey.value);
    quickSearch.value = '';
}

function onGridViewsPinnedChanged() {
    router.reload({ preserveScroll: true });
}

watch(
    () => props.rows?.length ?? 0,
    async () => {
        await nextTick();
        refreshAgGridPanelLayout();
    },
);

const loadDensity = () => {
    currentDensity.value = readPersistedAgGridDensity(props.userId);
};

const applyDensity = (densityKey) => {
    currentDensity.value = resolveGridDensity(densityKey).key;
    writeLocalAgGridDensity(props.userId, currentDensity.value);
    showDensityMenu.value = false;

    nextTick(() => {
        gridApi.value?.resetRowHeights();
        refreshAgGridPanelLayout();
    });
};

const toggleDensityMenu = () => {
    showDensityMenu.value = !showDensityMenu.value;
    showPaymentRunActionsMenu.value = false;
};

const onGlobalDensityChanged = (event) => {
    const next = event?.detail?.density;

    if (!next) {
        return;
    }

    currentDensity.value = resolveGridDensity(next).key;

    nextTick(() => {
        gridApi.value?.resetRowHeights();
        refreshAgGridPanelLayout();
    });
};

onMounted(() => {
    loadDensity();
    window.addEventListener(CRM_AG_GRID_DENSITY_CHANGED, onGlobalDensityChanged);
});

onUnmounted(() => {
    window.removeEventListener(CRM_AG_GRID_DENSITY_CHANGED, onGlobalDensityChanged);

    if (filterModelSaveTimeout) {
        clearTimeout(filterModelSaveTimeout);
    }
});
</script>
