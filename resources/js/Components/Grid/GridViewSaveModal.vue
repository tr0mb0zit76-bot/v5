<script setup>
import { computed, reactive, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import {
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
} from '@/support/crmUi.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'new' },
    initialName: { type: String, default: '' },
    initialVisibility: { type: String, default: 'private' },
    initialSharedWith: { type: Object, default: () => ({ role_ids: [], user_ids: [] }) },
    canShare: { type: Boolean, default: false },
    shareOptions: { type: Object, default: () => ({ roles: [], users: [] }) },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const form = reactive({
    name: '',
    visibility: 'private',
    roleIds: [],
    userIds: [],
});

const visibilityOptions = [
    { value: 'private', label: 'Только я' },
    { value: 'workspace', label: 'Все с доступом к гриду' },
    { value: 'role', label: 'Выбранные роли' },
    { value: 'users', label: 'Выбранные пользователи' },
];

const title = computed(() => {
    if (props.mode === 'share') {
        return 'Поделиться представлением';
    }

    if (props.mode === 'update') {
        return 'Сохранить представление';
    }

    return 'Новое представление';
});

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            return;
        }

        form.name = props.initialName ?? '';
        form.visibility = props.canShare ? (props.initialVisibility ?? 'private') : 'private';
        form.roleIds = [...(props.initialSharedWith?.role_ids ?? [])];
        form.userIds = [...(props.initialSharedWith?.user_ids ?? [])];
    },
);

function toggleId(list, id) {
    const index = list.indexOf(id);

    if (index === -1) {
        list.push(id);
    } else {
        list.splice(index, 1);
    }
}

function submit() {
    const trimmed = form.name.trim();

    if (trimmed === '') {
        return;
    }

    const payload = {
        name: trimmed,
        visibility: form.visibility,
    };

    if (props.canShare && form.visibility === 'role') {
        payload.shared_with = { role_ids: [...form.roleIds], user_ids: [] };
    } else if (props.canShare && form.visibility === 'users') {
        payload.shared_with = { role_ids: [], user_ids: [...form.userIds] };
    } else if (props.canShare && form.visibility === 'workspace') {
        payload.shared_with = null;
    } else {
        payload.shared_with = null;
        payload.visibility = 'private';
    }

    emit('submit', payload);
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="crm-modal-body space-y-4 p-6">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ title }}</h3>

            <div :class="crmModalFieldsWrap">
                <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                    <label :class="crmModalFieldLabel">Название</label>
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="120"
                        class="crm-input min-w-0 flex-1"
                        placeholder="Например: Мои активные"
                        @keyup.enter="submit"
                    />
                </div>
                <div v-if="canShare" :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                    <label :class="crmModalFieldLabel">Доступ</label>
                    <select v-model="form.visibility" class="crm-input min-w-0 flex-1">
                        <option v-for="option in visibilityOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="canShare && form.visibility === 'role'" class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                <label
                    v-for="role in shareOptions.roles ?? []"
                    :key="role.id"
                    class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                >
                    <input
                        type="checkbox"
                        :checked="form.roleIds.includes(role.id)"
                        @change="toggleId(form.roleIds, role.id)"
                    />
                    <span>{{ role.label }}</span>
                </label>
                <p v-if="(shareOptions.roles ?? []).length === 0" class="px-2 py-1 text-xs text-zinc-500">Роли не найдены</p>
            </div>

            <div v-if="canShare && form.visibility === 'users'" class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                <label
                    v-for="user in shareOptions.users ?? []"
                    :key="user.id"
                    class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                >
                    <input
                        type="checkbox"
                        :checked="form.userIds.includes(user.id)"
                        @change="toggleId(form.userIds, user.id)"
                    />
                    <span class="truncate">{{ user.label }}</span>
                </label>
                <p v-if="(shareOptions.users ?? []).length === 0" class="px-2 py-1 text-xs text-zinc-500">Пользователи не найдены</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <button type="button" class="crm-btn-secondary" :disabled="saving" @click="emit('close')">Отмена</button>
                <button type="button" class="crm-btn-primary" :disabled="saving || form.name.trim() === ''" @click="submit">
                    {{ saving ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
