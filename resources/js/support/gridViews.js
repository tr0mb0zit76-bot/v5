/** Shared saved grid views (filters, columns, quick search). */

function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token ?? '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

/**
 * fetch без follow redirect: иначе DELETE/PATCH после 302 на /login бьёт login методом DELETE.
 */
async function gridViewsFetch(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        redirect: 'manual',
        ...options,
        headers: {
            ...csrfHeaders(),
            ...(options.headers ?? {}),
        },
    });

    if (
        response.status === 401
        || response.status === 419
        || (response.status >= 300 && response.status < 400)
    ) {
        const location = response.headers.get('Location');
        const target = location && location !== ''
            ? (location.startsWith('http') ? location : new URL(location, window.location.origin).href)
            : '/login';

        window.location.assign(target);

        throw new Error('grid_views_auth_required');
    }

    return response;
}

export function gridViewsApiUrl(gridKey) {
    return `/grid-views?grid_key=${encodeURIComponent(gridKey)}`;
}

export async function fetchGridViews(gridKey) {
    try {
        const response = await gridViewsFetch(gridViewsApiUrl(gridKey), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return { views: [], can_share: false };
        }

        return response.json();
    } catch (error) {
        if (error instanceof Error && error.message === 'grid_views_auth_required') {
            throw error;
        }

        return { views: [], can_share: false };
    }
}

export async function fetchGridView(viewId) {
    try {
        const response = await gridViewsFetch(`/grid-views/${viewId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return null;
        }

        const data = await response.json();

        return data.view ?? null;
    } catch (error) {
        if (error instanceof Error && error.message === 'grid_views_auth_required') {
            throw error;
        }

        return null;
    }
}

export async function createGridView(payload) {
    const response = await gridViewsFetch('/grid-views', {
        method: 'POST',
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error('create_failed');
    }

    const data = await response.json();

    return data.view;
}

export async function updateGridView(viewId, payload) {
    const response = await gridViewsFetch(`/grid-views/${viewId}`, {
        method: 'PATCH',
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error('update_failed');
    }

    const data = await response.json();

    return data.view;
}

export async function deleteGridView(viewId) {
    const response = await gridViewsFetch(`/grid-views/${viewId}`, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    return response.ok;
}

export function readViewIdFromUrl() {
    try {
        const params = new URLSearchParams(window.location.search);
        const raw = params.get('view');

        if (raw === null || raw.trim() === '') {
            return null;
        }

        const id = Number.parseInt(raw, 10);

        return Number.isFinite(id) && id > 0 ? id : null;
    } catch {
        return null;
    }
}

export function writeViewIdToUrl(viewId) {
    const url = new URL(window.location.href);

    if (viewId) {
        url.searchParams.set('view', String(viewId));
    } else {
        url.searchParams.delete('view');
    }

    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
}

/**
 * @param {{
 *   columnStorageKey?: string|null,
 *   filterStorageKey?: string|null,
 *   quickSearchStorageKey?: string|null,
 *   column_state?: array|null,
 *   filter_state?: object|null,
 *   quick_search?: string|null,
 * }} view
 */
export function persistViewToLocalStorage(view, keys, options = {}) {
    if (keys.columnStorageKey && Array.isArray(view.column_state) && view.column_state.length > 0) {
        localStorage.setItem(keys.columnStorageKey, JSON.stringify(view.column_state));
    }

    if (keys.filterStorageKey) {
        localStorage.setItem(keys.filterStorageKey, JSON.stringify(view.filter_state ?? {}));
    }

    if (keys.quickSearchStorageKey) {
        const quickSearch = view.quick_search ?? '';

        if (options.quickSearchJsonWrapper) {
            localStorage.setItem(keys.quickSearchStorageKey, JSON.stringify({ quickSearch }));
        } else {
            localStorage.setItem(keys.quickSearchStorageKey, quickSearch);
        }
    }
}

export function captureGridStateFromApi(gridApi) {
    if (!gridApi) {
        return {
            column_state: [],
            filter_state: {},
        };
    }

    const column_state = gridApi.getColumnState().map((column, index) => ({
        colId: column.colId,
        hide: column.hide,
        width: column.width,
        order: index,
        sort: column.sort ?? null,
        sortIndex: column.sortIndex ?? null,
    }));

    return {
        column_state,
        filter_state: gridApi.getFilterModel() ?? {},
    };
}
