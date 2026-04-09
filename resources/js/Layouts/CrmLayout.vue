<template>
    <div class="flex min-h-dvh overflow-hidden bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <div
            v-if="showMobileAppGate"
            class="fixed inset-0 z-[70] flex min-h-dvh items-center justify-center bg-zinc-950 px-4 py-6 text-zinc-50 lg:hidden"
        >
            <div class="w-full max-w-sm space-y-5 rounded-3xl border border-zinc-800 bg-zinc-900/95 p-6 shadow-2xl">
                <div class="space-y-3 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-zinc-900">
                        <Smartphone class="h-7 w-7" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold">Откройте кабинет через приложение</h1>
                        <p class="mt-2 text-sm text-zinc-400">
                            Мобильный браузер для CRM будет отключён. Установите PWA-приложение и работайте через него.
                        </p>
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl border border-zinc-800 bg-zinc-950/60 p-4 text-sm text-zinc-300">
                    <div class="font-medium text-zinc-100">Что будет в приложении</div>
                    <div>Заказы, контрагенты, отчёты, счета и AI-чат в упрощённом мобильном интерфейсе.</div>
                </div>

                <div class="space-y-3">
                    <button
                        v-if="canInstallApp"
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-medium text-zinc-900 transition hover:bg-zinc-200"
                        @click="triggerInstallPrompt"
                    >
                        <Download class="h-4 w-4" />
                        Установить приложение
                    </button>

                    <div v-else class="rounded-2xl border border-zinc-800 bg-zinc-950/60 p-4 text-sm text-zinc-300">
                        Установка доступна из меню браузера:
                        <span class="font-medium text-zinc-100">Добавить на главный экран</span>
                        или
                        <span class="font-medium text-zinc-100">Установить приложение</span>.
                    </div>

                    <a
                        href="/"
                        class="flex w-full items-center justify-center rounded-2xl border border-zinc-700 px-4 py-3 text-sm font-medium text-zinc-200 transition hover:bg-zinc-800"
                    >
                        Вернуться на сайт
                    </a>
                </div>
            </div>
        </div>

        <div
            v-else-if="showMobileAppShell"
            class="flex min-h-dvh w-full flex-col overflow-hidden bg-zinc-50 dark:bg-zinc-950 lg:hidden"
        >
            <header class="shrink-0 border-b border-zinc-200 bg-zinc-50/95 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-base font-semibold text-zinc-900 dark:text-zinc-50">Logist CRM</div>
                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ authUser?.name || 'Пользователь' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <ThemeToggle />
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex h-10 w-10 items-center justify-center rounded-2xl border border-zinc-200 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        >
                            <LogOut class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto bg-zinc-50 px-4 py-4 dark:bg-zinc-950" scroll-region>
                <slot />
            </main>

            <nav class="shrink-0 border-t border-zinc-200 bg-white/95 px-2 py-2 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
                <div class="grid grid-cols-6 gap-2">
                    <button
                        v-for="item in mobileNavItems"
                        :key="item.key"
                        type="button"
                        class="flex flex-col items-center justify-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-medium transition-colors"
                        :class="activeKey === item.key
                            ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                            : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="handleMenuSelect(item.key)"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        <span class="truncate">{{ item.label }}</span>
                    </button>
                </div>
            </nav>
        </div>

        <div
            v-if="!showMobileAppShell && mobileMenuOpen"
            class="fixed inset-0 z-40 bg-zinc-950/50 lg:hidden"
            @click="mobileMenuOpen = false"
        />

        <aside
            v-if="!showMobileAppShell"
            class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-zinc-200 bg-zinc-50 transition-all duration-300 dark:border-zinc-800 dark:bg-zinc-950"
            :class="[
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                collapsed ? 'w-20' : 'w-64',
            ]"
        >
            <div class="flex h-16 items-center justify-between gap-2 border-b border-zinc-200 px-4 dark:border-zinc-800">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        v-if="!collapsed"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-zinc-900 font-semibold text-white dark:bg-white dark:text-zinc-900"
                    >
                        V5
                    </div>

                    <div v-if="!collapsed" class="min-w-0">
                        <div class="truncate font-semibold">Logist CRM</div>
                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">AI-first workspace</div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <ThemeToggle v-if="!collapsed" />

                    <button
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        @click="collapsed = !collapsed"
                    >
                        <PanelLeftClose v-if="!collapsed" class="h-4 w-4" />
                        <PanelLeftOpen v-else class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-2">
                <div v-for="item in menuItems" :key="item.key" class="space-y-1">
                    <div class="flex items-center gap-1">
                        <button
                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-3 py-2 transition-colors"
                            :class="activeKey === item.key
                                ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                            @click="handleMenuSelect(item.key)"
                        >
                            <component :is="item.icon" class="h-5 w-5 shrink-0" />
                            <span v-if="!collapsed" class="truncate text-sm font-medium">{{ item.label }}</span>
                        </button>

                        <button
                            v-if="!collapsed && item.children?.length"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            @click="toggleMenuGroup(item.key)"
                        >
                            <ChevronDown class="h-4 w-4 transition-transform" :class="isMenuGroupOpen(item.key) ? 'rotate-180' : ''" />
                        </button>
                    </div>

                    <div
                        v-if="!collapsed && item.children?.length && isMenuGroupOpen(item.key)"
                        class="ml-4 space-y-1 border-l border-zinc-200 pl-3 dark:border-zinc-800"
                    >
                        <div v-for="child in item.children" :key="child.key" class="space-y-1">
                            <div class="flex items-center gap-1">
                                <button
                                    class="flex min-w-0 flex-1 items-center rounded-lg px-3 py-2 text-left text-sm transition-colors"
                                    :class="isSettingsChildActive(child)
                                        ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                        : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                                    @click="handleMenuSelect(child.key)"
                                >
                                    {{ child.label }}
                                </button>

                                <button
                                    v-if="child.children?.length"
                                    type="button"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                    @click="toggleMenuGroup(child.key)"
                                >
                                    <ChevronDown class="h-4 w-4 transition-transform" :class="isMenuGroupOpen(child.key) ? 'rotate-180' : ''" />
                                </button>
                            </div>

                            <div
                                v-if="child.children?.length && isMenuGroupOpen(child.key)"
                                class="ml-3 space-y-1 border-l border-zinc-200 pl-3 dark:border-zinc-800"
                            >
                                <button
                                    v-for="grandChild in child.children"
                                    :key="grandChild.key"
                                    class="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition-colors"
                                    :class="activeLeafKey === grandChild.key
                                        ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                        : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                                    @click="handleMenuSelect(grandChild.key)"
                                >
                                    {{ grandChild.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
                <div v-if="!collapsed" class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 font-medium dark:bg-zinc-800">
                        {{ authUser?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ authUser?.name || 'Пользователь' }}</div>
                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ authUser?.email || '' }}</div>
                    </div>
                </div>
                <div v-else class="flex justify-center">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-sm font-medium dark:bg-zinc-800">
                        {{ authUser?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                </div>

                <div class="mt-3">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        :class="collapsed ? 'px-2' : ''"
                    >
                        <LogOut class="h-4 w-4 shrink-0" />
                        <span v-if="!collapsed">Выйти</span>
                    </Link>
                </div>
            </div>
        </aside>

        <div v-if="!showMobileAppShell" :class="[collapsed ? 'lg:pl-20' : 'lg:pl-64', 'flex min-h-0 min-w-0 flex-1 flex-col']">
            <header class="flex items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950 lg:hidden">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="mobileMenuOpen = true"
                >
                    <Menu class="h-5 w-5" />
                </button>

                <div class="min-w-0 flex-1 text-center">
                    <div class="truncate text-sm font-semibold">Logist CRM</div>
                </div>

                <ThemeToggle />
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto p-3 md:p-4 pb-[120px] md:pb-[140px]">
                <slot />
            </main>

            <footer
                class="fixed bottom-0 left-0 right-0 z-50 shrink-0 border-t border-zinc-200 bg-zinc-50/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95 transition-[left]"
                :class="collapsed ? 'lg:left-20' : 'lg:left-64'"
            >
                <div class="px-3 py-3 md:px-4">
                    <CrmCommandBar @submit="handleAiSubmit" />
                </div>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    ChevronDown,
    ClipboardList,
    Download,
    House,
    Kanban,
    LayoutDashboard,
    LogOut,
    Menu,
    Package,
    PanelLeftClose,
    PanelLeftOpen,
    Puzzle,
    SquarePen,
    Settings,
    Smartphone,
    Target,
    Truck,
    Users,
    Wallet,
} from 'lucide-vue-next';
import CrmCommandBar from '@/Components/Layout/CrmCommandBar.vue';
import ThemeToggle from '@/Components/Layout/ThemeToggle.vue';

const props = defineProps({
    activeKey: {
        type: String,
        default: 'dashboard',
    },
    activeSubKey: {
        type: String,
        default: null,
    },
    activeLeafKey: {
        type: String,
        default: null,
    },
});

const page = usePage();
const collapsed = ref(false);
const expandedGroups = ref([]);
const mobileMenuOpen = ref(false);
const deferredInstallPrompt = ref(null);
const isStandaloneApp = ref(false);
const isMobileViewport = ref(false);
const menuStateStorageKey = 'crm-sidebar-expanded-groups';

const authUser = computed(() => page.props.auth?.user ?? null);
const visibleAreas = computed(() => authUser.value?.role?.visibility_areas ?? ['dashboard']);
const hasLegacyAllSettingsAccess = computed(() => {
    const areas = visibleAreas.value;
    return areas.includes('settings') && !areas.includes('settings_system') && !areas.includes('settings_motivation');
});
const hasSettingsSystemAccess = computed(() => {
    const areas = visibleAreas.value;
    return hasLegacyAllSettingsAccess.value || areas.includes('settings_system');
});
const hasSettingsMotivationAccess = computed(() => {
    const areas = visibleAreas.value;
    return hasLegacyAllSettingsAccess.value || areas.includes('settings_motivation');
});
const hasFinanceSalaryAccess = computed(() => visibleAreas.value.includes('finance_salary'));
const showMobileAppGate = computed(() => isMobileViewport.value && !isStandaloneApp.value);
const showMobileAppShell = computed(() => isMobileViewport.value && isStandaloneApp.value);
const canInstallApp = computed(() => deferredInstallPrompt.value !== null);
const mobileNavItems = computed(() => [
    { key: 'dashboard', label: 'Главная', icon: House },
    { key: 'orders', label: 'Заказы', icon: Package },
    { key: 'tasks', label: 'Задачи', icon: ClipboardList },
    { key: 'kanban', label: 'Канбан', icon: Kanban },
    { key: 'finance', label: 'Финансы', icon: Wallet },
    { key: 'orders-create', label: 'Новый', icon: SquarePen },
    { key: 'contractors', label: 'База', icon: Users },
    { key: 'reports', label: 'Отчёты', icon: BarChart3 },
]);

const menuItems = computed(() => {
    const items = [
        { key: 'dashboard', label: 'Дашборд', icon: LayoutDashboard },
        { key: 'leads', label: 'Лиды', icon: Target, visibilityArea: 'leads' },
        { key: 'orders', label: 'Заказы', icon: Package, visibilityArea: 'orders' },
        { key: 'contractors', label: 'Контрагенты', icon: Users, visibilityArea: 'contractors' },
        { key: 'drivers', label: 'Водители', icon: Truck, visibilityArea: 'drivers' },
        {
            key: 'finance',
            label: 'Финансы',
            icon: Wallet,
            visibilityArea: 'documents',
            children: (() => {
                const children = [];

                if (visibleAreas.value.includes('documents')) {
                    children.push(
                        { key: 'finance-documents', label: 'Документы' },
                        { key: 'finance-dds', label: 'ДДС' },
                    );
                }

                if (hasFinanceSalaryAccess.value) {
                    children.push({ key: 'finance-salary', label: 'Зарплата' });
                }

                return children;
            })(),
        },
        { key: 'activities', label: 'Активности', icon: Activity, visibilityArea: 'activities' },
        { key: 'tasks', label: 'Задачи', icon: ClipboardList, visibilityArea: 'tasks' },
        { key: 'kanban', label: 'Канбан', icon: Kanban, visibilityArea: 'kanban' },
        { key: 'reports', label: 'Отчёты', icon: BarChart3, visibilityArea: 'reports' },
        { key: 'modules', label: 'Модули', icon: Puzzle, visibilityArea: 'modules' },
        {
            key: 'settings',
            label: 'Настройки',
            icon: Settings,
            children: (() => {
                const children = [];
                const administrationChildren = [];
                if (hasSettingsSystemAccess.value) {
                    administrationChildren.push({ key: 'users', label: 'Пользователи' });
                }
                if (authUser.value?.role?.name === 'admin') {
                    administrationChildren.push({ key: 'roles', label: 'Роли' });
                }
                if (administrationChildren.length > 0) {
                    children.push({
                        key: 'administration',
                        label: 'Администрирование',
                        children: administrationChildren,
                    });
                }
                if (hasSettingsSystemAccess.value) {
                    children.push({
                        key: 'configuration',
                        label: 'Конфигурация',
                        children: [
                            { key: 'table-presets', label: 'Управление таблицей' },
                            { key: 'dictionaries', label: 'Справочники' },
                            { key: 'templates', label: 'Шаблоны' },
                        ],
                    });
                }
                if (hasSettingsMotivationAccess.value) {
                    children.push({
                        key: 'motivation',
                        label: 'Мотивация',
                        children: [
                            { key: 'kpi-settings', label: 'Настройки KPI' },
                            { key: 'salary-settings', label: 'Условия' },
                        ],
                    });
                }
                return children;
            })(),
        },
    ];

    return items.filter((item) => {
        if (authUser.value?.role?.name === 'admin') {
            return true;
        }

        if (item.key === 'settings') {
            return hasSettingsSystemAccess.value || hasSettingsMotivationAccess.value;
        }

        if (item.key === 'finance') {
            return (item.children?.length ?? 0) > 0;
        }

        if (!item.visibilityArea) {
            return true;
        }

        return visibleAreas.value.includes(item.visibilityArea);
    });
});

watch(
    () => props.activeKey,
    (value) => {
        if (value === 'settings' && !expandedGroups.value.includes('settings')) {
            expandedGroups.value = [...expandedGroups.value, 'settings'];
        }
        if (value === 'finance' && !expandedGroups.value.includes('finance')) {
            expandedGroups.value = [...expandedGroups.value, 'finance'];
        }
    },
    { immediate: true },
);

watch(
    () => props.activeSubKey,
    (value) => {
        if (value && !expandedGroups.value.includes(value)) {
            expandedGroups.value = [...expandedGroups.value, value];
        }
    },
    { immediate: true },
);

watch(
    expandedGroups,
    (value) => {
        localStorage.setItem(menuStateStorageKey, JSON.stringify(value));
    },
    { deep: true },
);

watch(
    mobileMenuOpen,
    (value) => {
        document.body.classList.toggle('overflow-hidden', value);
    },
);

watch(
    showMobileAppGate,
    (value) => {
        if (value) {
            mobileMenuOpen.value = false;
        }
    },
    { immediate: true },
);

onMounted(() => {
    updateMobileEnvironment();

    try {
        const savedState = localStorage.getItem(menuStateStorageKey);

        if (!savedState) {
            return;
        }

        const parsedState = JSON.parse(savedState);

        if (Array.isArray(parsedState)) {
            expandedGroups.value = parsedState.filter((item) => typeof item === 'string');
        }
    } catch (error) {
        console.error('Failed to restore CRM sidebar state', error);
    }

    if (props.activeKey === 'settings' && !expandedGroups.value.includes('settings')) {
        expandedGroups.value = [...expandedGroups.value, 'settings'];
    }

    if (props.activeSubKey && !expandedGroups.value.includes(props.activeSubKey)) {
        expandedGroups.value = [...expandedGroups.value, props.activeSubKey];
    }

    window.addEventListener('resize', updateMobileEnvironment);
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);
});

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('resize', updateMobileEnvironment);
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.removeEventListener('appinstalled', handleAppInstalled);
});

function updateMobileEnvironment() {
    isMobileViewport.value = window.matchMedia('(max-width: 1023px)').matches;
    isStandaloneApp.value = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function handleBeforeInstallPrompt(event) {
    event.preventDefault();
    deferredInstallPrompt.value = event;
}

function handleAppInstalled() {
    deferredInstallPrompt.value = null;
    updateMobileEnvironment();
}

async function triggerInstallPrompt() {
    if (!deferredInstallPrompt.value) {
        return;
    }

    deferredInstallPrompt.value.prompt();
    await deferredInstallPrompt.value.userChoice;
    deferredInstallPrompt.value = null;
}

function isMenuGroupOpen(key) {
    return expandedGroups.value.includes(key);
}

function toggleMenuGroup(key) {
    expandedGroups.value = isMenuGroupOpen(key)
        ? expandedGroups.value.filter((item) => item !== key)
        : [...expandedGroups.value, key];
}

function isSettingsChildActive(child) {
    if (props.activeSubKey === child.key) {
        return true;
    }

    return child.children?.some((grandChild) => grandChild.key === props.activeLeafKey) ?? false;
}

function handleMenuSelect(key) {
        const routes = {
            dashboard: '/dashboard',
            leads: '/leads',
            orders: '/orders',
            tasks: '/tasks',
            kanban: '/kanban',
            'orders-create': '/orders/create',
            contractors: '/contractors',
            drivers: '/drivers',
            finance: '/finance',
            'finance-documents': '/finance?section=documents',
            'finance-dds': '/finance?section=dds',
            'finance-salary': '/finance/salary',
        activities: '/activities',
        reports: '/reports',
        modules: '/modules',
        settings: '/settings',
        users: '/settings/users',
        roles: '/settings/roles',
        'table-presets': '/settings/tables',
        dictionaries: '/settings/dictionaries',
        templates: '/settings/templates',
        motivation: '/settings/motivation',
        'kpi-settings': '/settings/motivation/kpi',
        'salary-settings': '/settings/motivation/salary',
    };

    if (['settings', 'administration', 'configuration', 'motivation', 'finance'].includes(key)) {
        toggleMenuGroup(key);
    }

    if (routes[key]) {
        mobileMenuOpen.value = false;
        router.visit(routes[key]);
    }
}

function handleAiSubmit(payload) {
    console.log('AI submit:', payload);
}
</script>
