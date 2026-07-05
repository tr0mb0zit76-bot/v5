<script setup>
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    portalToken: { type: String, required: true },
    documentSlots: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
    documentUploadHint: { type: String, default: '' },
    uploadRouteName: { type: String, default: 'portal.carrier.documents.store' },
});

const uploadingKey = ref('');
const uploadError = ref('');

const slotForms = reactive({});

function ensureSlotForm(slot) {
    const key = slot.key;
    if (!slotForms[key]) {
        const defaultType = slot.type_options?.[0]?.value ?? 'request';
        slotForms[key] = {
            type: defaultType,
            number: '',
            document_date: '',
            file: null,
        };
    }

    return slotForms[key];
}

function canUploadToSlot(slot) {
    if (props.readonly) {
        return false;
    }

    if (slot.allows_multiple) {
        return true;
    }

    return !slot.completed;
}

function slotStatusLabel(slot) {
    if (!slot.completed) {
        return 'Нужен файл';
    }

    return slot.allows_multiple ? 'Есть файлы' : 'Загружено';
}

function onFileChange(slot, event) {
    const form = ensureSlotForm(slot);
    const [file] = event.target.files ?? [];
    form.file = file ?? null;
    if (event.target) {
        event.target.value = '';
    }
}

async function uploadSlot(slot) {
    if (!canUploadToSlot(slot)) {
        return;
    }

    const form = ensureSlotForm(slot);
    if (!form.file) {
        uploadError.value = 'Выберите файл для загрузки.';

        return;
    }

    uploadingKey.value = slot.key;
    uploadError.value = '';

    const body = new FormData();
    body.append('slot_kind', slot.slot_kind);
    body.append('requirement_slot_key', slot.slot_key);
    body.append('type', form.type);
    if (form.number) {
        body.append('number', form.number);
    }
    if (form.document_date) {
        body.append('document_date', form.document_date);
    }
    body.append('file', form.file);

    try {
        const response = await fetch(route(props.uploadRouteName, { token: props.portalToken }), {
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
        router.reload({ only: ['document_slots'], preserveScroll: true });
    } catch {
        uploadError.value = 'Ошибка сети при загрузке документа.';
    } finally {
        uploadingKey.value = '';
    }
}
</script>

<template>
    <section v-if="documentSlots.length" class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/80 p-4">
        <div>
            <h2 class="text-sm font-semibold text-zinc-900">Документы</h2>
            <p class="mt-1 text-xs text-zinc-500">
                Подгрузите заявку с вашей подписью. После завершения перевозки подгрузите закрывающие документы.
            </p>
            <p v-if="documentUploadHint" class="mt-2 text-xs text-zinc-500">{{ documentUploadHint }}</p>
        </div>

        <p v-if="uploadError" class="text-xs text-rose-600">{{ uploadError }}</p>

        <div
            v-for="slot in documentSlots"
            :key="slot.key"
            class="space-y-2 rounded-lg border border-zinc-200 bg-white px-3 py-3"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-zinc-900">{{ slot.label }}</p>
                    <p v-if="slot.description" class="mt-0.5 text-xs text-zinc-500">{{ slot.description }}</p>
                </div>
                <span
                    class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                    :class="slot.completed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                >
                    {{ slotStatusLabel(slot) }}
                </span>
            </div>

            <ul v-if="slot.documents?.length" class="space-y-1 text-xs text-zinc-600">
                <li v-for="doc in slot.documents" :key="doc.id">
                    {{ doc.type_label }} — {{ doc.original_name || doc.number || `#${doc.id}` }}
                </li>
            </ul>

            <template v-if="canUploadToSlot(slot)">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs text-zinc-500">Тип</label>
                        <select
                            v-model="ensureSlotForm(slot).type"
                            class="w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm"
                        >
                            <option v-for="opt in slot.type_options" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs text-zinc-500">Номер (необязательно)</label>
                        <input
                            v-model="ensureSlotForm(slot).number"
                            type="text"
                            class="w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm"
                        />
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-xs text-zinc-500">Дата документа (необязательно)</label>
                    <input
                        v-model="ensureSlotForm(slot).document_date"
                        type="date"
                        class="w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="cursor-pointer rounded-lg border border-zinc-200 px-3 py-1.5 text-xs hover:bg-zinc-50">
                        Выбрать файл
                        <input type="file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp" @change="onFileChange(slot, $event)" />
                    </label>
                    <span v-if="ensureSlotForm(slot).file" class="max-w-[14rem] truncate text-xs text-zinc-600">
                        {{ ensureSlotForm(slot).file.name }}
                    </span>
                    <button
                        type="button"
                        class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="uploadingKey === slot.key"
                        @click="uploadSlot(slot)"
                    >
                        {{ uploadingKey === slot.key ? 'Загрузка…' : 'Загрузить' }}
                    </button>
                </div>
            </template>
        </div>
    </section>
</template>
