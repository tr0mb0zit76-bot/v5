<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            title="Шаблоны КП (HTML)"
            lead="Визуальный конструктор КП (GrapesJS): блоки и колонки, переменные лида, preview и PDF через Gotenberg."
        >
            <template #actions>
                <Link :href="route('modules.proposal-templates.create')" :class="crmBtnPrimary">
                    Новый шаблон
                </Link>
            </template>
        </CrmPageHeader>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Название</th>
                        <th class="px-4 py-3 text-left font-medium">Slug</th>
                        <th class="px-4 py-3 text-left font-medium">Версия</th>
                        <th class="px-4 py-3 text-left font-medium">Статус</th>
                        <th class="px-4 py-3 text-right font-medium">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <tr v-for="template in templates" :key="template.id">
                        <td class="px-4 py-3 font-medium">{{ template.name }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ template.slug }}</td>
                        <td class="px-4 py-3">{{ template.version }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="template.is_active
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                    : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'"
                            >
                                {{ template.is_active ? 'Активен' : 'Выключен' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="route('modules.proposal-templates.edit', template.id)"
                                class="text-emerald-700 hover:underline dark:text-emerald-300"
                            >
                                Редактировать
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="templates.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                            Шаблонов пока нет. Создайте первый HTML-шаблон КП.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnPrimary } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules' }, () => page),
});

defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
});
</script>
