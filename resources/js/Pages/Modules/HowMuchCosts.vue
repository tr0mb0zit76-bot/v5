<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            title="Сколько стоит?"
            lead="Калькулятор ставки на перевозку — в разработке."
        />

        <section :class="`${crmPanel} space-y-4 p-6`">
            <div class="flex items-start gap-4">
                <div class="crm-module-card__icon flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200">
                    <Calculator class="h-6 w-6" />
                </div>
                <div class="space-y-2">
                    <h2 :class="crmSectionTitle">Модуль в подготовке</h2>
                    <p :class="crmPageLead">
                        Здесь будет быстрая оценка стоимости рейса для клиента: маршрут, тариф, пробег,
                        тип транспорта и дополнительные условия — без привязки к закрытию заказа в CRM.
                    </p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        Для расчёта маржи по условиям вычета используйте модуль
                        <Link :href="route('sales-assistant.counter.index')" class="font-medium underline-offset-2 hover:underline">«Считалка»</Link>.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    Скоро
                </span>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Calculator } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmPageLead, crmPanel, crmSectionTitle } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules', activeSubKey: 'modules-how-much-costs' }, () => page),
});
</script>
