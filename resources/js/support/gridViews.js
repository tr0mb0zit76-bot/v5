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

/** Явный сброс к пресету роли — не поднимать прошлое представление при следующем заходе. */
export const GRID_VIEW_LAST_NONE = 'none';

export function lastGridViewStorageKey(gridKey, userId) {
    return `grid_view_last_${gridKey}_${userId}`;
}

/**
 * Последнее применённое представление грида (id) или null, если не задано / сброшено к дефолту.
 *
 * @returns {number|null}
 */
export function readLastGridViewId(gridKey, userId) {
    if (typeof window === 'undefined' || !gridKey || userId === null || userId === undefined || userId === '') {
        return null;
    }

    try {
        const raw = localStorage.getItem(lastGridViewStorageKey(gridKey, userId));

        if (raw === null || raw === '' || raw === GRID_VIEW_LAST_NONE) {
            return null;
        }

        const id = Number.parseInt(raw, 10);

        return Number.isFinite(id) && id > 0 ? id : null;
    } catch {
        return null;
    }
}

/**
 * @param {string} gridKey
 * @param {string|number} userId
 * @param {number|null} viewId — null = запомнить «по умолчанию (роль)»
 */
export function writeLastGridViewId(gridKey, userId, viewId) {
    if (typeof window === 'undefined' || !gridKey || userId === null || userId === undefined || userId === '') {
        return;
    }

    try {
        const key = lastGridViewStorageKey(gridKey, userId);

        if (viewId === null || viewId === undefined) {
            localStorage.setItem(key, GRID_VIEW_LAST_NONE);

            return;
        }

        localStorage.setItem(key, String(viewId));
    } catch {
        // ignore quota / private mode
    }
}

/**
 * @param {() => unknown} getGridApi
 * @param {number} [timeoutMs]
 * @returns {Promise<object|null>}
 */
export async function waitForGridApi(getGridApi, timeoutMs = 4000) {
    const started = Date.now();

    while (Date.now() - started < timeoutMs) {
        const api = typeof getGridApi === 'function' ? getGridApi() : null;

        if (api) {
            return api;
        }

        await new Promise((resolve) => setTimeout(resolve, 40));
    }

    return typeof getGridApi === 'function' ? (getGridApi() ?? null) : null;
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
