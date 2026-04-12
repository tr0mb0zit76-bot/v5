<template>
    <div class="flex h-full min-h-0 flex-col gap-2">
        <div v-if="featureUnavailable" class="border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200">
            Модуль лидов отключен для текущей схемы БД: таблицы лидов еще не развернуты.
        </div>
        <div class="min-h-0 flex-1 overflow-hidden">
            <LeadsGrid
                :rows="rows"
                :available-columns="availableColumns"
                :role-columns-config="roleColumnsConfig"
                :user-id="userId"
                :allow-create="!featureUnavailable"
                :can-filter-responsible="canFilterResponsible"
                :print-form-templates="leadPrintFormTemplates"
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
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const rows = computed(() => page.props.leads ?? []);
const availableColumns = computed(() => page.props.leadColumns ?? []);
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});
const featureUnavailable = computed(() => Boolean(page.props.featureUnavailable));
const canFilterResponsible = computed(() => Boolean(page.props.canFilterResponsible));
const leadPrintFormTemplates = computed(() => page.props.leadPrintFormTemplates ?? []);

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
