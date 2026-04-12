<template>
    <div class="flex min-h-dvh flex-col bg-zinc-100 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-50">
        <header class="shrink-0 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Предпросмотр заявки</div>
                    <h1 class="truncate text-lg font-semibold">{{ documentTitle }}</h1>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        Заказ {{ orderNumber }}
                        <span v-if="workflowStatusLabel" class="text-zinc-500"> · {{ workflowStatusLabel }}</span>
                    </p>
                </div>
                <Link
                    :href="route('orders.edit', orderId)"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:hover:bg-zinc-800"
                >
                    ← К редактированию заказа
                </Link>
            </div>
        </header>

        <main class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col p-3">
            <p class="mb-2 text-xs text-zinc-500 dark:text-zinc-400">
                Ниже — текст того же DOCX, что уйдёт в согласование (показ в браузере может отличаться от Word). Для точного вида и печати скачайте файл и откройте в Word.
            </p>
            <div
                class="flex min-h-[min(78dvh,900px)] flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:min-h-[calc(100dvh-12rem)]"
            >
                <DocxHtmlPreview :source-url="embedUrl" />
            </div>
        </main>

        <footer class="shrink-0 border-t border-zinc-200 bg-white px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <Link
                        :href="route('orders.edit', orderId)"
                        class="text-sm font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-200"
                    >
                        Вернуться и исправить данные
                    </Link>
                    <a
                        v-if="downloadUrl"
                        :href="downloadUrl"
                        class="text-sm font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-400"
                    >
                        Скачать DOCX
                    </a>
                </div>
                <button
                    v-if="canRequestApproval"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    :disabled="submitting"
                    @click="sendForApproval"
                >
                    {{ submitting ? 'Отправка…' : 'Отправить на согласование' }}
                </button>
                <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Отправка на согласование сейчас недоступна (нет прав или документ уже не в черновике).
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import DocxHtmlPreview from '@/Components/DocxHtmlPreview.vue';
import { docxDownloadUrlFromEmbed } from '@/utils/docxPreviewUrls';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const props = defineProps({
    orderId: { type: Number, required: true },
    orderNumber: { type: String, required: true },
    documentId: { type: Number, required: true },
    documentTitle: { type: String, required: true },
    embedUrl: { type: String, required: true },
    workflowStatusLabel: { type: String, default: null },
    canRequestApproval: { type: Boolean, default: false },
});

const submitting = ref(false);

const downloadUrl = computed(() => docxDownloadUrlFromEmbed(props.embedUrl));

function sendForApproval() {
    submitting.value = true;
    router.post(
        route('orders.documents.request-approval', [props.orderId, props.documentId]),
        {},
        {
            preserveScroll: false,
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>