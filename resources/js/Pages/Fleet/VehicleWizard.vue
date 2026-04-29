<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center border border-rose-200 bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60"
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
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
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
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Марка тягача</label>
                        <input v-model="form.tractor_brand" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Марка прицепа</label>
                        <input v-model="form.trailer_brand" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Гос. номер тягача</label>
                        <input v-model="form.tractor_plate" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Гос. номер прицепа</label>
                        <input v-model="form.trailer_plate" type="text" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Заметки</label>
                    <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" />
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-zinc-200 px-4 py-2 text-sm dark:border-zinc-700" @click="$emit('close')">Закрыть</button>
                    <button
                        type="submit"
                        class="rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900"
                        :disabled="form.processing || !form.owner_contractor_id"
                    >
                        Сохранить
                    </button>
                </div>
            </form>

            <div v-if="!isCreating && selectedVehicle" class="mt-10 space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Документы</div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">ПТС, договор аренды, страховка и др.</p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-zinc-200 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        @click="triggerDocPicker"
                    >
                        Добавить документ
                    </button>
                </div>

                <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitDoc">
                    <div class="space-y-1">
                        <label class="text-xs text-zinc-500">Тип</label>
                        <select v-model="docForm.document_type" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" required>
                            <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs text-zinc-500">Файл</label>
                        <input ref="docInput" type="file" class="text-sm" required @change="onDocFile" />
                        <p v-if="docForm.errors.file" class="text-xs text-rose-600 dark:text-rose-400">{{ docForm.errors.file }}</p>
                        <p v-if="docForm.errors.document_type" class="text-xs text-rose-600 dark:text-rose-400">{{ docForm.errors.document_type }}</p>
                    </div>
                    <button
                        type="submit"
                        class="rounded-xl border border-zinc-900 px-4 py-2 text-sm dark:border-zinc-50"
                        :disabled="docForm.processing || !docFile"
                    >
                        Загрузить
                    </button>
                </form>

                <ul class="space-y-2">
                    <li
                        v-for="document in selectedVehicle.documents || []"
                        :key="document.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                    >
                        <a :href="document.download_url" class="min-w-0 flex-1 truncate text-sky-700 underline dark:text-sky-300">{{ document.original_name }}</a>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-xs text-zinc-500">{{ document.document_type }}</span>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                                :disabled="deletingDocId === document.id"
                                @click="destroyVehicleDocument(document)"
                            >
                                Удалить
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';

const props = defineProps({
    selectedVehicle: { type: Object, default: null },
    isCreating: { type: Boolean, default: false },
    documentTypeOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const page = usePage();

const ownerSearch = ref('');
const ownerResults = ref([]);
const ownerDropdownOpen = ref(false);
const ownerPickedLabel = ref('');
const docInput = ref(null);
const docFile = ref(null);
const deletingDocId = ref(null);
let ownerTimer = null;

const form = useForm({
    owner_contractor_id: null,
    tractor_brand: '',
    trailer_brand: '',
    tractor_plate: '',
    trailer_plate: '',
    notes: '',
});

const docForm = useForm({
    document_type: 'pts',
    file: null,
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

function triggerDocPicker() {
    docInput.value?.click();
}

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

async function onDocFile(event) {
    const file = event.target?.files?.[0];

    if (file) {
        await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    }

    docFile.value = file ?? null;
    docForm.file = file ?? null;
}

function submitDoc() {
    if (!props.selectedVehicle?.id || !docFile.value) {
        return;
    }

    docForm.post(route('fleet.vehicles.documents.store', props.selectedVehicle.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            docForm.reset();
            docFile.value = null;
            docForm.document_type = 'pts';
            emit('saved');
        },
    });
}

function destroyVehicleDocument(document) {
    if (!props.selectedVehicle?.id || !document?.id) {
        return;
    }

    if (!window.confirm(`Удалить файл «${document.original_name}»?`)) {
        return;
    }

    deletingDocId.value = document.id;
    router.delete(route('fleet.vehicles.documents.destroy', [props.selectedVehicle.id, document.id]), {
        preserveScroll: true,
        onFinish: () => {
            deletingDocId.value = null;
        },
    });
}
</script>
