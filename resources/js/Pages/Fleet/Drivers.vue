<template>
    <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Водители</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Раздел в разработке.</p>
    </div>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'fleet', activeSubKey: 'fleet-drivers' }, () => page),
});
</script>
