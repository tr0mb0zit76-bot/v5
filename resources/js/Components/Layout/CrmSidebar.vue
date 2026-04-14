<template>
    <aside
        :class="[
            'h-screen border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 transition-all duration-200 flex flex-col',
            collapsed ? 'w-20' : 'w-72',
        ]"
    >
        <div class="h-16 px-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-10 w-10 rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 flex items-center justify-center font-semibold">
                    V5
                </div>

                <div v-if="!collapsed" class="min-w-0">
                    <div class="font-semibold truncate">AI</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">CRM</div>
                </div>
            </div>

            <button
                class="h-9 w-9 rounded-xl border border-zinc-200 dark:border-zinc-700 flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800"
                @click="$emit('toggle-collapse')"
            >
                <PanelLeftClose v-if="!collapsed" class="h-4 w-4" />
                <PanelLeftOpen v-else class="h-4 w-4" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            <div v-for="section in items" :key="section.label">
                <div
                    v-if="!collapsed"
                    class="px-3 mb-2 text-[11px] uppercase tracking-[0.12em] text-zinc-400 dark:text-zinc-500"
                >
                    {{ section.label }}
                </div>

                <div class="space-y-1">
                    <button
                        v-for="item in section.children"
                        :key="item.key"
                        type="button"
                        :class="[
                            'w-full flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-colors',
                            activeKey === item.key
                                ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-zinc-100',
                        ]"
                        @click="$emit('select', item.key)"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />

                        <template v-if="!collapsed">
                            <span class="flex-1 text-left truncate">{{ item.title }}</span>

                            <span
                                v-if="item.badge"
                                class="rounded-full bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 text-[11px]"
                            >
                                {{ item.badge }}
                            </span>
                        </template>
                    </button>
                </div>
            </div>
        </nav>

        <div class="p-3 border-t border-zinc-200 dark:border-zinc-800">
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 p-3 flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-zinc-200 dark:bg-zinc-700" />
                <div v-if="!collapsed" class="min-w-0">
                    <div class="text-sm font-medium truncate">{{ userName }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ userEmail }}</div>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import {
    LayoutDashboard,
    Package,
    Building2,
    Truck,
    FileText,
    BarChart3,
    Blocks,
    Settings,
    PanelLeftClose,
    PanelLeftOpen,
} from 'lucide-vue-next';

defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
    activeKey: {
        type: String,
        default: 'dashboard',
    },
    userName: {
        type: String,
        default: 'Руслан',
    },
    userEmail: {
        type: String,
        default: 'admin@v5.local',
    },
    items: {
        type: Array,
        default: () => [
            {
                label: 'Основное',
                children: [
                    { key: 'dashboard', title: 'Панель', icon: LayoutDashboard },
                    { key: 'orders', title: 'Заказы', icon: Package, badge: '24' },
                    { key: 'contractors', title: 'Контрагенты', icon: Building2 },
                    { key: 'drivers', title: 'Водители', icon: Truck },
                ],
            },
            {
                label: 'Работа',
                children: [
                    { key: 'documents', title: 'Документы', icon: FileText },
                    { key: 'reports', title: 'Отчёты', icon: BarChart3 },
                ],
            },
            {
                label: 'Система',
                children: [
                    { key: 'modules', title: 'Модули', icon: Blocks },
                    { key: 'settings', title: 'Настройки', icon: Settings },
                ],
            },
        ],
    },
});

defineEmits(['toggle-collapse', 'select']);
</script>