<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <div class="flex shrink-0 items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Авто</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Учёт ТС, владельцев и документов (ПТС, аренда и др.).</p>
            </div>
            <button
                type="button"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                @click="openCreate"
            >
                <Plus class="h-4 w-4" />
                Добавить ТС
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden">
            <FleetVehiclesGrid
                :rows="rows"
                :user-id="userId"
                @row-dblclick="handleRowDblClick"
            />
        </div>

        <Modal :show="isModalOpen" max-width="4xl" @close="closeModal">
            <VehicleWizard
                :selected-vehicle="selectedVehicle"
                :is-creating="isCreateOpen"
                :document-type-options="documentTypeOptions"
                @close="closeModal"
                @saved="onWizardSaved"
            />
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import Modal from '@/Components/Modal.vue';
import FleetVehiclesGrid from '@/Components/Fleet/FleetVehiclesGrid.vue';
import VehicleWizard from '@/Pages/Fleet/VehicleWizard.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'fleet', activeSubKey: 'fleet-vehicles' }, () => page),
});

const modalKeys = ['selectedVehicle', 'vehicles', 'vehicleDocumentTypeOptions'];

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
    window.history.pushState(window.history.state, '', route('fleet.vehicles.index'));
}

function handleRowDblClick(row) {
    if (row?.id) {
        isCreateOpen.value = false;
        isModalDismissed.value = false;
        router.get(route('fleet.vehicles.show', row.id), {}, {
            preserveScroll: true,
            preserveState: true,
            only: modalKeys,
        });
    }
}

function closeModal() {
    isCreateOpen.value = false;
    isModalDismissed.value = true;
    router.get(route('fleet.vehicles.index'), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['selectedVehicle'],
    });
}

function onWizardSaved() {
    router.reload({ only: modalKeys });
}
</script>
