<script setup>
import { Link } from '@inertiajs/vue3';
import { crmBtnPrimary } from '@/support/crmUi.js';

defineProps({
    docs: { type: Array, default: () => [] },
    isEditable: { type: Boolean, default: true },
    documentStorage: { type: Object, default: () => ({}) },
    rejectTargetId: { type: [Number, null], default: null },
    rejectReason: { type: String, default: '' },
});

const emit = defineEmits([
    'workflow-action',
    'toggle-reject',
    'submit-reject',
    'discard',
    'update:reject-reason',
]);

function title(doc) {
    return doc?.print_template_name || doc?.original_name || 'Документ';
}
</script>

<template>
    <div v-if="docs.length === 0" class="rounded-xl border border-dashed border-zinc-300/80 px-3 py-3 text-sm text-zinc-500 dark:border-zinc-700">
        Печатных форм по этому блоку пока нет.
    </div>

    <div
        v-for="doc in docs"
        :key="`print-wf-${doc.id}`"
        class="space-y-3 rounded-xl border border-zinc-200 bg-white/80 p-3 dark:border-zinc-700 dark:bg-zinc-900/40"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm font-medium">
                {{ title(doc) }}
                <span
                    v-if="doc.workflow_status_label"
                    class="ml-2 inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-normal text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    {{ doc.workflow_status_label }}
                </span>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="doc.draft_preview_url"
                    class="rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                    :href="doc.draft_preview_url"
                >
                    Предпросмотр
                </Link>
                <a
                    v-if="doc.draft_download_url"
                    class="rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                    :href="doc.draft_download_url"
                >
                    Скачать черновик DOCX
                </a>
                <a
                    v-if="doc.final_pdf_download_url"
                    class="rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                    :href="doc.final_pdf_download_url"
                >
                    Скачать PDF
                </a>
            </div>
        </div>
        <p v-if="doc.rejection_reason" class="text-xs text-rose-700 dark:text-rose-300">
            Причина отклонения: {{ doc.rejection_reason }}
        </p>
        <div class="flex flex-wrap gap-2">
            <button
                v-if="doc.can_request_approval"
                type="button"
                :class="`${crmBtnPrimary} px-3 py-1.5 text-xs`"
                :disabled="!isEditable"
                @click="emit('workflow-action', 'request-approval', doc.id)"
            >
                Отправить на согласование
            </button>
            <button
                v-if="doc.can_regenerate_draft"
                type="button"
                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs dark:border-zinc-600"
                :disabled="!isEditable"
                @click="emit('workflow-action', 'regenerate-draft', doc.id)"
            >
                Пересоздать черновик
            </button>
            <button
                v-if="doc.can_approve"
                type="button"
                class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs text-white"
                @click="emit('workflow-action', 'approve', doc.id)"
            >
                Подписать
            </button>
            <button
                v-if="doc.can_reject"
                type="button"
                class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs text-rose-700 dark:border-rose-800 dark:text-rose-300"
                @click="emit('toggle-reject', doc.id)"
            >
                Отказать
            </button>
            <button
                v-if="doc.can_discard_print_draft"
                type="button"
                class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs text-rose-700 dark:border-rose-800 dark:text-rose-300"
                :disabled="!isEditable"
                @click="emit('discard', doc)"
            >
                Удалить черновик
            </button>
        </div>
        <div v-if="rejectTargetId === doc.id" class="space-y-2 rounded-lg border border-rose-200 bg-rose-50/50 p-2 dark:border-rose-900 dark:bg-rose-950/30">
            <label class="text-xs font-medium text-rose-900 dark:text-rose-200">Причина отклонения</label>
            <textarea
                :value="rejectReason"
                rows="2"
                class="w-full rounded-lg border border-rose-200 bg-white px-2 py-1.5 text-sm dark:border-rose-800 dark:bg-zinc-950"
                @input="emit('update:reject-reason', $event.target.value)"
            />
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-rose-700 px-3 py-1 text-xs text-white"
                    :disabled="!rejectReason.trim()"
                    @click="emit('submit-reject', doc.id)"
                >
                    Подтвердить
                </button>
                <button type="button" class="rounded-lg border px-3 py-1 text-xs dark:border-zinc-600" @click="emit('toggle-reject', doc.id)">
                    Отмена
                </button>
            </div>
        </div>
    </div>
</template>
