<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Роли по колонкам, права и области видимости по строкам"
            title="Роли"
            :title-class="crmPageTitleSm"
        >
            <template #actions>
                <button
                    type="button"
                    :class="crmBtnNeutral"
                    @click="showCreateForm = !showCreateForm"
                >
                    <Plus class="h-4 w-4" />
                    {{ showCreateForm ? 'Скрыть форму' : 'Добавить роль' }}
                </button>
            </template>
        </CrmPageHeader>

        <div
            v-if="showCreateForm"
            :class="`${crmPanel} p-3`"
        >
            <div class="mb-4">
                <div class="text-sm font-medium">Новая роль</div>
                <div class="text-xs text-zinc-500">Создание роли без отдельного модального окна</div>
            </div>

            <form class="grid gap-3 lg:grid-cols-[220px,220px,1fr,170px,auto]" @submit.prevent="createRole">
                <div class="space-y-2">
                    <label class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Код</label>
                    <input
                        v-model="createForm.name"
                        type="text"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                        placeholder="manager"
                    />
                    <div v-if="createForm.errors.name" class="text-xs text-rose-600">{{ createForm.errors.name }}</div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Название</label>
                    <input
                        v-model="createForm.display_name"
                        type="text"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                        placeholder="Менеджер"
                    />
                    <div v-if="createForm.errors.display_name" class="text-xs text-rose-600">{{ createForm.errors.display_name }}</div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Описание</label>
                    <input
                        v-model="createForm.description"
                        type="text"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                        placeholder="Краткое описание роли"
                    />
                    <div v-if="createForm.errors.description" class="text-xs text-rose-600">{{ createForm.errors.description }}</div>
                </div>

                <label class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3 py-1.5 text-sm dark:border-zinc-700">
                    <input
                        v-model="createForm.has_signing_authority"
                        type="checkbox"
                        class="rounded border-zinc-300"
                    />
                    <span>Право подписи</span>
                </label>

                <button
                    type="submit"
                    :class="crmBtnPrimary"
                    :disabled="createForm.processing"
                >
                    {{ createForm.processing ? 'Создание...' : 'Создать' }}
                </button>
            </form>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="h-full overflow-auto">
                <table class="min-w-[1280px] border-collapse text-sm">
                    <thead class="sticky top-0 z-20 bg-zinc-100 dark:bg-zinc-800">
                        <tr>
                            <th class="sticky left-0 z-30 min-w-[320px] border-b border-r border-zinc-200 bg-zinc-100 px-3 py-3 text-left dark:border-zinc-700 dark:bg-zinc-800">
                                <div class="text-sm font-medium">Настройка</div>
                                <div class="text-xs text-zinc-500">Родительские строки управляют зависимыми ниже</div>
                            </th>

                            <th
                                v-for="role in roleColumns"
                                :key="role.id"
                                class="min-w-[280px] border-b border-zinc-200 px-3 py-3 align-top dark:border-zinc-700"
                            >
                                <div class="space-y-3 text-left">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <input
                                                v-model="role.display_name"
                                                type="text"
                                                class="w-full rounded-lg border border-zinc-300 bg-white px-2 py-1 text-sm font-semibold outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                            />
                                            <div class="mt-1 font-mono text-[11px] text-zinc-500">{{ role.name }}</div>
                                        </div>

                                        <button
                                            v-if="role.name !== 'admin'"
                                            type="button"
                                            class="rounded-lg border border-rose-200 p-2 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                            @click="removeRole(role)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <textarea
                                        v-model="role.description"
                                        rows="2"
                                        class="w-full rounded-lg border border-zinc-300 bg-white px-2 py-1 text-xs outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                        placeholder="Описание роли"
                                    />

                                    <button
                                        type="button"
                                        :class="`${crmBtnCreate} w-full justify-center py-1.5`"
                                        :disabled="savingRoleId === role.id"
                                        @click="saveRole(role)"
                                    >
                                        {{ savingRoleId === role.id ? 'Сохранение...' : 'Сохранить колонку' }}
                                    </button>
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="bg-zinc-50/80 dark:bg-zinc-950/50">
                            <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-zinc-50/80 px-3 py-2.5 font-medium dark:border-zinc-800 dark:bg-zinc-950/50">
                                Служебные параметры
                            </td>
                            <td
                                v-for="role in roleColumns"
                                :key="`service-${role.id}`"
                                class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800"
                            />
                        </tr>

                        <tr>
                            <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="font-medium">Пользователи</div>
                                <div class="text-xs text-zinc-500">Количество назначенных сотрудников</div>
                            </td>
                            <td
                                v-for="role in roleColumns"
                                :key="`users-${role.id}`"
                                class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800"
                            >
                                {{ role.users_count }}
                            </td>
                        </tr>

                        <tr>
                            <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="font-medium">Право подписи</div>
                                <div class="text-xs text-zinc-500">Подставляется новым пользователям этой роли</div>
                            </td>
                            <td
                                v-for="role in roleColumns"
                                :key="`sign-${role.id}`"
                                class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800"
                            >
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        v-model="role.has_signing_authority"
                                        type="checkbox"
                                        class="rounded border-zinc-300"
                                    />
                                    <span>Разрешено</span>
                                </label>
                            </td>
                        </tr>

                        <tr v-if="mobileNavCatalog.length">
                            <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="font-medium">Мобильное приложение</div>
                                <div class="text-xs text-zinc-500">Кнопки нижней панели по умолчанию (до 6). Пользователь может переопределить у себя в PWA.</div>
                            </td>
                            <td
                                v-for="role in roleColumns"
                                :key="`mnav-${role.id}`"
                                class="border-b border-zinc-200 px-3 py-2.5 align-top dark:border-zinc-800"
                            >
                                <div class="flex max-w-[220px] flex-col gap-2">
                                    <div
                                        v-if="mobileNavPresets.length"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <button
                                            v-for="preset in mobileNavPresets"
                                            :key="`${role.id}-preset-${preset.id}`"
                                            type="button"
                                            class="rounded-lg border border-zinc-200 px-2 py-1 text-[10px] font-medium leading-tight text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                            :title="preset.description"
                                            @click="applyRoleMobileNavPreset(role, preset)"
                                        >
                                            {{ preset.label }}
                                        </button>
                                    </div>
                                    <label
                                        v-for="opt in mobileNavCatalog"
                                        :key="`${role.id}-${opt.key}`"
                                        class="inline-flex items-center gap-2 text-xs"
                                    >
                                        <input
                                            type="checkbox"
                                            class="rounded border-zinc-300"
                                            :checked="(role.default_mobile_nav_keys || []).includes(opt.key)"
                                            @change="toggleRoleMobileNavKey(role, opt.key)"
                                        />
                                        <span>{{ opt.label }}</span>
                                    </label>
                                </div>
                            </td>
                        </tr>

                        <tr class="bg-zinc-50/80 dark:bg-zinc-950/50">
                            <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-zinc-50/80 px-3 py-2.5 font-medium dark:border-zinc-800 dark:bg-zinc-950/50">
                                Права
                            </td>
                            <td
                                v-for="role in roleColumns"
                                :key="`permissions-${role.id}`"
                                class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800"
                            />
                        </tr>

                        <tr
                            v-for="permission in flatPermissionOptions"
                            :key="permission.key"
                        >
                            <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="font-medium">{{ permission.label }}</div>
                                <div class="text-xs text-zinc-500">{{ permission.description }}</div>
                            </td>
                            <td
                                v-for="role in roleColumns"
                                :key="`${permission.key}-${role.id}`"
                                class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800"
                            >
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        :checked="role.permissions.includes(permission.key)"
                                        type="checkbox"
                                        class="rounded border-zinc-300"
                                        @change="togglePermission(role, permission.key)"
                                    />
                                    <span>Да</span>
                                </label>
                            </td>
                        </tr>

                        <template v-for="group in visibilityMatrix" :key="group.id">
                            <tr class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                <td
                                    class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-zinc-50/80 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-950/50"
                                >
                                    <button
                                        v-if="group.collapsible"
                                        type="button"
                                        class="flex w-full items-start gap-2 text-left"
                                        @click="toggleVisibilityGroup(group.id)"
                                    >
                                        <ChevronDown v-if="isVisibilityGroupExpanded(group.id)" class="mt-0.5 h-4 w-4 shrink-0" />
                                        <ChevronRight v-else class="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>
                                            <span class="font-medium">{{ group.label }}</span>
                                            <span class="mt-0.5 block text-xs text-zinc-500">{{ group.description }}</span>
                                        </span>
                                    </button>
                                    <template v-else>
                                        <div class="font-medium">{{ group.label }}</div>
                                        <div class="text-xs text-zinc-500">{{ group.description }}</div>
                                    </template>
                                </td>
                                <td
                                    v-for="role in roleColumns"
                                    :key="`${group.id}-header-${role.id}`"
                                    class="border-b border-zinc-200 px-3 py-2.5 align-top dark:border-zinc-800"
                                >
                                    <template v-if="group.collapsible && ! isVisibilityGroupExpanded(group.id) && (group.groupScopeKeys?.length ?? 0) > 0">
                                        <div class="space-y-1">
                                            <div class="text-xs text-zinc-500">Объём данных группы</div>
                                            <select
                                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                                :value="groupScopeMode(role, group)"
                                                @change="updateGroupVisibilityScope(role, group, $event.target.value)"
                                            >
                                                <option
                                                    v-for="scopeOption in visibilityScopeOptions"
                                                    :key="`${group.id}-${scopeOption.value}`"
                                                    :value="scopeOption.value"
                                                >
                                                    {{ scopeOption.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </template>
                                </td>
                            </tr>

                            <tr
                                v-for="row in visibleGroupRows(group)"
                                :key="row.id"
                            >
                                <td
                                    class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900"
                                    :class="indentClass(row.level)"
                                >
                                    <div class="font-medium">{{ row.label }}</div>
                                    <div class="text-xs text-zinc-500">{{ row.description }}</div>
                                </td>

                                <td
                                    v-for="role in roleColumns"
                                    :key="`${row.id}-${role.id}`"
                                    class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800"
                                >
                                    <template v-if="row.type === 'area'">
                                        <label class="inline-flex items-center gap-2">
                                            <input
                                                :checked="isAreaEnabled(role, row.areaKey)"
                                                type="checkbox"
                                                class="rounded border-zinc-300"
                                                @change="toggleArea(role, row.areaKey)"
                                            />
                                            <span>{{ isAreaEnabled(role, row.areaKey) ? 'Доступен' : 'Отключён' }}</span>
                                        </label>
                                    </template>

                                    <template v-else-if="row.type === 'mode'">
                                        <div v-if="! isAreaEnabled(role, row.parentKey)" class="text-xs text-zinc-400">
                                            Сначала включи модуль
                                        </div>
                                        <div v-else class="space-y-2">
                                            <label class="flex items-center gap-2">
                                                <input
                                                    :checked="moduleAccessMode(role, row.parentKey) === 'all'"
                                                    type="radio"
                                                    :name="`mode-${row.parentKey}-${role.id}`"
                                                    class="border-zinc-300"
                                                    @change="updateModuleAccessMode(role, row.parentKey, 'all')"
                                                />
                                                <span>Все компоненты</span>
                                            </label>
                                            <label class="flex items-center gap-2">
                                                <input
                                                    :checked="moduleAccessMode(role, row.parentKey) === 'selective'"
                                                    type="radio"
                                                    :name="`mode-${row.parentKey}-${role.id}`"
                                                    class="border-zinc-300"
                                                    @change="updateModuleAccessMode(role, row.parentKey, 'selective')"
                                                />
                                                <span>Выбор компонентов</span>
                                            </label>
                                        </div>
                                    </template>

                                    <template v-else-if="row.type === 'scope'">
                                        <div v-if="! isAreaEnabled(role, row.parentKey)" class="text-xs text-zinc-400">
                                            Сначала включи модуль
                                        </div>
                                        <select
                                            v-else
                                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                            :value="scopeModeFromRole(role, row.parentKey)"
                                            @change="updateVisibilityScope(role, row.parentKey, $event.target.value)"
                                        >
                                            <option
                                                v-for="scopeOption in visibilityScopeOptions"
                                                :key="scopeOption.value"
                                                :value="scopeOption.value"
                                            >
                                                {{ scopeOption.label }}
                                            </option>
                                        </select>
                                    </template>

                                    <template v-else-if="row.type === 'child'">
                                        <div v-if="! isAreaEnabled(role, row.parentKey)" class="text-xs text-zinc-400">
                                            Недоступно
                                        </div>
                                        <label v-else class="inline-flex items-center gap-2">
                                            <input
                                                :checked="isChildAreaEnabled(role, row.parentKey, row.areaKey)"
                                                type="checkbox"
                                                class="rounded border-zinc-300"
                                                :disabled="moduleAccessMode(role, row.parentKey) === 'all'"
                                                @change="toggleChildArea(role, row.parentKey, row.areaKey)"
                                            />
                                            <span>
                                                {{
                                                    moduleAccessMode(role, row.parentKey) === 'all'
                                                        ? 'Включено (все компоненты)'
                                                        : 'Точечный доступ'
                                                }}
                                            </span>
                                        </label>
                                    </template>

                                    <template v-else-if="row.type === 'book_permission'">
                                        <div v-if="! isAreaEnabled(role, row.parentKey)" class="text-xs text-zinc-400">
                                            Сначала включи «Книга продаж»
                                        </div>
                                        <label v-else class="inline-flex items-center gap-2">
                                            <input
                                                :checked="role.permissions.includes(row.permissionKey)"
                                                type="checkbox"
                                                class="rounded border-zinc-300"
                                                @change="toggleBookPermission(role, row.permissionKey)"
                                            />
                                            <span>{{ role.permissions.includes(row.permissionKey) ? 'Разрешено' : 'Запрещено' }}</span>
                                        </label>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Plus, Trash2 } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import { crmBtnCreate, crmBtnNeutral, crmBtnPrimary, crmPageTitleSm, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'roles' }, () => page),
});

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissionOptions: { type: Array, default: () => [] },
    visibilityAreaOptions: { type: Array, default: () => [] },
    visibilityScopeOptions: { type: Array, default: () => [] },
    mobileNavCatalog: { type: Array, default: () => [] },
    mobileNavPresets: { type: Array, default: () => [] },
});

const showCreateForm = ref(false);
const savingRoleId = ref(null);
const childAreaMap = {
    dashboard: ['dashboard_tiles'],
    own_fleet: ['fleet_trips', 'fleet_efficiency'],
    settings: ['settings_system', 'settings_motivation'],
    scripts: [
        'sales_assistant_scripts',
        'sales_assistant_book',
        'sales_assistant_trainer',
        'sales_assistant_counter',
    ],
    modules: [
        'modules_catalog',
        'modules_how_much_fits',
        'modules_how_much_costs',
        'modules_import_cost',
    ],
};
const salesBookPermissionKeys = ['sales_book_read', 'sales_book_comment', 'sales_book_write'];
const scopeAreaKeys = [
    'orders',
    'leads',
    'tasks',
    'kanban',
    'contractors',
    'documents',
    'payment_schedules',
    'dashboard_tiles',
];
const visibilityGroupDefinitions = [
    {
        id: 'sales',
        label: 'Продажи',
        description: 'Дашборд, лиды, заказы, контрагенты, ТС, задачи, канбан, pipeline',
        collapsible: true,
        keys: ['dashboard', 'leads', 'mail', 'orders', 'pipeline', 'contractors', 'drivers', 'tasks', 'kanban'],
        groupScopeKeys: ['orders', 'pipeline', 'leads', 'tasks', 'kanban', 'contractors'],
    },
    {
        id: 'own_fleet',
        label: 'Собственный парк',
        description: 'Рейсы и эффективность собственного парка',
        collapsible: true,
        keys: ['own_fleet'],
    },
    {
        id: 'finance',
        label: 'Финансы и аналитика',
        description: 'Документы, зарплата, график оплат',
        collapsible: true,
        keys: ['documents', 'finance_salary', 'payment_schedules'],
        groupScopeKeys: ['documents', 'payment_schedules'],
    },
    {
        id: 'reports',
        label: 'Отчёты',
        description: 'Сводные отчёты и аналитика обучения',
        collapsible: true,
        keys: ['reports', 'sales_assistant_trainer_analytics', 'sales_assistant_book_analytics'],
    },
    {
        id: 'sales_assistant',
        label: 'Помощник продавца',
        description: 'Скрипты, книга продаж, тренажёр, считалка',
        collapsible: true,
        keys: ['scripts'],
    },
    {
        id: 'administration',
        label: 'Настройки',
        description: 'Пользователи, роли, модули и системные разделы',
        collapsible: true,
        keys: ['users', 'roles', 'modules', 'settings'],
    },
];

const expandedVisibilityGroups = ref(new Set(visibilityGroupDefinitions.map((group) => group.id)));

const createForm = useForm({
    name: '',
    display_name: '',
    description: '',
    has_signing_authority: false,
    permissions: [],
    visibility_areas: [],
    visibility_scopes: {},
});

const visibilityAreaOptionsByKey = computed(() => Object.fromEntries(
    props.visibilityAreaOptions.map((area) => [area.key, area]),
));
const permissionOptionsByKey = computed(() => Object.fromEntries(
    props.permissionOptions.map((permission) => [permission.key, permission]),
));
const flatPermissionOptions = computed(() => props.permissionOptions.filter(
    (permission) => ! salesBookPermissionKeys.includes(permission.key),
));
const allowedVisibilityAreaKeys = computed(() => new Set(props.visibilityAreaOptions.map((area) => area.key)));

function sanitizeVisibilityAreas(areas) {
    if (!Array.isArray(areas)) {
        return [];
    }

    const allowed = allowedVisibilityAreaKeys.value;
    return [...new Set(areas.filter((key) => typeof key === 'string' && allowed.has(key)))];
}

const roleColumns = ref(props.roles.map(cloneRole));

/** Обновить таблицу из ответа Inertia (редирект после store/update/destroy), не из «тихих» перерисовок props. */
function replaceRoleColumnsFromInertiaPage(page) {
    const incoming = page?.props?.roles;
    roleColumns.value = Array.isArray(incoming) ? incoming.map(cloneRole) : [];
}

function deriveModuleModes(visibilityAreas) {
    const areas = visibilityAreas ?? [];

    return Object.fromEntries(
        Object.entries(childAreaMap).map(([areaKey, childKeys]) => {
            if ((childKeys ?? []).length === 0) {
                return [areaKey, 'all'];
            }

            const legacyScriptsFull =
                areaKey === 'scripts' &&
                areas.includes('scripts') &&
                !childKeys.some((k) => areas.includes(k));
            const allChildren = childKeys.every((k) => areas.includes(k));

            return [areaKey, legacyScriptsFull || allChildren ? 'all' : 'selective'];
        }),
    );
}

const visibilityMatrix = computed(() => visibilityGroupDefinitions.map((group) => {
    const rows = [];

    for (const key of group.keys) {
        const area = visibilityAreaOptionsByKey.value[key];

        if (!area) {
            continue;
        }

        rows.push({
            id: `area-${key}`,
            type: 'area',
            areaKey: key,
            label: area.label,
            description: area.description,
            level: 0,
        });

        if ((childAreaMap[key] ?? []).length > 0) {
            rows.push({
                id: `mode-${key}`,
                type: 'mode',
                parentKey: key,
                label: 'Режим доступа',
                description: 'Полный доступ к модулю или выбор конкретных компонентов',
                level: 1,
            });

            for (const childKey of childAreaMap[key]) {
                const childArea = visibilityAreaOptionsByKey.value[childKey];

                if (!childArea) {
                    continue;
                }

                rows.push({
                    id: `child-${childKey}`,
                    type: 'child',
                    parentKey: key,
                    areaKey: childKey,
                    label: childArea.label,
                    description: childArea.description,
                    level: 2,
                });

                if (childKey === 'sales_assistant_book') {
                    for (const permissionKey of salesBookPermissionKeys) {
                        const permission = permissionOptionsByKey.value[permissionKey];

                        if (! permission) {
                            continue;
                        }

                        rows.push({
                            id: `book-perm-${permissionKey}`,
                            type: 'book_permission',
                            parentKey: childKey,
                            permissionKey,
                            label: permission.label,
                            description: permission.description,
                            level: 3,
                        });
                    }
                }

                if (scopeAreaKeys.includes(childKey)) {
                    rows.push({
                        id: `child-scope-${childKey}`,
                        type: 'scope',
                        parentKey: childKey,
                        areaKey: childKey,
                        label: 'Объём данных',
                        description: scopeHint(childKey),
                        level: 3,
                    });
                }
            }
        } else if (scopeAreaKeys.includes(key)) {
            rows.push({
                id: `scope-${key}`,
                type: 'scope',
                parentKey: key,
                label: 'Объём данных',
                description: scopeHint(key),
                level: 1,
            });
        }
    }

    return { ...group, rows };
}));

function cloneRole(role) {
    const visibilityAreas = sanitizeVisibilityAreas(role.visibility_areas);

    return {
        id: role.id,
        name: role.name,
        display_name: role.display_name,
        description: role.description || '',
        users_count: role.users_count,
        permissions: Array.isArray(role.permissions) ? [...role.permissions] : [],
        visibility_areas: visibilityAreas,
        visibility_scopes: normalizeScopes(role.visibility_scopes || {}),
        has_signing_authority: Boolean(role.default_has_signing_authority),
        default_mobile_nav_keys: Array.isArray(role.default_mobile_nav_keys) ? [...role.default_mobile_nav_keys] : null,
        module_modes: deriveModuleModes(visibilityAreas),
    };
}

function normalizeScopes(scopes) {
    const normalized = {};

    for (const [key, value] of Object.entries(scopes)) {
        if (typeof value === 'string') {
            normalized[key] = { mode: value };
        } else if (value && typeof value === 'object' && value.mode) {
            normalized[key] = { mode: value.mode };
        }
    }

    return normalized;
}

function indentClass(level) {
    return {
        'pl-4': level === 0,
        'pl-10': level === 1,
        'pl-16': level === 2,
        'pl-24': level === 3,
    };
}

function createRole() {
    createForm.post(route('roles.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            createForm.reset();
            showCreateForm.value = false;
            replaceRoleColumnsFromInertiaPage(page);
        },
    });
}

function saveRole(role) {
    savingRoleId.value = role.id;

    router.patch(route('roles.update', role.id), serializeRole(role), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            replaceRoleColumnsFromInertiaPage(page);
        },
        onFinish: () => {
            savingRoleId.value = null;
        },
    });
}

function serializeRole(role) {
    const scopes = {};

    for (const [key, value] of Object.entries(role.visibility_scopes ?? {})) {
        const mode = value?.mode;
        if (mode === 'own' || mode === 'all') {
            scopes[key] = { mode };
        }
    }

    return {
        name: role.name,
        display_name: role.display_name,
        description: role.description ?? '',
        permissions: Array.isArray(role.permissions) ? [...role.permissions] : [],
        visibility_areas: sanitizeVisibilityAreas(role.visibility_areas),
        visibility_scopes: scopes,
        has_signing_authority: Boolean(role.has_signing_authority),
        default_mobile_nav_keys: Array.isArray(role.default_mobile_nav_keys)
            ? [...role.default_mobile_nav_keys]
            : role.default_mobile_nav_keys,
    };
}

function candidateMobileNavKeysForRole(role) {
    const areas = Array.isArray(role.visibility_areas) ? role.visibility_areas : [];

    if (role.name === 'admin') {
        return props.mobileNavCatalog.map((opt) => opt.key);
    }

    return props.mobileNavCatalog
        .map((opt) => opt.key)
        .filter((key) => {
            if (key === 'dashboard') {
                return areas.includes('dashboard');
            }

            if (key === 'kanban') {
                return areas.includes('kanban') || areas.includes('tasks');
            }

            if (key === 'trainer') {
                return areas.includes('sales_assistant_trainer') || areas.includes('scripts');
            }

            if (key === 'finance') {
                return areas.includes('finance')
                    || areas.includes('finance_salary')
                    || areas.includes('budgeting');
            }

            return areas.includes(key);
        });
}

function applyRoleMobileNavPreset(role, preset) {
    const allowed = new Set(candidateMobileNavKeysForRole(role));
    const keys = (preset?.keys ?? []).filter((key) => allowed.has(key)).slice(0, 6);

    role.default_mobile_nav_keys = keys.length ? keys : null;
}

function toggleRoleMobileNavKey(role, key) {
    const current = Array.isArray(role.default_mobile_nav_keys) ? [...role.default_mobile_nav_keys] : [];
    const idx = current.indexOf(key);
    if (idx >= 0) {
        current.splice(idx, 1);
    } else if (current.length < 6) {
        current.push(key);
    }
    role.default_mobile_nav_keys = current.length ? current : null;
}

function removeRole(role) {
    if (!window.confirm(`Удалить роль ${role.display_name}?`)) {
        return;
    }

    router.delete(route('roles.destroy', role.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            replaceRoleColumnsFromInertiaPage(page);
        },
    });
}

function togglePermission(role, permissionKey) {
    if (role.permissions.includes(permissionKey)) {
        role.permissions = role.permissions.filter((item) => item !== permissionKey);
        return;
    }

    role.permissions = [...role.permissions, permissionKey];
}

function clearSalesBookPermissions(role) {
    role.permissions = role.permissions.filter((item) => ! salesBookPermissionKeys.includes(item));
}

function ensureSalesBookReadPermission(role) {
    if (! salesBookPermissionKeys.some((key) => role.permissions.includes(key))) {
        role.permissions = [...role.permissions, 'sales_book_read'];
    }
}

function toggleBookPermission(role, permissionKey) {
    const enabled = role.permissions.includes(permissionKey);

    if (enabled) {
        if (permissionKey === 'sales_book_write') {
            role.permissions = role.permissions.filter((item) => item !== 'sales_book_write');
            return;
        }

        if (permissionKey === 'sales_book_comment') {
            role.permissions = role.permissions.filter((item) => item !== 'sales_book_comment' && item !== 'sales_book_write');
            return;
        }

        clearSalesBookPermissions(role);

        return;
    }

    if (permissionKey === 'sales_book_write') {
        role.permissions = [...new Set([...role.permissions, 'sales_book_read', 'sales_book_comment', 'sales_book_write'])];
        return;
    }

    if (permissionKey === 'sales_book_comment') {
        role.permissions = [...new Set([...role.permissions, 'sales_book_read', 'sales_book_comment'])];
        return;
    }

    role.permissions = [...new Set([...role.permissions, 'sales_book_read'])];
}

function isAreaEnabled(role, areaKey) {
    return role.visibility_areas.includes(areaKey);
}

function isChildAreaEnabled(role, parentKey, childKey) {
    if (! isAreaEnabled(role, parentKey)) {
        return false;
    }

    if (moduleAccessMode(role, parentKey) === 'all') {
        return true;
    }

    return isAreaEnabled(role, childKey);
}

function areaSupportsScope(areaKey) {
    return scopeAreaKeys.includes(areaKey);
}

function setAreaEnabled(role, areaKey, enabled) {
    const areas = new Set(role.visibility_areas);
    const scopes = { ...role.visibility_scopes };
    const moduleModes = { ...role.module_modes };

    if (enabled) {
        areas.add(areaKey);

        if (areaSupportsScope(areaKey) && !scopes[areaKey]) {
            scopes[areaKey] = { mode: 'own' };
        }

        if ((childAreaMap[areaKey] ?? []).length > 0) {
            moduleModes[areaKey] = 'all';

            for (const childKey of childAreaMap[areaKey]) {
                areas.add(childKey);

                if (childKey === 'sales_assistant_book') {
                    ensureSalesBookReadPermission(role);
                }
            }
        }
    } else {
        areas.delete(areaKey);
        delete scopes[areaKey];
        delete moduleModes[areaKey];

        for (const childKey of childAreaMap[areaKey] ?? []) {
            areas.delete(childKey);
            delete scopes[childKey];

            if (childKey === 'sales_assistant_book') {
                clearSalesBookPermissions(role);
            }
        }
    }

    role.visibility_areas = [...areas];
    role.visibility_scopes = scopes;
    role.module_modes = moduleModes;
}

function toggleArea(role, areaKey) {
    setAreaEnabled(role, areaKey, !isAreaEnabled(role, areaKey));
}

function moduleAccessMode(role, areaKey) {
    return role.module_modes?.[areaKey] ?? 'all';
}

function updateModuleAccessMode(role, areaKey, mode) {
    const areas = new Set(role.visibility_areas);
    const moduleModes = { ...role.module_modes };

    areas.add(areaKey);
    moduleModes[areaKey] = mode;

    if (mode === 'all') {
        for (const childKey of childAreaMap[areaKey] ?? []) {
            areas.add(childKey);

            if (childKey === 'sales_assistant_book') {
                ensureSalesBookReadPermission(role);
            }
        }
    }

    role.visibility_areas = [...areas];
    role.module_modes = moduleModes;
}

function toggleChildArea(role, parentKey, childKey) {
    const areas = new Set(role.visibility_areas);
    const moduleModes = { ...role.module_modes };

    areas.add(parentKey);
    moduleModes[parentKey] = 'selective';

    if (areas.has(childKey)) {
        areas.delete(childKey);

        if (childKey === 'sales_assistant_book') {
            clearSalesBookPermissions(role);
        }
    } else {
        areas.add(childKey);

        if (childKey === 'sales_assistant_book') {
            ensureSalesBookReadPermission(role);
        }
    }

    role.visibility_areas = [...areas];
    role.module_modes = moduleModes;
}

function toggleVisibilityGroup(groupId) {
    const next = new Set(expandedVisibilityGroups.value);

    if (next.has(groupId)) {
        next.delete(groupId);
    } else {
        next.add(groupId);
    }

    expandedVisibilityGroups.value = next;
}

function isVisibilityGroupExpanded(groupId) {
    return expandedVisibilityGroups.value.has(groupId);
}

function visibleGroupRows(group) {
    if (group.collapsible && ! isVisibilityGroupExpanded(group.id)) {
        return [];
    }

    return group.rows;
}

function groupScopeMode(role, group) {
    const keys = group.groupScopeKeys ?? [];

    if (keys.length === 0) {
        return 'own';
    }

    const modes = keys.map((key) => scopeModeFromRole(role, key));
    const unique = [...new Set(modes)];

    return unique.length === 1 ? unique[0] : 'own';
}

function updateGroupVisibilityScope(role, group, mode) {
    if (mode !== 'own' && mode !== 'all') {
        return;
    }

    const scopes = { ...role.visibility_scopes };

    for (const key of group.groupScopeKeys ?? []) {
        scopes[key] = { mode };
    }

    role.visibility_scopes = scopes;
}

function scopeModeFromRole(role, areaKey) {
    return role.visibility_scopes[areaKey]?.mode ?? 'own';
}

function updateVisibilityScope(role, areaKey, mode) {
    role.visibility_scopes = {
        ...role.visibility_scopes,
        [areaKey]: { mode },
    };
}

function scopeHint(areaKey) {
    if (areaKey === 'orders') {
        return 'Все заказы или только свои';
    }

    if (areaKey === 'pipeline') {
        return 'Все заказы на доске Pipeline или только свои';
    }

    if (areaKey === 'leads') {
        return 'Все лиды или только свои';
    }

    if (areaKey === 'tasks') {
        return 'Все задачи или только свои';
    }

    if (areaKey === 'kanban') {
        return 'Все карточки канбана или только свои';
    }

    if (areaKey === 'contractors') {
        return 'Все контрагенты или только закреплённые за ролью';
    }

    if (areaKey === 'documents') {
        return 'Все документы или только свои';
    }

    if (areaKey === 'payment_schedules') {
        return 'Все строки графика оплат по заказам или только по своим заказам';
    }

    if (areaKey === 'dashboard_tiles') {
        return 'Все плитки или только относящиеся к своим данным';
    }

    return 'Объём данных внутри раздела';
}
</script>
