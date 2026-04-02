<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Панель</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Добро пожаловать в CRM.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-3xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">Заказы</div>
                <div class="text-3xl font-semibold mt-2">24</div>
            </div>

            <div class="rounded-3xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">Контрагенты</div>
                <div class="text-3xl font-semibold mt-2">128</div>
            </div>

            <div class="rounded-3xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">Документы</div>
                <div class="text-3xl font-semibold mt-2">61</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'dashboard' }, () => page),
});
</script>