<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Учёт контейнеров, владельцев и документов."
            title="Контейнеры"
        >
            <template #actions>
                <button
                    type="button"
                    :class="crmBtnCreate"
                    @click="openCreate"
                >
                    <Plus class="h-4 w-4" />
                    Добавить
                </button>
            </template>
        </CrmPageHeader>

        <div :class="crmGridPanel">
            <FleetContainersGrid
                :rows="rows"
                :user-id="userId"
                @row-dblclick="handleRowDblClick"
                @create-request="openCreate"
            />
        </div>

        <Modal :show="isModalOpen" max-width="7xl" @close="closeModal">
            <section :class="crmModalEntityShell">
                <ContainerWizard
                    :selected-container="selectedContainer"
                    :is-creating="isCreateOpen"
                    :document-type-options="documentTypeOptions"
                    :size-options="sizeOptions"
                    :type-options="typeOptions"
                    @close="closeModal"
                    @saved="onWizardSaved"
                />
            </section>
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmGridPanel, crmModalEntityShell } from '@/support/crmUi.js';
import Modal from '@/Components/Modal.vue';
import FleetContainersGrid from '@/Components/Fleet/FleetContainersGrid.vue';
import ContainerWizard from '@/Pages/Fleet/ContainerWizard.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'fleet', activeSubKey: 'fleet-containers', mainFill: true }, () => page),
});

const modalOpenKeys = ['selectedContainer'];
const modalRefreshKeys = [
    'selectedContainer',
    'containers',
    'containerDocumentTypeOptions',
    'containerSizeOptions',
    'containerTypeOptions',
];

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const rows = computed(() => page.props.containers ?? []);
const selectedContainer = computed(() => page.props.selectedContainer ?? null);
const documentTypeOptions = computed(() => page.props.containerDocumentTypeOptions ?? []);
const sizeOptions = computed(() => page.props.containerSizeOptions ?? []);
const typeOptions = computed(() => page.props.containerTypeOptions ?? []);

const isCreateOpen = ref(false);
const isModalDismissed = ref(false);

const isModalOpen = computed(() => !isModalDismissed.value && (isCreateOpen.value || selectedContainer.value !== null));

watch(selectedContainer, (value) => {
    if (value !== null) {
        isModalDismissed.value = false;
        isCreateOpen.value = false;
    }
});

function openCreate() {
    isModalDismissed.value = false;
    isCreateOpen.value = true;
    window.history.pushState(window.history.state, '', route('fleet.containers.index', {}, false));
}

function handleRowDblClick(row) {
    if (row?.id) {
        isCreateOpen.value = false;
        isModalDismissed.value = false;
        const showUrl = typeof row?.show_url === 'string' && row.show_url !== ''
            ? row.show_url
            : route('fleet.containers.show', row.id, {}, false);
        router.get(showUrl, {}, {
            preserveScroll: true,
            preserveState: true,
            only: modalOpenKeys,
        });
    }
}

function closeModal() {
    isCreateOpen.value = false;
    isModalDismissed.value = true;
    router.get(route('fleet.containers.index', {}, false), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['selectedContainer'],
    });
}

function onWizardSaved() {
    router.reload({ only: modalRefreshKeys });
}
</script>
