<script setup>
import { crmBtnSecondary, crmFieldFluid } from '@/support/crmUi.js';

defineProps({
    selectedLeadId: {
        type: [Number, String, null],
        default: null,
    },
    attachments: {
        type: Array,
        default: () => [],
    },
    attachmentProcessing: {
        type: Boolean,
        default: false,
    },
    hasAttachmentFile: {
        type: Boolean,
        default: false,
    },
    formatAttachmentMeta: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['file-selected', 'upload', 'delete']);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="text-base font-semibold">Документы</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Пакинги, инвойсы и прочие файлы для работы с лидом. Не попадают в реестр «Документы».
            </p>
        </div>

        <div v-if="selectedLeadId" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    type="file"
                    class="max-w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-800 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-100"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp,.txt"
                    @change="emit('file-selected', $event)"
                />
                <button
                    type="button"
                    :class="crmBtnSecondary"
                    :disabled="!hasAttachmentFile || attachmentProcessing"
                    @click="emit('upload')"
                >
                    {{ attachmentProcessing ? 'Загрузка…' : 'Загрузить' }}
                </button>
            </div>

            <div v-if="attachments.length" class="space-y-2">
                <div
                    v-for="file in attachments"
                    :key="file.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                >
                    <div class="min-w-0">
                        <a :href="file.download_url" class="font-medium text-zinc-900 underline-offset-2 hover:underline dark:text-zinc-100">
                            {{ file.original_name }}
                        </a>
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ formatAttachmentMeta(file) }}
                        </div>
                    </div>
                    <button type="button" :class="crmBtnSecondary" @click="emit('delete', file)">Удалить</button>
                </div>
            </div>
            <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">Файлов пока нет.</p>
        </div>

        <div v-else class="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            Сохраните лид, чтобы прикреплять файлы.
        </div>
    </div>
</template>
