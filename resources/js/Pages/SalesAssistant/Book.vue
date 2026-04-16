<template>
    <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Книга продаж</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            База знаний в духе Notion: страницы, вложенная структура и совместная работа. Здесь появятся материалы для отдела продаж.
        </p>
        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
            Планируется импорт и хранение документов в формате <span class="font-mono text-zinc-800 dark:text-zinc-200">.md</span> (Markdown) с последующим редактированием в интерфейсе.
        </p>
    </div>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-book' }, () => page),
});
</script>
