<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Роли</h1>
                <p class="text-sm text-zinc-500">Роли по колонкам, права и области видимости по строкам</p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                @click="showCreateForm = !showCreateForm"
            >
                <Plus class="h-4 w-4" />
                {{ showCreateForm ? 'Скрыть форму' : 'Добавить роль' }}
            </button>
        </div>

        <div
            v-if="showCreateForm"
            class="rounded-2xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900"
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
                    class="rounded-xl bg-zinc-900 px-4 py-1.5 text-sm text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
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
                                        class="w-full rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
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
                            v-for="permission in permissionOptions"
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
                                <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-zinc-50/80 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-950/50">
                                    <div class="font-medium">{{ group.label }}</div>
                                    <div class="text-xs text-zinc-500">{{ group.description }}</div>
                                </td>
                                <td
                                    v-for="role in roleColumns"
                                    :key="`${group.id}-${role.id}`"
                                    class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800"
                                />
                            </tr>

                            <tr
                                v-for="row in group.rows"
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
                                                :checked="isAreaEnabled(role, row.areaKey)"
                                                type="checkbox"
                                                class="rounded border-zinc-300"
                                                :disabled="moduleAccessMode(role, row.parentKey) === 'all'"
                                                @change="toggleChildArea(role, row.parentKey, row.areaKey)"
                                            />
                                                <span>{{ moduleAccessMode(role, row.parentKey) === 'all' ? 'Доступ открывается родительской строкой' : 'Точечный доступ' }}</span>
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
import { Plus, Trash2 } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'roles' }, () => page),
});

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissionOptions: { type: Array, default: () => [] },
    visibilityAreaOptions: { type: Array, default: () => [] },
    visibilityScopeOptions: { type: Array, default: () => [] },
});

const showCreateForm = ref(false);
const savingRoleId = ref(null);
const childAreaMap = {
    dashboard: ['dashboard_tiles', 'dashboard_widgets', 'dashboard_reports'],
    settings: ['settings_system', 'settings_motivation'],
};
const scopeAreaKeys = [
    'orders',
    'leads',
    'tasks',
    'kanban',
    'contractors',
    'documents',
    'activities',
    'dashboard_tiles',
    'dashboard_widgets',
    'dashboard_reports',
];
const visibilityGroupDefinitions = [
    { id: 'core', label: 'Основные модули', description: 'Главные рабочие разделы', keys: ['dashboard', 'leads', 'orders', 'tasks', 'kanban'] },
    { id: 'directories', label: 'Реестры и справочники', description: 'Списки и карточки', keys: ['contractors', 'drivers', 'documents', 'activities', 'users', 'roles'] },
    { id: 'analytics', label: 'Финансы и аналитика', description: 'Отчёты и сводные показатели', keys: ['finance_salary', 'reports'] },
    { id: 'system', label: 'Администрирование', description: 'Системные разделы', keys: ['modules', 'settings'] },
];

const createForm = useForm({
    name: '',
    display_name: '',
    description: '',
    has_signing_authority: false,
    permissions: [],
    visibility_areas: [],
    visibility_scopes: {},
});

const roleColumns = ref(props.roles.map(cloneRole));

const visibilityAreaOptionsByKey = computed(() => Object.fromEntries(
    props.visibilityAreaOptions.map((area) => [area.key, area]),
));

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
    const visibilityAreas = [...role.visibility_areas];

    return {
        id: role.id,
        name: role.name,
        display_name: role.display_name,
        description: role.description || '',
        users_count: role.users_count,
        permissions: [...role.permissions],
        visibility_areas: visibilityAreas,
        visibility_scopes: normalizeScopes(role.visibility_scopes || {}),
        has_signing_authority: Boolean(role.default_has_signing_authority),
        module_modes: Object.fromEntries(
            Object.entries(childAreaMap).map(([areaKey, childKeys]) => [
                areaKey,
                childKeys.every((childKey) => visibilityAreas.includes(childKey)) ? 'all' : 'selective',
            ]),
        ),
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
        onSuccess: () => {
            createForm.reset();
            showCreateForm.value = false;
        },
    });
}

function saveRole(role) {
    savingRoleId.value = role.id;

    router.patch(route('roles.update', role.id), serializeRole(role), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            savingRoleId.value = null;
        },
    });
}

function serializeRole(role) {
    return {
        name: role.name,
        display_name: role.display_name,
        description: role.description,
        permissions: role.permissions,
        visibility_areas: role.visibility_areas,
        visibility_scopes: Object.fromEntries(
            Object.entries(role.visibility_scopes).map(([key, value]) => [key, { mode: value.mode }]),
        ),
        has_signing_authority: role.has_signing_authority,
    };
}

function removeRole(role) {
    if (!window.confirm(`Удалить роль ${role.display_name}?`)) {
        return;
    }

    router.delete(route('roles.destroy', role.id), {
        preserveScroll: true,
    });
}

function togglePermission(role, permissionKey) {
    if (role.permissions.includes(permissionKey)) {
        role.permissions = role.permissions.filter((item) => item !== permissionKey);
        return;
    }

    role.permissions = [...role.permissions, permissionKey];
}

function isAreaEnabled(role, areaKey) {
    return role.visibility_areas.includes(areaKey);
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
            }
        }
    } else {
        areas.delete(areaKey);
        delete scopes[areaKey];
        delete moduleModes[areaKey];

        for (const childKey of childAreaMap[areaKey] ?? []) {
            areas.delete(childKey);
            delete scopes[childKey];
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
    } else {
        areas.add(childKey);
    }

    role.visibility_areas = [...areas];
    role.module_modes = moduleModes;
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

    if (areaKey === 'activities') {
        return 'Все активности или только свои';
    }

    if (areaKey === 'dashboard_tiles') {
        return 'Все плитки или только относящиеся к своим данным';
    }

    if (areaKey === 'dashboard_widgets') {
        return 'Все виджеты или только относящиеся к своим данным';
    }

    if (areaKey === 'dashboard_reports') {
        return 'Все отчёты на дашборде или только по своим данным';
    }

    return 'Объём данных внутри раздела';
}
</script>
