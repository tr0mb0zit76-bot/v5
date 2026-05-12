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
                        {{ isCreating ? 'Новый водитель' : 'Карточка водителя' }}
                    </div>
                    <h2 class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ isCreating ? 'Добавление' : selectedDriver?.full_name }}
                    </h2>
                </div>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
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

                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-zinc-200 px-4 py-2 text-sm dark:border-zinc-700" @click="$emit('close')">Закрыть</button>
                    <button
                        type="submit"
                        :class="crmBtnCreate"
                        :disabled="form.processing || !form.carrier_contractor_id || !form.full_name?.trim()"
                    >
                        Сохранить
                    </button>
                </div>
            </form>

            <div v-if="!isCreating && selectedDriver" class="mt-10 space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Документы</div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Паспорт, водительское удостоверение.</p>
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
                        v-for="document in selectedDriver.documents || []"
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
                                @click="destroyDriverDocument(document)"
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
import { crmBtnCreate } from '@/support/crmUi.js';

const props = defineProps({
    selectedDriver: { type: Object, default: null },
    isCreating: { type: Boolean, default: false },
    documentTypeOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const page = usePage();

const carrierSearch = ref('');
const carrierResults = ref([]);
const carrierDropdownOpen = ref(false);
const carrierPickedLabel = ref('');
const docInput = ref(null);
const docFile = ref(null);
const deletingDocId = ref(null);
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

const docForm = useForm({
    document_type: 'passport',
    file: null,
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

function triggerDocPicker() {
    docInput.value?.click();
}

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

async function onDocFile(event) {
    const file = event.target?.files?.[0];

    if (file) {
        await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
    }

    docFile.value = file ?? null;
    docForm.file = file ?? null;
}

function submitDoc() {
    if (!props.selectedDriver?.id || !docFile.value) {
        return;
    }

    docForm.post(route('fleet.drivers.documents.store', props.selectedDriver.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            docForm.reset();
            docFile.value = null;
            docForm.document_type = 'passport';
            emit('saved');
        },
    });
}

function destroyDriverDocument(document) {
    if (!props.selectedDriver?.id || !document?.id) {
        return;
    }

    if (!window.confirm(`Удалить файл «${document.original_name}»?`)) {
        return;
    }

    deletingDocId.value = document.id;
    router.delete(route('fleet.drivers.documents.destroy', [props.selectedDriver.id, document.id]), {
        preserveScroll: true,
        onFinish: () => {
            deletingDocId.value = null;
        },
    });
}
</script>
