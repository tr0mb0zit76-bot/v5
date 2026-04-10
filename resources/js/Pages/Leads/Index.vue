<template>
    <div class="flex h-full min-h-0 flex-col gap-2">
        <div v-if="featureUnavailable" class="border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200">
            Модуль лидов отключен для текущей схемы БД: таблицы лидов еще не развернуты.
        </div>
        <div class="min-h-0 flex-1 overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <LeadsGrid
                :rows="rows"
                :allow-create="!featureUnavailable"
                :can-filter-responsible="canFilterResponsible"
                @create="openCreateLead"
                @row-dblclick="handleRowDblClick"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import LeadsGrid from '@/Components/Leads/LeadsGrid.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'leads' }, () => page),
});

const page = usePage();
const rows = computed(() => page.props.leads ?? []);
const featureUnavailable = computed(() => Boolean(page.props.featureUnavailable));
const canFilterResponsible = computed(() => Boolean(page.props.canFilterResponsible));

function openCreateLead() {
    if (featureUnavailable.value) {
        return;
    }

    router.get(route('leads.create'));
}

function handleRowDblClick(row) {
    if (! featureUnavailable.value && row?.id) {
        router.get(route('leads.show', row.id));
    }
}
</script>
