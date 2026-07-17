<script setup>
import { computed, watch } from 'vue';
import { crmFieldFluid } from '@/support/crmUi.js';

const props = defineProps({
    recipients: { type: Array, default: () => [] },
    selectedEmails: { type: Array, default: () => [] },
    extraRaw: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    emptyHint: { type: String, default: 'У связанной компании нет контактов с e-mail.' },
});

const emit = defineEmits(['update:selectedEmails', 'update:extraRaw']);

const selectedSet = computed(() => new Set(
    (props.selectedEmails ?? []).map((email) => String(email).trim().toLowerCase()).filter(Boolean),
));

watch(
    () => props.recipients,
    (list) => {
        if (!Array.isArray(list) || list.length === 0) {
            return;
        }

        if ((props.selectedEmails ?? []).length > 0) {
            return;
        }

        const primary = list.find((row) => row?.is_primary && row?.email);
        const fallback = list.find((row) => row?.email);
        const pick = primary || fallback;
        if (pick?.email) {
            emit('update:selectedEmails', [String(pick.email).trim().toLowerCase()]);
        }
    },
    { immediate: true },
);

function toggleEmail(email, checked) {
    const normalized = String(email || '').trim().toLowerCase();
    if (!normalized) {
        return;
    }

    const next = new Set(selectedSet.value);
    if (checked) {
        next.add(normalized);
    } else {
        next.delete(normalized);
    }

    emit('update:selectedEmails', [...next]);
}

function isChecked(email) {
    return selectedSet.value.has(String(email || '').trim().toLowerCase());
}
</script>

<template>
    <div class="space-y-2">
        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Кому</div>
        <p v-if="loading" class="text-xs text-zinc-500">Загружаем контакты…</p>
        <div
            v-else-if="recipients.length > 0"
            class="max-h-40 space-y-1.5 overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50/70 p-2 dark:border-zinc-700 dark:bg-zinc-950/40"
        >
            <label
                v-for="row in recipients"
                :key="row.key || row.email"
                class="flex cursor-pointer items-start gap-2 rounded-md px-1.5 py-1 text-sm hover:bg-white/80 dark:hover:bg-zinc-900/80"
            >
                <input
                    type="checkbox"
                    class="mt-0.5 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"
                    :checked="isChecked(row.email)"
                    @change="toggleEmail(row.email, $event.target.checked)"
                >
                <span class="min-w-0">
                    <span class="block truncate font-medium text-zinc-900 dark:text-zinc-100">{{ row.name }}</span>
                    <span class="block truncate text-xs text-zinc-500">{{ row.email }}</span>
                </span>
            </label>
        </div>
        <p v-else class="text-xs text-zinc-500">{{ emptyHint }}</p>
        <input
            :value="extraRaw"
            type="text"
            :class="crmFieldFluid"
            placeholder="Дополнительно (через запятую)"
            @input="emit('update:extraRaw', $event.target.value)"
        >
    </div>
</template>
