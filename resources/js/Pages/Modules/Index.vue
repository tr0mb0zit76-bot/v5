<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <div class="flex shrink-0 items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Модули</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Отдельные рабочие инструменты CRM.</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <button
                type="button"
                class="group rounded-3xl border border-zinc-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-sky-700"
                @click="router.visit(route('modules.how-much-fits.index'))"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200">
                        <Boxes class="h-6 w-6" />
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        Новый
                    </span>
                </div>
                <div class="mt-5 text-xl font-semibold text-zinc-900 dark:text-zinc-50">Сколько влезет?</div>
                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    3D-планировщик загрузки: грузы, шаблоны транспорта, расчёт вместимости и картинка для клиента.
                </p>
                <div class="mt-5 text-sm font-medium text-sky-700 group-hover:underline dark:text-sky-300">
                    Открыть модуль
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { Boxes } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules', activeSubKey: 'modules-catalog' }, () => page),
});
</script>
