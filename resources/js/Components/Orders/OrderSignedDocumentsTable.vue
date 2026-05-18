<script setup>
import { computed } from 'vue';
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import { checklistMarksForDocument } from '@/support/orderPrintFormSlots.js';

const props = defineProps({
    documents: { type: Array, default: () => [] },
    documentTypeOptions: { type: Array, default: () => [] },
    requiredDocumentRules: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: true },
    deletingId: { type: [Number, null], default: null },
});

const emit = defineEmits(['delete', 'update:field']);

const typeLabelByValue = computed(() => {
    const map = new Map();

    (props.documentTypeOptions || []).forEach((opt) => {
        map.set(opt.value, opt.label);
    });

    return map;
});

function partyLabel(party) {
    if (party === 'customer') {
        return 'Заказчик';
    }

    if (party === 'carrier') {
        return 'Перевозчик';
    }

    return 'Внутренний';
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
                    <th class="px-3 py-2.5">Сторона</th>
                    <th class="px-3 py-2.5">Тип</th>
                    <th class="px-3 py-2.5">Номер</th>
                    <th class="px-3 py-2.5">Дата</th>
                    <th class="px-3 py-2.5">Файл</th>
                    <th class="px-3 py-2.5">Чек-лист</th>
                    <th v-if="canEdit" class="px-3 py-2.5 text-right"> </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-950">
                <tr v-if="documents.length === 0">
                    <td colspan="7" class="px-3 py-6 text-center text-zinc-500 dark:text-zinc-400">
                        Подписанных документов пока нет. Прикрепите файл выше.
                    </td>
                </tr>
                <tr v-for="doc in documents" :key="`signed-doc-${doc.id ?? doc._localKey}`">
                    <td class="px-3 py-2.5 whitespace-nowrap">{{ partyLabel(doc.party) }}</td>
                    <td class="px-3 py-2.5">
                        <select
                            v-if="canEdit && doc.id"
                            :value="doc.type"
                            class="w-full min-w-[140px] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(doc, 'type', $event.target.value)"
                        >
                            <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <span v-else>{{ typeLabelByValue.get(doc.type) ?? doc.type }}</span>
                    </td>
                    <td class="px-3 py-2.5">
                        <input
                            v-if="canEdit && doc.id"
                            :value="doc.number ?? ''"
                            type="text"
                            class="w-full min-w-[100px] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(doc, 'number', $event.target.value)"
                        >
                        <span v-else>{{ doc.number || '—' }}</span>
                    </td>
                    <td class="px-3 py-2.5">
                        <input
                            v-if="canEdit && doc.id"
                            :value="doc.document_date ?? ''"
                            type="date"
                            class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(doc, 'document_date', $event.target.value)"
                        >
                        <span v-else>{{ doc.document_date || '—' }}</span>
                    </td>
                    <td class="px-3 py-2.5 max-w-[200px]">
                        <a
                            v-if="doc.uploaded_file_preview_url"
                            :href="doc.uploaded_file_preview_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex max-w-full items-center gap-1 truncate text-sky-700 underline dark:text-sky-300"
                        >
                            <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                            <span class="truncate">{{ doc.original_name || 'Открыть' }}</span>
                        </a>
                        <span v-else class="text-zinc-500">{{ doc.original_name || '—' }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">
                        <template v-if="checklistMarksForDocument(doc, requiredDocumentRules).length > 0">
                            <span
                                v-for="mark in checklistMarksForDocument(doc, requiredDocumentRules)"
                                :key="`${doc.id}-chk-${mark}`"
                                class="mr-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200"
                            >
                                {{ mark }}
                            </span>
                        </template>
                        <span v-else>—</span>
                    </td>
                    <td v-if="canEdit" class="px-3 py-2.5 text-right">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            :disabled="deletingId === doc.id"
                            @click="emit('delete', doc)"
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
