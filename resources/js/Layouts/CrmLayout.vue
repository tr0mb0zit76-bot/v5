<template>
    <div class="flex h-screen overflow-hidden bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <aside
            class="flex flex-col border-r border-zinc-200 bg-zinc-50 transition-all duration-300 dark:border-zinc-800 dark:bg-zinc-950"
            :class="collapsed ? 'w-20' : 'w-64'"
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

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <main class="flex-1 min-h-0 overflow-hidden p-3 md:p-4">
                <slot />
            </main>

            <footer class="shrink-0 border-t border-zinc-200 bg-zinc-50/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
                <div class="px-3 py-3 md:px-4">
                    <CrmCommandBar @submit="handleAiSubmit" />
                </div>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    ChevronDown,
    FileText,
    LayoutDashboard,
    LogOut,
    Package,
    PanelLeftClose,
    PanelLeftOpen,
    Puzzle,
    Settings,
    Truck,
    Users,
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
const menuStateStorageKey = 'crm-sidebar-expanded-groups';

const authUser = computed(() => page.props.auth?.user ?? null);
const visibleAreas = computed(() => authUser.value?.role?.visibility_areas ?? ['dashboard']);

const menuItems = computed(() => {
    const items = [
        { key: 'dashboard', label: 'Дашборд', icon: LayoutDashboard },
        { key: 'orders', label: 'Заказы', icon: Package, visibilityArea: 'orders' },
        { key: 'contractors', label: 'Контрагенты', icon: Users, visibilityArea: 'contractors' },
        { key: 'drivers', label: 'Водители', icon: Truck, visibilityArea: 'drivers' },
        { key: 'documents', label: 'Документы', icon: FileText, visibilityArea: 'documents' },
        { key: 'activities', label: 'Активности', icon: Activity, visibilityArea: 'activities' },
        { key: 'reports', label: 'Отчёты', icon: BarChart3, visibilityArea: 'reports' },
        { key: 'modules', label: 'Модули', icon: Puzzle, visibilityArea: 'modules' },
        {
            key: 'settings',
            label: 'Настройки',
            icon: Settings,
            visibilityArea: 'settings',
            children: authUser.value?.role?.name === 'admin'
                ? [
                    { key: 'users', label: 'Пользователи' },
                    { key: 'roles', label: 'Роли' },
                    { key: 'table-presets', label: 'Управление таблицей' },
                    {
                        key: 'motivation',
                        label: 'Мотивация',
                        children: [
                            { key: 'kpi-settings', label: 'Настройки KPI' },
                        ],
                    },
                ]
                : [],
        },
    ];

    return items.filter((item) => {
        if (authUser.value?.role?.name === 'admin') {
            return true;
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
    },
    { immediate: true },
);

watch(
    () => props.activeSubKey,
    (value) => {
        if (value === 'motivation' && !expandedGroups.value.includes('motivation')) {
            expandedGroups.value = [...expandedGroups.value, 'motivation'];
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

onMounted(() => {
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

    if (props.activeSubKey === 'motivation' && !expandedGroups.value.includes('motivation')) {
        expandedGroups.value = [...expandedGroups.value, 'motivation'];
    }
});

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
        orders: '/orders',
        contractors: '/contractors',
        drivers: '/drivers',
        documents: '/documents',
        activities: '/activities',
        reports: '/reports',
        modules: '/modules',
        settings: '/settings',
        users: '/settings/users',
        roles: '/settings/roles',
        'table-presets': '/settings/tables',
        'kpi-settings': '/settings/motivation/kpi',
    };

    if (key === 'settings' || key === 'motivation') {
        toggleMenuGroup(key);
    }

    if (routes[key]) {
        router.visit(routes[key]);
    }
}

function handleAiSubmit(payload) {
    console.log('AI submit:', payload);
}
</script>
