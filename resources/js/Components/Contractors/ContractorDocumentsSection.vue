<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Paperclip, Trash2 } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import { crmBtnCreate, crmBtnNeutral, crmModalFieldLabel, crmModalFieldRow, crmModalFieldsWrap } from '@/support/crmUi.js';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import { usePage } from '@inertiajs/vue3';

const documents = defineModel({ type: Array, default: () => [] });

const page = usePage();

const ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp';
const ALLOWED_EXTENSIONS = new Set(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp']);

const dropActive = ref(false);
let dropDepth = 0;
const fileInputRef = ref(null);
const attachModalOpen = ref(false);
const attachPendingFile = ref(null);
const attachTitle = ref('');
const attachDocumentDate = ref('');
const attachModalFileInputRef = ref(null);
const localPreviewUrls = ref({});

function todayIsoDate() {
    return new Date().toISOString().slice(0, 10);
}

function extensionFromFile(file) {
    const name = String(file?.name ?? '');
    const parts = name.split('.');

    return parts.length > 1 ? parts.pop().toLowerCase() : '';
}

function isAllowedFile(file) {
    return ALLOWED_EXTENSIONS.has(extensionFromFile(file));
}

function onDragEnter() {
    dropDepth += 1;
    dropActive.value = true;
}

function onDragLeave() {
    dropDepth = Math.max(0, dropDepth - 1);
    if (dropDepth === 0) {
        dropActive.value = false;
    }
}

function onDragOver(event) {
    event.preventDefault();
}

async function openAttachModal(file) {
    if (!file || !isAllowedFile(file)) {
        window.alert('Недопустимый тип файла. Разрешены: PDF, Word, Excel, JPG, PNG, WebP.');

        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});

    attachPendingFile.value = file;
    attachTitle.value = file.name.replace(/\.[^.]+$/, '') || file.name;
    attachDocumentDate.value = todayIsoDate();
    attachModalOpen.value = true;
}

async function onDrop(event) {
    event.preventDefault();
    dropDepth = 0;
    dropActive.value = false;

    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file) {
        await openAttachModal(file);
    }
}

function triggerFilePick() {
    fileInputRef.value?.click();
}

async function onFileInputChange(event) {
    const file = event.target.files?.[0] ?? null;
    if (file) {
        await openAttachModal(file);
    }

    if (event.target) {
        event.target.value = '';
    }
}

async function onAttachModalFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    if (!file) {
        return;
    }

    if (!isAllowedFile(file)) {
        window.alert('Недопустимый тип файла. Разрешены: PDF, Word, Excel, JPG, PNG, WebP.');

        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    attachPendingFile.value = file;

    if (!String(attachTitle.value ?? '').trim()) {
        attachTitle.value = file.name.replace(/\.[^.]+$/, '') || file.name;
    }

    if (event.target) {
        event.target.value = '';
    }
}

function closeAttachModal() {
    attachModalOpen.value = false;
    attachPendingFile.value = null;
    attachTitle.value = '';
    attachDocumentDate.value = '';
    attachModalFileInputRef.value && (attachModalFileInputRef.value.value = '');
}

function confirmAttach() {
    const file = attachPendingFile.value;
    const title = String(attachTitle.value ?? '').trim();

    if (!file) {
        window.alert('Выберите файл.');

        return;
    }

    if (!title) {
        window.alert('Укажите название документа.');

        return;
    }

    const index = documents.value.length;
    documents.value = [
        ...documents.value,
        {
            type: '',
            title,
            number: '',
            document_date: attachDocumentDate.value || todayIsoDate(),
            status: '',
            notes: '',
            file,
            original_name: file.name,
            preview_url: null,
            created_at: null,
        },
    ];

    localPreviewUrls.value[index] = URL.createObjectURL(file);
    closeAttachModal();
}

function removeDocument(index) {
    if (localPreviewUrls.value[index]) {
        URL.revokeObjectURL(localPreviewUrls.value[index]);
        delete localPreviewUrls.value[index];
    }

    documents.value = documents.value.filter((_, rowIndex) => rowIndex !== index);
}

function previewHref(document, index) {
    if (document.preview_url) {
        return document.preview_url;
    }

    return localPreviewUrls.value[index] ?? null;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value).slice(0, 10);
    }

    return date.toLocaleDateString('ru-RU');
}

function addedAtLabel(document) {
    if (document.created_at) {
        return formatDate(document.created_at);
    }

    return 'Не сохранён';
}

const hasDocuments = computed(() => documents.value.length > 0);

onBeforeUnmount(() => {
    Object.values(localPreviewUrls.value).forEach((url) => {
        if (url) {
            URL.revokeObjectURL(url);
        }
    });
});
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-2">
            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Прикрепить документ</div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Перетащите файл в область ниже или нажмите «Прикрепить». После выбора файла укажите название и дату документа.
            </p>
            <input
                ref="fileInputRef"
                type="file"
                :accept="ACCEPT"
                class="hidden"
                @change="onFileInputChange"
            >
            <div
                class="border border-dashed px-4 py-6 text-center transition-colors"
                :class="dropActive
                    ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-950/40'
                    : 'border-zinc-300 bg-zinc-50/40 dark:border-zinc-700 dark:bg-zinc-900/20'"
                @dragenter.prevent="onDragEnter"
                @dragleave.prevent="onDragLeave"
                @dragover.prevent="onDragOver"
                @drop.prevent="onDrop"
            >
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Перетащите файл сюда или нажмите «Прикрепить»
                </p>
                <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-900"
                        @click="triggerFilePick"
                    >
                        <Paperclip class="h-4 w-4 text-zinc-500" />
                        Прикрепить
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-800">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">Дата добавления</th>
                        <th class="px-3 py-2 font-medium">Название</th>
                        <th class="px-3 py-2 font-medium">Дата документа</th>
                        <th class="px-3 py-2 font-medium">Файл</th>
                        <th class="px-3 py-2 font-medium w-16" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <tr v-for="(document, index) in documents" :key="`contractor-document-${document.id ?? index}`">
                        <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">
                            {{ addedAtLabel(document) }}
                        </td>
                        <td class="px-3 py-2 font-medium text-zinc-900 dark:text-zinc-100">
                            {{ document.title || '—' }}
                        </td>
                        <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">
                            {{ formatDate(document.document_date) }}
                        </td>
                        <td class="px-3 py-2">
                            <a
                                v-if="previewHref(document, index)"
                                :href="previewHref(document, index)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sky-700 hover:underline dark:text-sky-300"
                            >
                                {{ document.original_name || document.title || 'Открыть' }}
                            </a>
                            <span v-else class="text-zinc-500 dark:text-zinc-400">—</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center text-rose-600 hover:text-rose-700 dark:text-rose-300"
                                title="Удалить"
                                @click="removeDocument(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!hasDocuments">
                        <td colspan="5" class="px-3 py-10 text-center text-zinc-500 dark:text-zinc-400">
                            Документы пока не добавлены.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Modal :show="attachModalOpen" max-width="md" @close="closeAttachModal">
        <CrmModalHeader title="Описание документа" @close="closeAttachModal" />
        <div class="space-y-4 border-t border-zinc-200 px-5 py-5 dark:border-zinc-800 sm:px-6">
            <div v-if="attachPendingFile" class="flex items-center gap-2 border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/50">
                <Paperclip class="h-4 w-4 shrink-0 text-zinc-500" />
                <span class="min-w-0 truncate font-medium text-zinc-800 dark:text-zinc-100">{{ attachPendingFile.name }}</span>
            </div>
            <div v-else>
                <label class="inline-flex cursor-pointer items-center gap-2 border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                    <span>Выбрать файл…</span>
                    <input
                        ref="attachModalFileInputRef"
                        type="file"
                        :accept="ACCEPT"
                        class="hidden"
                        @change="onAttachModalFileChange"
                    >
                </label>
            </div>

            <div :class="crmModalFieldsWrap">
                <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                    <label :class="crmModalFieldLabel">Название</label>
                    <input
                        v-model="attachTitle"
                        type="text"
                        class="crm-field crm-field--fluid min-w-0 flex-1 py-1.5"
                    >
                </div>
                <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                    <label :class="crmModalFieldLabel">Дата</label>
                    <input
                        v-model="attachDocumentDate"
                        type="date"
                        class="crm-field crm-field--fluid min-w-0 flex-1 py-1.5"
                    >
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <button type="button" :class="crmBtnNeutral" @click="closeAttachModal">Отмена</button>
                <button type="button" :class="crmBtnCreate" :disabled="!attachPendingFile" @click="confirmAttach">
                    Добавить
                </button>
            </div>
        </div>
    </Modal>
</template>
