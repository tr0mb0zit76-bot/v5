<script setup>
defineProps({
    documents: { type: Array, default: () => [] },
    heading: { type: String, default: 'Документы от нас' },
    hint: {
        type: String,
        default: 'Файлы от нас для скачивания (счета, закрывающие и другие исходящие документы).',
    },
    showEmpty: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Пока нет файлов.' },
});
</script>

<template>
    <section v-if="documents.length > 0 || showEmpty" class="space-y-3">
        <h2 class="text-sm font-medium text-zinc-800">{{ heading }}</h2>
        <p class="text-xs text-zinc-500">{{ hint }}</p>
        <ul
            v-if="documents.length > 0"
            class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white"
        >
            <li
                v-for="doc in documents"
                :key="doc.id"
                class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-zinc-900">
                        {{ doc.original_name || doc.type_label }}
                    </div>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        {{ doc.type_label }}
                        <template v-if="doc.number"> · № {{ doc.number }}</template>
                        <template v-if="doc.document_date"> · {{ doc.document_date }}</template>
                    </p>
                </div>
                <a
                    :href="doc.download_url"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-zinc-900 px-3 py-2 text-xs font-semibold text-white hover:bg-zinc-800"
                >
                    Скачать
                </a>
            </li>
        </ul>
        <p
            v-else
            class="rounded-xl border border-dashed border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-500"
        >
            {{ emptyText }}
        </p>
    </section>
</template>
