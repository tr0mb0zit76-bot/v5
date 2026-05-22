<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, Paperclip, Trash2 } from 'lucide-vue-next';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import { crmBtnCreate, crmFieldFluid, crmPanel, crmSectionTitle } from '@/support/crmUi.js';

const props = defineProps({
    entityKind: {
        type: String,
        required: true,
        validator: (value) => ['driver', 'vehicle'].includes(value),
    },
    entityId: { type: Number, required: true },
    documents: { type: Array, default: () => [] },
    documentTypeOptions: { type: Array, default: () => [] },
    defaultDocumentType: { type: String, default: 'other' },
});

const emit = defineEmits(['saved']);

const page = usePage();
const documentUploadHint = computed(() => page.props.document_upload_limits?.hint_ru ?? '');

const ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp';

const fileInputRef = ref(null);
const dropActive = ref(false);
const deletingDocId = ref(null);
let dropDepth = 0;

const uploadForm = useForm({
    document_type: props.defaultDocumentType,
    file: null,
});

const pendingFileName = computed(() => uploadForm.file?.name ?? null);

const storeRouteName = computed(() => (
    props.entityKind === 'driver' ? 'fleet.drivers.documents.store' : 'fleet.vehicles.documents.store'
));

const destroyRouteName = computed(() => (
    props.entityKind === 'driver' ? 'fleet.drivers.documents.destroy' : 'fleet.vehicles.documents.destroy'
));

function formatAddedAt(iso) {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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

async function assignFile(file) {
    if (!file) {
        return;
    }

    await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    uploadForm.file = file;
    uploadForm.clearErrors('file');
}

async function onDrop(event) {
    event.preventDefault();
    dropDepth = 0;
    dropActive.value = false;

    const file = event.dataTransfer?.files?.[0] ?? null;

    if (file) {
        await assignFile(file);
    }
}

function triggerFilePick() {
    fileInputRef.value?.click();
}

async function onFileInputChange(event) {
    const [file] = event.target.files ?? [];

    if (event.target) {
        event.target.value = '';
    }

    if (file) {
        await assignFile(file);
    }
}

function resetUploadForm() {
    uploadForm.reset();
    uploadForm.document_type = props.defaultDocumentType;
    uploadForm.file = null;
}

function submitUpload() {
    if (!uploadForm.file) {
        uploadForm.setError('file', 'Выберите файл.');

        return;
    }

    uploadForm.post(route(storeRouteName.value, props.entityId), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetUploadForm();
            emit('saved');
        },
    });
}

function destroyDocument(document) {
    if (!document?.id) {
        return;
    }

    if (!window.confirm(`Удалить файл «${document.original_name}»?`)) {
        return;
    }

    deletingDocId.value = document.id;
    router.delete(route(destroyRouteName.value, [props.entityId, document.id]), {
        preserveScroll: true,
        onFinish: () => {
            deletingDocId.value = null;
        },
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <section class="mt-10 space-y-6 border-t border-zinc-200 pt-6 dark:border-zinc-800">
        <h3 :class="crmSectionTitle">Документы</h3>
        <form :class="`${crmPanel} space-y-4 p-4`" @submit.prevent="submitUpload">
            <div>
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Добавить документ</div>
                <p v-if="documentUploadHint" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ documentUploadHint }}</p>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Тип документа</label>
                <select
                    v-model="uploadForm.document_type"
                    :class="`${crmFieldFluid} max-w-xs`"
                    required
                >
                    <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <p v-if="uploadForm.errors.document_type" class="text-xs text-rose-600 dark:text-rose-400">
                    {{ uploadForm.errors.document_type }}
                </p>
            </div>

            <input
                ref="fileInputRef"
                type="file"
                :accept="ACCEPT"
                class="hidden"
                @change="onFileInputChange"
            >

            <div
                class="rounded-xl border border-dashed px-4 py-5 text-center transition-colors"
                :class="dropActive
                    ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-950/40'
                    : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-950'"
                @dragenter.prevent="onDragEnter"
                @dragleave.prevent="onDragLeave"
                @dragover.prevent
                @drop.prevent="onDrop"
            >
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ pendingFileName ?? 'Перетащите файл сюда или выберите на диске' }}
                </p>
                <button
                    type="button"
                    class="mt-3 inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                    @click="triggerFilePick"
                >
                    <Paperclip class="h-4 w-4 text-zinc-500" />
                    {{ pendingFileName ? 'Заменить файл' : 'Выбрать файл' }}
                </button>
            </div>
            <p v-if="uploadForm.errors.file" class="text-xs text-rose-600 dark:text-rose-400">{{ uploadForm.errors.file }}</p>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    :class="crmBtnCreate"
                    :disabled="uploadForm.processing || !uploadForm.file"
                >
                    {{ uploadForm.processing ? 'Загрузка…' : 'Прикрепить' }}
                </button>
            </div>
        </form>

        <div class="space-y-3">
            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Сохранённые документы</div>

            <div :class="`${crmPanel} overflow-x-auto`">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50/80 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2.5">Добавлен</th>
                            <th class="px-3 py-2.5">Тип</th>
                            <th class="px-3 py-2.5">Файл</th>
                            <th class="px-3 py-2.5 text-right"> </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-950">
                        <tr v-for="document in documents" :key="document.id">
                            <td class="whitespace-nowrap px-3 py-2.5 text-zinc-600 dark:text-zinc-400">
                                {{ formatAddedAt(document.created_at) }}
                            </td>
                            <td class="px-3 py-2.5 text-zinc-700 dark:text-zinc-300">
                                {{ document.document_type_label ?? document.document_type }}
                            </td>
                            <td class="max-w-[240px] px-3 py-2.5">
                                <a
                                    v-if="document.preview_url"
                                    :href="document.preview_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex max-w-full items-center gap-1 truncate text-sky-700 underline dark:text-sky-300"
                                >
                                    <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">{{ document.original_name || 'Открыть' }}</span>
                                </a>
                                <a
                                    v-else-if="document.download_url"
                                    :href="document.download_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="truncate text-sky-700 underline dark:text-sky-300"
                                >
                                    {{ document.original_name || 'Скачать' }}
                                </a>
                                <span v-else class="text-zinc-500">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                    :disabled="deletingDocId === document.id"
                                    @click="destroyDocument(document)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Удалить
                                </button>
                            </td>
                        </tr>
                        <tr v-if="documents.length === 0">
                            <td colspan="4" class="px-3 py-10 text-center text-zinc-500 dark:text-zinc-400">
                                Документы пока не добавлены.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
