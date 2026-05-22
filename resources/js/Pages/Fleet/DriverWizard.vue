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
                        {{ isCreating ? 'Новый водитель' : 'Карточка водителя' }}
                    </div>
                    <h2 class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ isCreating ? 'Добавление' : selectedDriver?.full_name }}
                    </h2>
                </div>
            </div>
            <button
                type="button"
                :class="crmBtnCreate"
                :disabled="form.processing || !form.carrier_contractor_id || !form.full_name?.trim()"
                @click="submitMain"
            >
                <Save class="h-4 w-4" />
                Сохранить
            </button>
        </div>

        <div :class="crmWizardBody">
            <form class="space-y-5" @submit.prevent="submitMain">
                <div class="space-y-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">Контрагент-перевозчик</label>
                    <div class="relative">
                        <input
                            v-model="carrierSearch"
                            type="text"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950"
                            placeholder="Поиск перевозчика"
                            @focus="carrierDropdownOpen = true"
                            @input="onCarrierInput"
                        />
                        <button
                            v-if="form.carrier_contractor_id"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"
                            @click="clearCarrier"
                        >
                            Сброс
                        </button>
                        <div
                            v-if="carrierDropdownOpen && carrierResults.length > 0"
                            class="absolute left-0 top-full z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <button
                                v-for="contractor in carrierResults"
                                :key="contractor.id"
                                type="button"
                                class="flex w-full flex-col items-start px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                @click="pickCarrier(contractor)"
                            >
                                <span class="font-medium">{{ contractor.name }}</span>
                                <span class="text-xs text-zinc-500">{{ contractor.inn || '—' }}</span>
                            </button>
                        </div>
                    </div>
                    <p v-if="carrierPickedLabel" class="text-xs text-zinc-600 dark:text-zinc-300">Выбрано: {{ carrierPickedLabel }}</p>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">ФИО</label>
                    <input v-model="form.full_name" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Телефон</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Номер ВУ</label>
                        <input v-model="form.license_number" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Категории ВУ</label>
                        <input v-model="form.license_categories" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Паспорт серия</label>
                        <input v-model="form.passport_series" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Паспорт номер</label>
                        <input v-model="form.passport_number" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Дата выдачи</label>
                        <input v-model="form.passport_issued_at" type="date" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Кем выдан паспорт</label>
                    <input v-model="form.passport_issued_by" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Заметки</label>
                    <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                </div>

            </form>

            <FleetEntityDocumentsSection
                v-if="!isCreating && selectedDriver?.id"
                entity-kind="driver"
                :entity-id="selectedDriver.id"
                :documents="selectedDriver.documents ?? []"
                :document-type-options="documentTypeOptions"
                default-document-type="passport"
                @saved="emit('saved')"
            />
            <section
                v-else
                :class="`${crmPanel} mt-8 border-dashed p-6 text-center text-sm text-zinc-500 dark:text-zinc-400`"
            >
                Сохраните карточку водителя — после этого здесь появятся загрузка документов и таблица вложений.
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
    selectedDriver: { type: Object, default: null },
    isCreating: { type: Boolean, default: false },
    documentTypeOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const carrierSearch = ref('');
const carrierResults = ref([]);
const carrierDropdownOpen = ref(false);
const carrierPickedLabel = ref('');
let carrierTimer = null;

const form = useForm({
    carrier_contractor_id: null,
    full_name: '',
    passport_series: '',
    passport_number: '',
    passport_issued_by: '',
    passport_issued_at: '',
    phone: '',
    license_number: '',
    license_categories: '',
    notes: '',
});

function syncFromSelected() {
    const driver = props.selectedDriver;

    if (!driver || props.isCreating) {
        form.reset();
        form.carrier_contractor_id = null;
        carrierPickedLabel.value = '';
        carrierSearch.value = '';

        return;
    }

    form.carrier_contractor_id = driver.carrier_contractor_id;
    form.full_name = driver.full_name ?? '';
    form.passport_series = driver.passport_series ?? '';
    form.passport_number = driver.passport_number ?? '';
    form.passport_issued_by = driver.passport_issued_by ?? '';
    form.passport_issued_at = driver.passport_issued_at ?? '';
    form.phone = driver.phone ?? '';
    form.license_number = driver.license_number ?? '';
    form.license_categories = driver.license_categories ?? '';
    form.notes = driver.notes ?? '';
    carrierPickedLabel.value = driver.carrier_name ? `${driver.carrier_name}${driver.carrier_inn ? ` · ИНН ${driver.carrier_inn}` : ''}` : '';
    carrierSearch.value = driver.carrier_name ?? '';
}

watch(() => [props.selectedDriver, props.isCreating], syncFromSelected, { immediate: true });

function onCarrierInput() {
    carrierDropdownOpen.value = true;

    if (carrierTimer) {
        clearTimeout(carrierTimer);
    }

    carrierTimer = setTimeout(async () => {
        const query = carrierSearch.value.trim();

        if (query.length < 2) {
            carrierResults.value = [];

            return;
        }

        try {
            const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=carrier&limit=40`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include',
            });
            const data = await response.json();
            carrierResults.value = data.contractors ?? [];
        } catch {
            carrierResults.value = [];
        }
    }, 350);
}

function pickCarrier(contractor) {
    form.carrier_contractor_id = contractor.id;
    carrierPickedLabel.value = contractor.name;
    carrierSearch.value = contractor.name;
    carrierResults.value = [];
    carrierDropdownOpen.value = false;
}

function clearCarrier() {
    form.carrier_contractor_id = null;
    carrierPickedLabel.value = '';
    carrierSearch.value = '';
}

function submitMain() {
    const options = { preserveScroll: true, onSuccess: () => emit('saved') };

    if (props.isCreating) {
        form.post(route('fleet.drivers.store'), options);

        return;
    }

    if (props.selectedDriver?.id) {
        form.patch(route('fleet.drivers.update', props.selectedDriver.id), options);
    }
}
</script>
