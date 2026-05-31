<template>
    <div ref="gridSection" class="flex min-h-0 flex-1 flex-col gap-2">
        <div class="flex shrink-0 items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
            <span>Строк: {{ rows.length }}. Редактируйте ячейки — сохранение автоматически.</span>
            <span>Фильтр: статус «Выполняется»</span>
        </div>

        <div ref="gridPanel" :class="crmGridInnerPanel">
            <div class="ag-theme-alpine orders-grid-theme" :style="gridContainerStyle">
                <AgGridVue
                    ref="agGrid"
                    :grid-options="gridOptions"
                    :row-data="rows"
                    :column-defs="columnDefs"
                    :default-col-def="defaultColDef"
                    dom-layout="normal"
                    :pagination="false"
                    :animate-rows="false"
                    :suppress-cell-focus="false"
                    style="height: 100%; width: 100%;"
                    @grid-ready="onGridReady"
                    @cell-value-changed="onCellValueChanged"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { AgGridVue } from 'ag-grid-vue3';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';
import { agGridLocaleRu } from '@/Components/Grid/ag-grid-locale-ru';
import '@/Components/Grid/grid-theme.css';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { crmGridInnerPanel } from '@/support/crmUi.js';

ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps({
    dates: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    userId: { type: [String, Number], default: 'guest' },
});

const gridApi = ref(null);
const gridSection = ref(null);
const gridPanel = ref(null);
const gridViewportHeight = ref(400);
const savingCells = ref(new Set());

const gridContainerStyle = computed(() => ({
    height: `${gridViewportHeight.value}px`,
    width: '100%',
}));

const defaultColDef = {
    sortable: false,
    filter: false,
    resizable: true,
    editable: false,
    suppressHeaderMenuButton: true,
};

const gridOptions = {
    localeText: agGridLocaleRu,
};

function fieldPrefix(date, slot) {
    return `cell_${String(date).replace(/-/g, '')}_${slot}`;
}

function formatDateHeader(date) {
    const parts = String(date).split('-');
    if (parts.length !== 3) {
        return date;
    }

    return `${parts[2]}.${parts[1]}`;
}

const columnDefs = computed(() => {
    const pinnedLeft = [
        {
            field: 'order_number',
            headerName: 'Заказ',
            pinned: 'left',
            width: 130,
            minWidth: 110,
            editable: false,
        },
        {
            field: 'route_hint',
            headerName: 'Маршрут',
            pinned: 'left',
            width: 200,
            minWidth: 160,
            editable: false,
        },
    ];

    const dayGroups = (props.dates ?? []).map((date) => ({
        headerName: formatDateHeader(date),
        children: [
            {
                field: `${fieldPrefix(date, 'morning')}_location`,
                headerName: 'Утро — место',
                width: 140,
                minWidth: 110,
                editable: true,
            },
            {
                field: `${fieldPrefix(date, 'morning')}_comment`,
                headerName: 'Утро — комм.',
                width: 140,
                minWidth: 110,
                editable: true,
            },
            {
                field: `${fieldPrefix(date, 'evening')}_location`,
                headerName: 'Вечер — место',
                width: 140,
                minWidth: 110,
                editable: true,
            },
            {
                field: `${fieldPrefix(date, 'evening')}_comment`,
                headerName: 'Вечер — комм.',
                width: 140,
                minWidth: 110,
                editable: true,
            },
        ],
    }));

    const pinnedRight = [
        {
            field: 'planned_arrival_date',
            headerName: 'План прибытия',
            pinned: 'right',
            width: 120,
            minWidth: 100,
            editable: false,
            valueFormatter: (params) => {
                if (!params.value) {
                    return '—';
                }

                const p = String(params.value).split('-');

                return p.length === 3 ? `${p[2]}.${p[1]}.${p[0]}` : params.value;
            },
        },
    ];

    return [...pinnedLeft, ...dayGroups, ...pinnedRight];
});

function parseCellField(field) {
    const match = /^cell_(\d{8})_(morning|evening)_(location|comment)$/.exec(field ?? '');

    if (!match) {
        return null;
    }

    const ymd = match[1];
    const date = `${ymd.slice(0, 4)}-${ymd.slice(4, 6)}-${ymd.slice(6, 8)}`;

    return {
        date,
        slot: match[2],
        part: match[3],
    };
}

async function saveCell(row, parsed, newValue) {
    const key = `${row.order_id}|${parsed.date}|${parsed.slot}|${parsed.part}`;
    if (savingCells.value.has(key)) {
        return;
    }

    savingCells.value.add(key);

    const prefix = fieldPrefix(parsed.date, parsed.slot);
    const payload = {
        order_id: row.order_id,
        date: parsed.date,
        slot: parsed.slot,
        location: parsed.part === 'location' ? newValue : (row[`${prefix}_location`] ?? null),
        comment: parsed.part === 'comment' ? newValue : (row[`${prefix}_comment`] ?? null),
    };

    try {
        await window.axios.post(route('disposition.entries.upsert'), payload);
    } finally {
        savingCells.value.delete(key);
    }
}

function onCellValueChanged(event) {
    const parsed = parseCellField(event.colDef?.field);
    const row = event.data;

    if (!parsed || !row?.order_id) {
        return;
    }

    saveCell(row, parsed, event.newValue ?? '');
}

function onGridReady(params) {
    gridApi.value = params.api;

    if (props.today) {
        const colId = `${fieldPrefix(props.today, 'morning')}_location`;
        params.api.ensureColumnVisible(colId);
    }

    const panel = gridPanel.value;
    if (panel && typeof panel.getBoundingClientRect === 'function') {
        const rect = panel.getBoundingClientRect();
        gridViewportHeight.value = Math.max(280, Math.floor(rect.height) || 400);
    }
}
</script>
