<template>
    <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Тренажёр</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Интерактивные тренировки переговоров для менеджеров. Модуль будет опираться на опубликованные сценарии из раздела «Скрипты» и на AI-агента: разбор реплик, подсказки и адаптация сложности.
        </p>
        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
            Сейчас это заглушка: связь со «Скриптами» и обучающий агент появятся на следующих этапах.
        </p>
    </div>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-trainer' }, () => page),
});
</script>
