<template>
    <div class="flex h-full min-h-0 flex-col gap-2">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Пользователи</h1>
                <p class="text-sm text-zinc-500">
                    Всего: {{ users.length }} · Активных: {{ activeUsers.length }} · Неактивных: {{ inactiveUsers.length }}
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />
                Добавить пользователя
            </button>
        </div>

        <div class="flex items-center gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl border px-3 py-1.5 text-sm transition-colors"
                :class="activeTab === tab.key
                    ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                    : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="h-full overflow-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-100 dark:bg-zinc-800">
                        <tr class="text-left text-zinc-600 dark:text-zinc-200">
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Имя</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Email</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Роль</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Подпись</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Статус</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Создан</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in displayedUsers"
                            :key="user.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="px-3 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ user.email }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium dark:bg-zinc-800">
                                    {{ user.role?.display_name || user.role?.name || 'Без роли' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="user.has_signing_authority
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'"
                                >
                                    {{ user.has_signing_authority ? 'Есть' : 'Нет' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="user.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'"
                                >
                                    {{ user.is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ formatDate(user.created_at) }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-2 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        @click="openEditModal(user)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="user.id !== currentUserId"
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-2 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        @click="toggleActive(user)"
                                    >
                                        <Power class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="user.id !== currentUserId"
                                        type="button"
                                        class="rounded-lg border border-rose-200 p-2 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        @click="removeUser(user)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="displayedUsers.length === 0">
                            <td colspan="7" class="px-3 py-12 text-center text-zinc-500">
                                Пользователи в этой вкладке не найдены.
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
                <div class="w-full max-w-xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <div class="text-lg font-semibold">
                                {{ editingUser === null ? 'Новый пользователь' : 'Редактирование пользователя' }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ editingUser === null ? 'Создание учетной записи и назначение роли' : 'Изменение роли, статуса и базовых данных' }}
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

                    <form class="space-y-4 px-5 py-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Имя</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                            />
                            <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Email</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                            />
                            <div v-if="form.errors.email" class="text-sm text-rose-600">{{ form.errors.email }}</div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Роль</label>
                                <select
                                    v-model="form.role_id"
                                    class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                >
                                    <option :value="null">Без роли</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">
                                        {{ role.display_name || role.name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.role_id" class="text-sm text-rose-600">{{ form.errors.role_id }}</div>
                            </div>

                            <label class="flex items-center gap-3 pt-8 text-sm">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-zinc-300"
                                />
                                Активный пользователь
                            </label>
                        </div>

                        <label class="flex items-start gap-3 border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-800">
                            <input
                                v-model="form.has_signing_authority"
                                type="checkbox"
                                class="mt-1 rounded border-zinc-300"
                            />
                            <div>
                                <div class="font-medium">Имеет право подписи</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Фактическое персональное право пользователя на выпуск документов с подписью и печатью.
                                </div>
                            </div>
                        </label>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">
                                    {{ editingUser === null ? 'Пароль' : 'Новый пароль' }}
                                </label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                />
                                <div v-if="form.errors.password" class="text-sm text-rose-600">{{ form.errors.password }}</div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium">Подтверждение пароля</label>
                                <input
                                    v-model="form.password_confirmation"
                                    type="password"
                                    class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
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
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Power, Trash2, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'users' }, () => page),
});

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const showModal = ref(false);
const editingUser = ref(null);
const activeTab = ref('active');

const activeUsers = computed(() => props.users.filter((user) => user.is_active));
const inactiveUsers = computed(() => props.users.filter((user) => !user.is_active));
const displayedUsers = computed(() => activeTab.value === 'active' ? activeUsers.value : inactiveUsers.value);

const tabs = computed(() => [
    { key: 'active', label: `Активные (${activeUsers.value.length})` },
    { key: 'inactive', label: `Неактивные (${inactiveUsers.value.length})` },
]);

const form = useForm({
    name: '',
    email: '',
    role_id: null,
    is_active: true,
    has_signing_authority: false,
    password: '',
    password_confirmation: '',
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
}

function resetForm() {
    form.reset();
    form.clearErrors();
    form.name = '';
    form.email = '';
    form.role_id = null;
    form.is_active = true;
    form.has_signing_authority = false;
    form.password = '';
    form.password_confirmation = '';
}

function openCreateModal() {
    editingUser.value = null;
    resetForm();
    showModal.value = true;
}

function openEditModal(user) {
    editingUser.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role_id = user.role_id;
    form.is_active = user.is_active;
    form.has_signing_authority = Boolean(user.has_signing_authority);
    form.password = '';
    form.password_confirmation = '';
    showModal.value = true;
}

watch(() => form.role_id, (roleId) => {
    if (editingUser.value !== null) {
        return;
    }

    const selectedRole = props.roles.find((role) => role.id === roleId) ?? null;
    form.has_signing_authority = Boolean(selectedRole?.default_has_signing_authority);
});

function closeModal() {
    showModal.value = false;
    editingUser.value = null;
    resetForm();
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingUser.value === null) {
        form.post(route('users.store'), options);
        return;
    }

    form.patch(route('users.update', editingUser.value.id), options);
}

function buildUpdatePayload(user, overrides = {}) {
    return {
        name: user.name,
        email: user.email,
        role_id: user.role_id,
        is_active: user.is_active,
        has_signing_authority: user.has_signing_authority,
        password: '',
        password_confirmation: '',
        ...overrides,
    };
}

function toggleActive(user) {
    router.patch(route('users.update', user.id), buildUpdatePayload(user, {
        is_active: !user.is_active,
    }), {
        preserveScroll: true,
    });
}

function removeUser(user) {
    if (!window.confirm(`Удалить пользователя ${user.name}?`)) {
        return;
    }

    router.delete(route('users.destroy', user.id), {
        preserveScroll: true,
    });
}
</script>
