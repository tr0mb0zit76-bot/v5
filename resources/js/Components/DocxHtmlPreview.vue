<template>
    <div class="flex min-h-0 flex-1 flex-col bg-zinc-50 dark:bg-zinc-950">
        <div v-if="loading" class="flex flex-1 items-center justify-center py-16 text-sm text-zinc-500 dark:text-zinc-400">
            Загрузка и разбор документа…
        </div>
        <div
            v-else-if="errorMessage"
            class="m-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
        >
            {{ errorMessage }}
        </div>
        <div
            v-else
            class="docx-html-preview min-h-0 flex-1 overflow-y-auto px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100"
            v-html="html"
        />
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    /** URL того же DOCX, что отдаёт сервер с preview=1 (GET, cookie сессии). */
    sourceUrl: {
        type: String,
        default: '',
    },
});

const loading = ref(false);
const errorMessage = ref('');
const html = ref('');

async function loadPreview() {
    const url = props.sourceUrl?.trim() ?? '';
    if (url === '') {
        errorMessage.value = '';
        html.value = '';

        return;
    }

    loading.value = true;
    errorMessage.value = '';
    html.value = '';

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Не удалось загрузить файл (${response.status}).`);
        }

        const buffer = await response.arrayBuffer();
        const mammoth = await import('mammoth');
        const result = await mammoth.convertToHtml({ arrayBuffer: buffer });
        const body = (result.value ?? '').trim();

        if (body === '') {
            errorMessage.value = 'Документ пустой или формат не поддерживается для предпросмотра. Скачайте DOCX и откройте в Word.';
        } else {
            html.value = result.value;
        }
    } catch (e) {
        const msg = e instanceof Error ? e.message : String(e);
        errorMessage.value = msg.includes('arrayBuffer')
            ? 'Не удалось прочитать ответ сервера. Попробуйте скачать DOCX.'
            : (msg || 'Ошибка при разборе DOCX. Скачайте файл и откройте в Word.');
    } finally {
        loading.value = false;
    }
}

watch(() => props.sourceUrl, loadPreview, { immediate: true });
</script>

<style scoped>
.docx-html-preview :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0.75rem 0;
    font-size: 0.8125rem;
}

.docx-html-preview :deep(th),
.docx-html-preview :deep(td) {
    border: 1px solid rgb(212 212 216);
    padding: 0.35rem 0.5rem;
    vertical-align: top;
}

.dark .docx-html-preview :deep(th),
.dark .docx-html-preview :deep(td) {
    border-color: rgb(63 63 70);
}

.docx-html-preview :deep(p) {
    margin: 0.4em 0;
}

.docx-html-preview :deep(ul),
.docx-html-preview :deep(ol) {
    margin: 0.4em 0;
    padding-left: 1.25rem;
}

.docx-html-preview :deep(h1),
.docx-html-preview :deep(h2),
.docx-html-preview :deep(h3) {
    margin: 0.75em 0 0.35em;
    font-weight: 600;
}
</style>
