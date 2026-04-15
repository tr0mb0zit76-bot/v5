<template>
    <div class="flex max-h-[calc(100dvh-3rem)] flex-col overflow-hidden">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                {{ isCreating ? 'Новый водитель' : 'Карточка водителя' }}
            </div>
            <h2 class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                {{ isCreating ? 'Добавление' : selectedDriver?.full_name }}
            </h2>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
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
                                v-for="c in carrierResults"
                                :key="c.id"
                                type="button"
                                class="flex w-full flex-col items-start px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                @click="pickCarrier(c)"
                            >
                                <span class="font-medium">{{ c.name }}</span>
                                <span class="text-xs text-zinc-500">{{ c.inn || '—' }}</span>
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
                        class="rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900"
                        :disabled="form.processing || !form.carrier_contractor_id || !form.full_name?.trim()"
                    >
                        Сохранить
                    </button>
                </div>
            </form>

            <div v-if="!isCreating && selectedDriver" class="mt-10 space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Документы</div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Паспорт, водительское удостоверение.</p>

                <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitDoc">
                    <div class="space-y-1">
                        <label class="text-xs text-zinc-500">Тип</label>
                        <select v-model="docForm.document_type" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-950" required>
                            <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs text-zinc-500">Файл</label>
                        <input type="file" class="text-sm" required @change="onDocFile" />
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
                        v-for="doc in selectedDriver.documents || []"
                        :key="doc.id"
                        class="flex items-center justify-between gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                    >
                        <a :href="doc.download_url" class="truncate text-sky-700 underline dark:text-sky-300">{{ doc.original_name }}</a>
                        <span class="shrink-0 text-xs text-zinc-500">{{ doc.document_type }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

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
const docFile = ref(null);

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
    const d = props.selectedDriver;
    if (!d || props.isCreating) {
        form.reset();
        form.carrier_contractor_id = null;
        carrierPickedLabel.value = '';
        carrierSearch.value = '';

        return;
    }
    form.carrier_contractor_id = d.carrier_contractor_id;
    form.full_name = d.full_name ?? '';
    form.passport_series = d.passport_series ?? '';
    form.passport_number = d.passport_number ?? '';
    form.passport_issued_by = d.passport_issued_by ?? '';
    form.passport_issued_at = d.passport_issued_at ?? '';
    form.phone = d.phone ?? '';
    form.license_number = d.license_number ?? '';
    form.license_categories = d.license_categories ?? '';
    form.notes = d.notes ?? '';
    carrierPickedLabel.value = d.carrier_name ? `${d.carrier_name}${d.carrier_inn ? ' · ИНН '+d.carrier_inn : ''}` : '';
    carrierSearch.value = d.carrier_name ?? '';
}

watch(() => [props.selectedDriver, props.isCreating], syncFromSelected, { immediate: true });

function onCarrierInput() {
    carrierDropdownOpen.value = true;
    if (carrierTimer) {
        clearTimeout(carrierTimer);
    }
    carrierTimer = setTimeout(async () => {
        const q = carrierSearch.value.trim();
        if (q.length < 2) {
            carrierResults.value = [];

            return;
        }
        try {
            const r = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(q)}&type=carrier&limit=40`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include',
            });
            const data = await r.json();
            carrierResults.value = data.contractors ?? [];
        } catch {
            carrierResults.value = [];
        }
    }, 350);
}

function pickCarrier(c) {
    form.carrier_contractor_id = c.id;
    carrierPickedLabel.value = c.name;
    carrierSearch.value = c.name;
    carrierResults.value = [];
    carrierDropdownOpen.value = false;
}

function clearCarrier() {
    form.carrier_contractor_id = null;
    carrierPickedLabel.value = '';
    carrierSearch.value = '';
}

function submitMain() {
    const opts = { preserveScroll: true, onSuccess: () => emit('saved') };
    if (props.isCreating) {
        form.post(route('fleet.drivers.store'), opts);

        return;
    }
    if (props.selectedDriver?.id) {
        form.patch(route('fleet.drivers.update', props.selectedDriver.id), opts);
    }
}

function onDocFile(e) {
    const f = e.target?.files?.[0];
    docFile.value = f ?? null;
    docForm.file = f ?? null;
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
</script>
