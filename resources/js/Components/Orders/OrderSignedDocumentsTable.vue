<script setup>
import { computed } from 'vue';
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import { buildRegistryTableRows } from '@/support/orderDocumentRegistryRows.js';
import {
    documentTypeDisplayLabel,
    isTransportDocumentType,
    TRANSPORT_SUBTYPE_OPTIONS,
    withTransportSubtypeOptions,
} from '@/support/orderDocumentTypes.js';

const props = defineProps({
    signedDocuments: { type: Array, default: () => [] },
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    documentTypeOptions: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: true },
    deletingId: { type: [Number, null], default: null },
});

const emit = defineEmits(['delete', 'update:field']);

const rows = computed(() => buildRegistryTableRows(
    props.signedDocuments,
    props.requiredDocumentRules,
    props.requiredDocumentChecklist,
    props.documentTypeOptions,
));

const typeLabelByValue = computed(() => {
    const map = new Map();

    (props.documentTypeOptions || []).forEach((opt) => {
        map.set(opt.value, opt.label);
    });

    return map;
});

const attachTypeOptions = computed(() => withTransportSubtypeOptions(props.documentTypeOptions || []));

function partyLabel(party) {
    if (party === 'customer') {
        return 'Заказчик';
    }

    if (party === 'carrier') {
        return 'Перевозчик';
    }

    if (party === 'contractor') {
        return 'Подрядчик';
    }

    return 'Внутренний';
}

function displayTypeLabel(row) {
    if (row.type_label) {
        return row.type_label;
    }

    return documentTypeDisplayLabel(row.type, typeLabelByValue.value);
}

function typeOptionsForRow(row) {
    if (isTransportDocumentType(row.type)) {
        return TRANSPORT_SUBTYPE_OPTIONS;
    }

    return attachTypeOptions.value;
}

function onFieldChange(doc, field, value) {
    emit('update:field', { id: doc.id, field, value });
}
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50/80 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                <tr>
                    <th class="w-10 px-3 py-2.5 text-center" title="Обязательный документ для этапов «Оплата» и «Завершено»">
                        ✓
                    </th>
                    <th class="px-3 py-2.5">Сторона</th>
                    <th class="px-3 py-2.5">Тип</th>
                    <th class="px-3 py-2.5">Номер</th>
                    <th class="px-3 py-2.5">Дата</th>
                    <th class="px-3 py-2.5">Файл</th>
                    <th v-if="canEdit" class="px-3 py-2.5 text-right"> </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-950">
                <tr
                    v-for="row in rows"
                    :key="`registry-row-${row.id ?? row._localKey}`"
                    :class="row.is_placeholder ? 'bg-zinc-50/80 dark:bg-zinc-900/30' : ''"
                >
                    <td class="px-3 py-2.5 text-center align-middle">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-600"
                            :checked="row.checklist_completed"
                            disabled
                            :title="row.requirement_label ?? 'Обязательный документ'"
                        >
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                        {{ partyLabel(row.party) }}
                    </td>
                    <td class="px-3 py-2.5 text-zinc-700 dark:text-zinc-300">
                        <select
                            v-if="canEdit && row.id && !row.is_placeholder"
                            :value="row.type"
                            class="w-full min-w-[140px] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(row, 'type', $event.target.value)"
                        >
                            <option v-for="opt in typeOptionsForRow(row)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <span v-else>{{ displayTypeLabel(row) }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <input
                            v-if="canEdit && row.id && !row.is_placeholder"
                            :value="row.number ?? ''"
                            type="text"
                            class="w-full min-w-[100px] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(row, 'number', $event.target.value)"
                        >
                        <span v-else>{{ row.is_placeholder ? '—' : (row.number || '—') }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <input
                            v-if="canEdit && row.id && !row.is_placeholder"
                            :value="row.document_date ?? ''"
                            type="date"
                            class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(row, 'document_date', $event.target.value)"
                        >
                        <span v-else>{{ row.is_placeholder ? '—' : (row.document_date || '—') }}</span>
                    </td>
                    <td class="max-w-[200px] px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <a
                            v-if="row.uploaded_file_preview_url"
                            :href="row.uploaded_file_preview_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex max-w-full items-center gap-1 truncate text-sky-700 underline dark:text-sky-300"
                        >
                            <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                            <span class="truncate">{{ row.original_name || 'Открыть' }}</span>
                        </a>
                        <span v-else>—</span>
                    </td>
                    <td v-if="canEdit" class="px-3 py-2.5 text-right">
                        <button
                            v-if="row.id && !row.is_placeholder"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            :disabled="deletingId === row.id"
                            @click="emit('delete', row)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Удалить
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
