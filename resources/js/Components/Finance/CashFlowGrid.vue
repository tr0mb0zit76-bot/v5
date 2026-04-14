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

            <div class="flex shrink-0 items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="quickSearch"
                        type="text"
                        placeholder="Поиск по реестру"
                        class="w-72 rounded-xl border border-zinc-200 bg-white py-1.5 pl-10 pr-3 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                    />
                </div>
            </div>
        </div>

        <div v-if="props.rows.length === 0" class="shrink-0 px-1 py-10 text-sm text-zinc-500 dark:text-zinc-400">
            График оплат пока не заполнен — задайте платежи в финансовом блоке заказа.
        </div>
        <div
            v-else
            ref="gridPanel"
            class="flex min-h-0 flex-1 flex-col overflow-hidden border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="ag-theme-alpine orders-grid-theme orders-grid-density--comfortable" :style="gridContainerStyle">
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
                    :alwaysShowVerticalScroll="true"
                    style="height: 100%; width: 100%;"
                    @grid-ready="onGridReady"
                    @first-data-rendered="onFirstDataRendered"
                    @cell-double-clicked="onCellDoubleClicked"
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
    </div>
</template>

<script setup>
import { createVNode, computed, nextTick, onMounted, onUnmounted, ref, render, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { Search } from 'lucide-vue-next';

import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import '@/Components/Grid/grid-theme.css';
import PaymentScheduleActions from '@/Components/PaymentScheduleActions.vue';

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

const quickSearch = ref('');
const agGrid = ref(null);
const gridApi = ref(null);
const gridSection = ref(null);
const gridPanel = ref(null);
const bottomScrollbar = ref(null);
const bottomScrollbarWidth = ref(0);
const gridViewportHeight = ref(280);

let isSyncingHorizontalScroll = false;
let filterModelSaveTimeout = null;
let removeCenterViewportListener = null;

const filterModelStorageKey = computed(() => `cashflow_grid_filter_model_v1_${props.userId}`);

const gridOptions = {
    theme: 'legacy',
};

const gridContainerStyle = computed(() => ({
    height: `${gridViewportHeight.value}px`,
    minHeight: `${gridViewportHeight.value}px`,
    width: '100%',
}));

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

function statusLabel(status) {
    const labels = {
        pending: 'По плану',
        paid: 'Оплачено',
        overdue: 'Просрочено',
    };

    return labels[status] || status;
}

function statusClass(status) {
    const classes = {
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
        paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200',
        overdue: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-200',
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
    span.className = `px-2.5 py-1 text-xs font-medium ${statusClass(params.data?.status)}`;
    span.textContent = statusLabel(params.data?.status);
    wrap.appendChild(span);

    return wrap;
}

class PaymentScheduleCell {
    eGui = null;

    init(params) {
        this.eGui = document.createElement('div');
        this.eGui.className = 'flex items-center';
        const vnode = createVNode(PaymentScheduleActions, { payment: params.data });
        render(vnode, this.eGui);
        this.vnode = vnode;
    }

    getGui() {
        return this.eGui;
    }

    refresh() {
        return false;
    }

    destroy() {
        if (this.eGui) {
            render(null, this.eGui);
        }
    }
}

const columnDefs = [
    {
        colId: 'order_number',
        headerName: 'Заказ',
        minWidth: 120,
        flex: 1,
        sortable: true,
        cellRenderer: orderLinkCellRenderer,
        valueGetter: (p) => p.data?.order_number || `#${p.data?.order_id}`,
    },
    {
        field: 'direction',
        headerName: 'Направление',
        minWidth: 110,
        sortable: true,
    },
    {
        field: 'counterparty_name',
        headerName: 'Контрагент',
        minWidth: 160,
        flex: 1,
        sortable: true,
        valueFormatter: (p) => p.value || '—',
    },
    {
        field: 'payment_type',
        headerName: 'Тип',
        minWidth: 120,
        sortable: true,
    },
    {
        field: 'planned_date',
        headerName: 'План',
        minWidth: 120,
        sortable: true,
        valueFormatter: (p) => (p.value ? String(p.value).slice(0, 10) : '—'),
    },
    {
        field: 'actual_date',
        headerName: 'Факт',
        minWidth: 120,
        sortable: true,
        valueFormatter: (p) => (p.value ? String(p.value).slice(0, 10) : '—'),
    },
    {
        field: 'amount',
        headerName: 'Сумма',
        minWidth: 120,
        sortable: true,
        valueFormatter: (p) => formatMoneyValue(p.value),
    },
    {
        colId: 'status',
        headerName: 'Статус',
        minWidth: 120,
        sortable: true,
        valueGetter: (p) => statusLabel(p.data?.status),
        cellRenderer: statusCellRenderer,
    },
    {
        colId: 'actions',
        headerName: 'Действия',
        width: 160,
        minWidth: 140,
        maxWidth: 200,
        pinned: 'right',
        sortable: false,
        filter: false,
        resizable: false,
        cellRenderer: PaymentScheduleCell,
    },
];

const defaultColDef = {
    sortable: true,
    filter: true,
    resizable: true,
    floatingFilter: true,
    minWidth: 80,
    suppressSizeToFit: true,
};

const getCenterViewport = () => agGrid.value?.$el?.querySelector('.ag-viewport.ag-center-cols-viewport') ?? null;

const updateGridViewportHeight = () => {
    const panel = gridPanel.value;

    if (!panel) {
        return;
    }

    const panelTop = panel.getBoundingClientRect().top;
    const bottomScrollbarHeight = bottomScrollbar.value?.offsetHeight ?? 16;
    const commandBarFooter = document.querySelector('footer');
    const footerTop = commandBarFooter?.getBoundingClientRect().top ?? window.innerHeight;
    const footerReserve = 60;

    gridViewportHeight.value = Math.max(
        280,
        Math.floor(footerTop - panelTop - bottomScrollbarHeight - footerReserve),
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

    loadPersistedFilterModel();

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

const onCellDoubleClicked = (event) => {
    const orderId = event.data?.order_id;

    if (orderId) {
        router.visit(route('orders.edit', orderId));
    }
};

watch(quickSearch, (value) => {
    if (!gridApi.value) {
        return;
    }

    gridApi.value.setGridOption('quickFilterText', value ?? '');
});

watch(() => props.rows, async () => {
    await nextTick();
    updateGridViewportHeight();
    attachCenterViewportListener();
    syncBottomScrollbar();
}, { deep: true });

onMounted(() => {
    updateGridViewportHeight();
    window.addEventListener('resize', updateGridViewportHeight);
    window.addEventListener('resize', syncBottomScrollbar);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateGridViewportHeight);
    window.removeEventListener('resize', syncBottomScrollbar);
    removeCenterViewportListener?.();

    if (filterModelSaveTimeout) {
        clearTimeout(filterModelSaveTimeout);
    }
});
</script>
