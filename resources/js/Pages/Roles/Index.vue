<template>
    <div class="flex h-full min-h-0 flex-col gap-2">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Роли</h1>
                <p class="text-sm text-zinc-500">Управление правами и областями видимости для ролей</p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />
                Добавить роль
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="h-full overflow-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-100 dark:bg-zinc-800">
                        <tr class="text-left text-zinc-600 dark:text-zinc-200">
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Код</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Название</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Пользователи</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Права</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">По умолчанию</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Области видимости</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="role in roles"
                            :key="role.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">{{ role.name }}</td>
                            <td class="px-3 py-3">
                                <div class="font-medium">{{ role.display_name }}</div>
                                <div v-if="role.description" class="text-xs text-zinc-500">{{ role.description }}</div>
                            </td>
                            <td class="px-3 py-3">{{ role.users_count }}</td>
                            <td class="px-3 py-3">{{ role.permissions.length }}</td>
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="role.default_has_signing_authority
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'"
                                >
                                    {{ role.default_has_signing_authority ? 'Право подписи' : 'Нет' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="space-y-1">
                                    <div>{{ role.visibility_areas.length }}</div>
                                    <div v-if="role.visibility_areas.includes('orders')" class="text-xs text-zinc-500">
                                        Заказы: {{ visibilityScopeLabel(resolveScopeModeFromRole(role.visibility_scopes, 'orders')) }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-2 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        @click="openEditModal(role)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="role.users_count === 0 && role.name !== 'admin'"
                                        type="button"
                                        class="rounded-lg border border-rose-200 p-2 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        @click="removeRole(role)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeModal"
            >
                <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <div class="text-lg font-semibold">
                                {{ editingRole === null ? 'Новая роль' : 'Редактирование роли' }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Настройка прав и областей видимости
                            </div>
                        </div>

                        <div id="section-widgets" class="space-y-3 px-5 py-5 lg:px-6">
                            <div>
                                <div class="text-sm font-medium">Доступные виджеты</div>
                                <div class="text-xs text-zinc-500">Контролируйте, какие виджеты и плитки отображаются на дашборде.</div>
                            </div>
                            <div class="space-y-2">
                                <label
                                    v-for="area in widgetVisibilityAreaOptions"
                                    :key="area.key"
                                    class="block border border-zinc-200 px-3 py-3 dark:border-zinc-800"
                                >
                                    <div class="flex items-start gap-3">
                                        <input
                                            :checked="form.visibility_areas.includes(area.key)"
                                            type="checkbox"
                                            class="mt-1 rounded border-zinc-300"
                                            @change="toggleSelection('visibility_areas', area.key)"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium">{{ area.label }}</div>
                                            <div class="text-xs text-zinc-500">{{ area.description }}</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="section-reports" class="space-y-3 px-5 py-5 lg:px-6">
                            <div>
                                <div class="text-sm font-medium">Доступные отчёты</div>
                                <div class="text-xs text-zinc-500">Отдельный контроль доступа к отчетным карточкам и аналитике.</div>
                            </div>
                            <div class="space-y-2">
                                <label
                                    v-for="area in reportVisibilityAreaOptions"
                                    :key="area.key"
                                    class="block border border-zinc-200 px-3 py-3 dark:border-zinc-800"
                                >
                                    <div class="flex items-start gap-3">
                                        <input
                                            :checked="form.visibility_areas.includes(area.key)"
                                            type="checkbox"
                                            class="mt-1 rounded border-zinc-300"
                                            @change="toggleSelection('visibility_areas', area.key)"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium">{{ area.label }}</div>
                                            <div class="text-xs text-zinc-500">{{ area.description }}</div>
                                        </div>
                                    </div>

                                    <div v-if="form.visibility_areas.includes(area.key)" class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="text-xs text-zinc-500">Объём данных внутри отчетов</div>
                                            <select
                                                class="w-40 border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                                :value="scopeModeFromForm(area.key)"
                                                @change="updateVisibilityScope(area.key, $event.target.value)"
                                            >
                                                <option
                                                    v-for="scopeOption in visibilityScopeOptions"
                                                    :key="scopeOption.value"
                                                    :value="scopeOption.value"
                                                >
                                                    {{ scopeOption.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="closeModal"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                        <div class="px-5 py-4">
                            <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500">
                                <button
                                    v-for="section in sectionDefinitions"
                                    :key="section.id"
                                    type="button"
                                    class="rounded-full px-3 py-1 transition"
                                    :class="activeSection === section.id
                                        ? 'bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900'
                                        : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'"
                                    @click="scrollToSection(section.id)"
                                >
                                    {{ section.label }}
                                </button>
                            </div>
                        </div>
                        <div class="grid min-h-0 flex-1 grid-cols-1 gap-6 overflow-y-auto px-5 py-5 lg:grid-cols-2" id="section-permissions">
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Код роли</label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                        placeholder="manager"
                                    />
                                    <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Название</label>
                                    <input
                                        v-model="form.display_name"
                                        type="text"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                        placeholder="Менеджер"
                                    />
                                    <div v-if="form.errors.display_name" class="text-sm text-rose-600">{{ form.errors.display_name }}</div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Описание</label>
                                    <textarea
                                        v-model="form.description"
                                        rows="4"
                                        class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                        placeholder="Краткое описание роли"
                                    />
                                    <div v-if="form.errors.description" class="text-sm text-rose-600">{{ form.errors.description }}</div>
                                </div>

                                <label class="flex items-start gap-3 border border-zinc-200 px-3 py-3 dark:border-zinc-800">
                                    <input
                                        v-model="form.has_signing_authority"
                                        type="checkbox"
                                        class="mt-1 rounded border-zinc-300"
                                    />
                                    <div>
                                        <div class="text-sm font-medium">Право подписи по умолчанию</div>
                                        <div class="text-xs text-zinc-500">Подставляется новым пользователям этой роли, но может быть изменено персонально.</div>
                                    </div>
                                </label>
                            </div>

                            <div class="space-y-6">
                                <div class="space-y-3" id="section-visibility">
                                    <div>
                                        <div class="text-sm font-medium">Права</div>
                                        <div class="text-xs text-zinc-500">Что можно делать внутри системы</div>
                                    </div>
                                    <div class="space-y-2">
                                        <label
                                            v-for="permission in permissionOptions"
                                            :key="permission.key"
                                            class="flex items-start gap-3 border border-zinc-200 px-3 py-3 dark:border-zinc-800"
                                        >
                                            <input
                                                :checked="form.permissions.includes(permission.key)"
                                                type="checkbox"
                                                class="mt-1 rounded border-zinc-300"
                                                @change="toggleSelection('permissions', permission.key)"
                                            />
                                            <div>
                                                <div class="text-sm font-medium">{{ permission.label }}</div>
                                                <div class="text-xs text-zinc-500">{{ permission.description }}</div>
                                            </div>
                                        </label>
                                    </div>
                                    <div v-if="form.errors.permissions" class="text-sm text-rose-600">{{ form.errors.permissions }}</div>
                                </div>

                                <div class="space-y-3">
                                    <div>
                                        <div class="text-sm font-medium">Области видимости</div>
                                        <div class="text-xs text-zinc-500">Какие разделы интерфейса видит роль</div>
                                    </div>
                                    <div class="space-y-2">
                                        <label
                                            v-for="area in generalVisibilityAreaOptions"
                                            :key="area.key"
                                            class="block border border-zinc-200 px-3 py-3 dark:border-zinc-800"
                                        >
                                            <div class="flex items-start gap-3">
                                                <input
                                                    :checked="form.visibility_areas.includes(area.key)"
                                                    type="checkbox"
                                                    class="mt-1 rounded border-zinc-300"
                                                    @change="toggleSelection('visibility_areas', area.key)"
                                                />
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-sm font-medium">{{ area.label }}</div>
                                                    <div class="text-xs text-zinc-500">{{ area.description }}</div>
                                                </div>
                                            </div>

                                            <div v-if="form.visibility_areas.includes(area.key)" class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="text-xs text-zinc-500">Объём данных внутри раздела</div>
                                                    <select
                                                        class="w-40 border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                                        :value="scopeModeFromForm(area.key)"
                                                        @change="updateVisibilityScope(area.key, $event.target.value)"
                                                    >
                                                        <option
                                                            v-for="scopeOption in visibilityScopeOptions"
                                                            :key="scopeOption.value"
                                                            :value="scopeOption.value"
                                                        >
                                                            {{ scopeOption.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <div v-if="form.errors.visibility_areas" class="text-sm text-rose-600">{{ form.errors.visibility_areas }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <button
                                type="button"
                                class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                @click="closeModal"
                            >
                                Отмена
                            </button>
                            <button
                                type="submit"
                                class="rounded-xl bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'roles' }, () => page),
});

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    permissionOptions: {
        type: Array,
        default: () => [],
    },
    visibilityAreaOptions: {
        type: Array,
        default: () => [],
    },
    visibilityScopeOptions: {
        type: Array,
        default: () => [],
    },
});

const sectionDefinitions = [
    { id: 'section-permissions', label: 'Права' },
    { id: 'section-visibility', label: 'Области видимости' },
    { id: 'section-widgets', label: 'Доступные виджеты' },
    { id: 'section-reports', label: 'Доступные отчёты' },
];

const activeSection = ref(sectionDefinitions[0].id);

const widgetAreaKeys = ['dashboard_tiles', 'dashboard_widgets'];
const reportAreaKeys = ['reports', 'dashboard_reports'];

const generalVisibilityAreaOptions = computed(() =>
    props.visibilityAreaOptions.filter(
        (area) => !widgetAreaKeys.includes(area.key) && !reportAreaKeys.includes(area.key),
    ),
);

const widgetVisibilityAreaOptions = computed(() =>
    props.visibilityAreaOptions.filter((area) => widgetAreaKeys.includes(area.key)),
);

const reportVisibilityAreaOptions = computed(() =>
    props.visibilityAreaOptions.filter((area) => reportAreaKeys.includes(area.key)),
);

const showModal = ref(false);
const editingRole = ref(null);

const form = useForm({
    name: '',
    display_name: '',
    description: '',
    has_signing_authority: false,
    permissions: [],
    visibility_areas: [],
    visibility_scopes: {},
});

function resetForm() {
    form.reset();
    form.clearErrors();
    form.name = '';
    form.display_name = '';
    form.description = '';
    form.has_signing_authority = false;
    form.permissions = [];
    form.visibility_areas = [];
    form.visibility_scopes = {};
}

function openCreateModal() {
    editingRole.value = null;
    resetForm();
    showModal.value = true;
}

/**
 * API и БД хранят visibility_scopes плоско: { orders: 'own', leads: 'all' }.
 * Форма работает с вложенным видом: { orders: { mode: 'own' } }.
 */
function normalizeVisibilityScopesForForm(scopes) {
    const out = {};
    if (!scopes || typeof scopes !== 'object') {
        return out;
    }
    for (const [key, val] of Object.entries(scopes)) {
        if (val === null || val === undefined) {
            continue;
        }
        if (typeof val === 'string' && (val === 'own' || val === 'all')) {
            out[key] = { mode: val };
        } else if (typeof val === 'object' && val !== null && (val.mode === 'own' || val.mode === 'all')) {
            out[key] = { mode: val.mode };
        }
    }

    return out;
}

function scopeModeFromForm(areaKey) {
    const v = form.visibility_scopes[areaKey];
    if (typeof v === 'string') {
        return v === 'own' || v === 'all' ? v : (areaKey === 'orders' ? 'own' : 'all');
    }

    return v?.mode ?? (areaKey === 'orders' ? 'own' : 'all');
}

function openEditModal(role) {
    editingRole.value = role;
    form.clearErrors();
    form.name = role.name;
    form.display_name = role.display_name;
    form.description = role.description || '';
    form.has_signing_authority = Boolean(role.default_has_signing_authority);
    form.permissions = [...role.permissions];
    form.visibility_areas = [...role.visibility_areas];
    form.visibility_scopes = normalizeVisibilityScopesForForm(role.visibility_scopes || {});
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingRole.value = null;
    resetForm();
}

function toggleSelection(field, key) {
    if (form[field].includes(key)) {
        form[field] = form[field].filter((item) => item !== key);

        if (field === 'visibility_areas') {
            const nextScopes = { ...form.visibility_scopes };
            delete nextScopes[key];
            form.visibility_scopes = nextScopes;
        }

        return;
    }

    form[field] = [...form[field], key];

    if (field === 'visibility_areas' && form.visibility_scopes[key] === undefined) {
        form.visibility_scopes = {
            ...form.visibility_scopes,
            [key]: { mode: key === 'orders' ? 'own' : 'all' },
        };
    }
}

function updateVisibilityScope(areaKey, mode) {
    form.visibility_scopes = {
        ...form.visibility_scopes,
        [areaKey]: { mode },
    };
}

function resolveScopeModeFromRole(scopes, areaKey) {
    const v = scopes?.[areaKey];
    if (typeof v === 'string') {
        return v === 'own' || v === 'all' ? v : undefined;
    }

    return v?.mode;
}

function visibilityScopeLabel(mode) {
    return props.visibilityScopeOptions.find((option) => option.value === mode)?.label ?? 'Только своё';
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingRole.value === null) {
        form.post(route('roles.store'), options);
        return;
    }

    form.patch(route('roles.update', editingRole.value.id), options);
}

function scrollToSection(sectionId) {
    activeSection.value = sectionId;

    if (typeof window === 'undefined') {
        return;
    }

    const element = document.getElementById(sectionId);

    if (! element) {
        return;
    }

    element.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function removeRole(role) {
    if (!window.confirm(`Удалить роль ${role.display_name}?`)) {
        return;
    }

    router.delete(route('roles.destroy', role.id), {
        preserveScroll: true,
    });
}
</script>
