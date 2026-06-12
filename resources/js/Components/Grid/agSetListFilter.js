/**
 * Простой фильтр-список значений (чекбоксы) для AG Grid Community.
 *
 * @typedef {object} AgSetListFilterParams
 * @property {string[]} values
 * @property {boolean} [sortValues]
 * @property {string} [searchPlaceholder]
 */

/**
 * @param {string | string[] | undefined} existing
 * @param {string} className
 */
function appendHeaderClass(existing, className) {
    if (! existing) {
        return className;
    }

    const parts = Array.isArray(existing) ? [...existing] : String(existing).split(/\s+/);

    if (! parts.includes(className)) {
        parts.push(className);
    }

    return parts.join(' ');
}

/**
 * @param {string[]} selected
 * @param {string[]} allValues
 */
function floatingSelectionLabel(selected, allValues) {
    if (selected.length === 0) {
        return 'Ничего';
    }

    if (selected.length === allValues.length) {
        return 'Все';
    }

    if (selected.length <= 2) {
        return selected.join(', ');
    }

    return `${selected.length} из ${allValues.length}`;
}

/**
 * @param {string[] | Iterable<string>} values
 */
export function setListFilterParams(values) {
    return {
        values: [...values],
        sortValues: true,
        searchPlaceholder: 'Поиск…',
    };
}

/**
 * @param {object} columnDefinition
 * @param {{ values: string[], filterValueGetter?: (params: import('ag-grid-community').IDoesFilterPassParams) => string, compact?: boolean, floatingFilterRow?: boolean, inlineSelectInFloatingRow?: boolean }} options
 */
export function applyAgSetListColumn(columnDefinition, options) {
    const values = [...options.values];

    columnDefinition.filter = AgSetListFilter;
    columnDefinition.filterParams = {
        ...setListFilterParams(values),
        compact: options.compact ?? values.length <= 3,
    };

    if (options.floatingFilterRow) {
        columnDefinition.floatingFilter = true;
        columnDefinition.suppressHeaderFilterButton = true;
        columnDefinition.suppressFloatingFilterButton = true;
        columnDefinition.floatingFilterComponent = AgSetListFloatingFilter;
        columnDefinition.floatingFilterComponentParams = {
            multiSelect: options.inlineSelectInFloatingRow !== false,
        };
        columnDefinition.headerClass = appendHeaderClass(
            columnDefinition.headerClass,
            'ag-set-list-floating-only-header',
        );
    } else {
        columnDefinition.floatingFilter = false;
        columnDefinition.suppressHeaderFilterButton = false;
    }

    if (typeof options.filterValueGetter === 'function') {
        columnDefinition.filterValueGetter = options.filterValueGetter;
    }
}

export class AgSetListFloatingFilter {
    /** @type {import('ag-grid-community').IFloatingFilterParams} */
    params = {};

    /** @type {HTMLElement} */
    eGui;

    /** @type {HTMLButtonElement | null} */
    triggerBtn = null;

    /** @type {HTMLElement | null} */
    panelEl = null;

    /** @type {string[]} */
    allValues = [];

    /** @type {Set<string>} */
    selected = new Set();

    /** @type {boolean} */
    panelOpen = false;

    /** @type {((event: MouseEvent) => void) | null} */
    onDocumentClick = null;

    init(params) {
        this.params = params;
        this.allValues = [...(params.filterParams?.values ?? [])];
        this.selected = new Set(this.allValues);

        const useMultiSelect = params.multiSelect !== false && this.allValues.length > 0;

        this.eGui = document.createElement('div');

        if (useMultiSelect) {
            this.buildMultiSelectUi();
        } else {
            this.buildLegacyTriggerUi();
        }
    }

    buildMultiSelectUi() {
        this.eGui.className = 'ag-set-list-floating-multi-wrap flex h-full w-full items-center px-0.5';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ag-set-list-floating-multi-trigger w-full min-w-0 truncate rounded-md border border-zinc-200 bg-white px-1.5 py-1 text-left text-xs text-zinc-800 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100';
        button.title = 'Фильтр';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.textContent = floatingSelectionLabel([...this.selected], this.allValues);
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            this.togglePanel();
        });

        this.triggerBtn = button;
        this.eGui.appendChild(button);

        this.onDocumentClick = (event) => {
            if (! this.panelOpen) {
                return;
            }

            const target = event.target;
            if (target instanceof Node && (this.eGui.contains(target) || this.panelEl?.contains(target))) {
                return;
            }

            this.closePanel();
        };

        document.addEventListener('mousedown', this.onDocumentClick);
    }

    buildLegacyTriggerUi() {
        this.eGui.className = 'ag-set-list-floating flex h-full w-full items-center justify-center px-0.5';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ag-set-list-floating-trigger inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800';
        button.title = 'Фильтр';
        button.setAttribute('aria-label', 'Открыть фильтр');
        button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>';
        button.addEventListener('click', () => {
            this.params.showParentFilter();
        });

        this.triggerBtn = button;
        this.eGui.appendChild(button);
    }

    getGui() {
        return this.eGui;
    }

    destroy() {
        this.closePanel();
        if (this.onDocumentClick) {
            document.removeEventListener('mousedown', this.onDocumentClick);
            this.onDocumentClick = null;
        }
    }

    togglePanel() {
        if (this.panelOpen) {
            this.closePanel();
        } else {
            this.openPanel();
        }
    }

    openPanel() {
        if (! this.triggerBtn || this.panelEl) {
            return;
        }

        const panel = document.createElement('div');
        panel.className = 'ag-set-list-floating-multi-panel rounded-lg border border-zinc-200 bg-white p-2 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900';
        panel.setAttribute('role', 'listbox');
        panel.addEventListener('mousedown', (event) => {
            event.stopPropagation();
        });

        const actions = document.createElement('div');
        actions.className = 'mb-1.5 flex gap-2 text-[11px]';

        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'text-zinc-600 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-400';
        selectAllBtn.textContent = 'Все';
        selectAllBtn.addEventListener('click', () => {
            this.selected = new Set(this.allValues);
            this.syncPanelCheckboxes();
            this.commitSelection();
        });

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'text-zinc-600 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-400';
        clearBtn.textContent = 'Сброс';
        clearBtn.addEventListener('click', () => {
            this.selected = new Set();
            this.syncPanelCheckboxes();
            this.commitSelection();
        });

        actions.appendChild(selectAllBtn);
        actions.appendChild(clearBtn);
        panel.appendChild(actions);

        const list = document.createElement('div');
        list.className = 'ag-set-list-floating-multi-list flex max-h-48 flex-col gap-0.5 overflow-y-auto';

        for (const value of this.allValues) {
            const label = document.createElement('label');
            label.className = 'flex cursor-pointer items-center gap-2 rounded-md px-1 py-0.5 hover:bg-zinc-50 dark:hover:bg-zinc-800';
            label.setAttribute('data-set-filter-value', value);

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = this.selected.has(value);
            input.addEventListener('change', () => {
                if (input.checked) {
                    this.selected.add(value);
                } else {
                    this.selected.delete(value);
                }
                this.commitSelection();
            });

            const span = document.createElement('span');
            span.className = 'text-xs';
            span.textContent = value;

            label.appendChild(input);
            label.appendChild(span);
            list.appendChild(label);
        }

        panel.appendChild(list);
        document.body.appendChild(panel);

        const rect = this.triggerBtn.getBoundingClientRect();
        panel.style.position = 'fixed';
        panel.style.left = `${rect.left}px`;
        panel.style.top = `${rect.bottom + 4}px`;
        panel.style.minWidth = `${Math.max(rect.width, 160)}px`;
        panel.style.zIndex = '10000';

        this.panelEl = panel;
        this.panelOpen = true;
        this.triggerBtn.setAttribute('aria-expanded', 'true');
    }

    closePanel() {
        if (this.panelEl) {
            this.panelEl.remove();
            this.panelEl = null;
        }

        this.panelOpen = false;
        this.triggerBtn?.setAttribute('aria-expanded', 'false');
    }

    syncPanelCheckboxes() {
        if (! this.panelEl) {
            return;
        }

        for (const label of this.panelEl.querySelectorAll('[data-set-filter-value]')) {
            const value = label.getAttribute('data-set-filter-value') ?? '';
            const input = label.querySelector('input[type="checkbox"]');
            if (input) {
                input.checked = this.selected.has(value);
            }
        }
    }

    commitSelection() {
        const values = [...this.selected];
        this.applySelectedValues(values);
        this.updateTriggerAppearance(values);
    }

    /** @param {string[]} values */
    applySelectedValues(values) {
        this.params.parentFilterInstance((parent) => {
            if (! parent) {
                return;
            }

            if (values.length === 0) {
                parent.setModel({ values: [] });
            } else if (values.length === this.allValues.length) {
                parent.setModel(null);
            } else {
                parent.setModel({ values: [...values] });
            }

            parent.params?.filterChangedCallback?.();
        });
    }

    /** @param {string[]} values */
    updateTriggerAppearance(values) {
        if (! this.triggerBtn) {
            return;
        }

        const allCount = this.allValues.length;
        const active = values.length > 0 && values.length < allCount;

        this.triggerBtn.textContent = floatingSelectionLabel(values, this.allValues);
        this.triggerBtn.classList.toggle('ag-set-list-floating-multi-trigger--active', active);
        this.triggerBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
    }

    onParentModelChanged(parentModel) {
        if (! this.triggerBtn || this.panelEl) {
            return;
        }

        const allCount = this.allValues.length;
        const isMulti = this.eGui.classList.contains('ag-set-list-floating-multi-wrap');

        if (! isMulti) {
            const selectedCount = Array.isArray(parentModel?.values) ? parentModel.values.length : 0;
            const active = allCount > 0 && selectedCount > 0 && selectedCount < allCount;
            this.triggerBtn.classList.toggle('ag-set-list-floating-trigger--active', active);
            this.triggerBtn.setAttribute('aria-pressed', active ? 'true' : 'false');

            return;
        }

        let values = [...this.allValues];

        if (parentModel?.values) {
            values = [...parentModel.values];
        }

        this.selected = new Set(values);

        const active = allCount > 0 && values.length > 0 && values.length < allCount;
        this.triggerBtn.textContent = floatingSelectionLabel(values, this.allValues);
        this.triggerBtn.classList.toggle('ag-set-list-floating-multi-trigger--active', active);
        this.triggerBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
    }
}

export class AgSetListFilter {
    /** @type {AgSetListFilterParams} */
    params = {};

    /** @type {Set<string>} */
    selected = new Set();

    /** @type {string[]} */
    allValues = [];

    /** @type {HTMLElement} */
    eGui;

    /** @type {HTMLInputElement | null} */
    searchInput = null;

    init(params) {
        this.params = params;
        this.allValues = [...(params.values ?? [])];
        if (params.sortValues !== false) {
            this.allValues.sort((a, b) => String(a).localeCompare(String(b), 'ru'));
        }
        this.selected = new Set(this.allValues);
        const compact = Boolean(params.compact);

        this.eGui = document.createElement('div');
        this.eGui.className = compact
            ? 'ag-set-list-filter flex w-44 flex-col gap-2 p-3 text-sm'
            : 'ag-set-list-filter flex max-h-72 w-56 flex-col gap-2 p-3 text-sm';

        if (! compact) {
            const search = document.createElement('input');
            search.type = 'text';
            search.placeholder = params.searchPlaceholder ?? 'Поиск…';
            search.className = 'w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950';
            search.addEventListener('input', () => this.applySearch());
            this.searchInput = search;
            this.eGui.appendChild(search);

            const actions = document.createElement('div');
            actions.className = 'flex gap-2 text-xs';
            const selectAllBtn = document.createElement('button');
            selectAllBtn.type = 'button';
            selectAllBtn.className = 'text-zinc-600 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-400';
            selectAllBtn.textContent = 'Все';
            selectAllBtn.addEventListener('click', () => this.setAll(true));
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'text-zinc-600 underline underline-offset-2 hover:text-zinc-900 dark:text-zinc-400';
            clearBtn.textContent = 'Сброс';
            clearBtn.addEventListener('click', () => this.setAll(false));
            actions.appendChild(selectAllBtn);
            actions.appendChild(clearBtn);
            this.eGui.appendChild(actions);
        }

        this.listEl = document.createElement('div');
        this.listEl.className = 'flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto';
        this.eGui.appendChild(this.listEl);

        this.renderList();
    }

    getGui() {
        return this.eGui;
    }

    isFilterActive() {
        return this.selected.size !== this.allValues.length;
    }

    doesFilterPass(params) {
        if (! this.isFilterActive()) {
            return true;
        }

        if (this.selected.size === 0) {
            return false;
        }

        const value = this.resolveValue(params);

        return this.selected.has(value);
    }

    getModel() {
        if (! this.isFilterActive()) {
            return null;
        }

        return { values: [...this.selected] };
    }

    setModel(model) {
        if (! model?.values) {
            this.selected = new Set(this.allValues);
            this.syncCheckboxes();

            return;
        }

        this.selected = new Set(model.values);
        this.syncCheckboxes();
    }

    /** @param {boolean} checked */
    setAll(checked) {
        this.selected = checked ? new Set(this.allValues) : new Set();
        this.syncCheckboxes();
        this.params.filterChangedCallback();
    }

    applySearch() {
        const query = (this.searchInput?.value ?? '').trim().toLowerCase();
        for (const row of this.listEl.querySelectorAll('[data-set-filter-value]')) {
            const label = row.getAttribute('data-set-filter-value') ?? '';
            row.classList.toggle('hidden', query !== '' && ! label.toLowerCase().includes(query));
        }
    }

    renderList() {
        this.listEl.innerHTML = '';
        for (const value of this.allValues) {
            const label = document.createElement('label');
            label.className = 'flex cursor-pointer items-center gap-2 rounded-lg px-1 py-0.5 hover:bg-zinc-50 dark:hover:bg-zinc-800';
            label.setAttribute('data-set-filter-value', value);

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = this.selected.has(value);
            input.addEventListener('change', () => {
                if (input.checked) {
                    this.selected.add(value);
                } else {
                    this.selected.delete(value);
                }
                this.params.filterChangedCallback();
            });

            const span = document.createElement('span');
            span.textContent = value;

            label.appendChild(input);
            label.appendChild(span);
            this.listEl.appendChild(label);
        }
    }

    syncCheckboxes() {
        for (const label of this.listEl.querySelectorAll('[data-set-filter-value]')) {
            const value = label.getAttribute('data-set-filter-value') ?? '';
            const input = label.querySelector('input[type="checkbox"]');
            if (input) {
                input.checked = this.selected.has(value);
            }
        }
    }

    /** @param {import('ag-grid-community').IDoesFilterPassParams} params */
    resolveValue(params) {
        const colDef = this.params.colDef;
        if (typeof colDef?.filterValueGetter === 'function') {
            return String(colDef.filterValueGetter(params) ?? '—');
        }

        const field = colDef?.field;
        if (field && params.data) {
            const raw = params.data[field];

            return raw === null || raw === undefined || raw === '' ? '—' : String(raw);
        }

        return '—';
    }
}
