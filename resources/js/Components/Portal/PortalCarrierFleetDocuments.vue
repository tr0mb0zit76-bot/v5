<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
    portalToken: { type: String, required: true },
    sections: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
    fleetIdentity: { type: Object, required: true },
});

const emit = defineEmits(['uploaded']);

const uploadingKey = ref('');
const uploadError = ref('');
const localSections = ref([...props.sections]);
const sectionForms = reactive({});

function ensureSectionForm(section) {
    const key = section.key;
    if (!sectionForms[key]) {
        sectionForms[key] = {
            document_type: section.type_options?.[0]?.value ?? 'other',
            file: null,
        };
    }

    return sectionForms[key];
}

function onFileChange(section, event) {
    const form = ensureSectionForm(section);
    const [file] = event.target.files ?? [];
    form.file = file ?? null;
    if (event.target) {
        event.target.value = '';
    }
}

async function uploadSection(section) {
    if (props.readonly || !section.identity_ready) {
        return;
    }

    const form = ensureSectionForm(section);
    if (!form.file) {
        uploadError.value = 'Выберите файл для загрузки.';

        return;
    }

    uploadingKey.value = section.key;
    uploadError.value = '';

    const body = new FormData();
    body.append('fleet_target', section.key);
    body.append('document_type', form.document_type);
    body.append('tractor_plate', props.fleetIdentity.tractor_plate ?? '');
    body.append('trailer_plate', props.fleetIdentity.trailer_plate ?? '');
    body.append('tractor_brand', props.fleetIdentity.tractor_brand ?? '');
    body.append('trailer_brand', props.fleetIdentity.trailer_brand ?? '');
    body.append('driver_full_name', props.fleetIdentity.driver_full_name ?? '');
    body.append('driver_phone', props.fleetIdentity.driver_phone ?? '');
    body.append('driver_license', props.fleetIdentity.driver_license ?? '');
    body.append('file', form.file);

    try {
        const response = await fetch(route('portal.carrier.fleet-documents.store', { token: props.portalToken }), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            uploadError.value = data.message
                ?? Object.values(data.errors ?? {}).flat().join(' ')
                ?? 'Не удалось загрузить документ.';

            return;
        }

        form.file = null;
        localSections.value = data.fleet_document_sections ?? localSections.value;
        emit('uploaded', data.fleet_document_sections ?? []);
    } catch {
        uploadError.value = 'Ошибка сети при загрузке документа.';
    } finally {
        uploadingKey.value = '';
    }
}
</script>

<template>
    <div v-if="localSections.length" class="space-y-4">
        <p v-if="uploadError" class="text-xs text-rose-600">{{ uploadError }}</p>

        <section
            v-for="section in localSections"
            :key="section.key"
            class="space-y-2 rounded-lg border border-zinc-200 bg-zinc-50/80 px-3 py-3"
        >
            <div>
                <h3 class="text-sm font-medium text-zinc-900">{{ section.label }}</h3>
                <p class="mt-0.5 text-xs text-zinc-500">{{ section.hint }}</p>
            </div>

            <ul v-if="section.documents?.length" class="space-y-1 text-xs text-zinc-600">
                <li v-for="doc in section.documents" :key="doc.id">
                    {{ doc.type_label }} — {{ doc.original_name }}
                </li>
            </ul>

            <template v-if="!readonly">
                <p v-if="!section.identity_ready" class="text-xs text-amber-700">{{ section.requires_identity }}</p>
                <template v-else>
                    <div class="space-y-1">
                        <label class="text-xs text-zinc-500">Тип документа</label>
                        <select
                            v-model="ensureSectionForm(section).document_type"
                            class="w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm"
                        >
                            <option v-for="opt in section.type_options" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="cursor-pointer rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs hover:bg-zinc-50">
                            Выбрать файл
                            <input type="file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp" @change="onFileChange(section, $event)" />
                        </label>
                        <span v-if="ensureSectionForm(section).file" class="max-w-[14rem] truncate text-xs text-zinc-600">
                            {{ ensureSectionForm(section).file.name }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="uploadingKey === section.key"
                            @click="uploadSection(section)"
                        >
                            {{ uploadingKey === section.key ? 'Загрузка…' : 'Загрузить' }}
                        </button>
                    </div>
                </template>
            </template>
        </section>
    </div>
</template>
