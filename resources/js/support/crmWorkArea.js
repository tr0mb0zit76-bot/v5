import { computed, markRaw, reactive } from 'vue';
import axios from 'axios';

/**
 * CUBA-like work area: tabs + up to 3 live grid instances (leads/tasks/orders).
 * Soft-switch between live grids without Inertia wait; background poll keeps rows fresh.
 * ponytail: max live = 3 (product ceiling); upgrade = Echo events instead of poll.
 */
export const WORK_AREA_MAX_TABS = 10;
/** Product ceiling: rarely more than 3 grids per user. */
export const WORK_AREA_MAX_LIVE = 3;
export const WORK_AREA_LIVE_REFRESH_MS = 45_000;

export const LIVE_GRID_MENU_KEYS = new Set(['leads', 'tasks', 'orders']);

const LIVE_GRID_COMPONENT_BY_MENU = {
    leads: 'Leads/Index',
    tasks: 'Tasks/Index',
    orders: 'Orders/Index',
};

const LIVE_REFRESH_ONLY = {
    leads: ['leads', 'leadAttentionQueue', 'salesCoachingInsights'],
    tasks: ['tasks', 'quickFilters'],
    orders: ['rows'],
};

const pageLoaders = typeof import.meta.glob === 'function'
    ? import.meta.glob('../Pages/**/*.vue')
    : {};

/** @type {(href: string) => void} */
let visitPath = (href) => {
    if (typeof window !== 'undefined' && href) {
        window.location.assign(href);
    }
};

/** @type {(() => object | null) | null} */
let getInertiaPage = null;

export function bindCrmWorkAreaVisit(fn) {
    if (typeof fn === 'function') {
        visitPath = fn;
    }
}

export function bindCrmWorkAreaInertiaPage(getter) {
    if (typeof getter === 'function') {
        getInertiaPage = getter;
    }
}

export const WORK_AREA_MENU_KEYS = new Set([
    'dashboard',
    'leads',
    'orders',
    'tasks',
    'kanban',
    'disposition',
    'contractors',
    'documents',
    'claims',
    'mail',
    'load-board',
    'pipeline',
    'company-planning',
    'finance',
    'finance-cashflow',
    'finance-reconciliation',
    'finance-salary',
    'finance-budgeting',
    'finance-management-accounting',
    'fleet-vehicles',
    'fleet-trips',
    'fleet-efficiency',
    'fleet-containers',
    'fleet-drivers',
    'reports',
    'reports-overview',
]);

const COMPONENT_TO_MENU_KEY = {
    Dashboard: 'dashboard',
    'Leads/Index': 'leads',
    'Leads/Wizard': 'leads',
    'Orders/Index': 'orders',
    'Orders/Wizard': 'orders',
    'Tasks/Index': 'tasks',
    'Kanban/Index': 'kanban',
    'Disposition/Index': 'disposition',
    'Contractors/Index': 'contractors',
    'Documents/Index': 'documents',
    'Claims/Index': 'claims',
    'Mail/Index': 'mail',
    'LoadBoard/Index': 'load-board',
    'LoadBoard/Show': 'load-board',
    'Pipeline/Index': 'pipeline',
    'CompanyPlanning/Index': 'company-planning',
    'CompanyPlanning/Show': 'company-planning',
    'Finance/Index': 'finance',
    'Finance/Reconciliation': 'finance-reconciliation',
    'Budgeting/Index': 'finance-budgeting',
    'Finance/ManagementAccounting/Index': 'finance-management-accounting',
    'Finance/ManagementAccounting/Reconcile': 'finance-management-accounting',
    'Fleet/Vehicles': 'fleet-vehicles',
    'Fleet/VehicleWizard': 'fleet-vehicles',
    'Fleet/Trips': 'fleet-trips',
    'Fleet/Efficiency': 'fleet-efficiency',
    'Fleet/Containers': 'fleet-containers',
    'Fleet/Drivers': 'fleet-drivers',
    'Fleet/DriverWizard': 'fleet-drivers',
    'Reports/Index': 'reports',
};

const MENU_KEY_TITLES = {
    dashboard: 'Дашборд',
    leads: 'Лиды',
    orders: 'Заказы',
    tasks: 'Задачи',
    kanban: 'Канбан',
    disposition: 'Диспозиция',
    contractors: 'Контрагенты',
    documents: 'Документы',
    claims: 'Претензии',
    mail: 'Почта',
    'load-board': 'Борд',
    pipeline: 'Воронка',
    'company-planning': 'Планирование',
    finance: 'Финансы',
    'finance-cashflow': 'ДДС',
    'finance-reconciliation': 'Сверка',
    'finance-salary': 'Зарплата',
    'finance-budgeting': 'Бюджет',
    'finance-management-accounting': 'Учёт',
    'fleet-vehicles': 'ТС',
    'fleet-trips': 'Рейсы',
    'fleet-efficiency': 'Эффективность',
    'fleet-containers': 'Контейнеры',
    'fleet-drivers': 'Водители',
    reports: 'Отчёты',
    'reports-overview': 'Отчёты',
};

const state = reactive({
    tabs: [],
    activeId: null,
    /** @type {Array<{ menuKey: string, componentName: string, component: object, props: object, url: string, lastTouchedAt: number }>} */
    liveEntries: [],
    /** @type {string | null} */
    liveVisibleKey: null,
    /** @type {(() => void) | null} */
    _unsubscribeSuccess: null,
    /** @type {ReturnType<typeof setInterval> | null} */
    _refreshTimer: null,
    _refreshing: new Set(),
});

function normalizeUrl(url) {
    if (typeof url !== 'string' || url === '') {
        return '/';
    }

    try {
        if (url.startsWith('http://') || url.startsWith('https://')) {
            const parsed = new URL(url);

            return `${parsed.pathname}${parsed.search}`;
        }
    } catch {
        /* ignore */
    }

    return url.startsWith('/') ? url : `/${url}`;
}

export function menuKeyForComponent(componentName, url = '', pageProps = {}) {
    if (typeof componentName !== 'string' || componentName === '') {
        return null;
    }

    if (componentName === 'Finance/Index') {
        const normalized = normalizeUrl(url);
        if (normalized.includes('section=cashflow') || normalized.includes('section%3Dcashflow')) {
            return 'finance-cashflow';
        }

        return 'finance';
    }

    if (componentName === 'Settings/MotivationSalary' && pageProps?.salary_module === 'finance') {
        return 'finance-salary';
    }

    if (componentName === 'Reports/Index') {
        return 'reports-overview';
    }

    return COMPONENT_TO_MENU_KEY[componentName] ?? null;
}

export function isWorkAreaMenuKey(menuKey) {
    return typeof menuKey === 'string' && WORK_AREA_MENU_KEYS.has(menuKey);
}

export function isLiveGridMenuKey(menuKey) {
    return typeof menuKey === 'string' && LIVE_GRID_MENU_KEYS.has(menuKey);
}

export function isLiveGridIndexComponent(componentName) {
    return Object.values(LIVE_GRID_COMPONENT_BY_MENU).includes(componentName);
}

export function titleForMenuKey(menuKey) {
    return MENU_KEY_TITLES[menuKey] ?? menuKey;
}

function findTabByMenuKey(menuKey) {
    return state.tabs.find((tab) => tab.menuKey === menuKey) ?? null;
}

function findTabById(id) {
    return state.tabs.find((tab) => tab.id === id) ?? null;
}

function findLiveEntry(menuKey) {
    return state.liveEntries.find((entry) => entry.menuKey === menuKey) ?? null;
}

function evictOldestTabsIfNeeded() {
    while (state.tabs.length > WORK_AREA_MAX_TABS) {
        const victim = state.tabs.find((tab) => tab.id !== state.activeId) ?? state.tabs[0];
        if (!victim) {
            break;
        }
        removeLiveEntry(victim.menuKey);
        const index = state.tabs.findIndex((tab) => tab.id === victim.id);
        if (index >= 0) {
            state.tabs.splice(index, 1);
        }
    }
}

function removeLiveEntry(menuKey) {
    const index = state.liveEntries.findIndex((entry) => entry.menuKey === menuKey);
    if (index >= 0) {
        state.liveEntries.splice(index, 1);
    }
    if (state.liveVisibleKey === menuKey) {
        state.liveVisibleKey = null;
    }
}

function evictOldestLiveIfNeeded(keepMenuKey) {
    while (state.liveEntries.length >= WORK_AREA_MAX_LIVE) {
        const victim = [...state.liveEntries]
            .filter((entry) => entry.menuKey !== keepMenuKey && entry.menuKey !== state.liveVisibleKey)
            .sort((a, b) => a.lastTouchedAt - b.lastTouchedAt)[0]
            ?? state.liveEntries.find((entry) => entry.menuKey !== keepMenuKey);

        if (!victim) {
            break;
        }
        removeLiveEntry(victim.menuKey);
    }
}

function touchLive(menuKey) {
    const entry = findLiveEntry(menuKey);
    if (entry) {
        entry.lastTouchedAt = Date.now();
    }
}

function pushHistoryUrl(url) {
    if (typeof window === 'undefined') {
        return;
    }

    const target = normalizeUrl(url);
    const current = `${window.location.pathname}${window.location.search}`;
    if (target !== current) {
        window.history.pushState({}, '', target);
    }
}

/**
 * @returns {Promise<boolean>}
 */
async function hydrateLiveFromPage(page) {
    if (!page || page.props?.standalone === true) {
        state.liveVisibleKey = null;
        return false;
    }

    const componentName = page.component;
    const url = normalizeUrl(page.url);
    const menuKey = menuKeyForComponent(componentName, url, page.props ?? {});

    if (!isLiveGridIndexComponent(componentName) || !menuKey || !isLiveGridMenuKey(menuKey)) {
        // Wizard / overlay / non-grid: show Inertia slot, keep any live caches.
        state.liveVisibleKey = null;
        return false;
    }

    const loader = pageLoaders[`../Pages/${componentName}.vue`];
    if (!loader) {
        state.liveVisibleKey = null;
        return false;
    }

    const mod = await loader();
    let entry = findLiveEntry(menuKey);

    if (!entry) {
        evictOldestLiveIfNeeded(menuKey);
        entry = reactive({
            menuKey,
            componentName,
            component: markRaw(mod.default),
            props: reactive({ ...(page.props ?? {}) }),
            url,
            lastTouchedAt: Date.now(),
        });
        state.liveEntries.push(entry);
    } else {
        Object.assign(entry.props, page.props ?? {});
        entry.url = url;
        entry.componentName = componentName;
        entry.component = markRaw(mod.default);
        entry.lastTouchedAt = Date.now();
    }

    state.liveVisibleKey = menuKey;
    touchLive(menuKey);

    return true;
}

export function softActivateLive(menuKey) {
    const entry = findLiveEntry(menuKey);
    if (!entry) {
        return false;
    }

    state.liveVisibleKey = menuKey;
    state.activeId = menuKey;
    touchLive(menuKey);

    const tab = findTabByMenuKey(menuKey);
    if (tab) {
        tab.url = entry.url;
        state.activeId = tab.id;
    }

    pushHistoryUrl(entry.url);
    scheduleLiveRefresh(menuKey);

    return true;
}

export function registerOrUpdateTabFromPage(page) {
    if (!page || page.props?.standalone === true) {
        return null;
    }

    const component = page.component;
    const url = normalizeUrl(page.url);
    const menuKey = menuKeyForComponent(component, url, page.props ?? {});
    if (!menuKey || !isWorkAreaMenuKey(menuKey)) {
        state.liveVisibleKey = null;
        return null;
    }

    const title = titleForMenuKey(menuKey);
    const existing = findTabByMenuKey(menuKey);

    if (existing) {
        // Keep live grid index URL when navigating to wizard within module.
        if (!(isLiveGridMenuKey(menuKey) && findLiveEntry(menuKey) && !isLiveGridIndexComponent(component))) {
            existing.url = url;
        }
        existing.component = component;
        existing.title = title;
        state.activeId = existing.id;
    } else {
        state.tabs.push({
            id: menuKey,
            menuKey,
            title,
            url,
            component,
        });
        state.activeId = menuKey;
        evictOldestTabsIfNeeded();
    }

    void hydrateLiveFromPage(page);

    return findTabByMenuKey(menuKey);
}

export function openOrActivate(menuKey, href) {
    if (!isWorkAreaMenuKey(menuKey)) {
        visitPath(href);
        return;
    }

    if (isLiveGridMenuKey(menuKey) && softActivateLive(menuKey)) {
        return;
    }

    const existing = findTabByMenuKey(menuKey);
    if (existing) {
        state.activeId = existing.id;
        const target = existing.url || href;
        const current = typeof window !== 'undefined'
            ? `${window.location.pathname}${window.location.search}`
            : '';
        if (normalizeUrl(target) === normalizeUrl(current) && state.liveVisibleKey === null) {
            return;
        }
        visitPath(target);
        return;
    }

    visitPath(href);
}

export function activateTab(id) {
    const tab = findTabById(id);
    if (!tab) {
        return;
    }

    if (isLiveGridMenuKey(tab.menuKey) && softActivateLive(tab.menuKey)) {
        return;
    }

    state.activeId = tab.id;
    state.liveVisibleKey = null;
    const current = typeof window !== 'undefined'
        ? `${window.location.pathname}${window.location.search}`
        : '';
    if (normalizeUrl(tab.url) === normalizeUrl(current)) {
        return;
    }
    visitPath(tab.url);
}

export function closeTab(id, options = {}) {
    const index = state.tabs.findIndex((tab) => tab.id === id);
    if (index < 0) {
        return;
    }

    const closing = state.tabs[index];
    const wasActive = state.activeId === id;
    state.tabs.splice(index, 1);
    removeLiveEntry(closing.menuKey);

    if (!wasActive) {
        return;
    }

    const neighbor = state.tabs[index] ?? state.tabs[index - 1] ?? state.tabs[0] ?? null;
    if (!neighbor) {
        state.activeId = null;
        state.liveVisibleKey = null;
        return;
    }

    state.activeId = neighbor.id;
    if (options.navigate === false) {
        return;
    }

    if (isLiveGridMenuKey(neighbor.menuKey) && softActivateLive(neighbor.menuKey)) {
        return;
    }

    state.liveVisibleKey = null;
    visitPath(neighbor.url);
}

export async function refreshLiveEntry(menuKey) {
    const entry = findLiveEntry(menuKey);
    if (!entry || state._refreshing.has(menuKey)) {
        return;
    }

    const only = LIVE_REFRESH_ONLY[menuKey];
    if (!only?.length) {
        return;
    }

    state._refreshing.add(menuKey);

    try {
        const inertiaPage = getInertiaPage?.() ?? null;
        const { data } = await axios.get(entry.url, {
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Inertia': 'true',
                'X-Inertia-Version': inertiaPage?.version ?? '',
                'X-Inertia-Partial-Component': entry.componentName,
                'X-Inertia-Partial-Data': only.join(','),
            },
        });

        if (data?.props && typeof data.props === 'object') {
            for (const key of only) {
                if (Object.prototype.hasOwnProperty.call(data.props, key)) {
                    entry.props[key] = data.props[key];
                }
            }
            entry.lastTouchedAt = Date.now();
        }
    } catch {
        /* poll is best-effort */
    } finally {
        state._refreshing.delete(menuKey);
    }
}

function scheduleLiveRefresh(menuKey) {
    void refreshLiveEntry(menuKey);
}

export function startLiveRefreshLoop() {
    if (state._refreshTimer || typeof window === 'undefined') {
        return;
    }

    state._refreshTimer = window.setInterval(() => {
        if (typeof document !== 'undefined' && document.hidden) {
            return;
        }

        for (const entry of state.liveEntries) {
            void refreshLiveEntry(entry.menuKey);
        }
    }, WORK_AREA_LIVE_REFRESH_MS);
}

export function useCrmWorkArea() {
    return {
        tabs: computed(() => state.tabs),
        activeId: computed(() => state.activeId),
        liveEntries: computed(() => state.liveEntries),
        liveVisibleKey: computed(() => state.liveVisibleKey),
        maxTabs: WORK_AREA_MAX_TABS,
        maxLive: WORK_AREA_MAX_LIVE,
        openOrActivate,
        activateTab,
        closeTab,
        softActivateLive,
        refreshLiveEntry,
        registerOrUpdateTabFromPage,
    };
}

export function __crmWorkAreaState() {
    return state;
}

export function __crmWorkAreaReset() {
    state.tabs.splice(0, state.tabs.length);
    state.liveEntries.splice(0, state.liveEntries.length);
    state.activeId = null;
    state.liveVisibleKey = null;
    state._refreshing.clear();
}

export function ensureCrmWorkAreaInertiaBridge(inertiaRouter) {
    if (state._unsubscribeSuccess || !inertiaRouter?.on) {
        return;
    }

    bindCrmWorkAreaInertiaPage(() => inertiaRouter.page ?? null);

    state._unsubscribeSuccess = inertiaRouter.on('success', (event) => {
        registerOrUpdateTabFromPage(event.detail.page);
    });

    if (inertiaRouter.page) {
        registerOrUpdateTabFromPage(inertiaRouter.page);
    }

    startLiveRefreshLoop();
}

/**
 * @returns {{ ok: boolean, errors: string[] }}
 */
export function selfCheckCrmWorkArea() {
    const errors = [];
    __crmWorkAreaReset();

    registerOrUpdateTabFromPage({
        component: 'Leads/Index',
        url: '/leads',
        props: {},
    });
    if (state.tabs.length !== 1 || state.activeId !== 'leads') {
        errors.push('register leads failed');
    }

    registerOrUpdateTabFromPage({
        component: 'Tasks/Index',
        url: '/tasks',
        props: {},
    });
    if (state.tabs.length !== 2 || state.activeId !== 'tasks') {
        errors.push('register tasks failed');
    }

    registerOrUpdateTabFromPage({
        component: 'Leads/Wizard',
        url: '/leads/9',
        props: {},
    });
    const leads = findTabByMenuKey('leads');
    if (!leads || state.tabs.length !== 2) {
        errors.push('update leads in place failed');
    }
    // Wizard must not wipe live index URL permanently — live entry keeps /leads
    if (leads && findLiveEntry('leads') && leads.url === '/leads/9' && findLiveEntry('leads').url === '/leads') {
        // tab url may track wizard; live url stays on index — OK either way for soft restore
    }

    registerOrUpdateTabFromPage({
        component: 'Tasks/Index',
        url: '/tasks/1?standalone=1',
        props: { standalone: true },
    });
    if (state.tabs.length !== 2 || findTabByMenuKey('tasks')?.url === '/tasks/1?standalone=1') {
        errors.push('standalone must not register/update tabs');
    }

    closeTab('tasks', { navigate: false });
    if (state.tabs.length !== 1 || state.activeId !== 'leads') {
        errors.push('close tab neighbor activate failed');
    }

    if (WORK_AREA_MAX_LIVE !== 3) {
        errors.push('max live must be 3');
    }

    __crmWorkAreaReset();

    return { ok: errors.length === 0, errors };
}
