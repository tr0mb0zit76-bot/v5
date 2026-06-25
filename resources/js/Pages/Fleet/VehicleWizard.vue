<template>
    <div :class="`${crmWizardShell} h-full min-h-0`">
        <div :class="crmWizardHeader">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :class="crmWizardBack"
                    title="Назад"
                    @click="$emit('close')"
                >
                    <X class="h-5 w-5" />
                    <span class="sr-only">Назад</span>
                </button>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                        {{ isCreating ? 'Новое ТС' : 'Карточка ТС' }}
                    </div>
                    <h2 class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ isCreating ? 'Добавление' : `ТС #${selectedVehicle?.id}` }}
                    </h2>
                </div>
            </div>
            <button
                type="button"
                :class="crmBtnCreate"
                :disabled="form.processing || !form.owner_contractor_id"
                @click="submitMain"
            >
                <Save class="h-4 w-4" />
                Сохранить
            </button>
        </div>

        <div :class="crmWizardBody">
            <form class="space-y-5" @submit.prevent="submitMain">
                <div class="space-y-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">Владелец (контрагент)</label>
                    <div class="relative">
                        <input
                            v-model="ownerSearch"
                            type="text"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950"
                            placeholder="Поиск по названию, ИНН"
                            @focus="ownerDropdownOpen = true"
                            @input="onOwnerInput"
                        />
                        <button
                            v-if="form.owner_contractor_id"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"
                            @click="clearOwner"
                        >
                            Сброс
                        </button>
                        <div
                            v-if="ownerDropdownOpen && ownerResults.length > 0"
                            class="absolute left-0 top-full z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <button
                                v-for="contractor in ownerResults"
                                :key="contractor.id"
                                type="button"
                                class="flex w-full flex-col items-start px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                @click="pickOwner(contractor)"
                            >
                                <span class="font-medium">{{ contractor.name }}</span>
                                <span class="text-xs text-zinc-500">{{ contractor.inn || '—' }}</span>
                            </button>
                        </div>
                    </div>
                    <p v-if="ownerPickedLabel" class="text-xs text-zinc-600 dark:text-zinc-300">Выбрано: {{ ownerPickedLabel }}</p>
                    <p v-if="form.errors.owner_contractor_id" class="text-xs text-red-600">{{ form.errors.owner_contractor_id }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Марка тягача</label>
                        <input v-model="form.tractor_brand" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                        <p v-if="form.errors.tractor_brand" class="text-xs text-red-600">{{ form.errors.tractor_brand }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Марка прицепа</label>
                        <input v-model="form.trailer_brand" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                        <p v-if="form.errors.trailer_brand" class="text-xs text-red-600">{{ form.errors.trailer_brand }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Гос. номер тягача</label>
                        <input v-model="form.tractor_plate" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                        <p v-if="form.errors.tractor_plate" class="text-xs text-red-600">{{ form.errors.tractor_plate }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Гос. номер прицепа</label>
                        <input v-model="form.trailer_plate" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                        <p v-if="form.errors.trailer_plate" class="text-xs text-red-600">{{ form.errors.trailer_plate }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Заметки</label>
                    <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                </div>

            </form>

            <FleetEntityDocumentsSection
                v-if="!isCreating && selectedVehicle?.id"
                entity-kind="vehicle"
                :entity-id="selectedVehicle.id"
                :documents="selectedVehicle.documents ?? []"
                :document-type-options="documentTypeOptions"
                default-document-type="pts"
                @saved="emit('saved')"
            />
            <section
                v-else
                :class="`${crmPanel} mt-8 border-dashed p-6 text-center text-sm text-zinc-500 dark:text-zinc-400`"
            >
                Сохраните карточку ТС — после этого здесь появятся загрузка документов и таблица вложений.
            </section>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Save, X } from 'lucide-vue-next';
import FleetEntityDocumentsSection from '@/Components/Fleet/FleetEntityDocumentsSection.vue';
import { crmBtnCreate, crmPanel, crmWizardBack, crmWizardBody, crmWizardHeader, crmWizardShell } from '@/support/crmUi.js';

const props = defineProps({
    selectedVehicle: { type: Object, default: null },
    isCreating: { type: Boolean, default: false },
    documentTypeOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const ownerSearch = ref('');
const ownerResults = ref([]);
const ownerDropdownOpen = ref(false);
const ownerPickedLabel = ref('');
let ownerTimer = null;

const form = useForm({
    owner_contractor_id: null,
    tractor_brand: '',
    trailer_brand: '',
    tractor_plate: '',
    trailer_plate: '',
    notes: '',
});

function syncFromSelected() {
    const vehicle = props.selectedVehicle;

    if (!vehicle || props.isCreating) {
        form.reset();
        form.owner_contractor_id = null;
        ownerPickedLabel.value = '';
        ownerSearch.value = '';

        return;
    }

    form.owner_contractor_id = vehicle.owner_contractor_id;
    form.tractor_brand = vehicle.tractor_brand ?? '';
    form.trailer_brand = vehicle.trailer_brand ?? '';
    form.tractor_plate = vehicle.tractor_plate ?? '';
    form.trailer_plate = vehicle.trailer_plate ?? '';
    form.notes = vehicle.notes ?? '';
    ownerPickedLabel.value = vehicle.owner_name ? `${vehicle.owner_name}${vehicle.owner_inn ? ` · ИНН ${vehicle.owner_inn}` : ''}` : '';
    ownerSearch.value = vehicle.owner_name ?? '';
}

watch(() => [props.selectedVehicle, props.isCreating], syncFromSelected, { immediate: true });

function onOwnerInput() {
    ownerDropdownOpen.value = true;

    if (ownerTimer) {
        clearTimeout(ownerTimer);
    }

    ownerTimer = setTimeout(async () => {
        const query = ownerSearch.value.trim();

        if (query.length < 2) {
            ownerResults.value = [];

            return;
        }

        try {
            const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=carrier&limit=40`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include',
            });
            const data = await response.json();
            ownerResults.value = data.contractors ?? [];
        } catch {
            ownerResults.value = [];
        }
    }, 350);
}

function pickOwner(contractor) {
    form.owner_contractor_id = contractor.id;
    ownerPickedLabel.value = contractor.name;
    ownerSearch.value = contractor.name;
    ownerResults.value = [];
    ownerDropdownOpen.value = false;
}

function clearOwner() {
    form.owner_contractor_id = null;
    ownerPickedLabel.value = '';
    ownerSearch.value = '';
}

function submitMain() {
    const options = { preserveScroll: true, onSuccess: () => emit('saved') };

    if (props.isCreating) {
        form.post(route('fleet.vehicles.store'), options);

        return;
    }

    if (props.selectedVehicle?.id) {
        form.patch(route('fleet.vehicles.update', props.selectedVehicle.id), options);
    }
}
</script>
