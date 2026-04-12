<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-3 sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="docx-preview-modal-title"
            @click.self="emit('close')"
        >
            <div
                class="flex max-h-[92dvh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <h2 id="docx-preview-modal-title" class="min-w-0 truncate text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ title }}
                    </h2>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        aria-label="Закрыть"
                        @click="emit('close')"
                    >
                        ×
                    </button>
                </div>
                <p class="shrink-0 border-b border-zinc-100 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    {{ hint }}
                </p>
                <div v-if="embedUrl" class="flex min-h-[55dvh] min-w-0 flex-1 flex-col">
                    <DocxHtmlPreview :source-url="embedUrl" />
                </div>
                <div
                    class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-800"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            v-if="downloadUrl"
                            :href="downloadUrl"
                            class="text-sm font-medium text-sky-700 underline-offset-2 hover:underline dark:text-sky-400"
                        >
                            Скачать DOCX
                        </a>
                        <a
                            v-if="fullPageUrl"
                            :href="fullPageUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                        >
                            {{ fullPageLinkText }}
                        </a>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        @click="emit('close')"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import DocxHtmlPreview from '@/Components/DocxHtmlPreview.vue';
import { docxDownloadUrlFromEmbed } from '@/utils/docxPreviewUrls';
import { computed } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    embedUrl: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: 'Предпросмотр',
    },
    /** Отдельная страница Inertia (например, с кнопкой «Отправить на согласование»). */
    fullPageUrl: {
        type: String,
        default: '',
    },
    fullPageLabel: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: 'Текст документа показан ниже (конвертация в браузере). Для точного вида как в Word скачайте DOCX.',
    },
});

const emit = defineEmits(['close']);

const downloadUrl = computed(() => docxDownloadUrlFromEmbed(props.embedUrl));

const fullPageLinkText = computed(() => {
    const raw = props.fullPageLabel?.trim() ?? '';

    return raw !== '' ? raw : 'Страница с отправкой на согласование';
});
</script>
