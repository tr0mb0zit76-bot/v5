<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Контрагент-перевозчик, реквизиты и документы."
            title="Водители"
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
            <FleetDriversGrid
                :rows="rows"
                :user-id="userId"
                @row-dblclick="handleRowDblClick"
                @create-request="openCreate"
            />
        </div>

        <Modal :show="isModalOpen" max-width="7xl" @close="closeModal">
            <section :class="crmModalEntityShell">
                <DriverWizard
                    :selected-driver="selectedDriver"
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
import FleetDriversGrid from '@/Components/Fleet/FleetDriversGrid.vue';
import DriverWizard from '@/Pages/Fleet/DriverWizard.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'fleet', activeSubKey: 'fleet-drivers', mainFill: true }, () => page),
});

const modalOpenKeys = ['selectedDriver'];
const modalRefreshKeys = ['selectedDriver', 'drivers', 'driverDocumentTypeOptions'];

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const rows = computed(() => page.props.drivers ?? []);
const selectedDriver = computed(() => page.props.selectedDriver ?? null);
const documentTypeOptions = computed(() => page.props.driverDocumentTypeOptions ?? []);

const isCreateOpen = ref(false);
const isModalDismissed = ref(false);

const isModalOpen = computed(() => !isModalDismissed.value && (isCreateOpen.value || selectedDriver.value !== null));

watch(selectedDriver, (v) => {
    if (v !== null) {
        isModalDismissed.value = false;
        isCreateOpen.value = false;
    }
});

function openCreate() {
    isModalDismissed.value = false;
    isCreateOpen.value = true;
    window.history.pushState(window.history.state, '', route('drivers.index', {}, false));
}

function handleRowDblClick(row) {
    if (row?.id) {
        isCreateOpen.value = false;
        isModalDismissed.value = false;
        const showUrl = typeof row?.show_url === 'string' && row.show_url !== ''
            ? row.show_url
            : (route().has('fleet.drivers.show') ? route('fleet.drivers.show', row.id, {}, false) : null);
        if (!showUrl) {
            return;
        }
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
    router.get(route('drivers.index', {}, false), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['selectedDriver'],
    });
}

function onWizardSaved() {
    router.reload({ only: modalRefreshKeys });
}
</script>
