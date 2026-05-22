<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            title="Модули"
            lead="Отдельные рабочие инструменты CRM."
        />

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <button
                type="button"
                :class="crmModuleCard"
                @click="router.visit(route('modules.how-much-fits.index'))"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="crm-module-card__icon flex h-12 w-12 items-center justify-center rounded-2xl">
                        <Boxes class="h-6 w-6" />
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        Новый
                    </span>
                </div>
                <div class="mt-5 text-xl font-semibold">Сколько влезет?</div>
                <p class="mt-2 text-sm leading-6">
                    3D-планировщик загрузки: грузы, шаблоны транспорта, расчёт вместимости и картинка для клиента.
                </p>
                <div class="crm-module-card__cta mt-5 text-sm font-medium group-hover:underline">
                    Открыть модуль
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { Boxes } from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmModuleCard } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules', activeSubKey: 'modules-catalog' }, () => page),
});
</script>
