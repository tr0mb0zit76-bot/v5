<template>
    <div class="flex min-h-0 flex-1 flex-col gap-1.5">
        <div v-if="featureUnavailable" class="border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200">
            Модуль лидов отключен для текущей схемы БД: таблицы лидов еще не развернуты.
        </div>

        <div
            v-if="!featureUnavailable"
            class="flex shrink-0 flex-wrap items-center justify-between gap-2"
        >
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                <h1 :class="crmPageTitleSm">Лиды</h1>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">Всего лидов: {{ rows.length }}</span>
            </div>
            <button
                type="button"
                :class="crmBtnCreate"
                @click="openCreateLead"
            >
                <Plus class="h-4 w-4" />
                Добавить
            </button>
        </div>

        <div
            v-if="showLeadSummaries"
            ref="leadSummariesRef"
            class="grid shrink-0 gap-2 lg:grid-cols-2 lg:items-stretch"
        >
            <LeadSalesCoachingPanel
                v-if="hasSalesCoachingInsights"
                compact
                :insights="salesCoachingInsights"
            />
            <LeadAttentionPanel
                v-if="hasLeadAttentionQueue"
                compact
                :queue="leadAttentionQueue"
                @open-lead="handleRowDblClick"
            />
        </div>

        <div :class="crmGridPanel">
            <LeadsGrid
                ref="leadsGridRef"
                :rows="rows"
                :available-columns="availableColumns"
                :role-columns-config="roleColumnsConfig"
                :user-id="userId"
                :source-options="page.props.sourceOptions ?? []"
                :status-options="page.props.statusOptions ?? []"
                :responsible-users="page.props.responsibleUsers ?? []"
                :can-assign-responsible="Boolean(page.props.canAssignResponsible)"
                :allow-create="!featureUnavailable"
                @create="openCreateLead"
                @create-from="openCreateLeadFrom"
                @row-dblclick="handleRowDblClick"
                @delete-request="handleLeadDeleteRequest"
            />
        </div>

        <Modal :show="isLeadModalOpen" max-width="7xl" @close="closeLeadModal">
            <section class="flex max-h-[calc(100dvh-3rem)] min-h-[78dvh] flex-col overflow-hidden bg-white dark:bg-zinc-900">
                <LeadWizard
                    class="min-h-0 flex-1"
                    embedded
                    :selected-lead="(isCreateModalOpen || isCreateRoute) ? null : selectedLead"
                    :is-creating="isCreateModalOpen || isCreateRoute"
                    :lead-template="leadTemplate"
                    :contractors="page.props.contractors ?? []"
                    :responsible-users="page.props.responsibleUsers ?? []"
                    :status-options="page.props.statusOptions ?? []"
                    :source-options="page.props.sourceOptions ?? []"
                    :transport-type-options="page.props.transportTypeOptions ?? []"
                    :currency-options="page.props.currencyOptions ?? []"
                    :payment-form-options="page.props.paymentFormOptions ?? []"
                    :print-form-template-options="page.props.printFormTemplateOptions ?? []"
                    :proposal-html-template-options="page.props.proposalHtmlTemplateOptions ?? []"
                    :current-user-id="page.props.currentUserId ?? null"
                    :can-assign-responsible="Boolean(page.props.canAssignResponsible)"
                    :can-use-lead-tasks="Boolean(page.props.canUseLeadTasks)"
                    :business-processes-enabled="Boolean(page.props.businessProcessesEnabled)"
                    :business-processes="page.props.businessProcesses ?? []"
                    :lost-close-outcome-options="page.props.lostCloseOutcomeOptions ?? []"
                    :won-close-outcome-options="page.props.wonCloseOutcomeOptions ?? []"
                    :cargo-type-options="page.props.cargoTypeOptions ?? []"
                    :package-type-options="page.props.packageTypeOptions ?? []"
                    :loading-type-options="page.props.loadingTypeOptions ?? []"
                    :truck-body-type-options="page.props.truckBodyTypeOptions ?? []"
                    :trailer-type-options="page.props.trailerTypeOptions ?? []"
                    :cargo-title-suggestions="page.props.cargoTitleSuggestions ?? []"
                    :sales-coaching-insights="page.props.salesCoachingInsights ?? null"
                    @close="closeLeadModal"
                />
            </section>
        </Modal>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { crmBtnCreate, crmGridPanel, crmPageTitleSm } from '@/support/crmUi.js';
import LeadsGrid from '@/Components/Leads/LeadsGrid.vue';
import LeadSalesCoachingPanel from '@/Components/Leads/LeadSalesCoachingPanel.vue';
import LeadAttentionPanel from '@/Components/Leads/LeadAttentionPanel.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import Modal from '@/Components/Modal.vue';
import LeadWizard from '@/Pages/Leads/Wizard.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'leads', mainFill: true }, () => page),
});

const page = usePage();
const leadSummariesRef = ref(null);
const leadsGridRef = ref(null);
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const rows = computed(() => page.props.leads ?? []);
const salesCoachingInsights = computed(() => page.props.salesCoachingInsights ?? null);
const leadAttentionQueue = computed(() => page.props.leadAttentionQueue ?? null);
const availableColumns = computed(() => page.props.leadColumns ?? []);
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});
const featureUnavailable = computed(() => Boolean(page.props.featureUnavailable));
const selectedLead = computed(() => page.props.selectedLead ?? null);
const isCreateRoute = computed(() => Boolean(page.props.isCreating));
const leadTemplate = computed(() => page.props.leadTemplate ?? null);
const isCreateModalOpen = ref(false);
const isLeadModalDismissed = ref(false);
const isLeadModalOpen = computed(() => !featureUnavailable.value
    && (isCreateModalOpen.value || (isCreateRoute.value && !isLeadModalDismissed.value) || (selectedLead.value !== null && !isLeadModalDismissed.value)));

const hasSalesCoachingInsights = computed(() => Boolean(salesCoachingInsights.value?.available));
const hasLeadAttentionQueue = computed(() => Boolean(leadAttentionQueue.value?.available && leadAttentionQueue.value?.total > 0));
const showLeadSummaries = computed(() => !featureUnavailable.value
    && !isLeadModalOpen.value
    && (hasSalesCoachingInsights.value || hasLeadAttentionQueue.value));

let summariesResizeObserver = null;

function refreshLeadsGridLayout() {
    nextTick(() => {
        leadsGridRef.value?.refreshAgGridPanelLayout?.();
    });
}

function bindSummariesResizeObserver() {
    if (typeof ResizeObserver === 'undefined' || !leadSummariesRef.value) {
        return;
    }

    summariesResizeObserver?.disconnect();
    summariesResizeObserver = new ResizeObserver(() => {
        refreshLeadsGridLayout();
    });
    summariesResizeObserver.observe(leadSummariesRef.value);
}

watch(showLeadSummaries, (visible) => {
    if (visible) {
        nextTick(() => {
            bindSummariesResizeObserver();
            refreshLeadsGridLayout();
        });

        return;
    }

    summariesResizeObserver?.disconnect();
    summariesResizeObserver = null;
    refreshLeadsGridLayout();
}, { immediate: true });

watch(isLeadModalOpen, (open) => {
    if (!open) {
        refreshLeadsGridLayout();
    }
});

onMounted(() => {
    refreshLeadsGridLayout();
});

onUnmounted(() => {
    summariesResizeObserver?.disconnect();
    summariesResizeObserver = null;
});

const modalPropKeys = [
    'selectedLead',
    'isCreating',
    'leadTemplate',
    'contractors',
    'responsibleUsers',
    'statusOptions',
    'sourceOptions',
    'transportTypeOptions',
    'currencyOptions',
    'printFormTemplateOptions',
    'proposalHtmlTemplateOptions',
    'currentUserId',
    'canAssignResponsible',
    'canUseLeadTasks',
    'salesCoachingInsights',
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

function openCreateLeadFrom(row) {
    if (featureUnavailable.value || !row?.id) {
        return;
    }

    isLeadModalDismissed.value = false;
    isCreateModalOpen.value = false;

    router.get(route('leads.create', { from: row.id }), {}, {
        preserveScroll: true,
        preserveState: true,
        only: [...modalPropKeys, 'leads', 'salesCoachingInsights', 'leadAttentionQueue'],
    });
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

function handleLeadDeleteRequest(row) {
    if (featureUnavailable.value || !row?.id) {
        return;
    }

    const label = row.number ? `лид ${row.number}` : `лид #${row.id}`;
    if (!window.confirm(`Удалить ${label}? Это действие необратимо.`)) {
        return;
    }

    router.delete(route('leads.destroy', row.id), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            isLeadModalDismissed.value = true;
            window.history.replaceState(window.history.state, '', route('leads.index'));
        },
    });
}

function closeLeadModal() {
    isCreateModalOpen.value = false;
    isLeadModalDismissed.value = true;
    window.history.replaceState(window.history.state, '', route('leads.index'));
}
</script>
