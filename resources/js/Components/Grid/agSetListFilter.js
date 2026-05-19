/**
 * Простой фильтр-список значений (чекбоксы) для AG Grid Community.
 *
 * @typedef {object} AgSetListFilterParams
 * @property {string[]} values
 * @property {boolean} [sortValues]
 * @property {string} [searchPlaceholder]
 */

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

        this.eGui = document.createElement('div');
        this.eGui.className = 'ag-set-list-filter flex max-h-72 w-56 flex-col gap-2 p-3 text-sm';

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
        if (!this.isFilterActive()) {
            return true;
        }

        const value = this.resolveValue(params);

        return this.selected.has(value);
    }

    getModel() {
        if (!this.isFilterActive()) {
            return null;
        }

        return { values: [...this.selected] };
    }

    setModel(model) {
        if (!model?.values) {
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
            row.classList.toggle('hidden', query !== '' && !label.toLowerCase().includes(query));
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
