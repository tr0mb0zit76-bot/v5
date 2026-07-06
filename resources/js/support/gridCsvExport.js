const DEFAULT_EXCLUDED_FIELDS = new Set([
    'actions',
    '__actions',
    'order_actions',
]);

export function buildResponsibleOptionsFromRows(rows, { idField, nameField }) {
    const list = Array.isArray(rows) ? rows : [];
    const map = new Map();

    for (const row of list) {
        const rawId = row?.[idField];
        const id = rawId === null || rawId === undefined || rawId === '' ? null : Number(rawId);
        const name = String(row?.[nameField] ?? '').trim() || '—';
        const key = id !== null && Number.isFinite(id) ? `id:${id}` : `name:${name}`;

        if (!map.has(key)) {
            map.set(key, {
                id,
                label: name,
            });
        }
    }

    return [...map.values()].sort((left, right) => left.label.localeCompare(right.label, 'ru'));
}

export function buildExportColumnsFromGrid(gridApi, excludedFields = DEFAULT_EXCLUDED_FIELDS) {
    if (!gridApi) {
        return [];
    }

    const columns = gridApi.getColumns() ?? [];

    return columns
        .filter((column) => {
            const colDef = column.getColDef();
            const field = colDef.field ?? column.getColId();

            if (!field || excludedFields.has(field)) {
                return false;
            }

            return true;
        })
        .map((column) => {
            const colDef = column.getColDef();
            const field = colDef.field ?? column.getColId();

            return {
                field,
                headerName: colDef.headerName ?? field,
                visible: column.isVisible() !== false,
            };
        });
}

function resolveExportCellValue(gridApi, node, field) {
    const column = gridApi.getColumn(field);

    if (!column) {
        return node.data?.[field] ?? '';
    }

    const colDef = column.getColDef();
    let value = node.data?.[colDef.field ?? field];

    if (typeof colDef.valueGetter === 'function') {
        value = colDef.valueGetter({
            api: gridApi,
            colDef,
            column,
            context: undefined,
            data: node.data,
            getValue: (key) => node.data?.[key],
            node,
        });
    }

    if (typeof colDef.valueFormatter === 'function') {
        return colDef.valueFormatter({
            api: gridApi,
            colDef,
            column,
            context: undefined,
            data: node.data,
            node,
            value,
        });
    }

    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? 'Да' : 'Нет';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return value;
}

function buildResponsibleRowPredicate({ responsibleMode, responsibleIds, idField }) {
    if (responsibleMode !== 'selected' || !Array.isArray(responsibleIds) || responsibleIds.length === 0) {
        return () => true;
    }

    const selectedIds = new Set(
        responsibleIds
            .filter((value) => value !== null && value !== undefined && value !== '')
            .map((value) => Number(value))
            .filter((value) => Number.isFinite(value)),
    );
    const includeUnassigned = responsibleIds.includes(null);

    return (row) => {
        const rawId = row?.[idField];
        const id = rawId === null || rawId === undefined || rawId === '' ? null : Number(rawId);

        if (id === null || !Number.isFinite(id)) {
            return includeUnassigned;
        }

        return selectedIds.has(id);
    };
}

function escapeCsvCell(value) {
    const text = String(value ?? '');

    if (/[",\n\r;]/.test(text)) {
        return `"${text.replace(/"/g, '""')}"`;
    }

    return text;
}

function downloadCsvFile(headers, body, fileName) {
    const normalizedName = fileName.endsWith('.csv') ? fileName : `${fileName}.csv`;
    const lines = [
        headers.map(escapeCsvCell).join(';'),
        ...body.map((row) => row.map(escapeCsvCell).join(';')),
    ];
    const blob = new Blob([`\uFEFF${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = normalizedName;
    link.click();
    URL.revokeObjectURL(url);
}

export function exportAgGridToCsv({
    gridApi,
    columns,
    fileName,
    responsibleMode = 'grid',
    responsibleIds = [],
    responsibleIdField = null,
}) {
    if (!gridApi) {
        return { exportedRows: 0 };
    }

    const exportColumns = (Array.isArray(columns) ? columns : []).filter((column) => column?.visible !== false);

    if (exportColumns.length === 0) {
        return { exportedRows: 0 };
    }

    const rowPredicate = responsibleIdField
        ? buildResponsibleRowPredicate({
            responsibleMode,
            responsibleIds,
            idField: responsibleIdField,
        })
        : () => true;

    const headers = exportColumns.map((column) => column.headerName ?? column.field);
    const body = [];

    gridApi.forEachNodeAfterFilterAndSort((node) => {
        if (!node?.data || !rowPredicate(node.data)) {
            return;
        }

        body.push(
            exportColumns.map((column) => resolveExportCellValue(gridApi, node, column.field)),
        );
    });

    downloadCsvFile(headers, body, fileName);

    return { exportedRows: body.length };
}

export function defaultGridExportFileName(prefix) {
    const date = new Date().toISOString().slice(0, 10);

    return `${prefix}_${date}.csv`;
}
