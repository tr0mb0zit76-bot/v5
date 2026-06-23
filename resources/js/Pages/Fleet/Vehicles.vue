<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Учёт ТС, владельцев и документов."
            title="Авто"
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
            <FleetVehiclesGrid
                :rows="rows"
                :user-id="userId"
                @row-dblclick="handleRowDblClick"
                @create-request="openCreate"
            />
        </div>

        <Modal :show="isModalOpen" max-width="7xl" @close="closeModal">
            <section :class="crmModalEntityShell">
                <VehicleWizard
                    :selected-vehicle="selectedVehicle"
                    :is-creating="isCreateOpen"
                    :document-type-options="documentTypeOptions"
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
import FleetVehiclesGrid from '@/Components/Fleet/FleetVehiclesGrid.vue';
import VehicleWizard from '@/Pages/Fleet/VehicleWizard.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'fleet', activeSubKey: 'fleet-vehicles', mainFill: true }, () => page),
});

const modalOpenKeys = ['selectedVehicle'];
const modalRefreshKeys = ['selectedVehicle', 'vehicles', 'vehicleDocumentTypeOptions'];

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const rows = computed(() => page.props.vehicles ?? []);
const selectedVehicle = computed(() => page.props.selectedVehicle ?? null);
const documentTypeOptions = computed(() => page.props.vehicleDocumentTypeOptions ?? []);

const isCreateOpen = ref(false);
const isModalDismissed = ref(false);

const isModalOpen = computed(() => !isModalDismissed.value && (isCreateOpen.value || selectedVehicle.value !== null));

watch(selectedVehicle, (v) => {
    if (v !== null) {
        isModalDismissed.value = false;
        isCreateOpen.value = false;
    }
});

function openCreate() {
    isModalDismissed.value = false;
    isCreateOpen.value = true;
    window.history.pushState(window.history.state, '', route('fleet.vehicles.index', {}, false));
}

function handleRowDblClick(row) {
    if (row?.id) {
        isCreateOpen.value = false;
        isModalDismissed.value = false;
        router.get(route('fleet.vehicles.show', row.id, {}, false), {}, {
            preserveScroll: true,
            preserveState: true,
            only: modalOpenKeys,
        });
    }
}

function closeModal() {
    isCreateOpen.value = false;
    isModalDismissed.value = true;
    router.get(route('fleet.vehicles.index', {}, false), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['selectedVehicle'],
    });
}

function onWizardSaved() {
    router.reload({ only: modalRefreshKeys });
}
</script>
