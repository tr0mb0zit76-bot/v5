<template>
    <div class="flex max-h-[calc(100dvh-3rem)] flex-col overflow-hidden">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                {{ isCreating ? 'Новое ТС' : 'Карточка ТС' }}
            </div>
            <h2 class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                {{ isCreating ? 'Добавление' : `ТС #${selectedVehicle?.id}` }}
            </h2>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
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
                                v-for="c in ownerResults"
                                :key="c.id"
                                type="button"
                                class="flex w-full flex-col items-start px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                @click="pickOwner(c)"
                            >
                                <span class="font-medium">{{ c.name }}</span>
                                <span class="text-xs text-zinc-500">{{ c.inn || '—' }}</span>
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
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Документы</div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">ПТС, договор аренды, страховка и др.</p>

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
                        v-for="doc in selectedVehicle.documents || []"
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
const docFile = ref(null);

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
    const v = props.selectedVehicle;
    if (!v || props.isCreating) {
        form.reset();
        form.owner_contractor_id = null;
        ownerPickedLabel.value = '';
        ownerSearch.value = '';

        return;
    }
    form.owner_contractor_id = v.owner_contractor_id;
    form.tractor_brand = v.tractor_brand ?? '';
    form.trailer_brand = v.trailer_brand ?? '';
    form.tractor_plate = v.tractor_plate ?? '';
    form.trailer_plate = v.trailer_plate ?? '';
    form.notes = v.notes ?? '';
    ownerPickedLabel.value = v.owner_name ? `${v.owner_name}${v.owner_inn ? ' · ИНН '.$v.owner_inn : ''}` : '';
    ownerSearch.value = v.owner_name ?? '';
}

watch(() => [props.selectedVehicle, props.isCreating], syncFromSelected, { immediate: true });

function onOwnerInput() {
    ownerDropdownOpen.value = true;
    if (ownerTimer) {
        clearTimeout(ownerTimer);
    }
    ownerTimer = setTimeout(async () => {
        const q = ownerSearch.value.trim();
        if (q.length < 2) {
            ownerResults.value = [];

            return;
        }
        try {
            const r = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(q)}&type=carrier&limit=40`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include',
            });
            const data = await r.json();
            ownerResults.value = data.contractors ?? [];
        } catch {
            ownerResults.value = [];
        }
    }, 350);
}

function pickOwner(c) {
    form.owner_contractor_id = c.id;
    ownerPickedLabel.value = c.name;
    ownerSearch.value = c.name;
    ownerResults.value = [];
    ownerDropdownOpen.value = false;
}

function clearOwner() {
    form.owner_contractor_id = null;
    ownerPickedLabel.value = '';
    ownerSearch.value = '';
}

function submitMain() {
    const opts = { preserveScroll: true, onSuccess: () => emit('saved') };
    if (props.isCreating) {
        form.post(route('fleet.vehicles.store'), opts);

        return;
    }
    if (props.selectedVehicle?.id) {
        form.patch(route('fleet.vehicles.update', props.selectedVehicle.id), opts);
    }
}

function onDocFile(e) {
    const f = e.target?.files?.[0];
    docFile.value = f ?? null;
    docForm.file = f ?? null;
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
</script>
