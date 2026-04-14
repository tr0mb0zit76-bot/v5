<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <div v-if="featureUnavailable" class="border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200">
            Модуль лидов отключен для текущей схемы БД: таблицы лидов еще не развернуты.
        </div>

        <div v-if="!featureUnavailable" class="flex shrink-0 items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Лиды</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Всего лидов: {{ rows.length }}</p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                @click="openCreateLead"
            >
                <Plus class="h-4 w-4" />
                Добавить
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden">
            <LeadsGrid
                :rows="rows"
                :available-columns="availableColumns"
                :role-columns-config="roleColumnsConfig"
                :user-id="userId"
                :allow-create="!featureUnavailable"
                @create="openCreateLead"
                @row-dblclick="handleRowDblClick"
            />
        </div>

        <Modal :show="isLeadModalOpen" max-width="7xl" @close="closeLeadModal">
            <section class="flex max-h-[calc(100dvh-3rem)] min-h-[78dvh] flex-col overflow-hidden bg-white dark:bg-zinc-900">
                <LeadWizard
                    embedded
                    :selected-lead="selectedLead"
                    :is-creating="isCreateModalOpen || isCreateRoute"
                    :contractors="page.props.contractors ?? []"
                    :responsible-users="page.props.responsibleUsers ?? []"
                    :status-options="page.props.statusOptions ?? []"
                    :source-options="page.props.sourceOptions ?? []"
                    :transport-type-options="page.props.transportTypeOptions ?? []"
                    :currency-options="page.props.currencyOptions ?? []"
                    :print-form-template-options="page.props.printFormTemplateOptions ?? []"
                    :current-user-id="page.props.currentUserId ?? null"
                    :can-assign-responsible="Boolean(page.props.canAssignResponsible)"
                    :can-use-lead-tasks="Boolean(page.props.canUseLeadTasks)"
                    @close="closeLeadModal"
                />
            </section>
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import LeadsGrid from '@/Components/Leads/LeadsGrid.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import Modal from '@/Components/Modal.vue';
import LeadWizard from '@/Pages/Leads/Wizard.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'leads' }, () => page),
});

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const rows = computed(() => page.props.leads ?? []);
const availableColumns = computed(() => page.props.leadColumns ?? []);
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});
const featureUnavailable = computed(() => Boolean(page.props.featureUnavailable));
const selectedLead = computed(() => page.props.selectedLead ?? null);
const isCreateRoute = computed(() => Boolean(page.props.isCreating));
const isCreateModalOpen = ref(false);
const isLeadModalDismissed = ref(false);
const isLeadModalOpen = computed(() => !featureUnavailable.value
    && (isCreateModalOpen.value || (isCreateRoute.value && !isLeadModalDismissed.value) || (selectedLead.value !== null && !isLeadModalDismissed.value)));

const modalPropKeys = [
    'selectedLead',
    'isCreating',
    'contractors',
    'responsibleUsers',
    'statusOptions',
    'sourceOptions',
    'transportTypeOptions',
    'currencyOptions',
    'printFormTemplateOptions',
    'currentUserId',
    'canAssignResponsible',
    'canUseLeadTasks',
];

watch(selectedLead, (lead) => {
    if (lead !== null) {
        isCreateModalOpen.value = false;
        isLeadModalDismissed.value = false;
    }
});

function openCreateLead() {
    if (featureUnavailable.value) {
        return;
    }

    isLeadModalDismissed.value = false;
    isCreateModalOpen.value = true;
    window.history.pushState(window.history.state, '', route('leads.create'));
}

function handleRowDblClick(row) {
    if (! featureUnavailable.value && row?.id) {
        isCreateModalOpen.value = false;
        isLeadModalDismissed.value = false;

        router.get(route('leads.show', row.id), {}, {
            preserveScroll: true,
            preserveState: true,
            only: modalPropKeys,
        });
    }
}

function closeLeadModal() {
    isCreateModalOpen.value = false;
    isLeadModalDismissed.value = true;
    window.history.replaceState(window.history.state, '', route('leads.index'));
}
</script>
