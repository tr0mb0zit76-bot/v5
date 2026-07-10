<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmFieldFluid,
    crmModalFormBody,
    crmModalFormShell,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
    crmModalFieldStack,
    crmPill,
} from '@/support/crmUi.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    contractorId: {
        type: Number,
        required: true,
    },
    contacts: {
        type: Array,
        default: () => [],
    },
    portraitOptions: {
        type: Object,
        required: true,
    },
    interactionChannels: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'stored']);

const saving = ref(false);
const error = ref('');
const form = ref(blankForm());

function blankForm() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());

    return {
        contractor_contact_id: '',
        contacted_at: now.toISOString().slice(0, 16),
        channel: 'phone',
        outcome_code: 'reached',
        next_contact_at: '',
        subject: '',
        summary: '',
        objection_tags: [],
        merge_to_portrait: true,
    };
}

function resetForm() {
    form.value = blankForm();
    error.value = '';
}

function toggleObjection(tag) {
    const tags = form.value.objection_tags;
    if (tags.includes(tag)) {
        form.value.objection_tags = tags.filter((item) => item !== tag);
    } else {
        form.value.objection_tags = [...tags, tag];
    }
}

async function submit() {
    saving.value = true;
    error.value = '';

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const payload = {
            ...form.value,
            contractor_contact_id: form.value.contractor_contact_id || null,
            next_contact_at: form.value.next_contact_at || null,
        };

        const response = await fetch(route('contractors.portrait-interactions.store', props.contractorId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Не удалось сохранить итог контакта');
        }

        emit('stored', data);
        emit('close');
        resetForm();
        router.reload({ only: ['selectedContractor'], preserveScroll: true });
    } catch (err) {
        error.value = err.message || 'Не удалось сохранить итог контакта';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <section :class="crmModalFormShell">
            <CrmModalHeader eyebrow="Контакт" title="Зафиксировать итог" @close="emit('close')" />
            <form :class="`${crmModalFormBody} space-y-4 px-6 pb-6 pt-2`" @submit.prevent="submit">
                <div :class="crmModalFieldsWrap">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Дата</label>
                        <input v-model="form.contacted_at" type="datetime-local" required :class="crmFieldFluid" />
                    </div>
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Канал</label>
                        <select v-model="form.channel" required :class="crmFieldFluid">
                            <option v-for="channel in interactionChannels" :key="channel.value" :value="channel.value">
                                {{ channel.label }}
                            </option>
                        </select>
                    </div>
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">С кем</label>
                        <select v-model="form.contractor_contact_id" :class="crmFieldFluid">
                            <option value="">Не указано</option>
                            <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                                {{ contact.full_name }}
                            </option>
                        </select>
                    </div>
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Исход</label>
                        <select v-model="form.outcome_code" required :class="crmFieldFluid">
                            <option v-for="option in portraitOptions.outcome_code" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">След. контакт</label>
                        <input v-model="form.next_contact_at" type="datetime-local" :class="crmFieldFluid" />
                    </div>
                </div>

                <div :class="crmModalFieldStack">
                    <label :class="crmModalFieldLabel">Краткий итог</label>
                    <textarea v-model="form.summary" rows="4" required :class="crmFieldFluid" />
                </div>

                <div>
                    <span :class="crmModalFieldLabel">Теги возражений</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="option in portraitOptions.objection_tag"
                            :key="option.value"
                            type="button"
                            :class="form.objection_tags.includes(option.value) ? crmBtnCreate : crmPill"
                            @click="toggleObjection(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <input v-model="form.merge_to_portrait" type="checkbox" class="rounded border-zinc-300" />
                    Обновить портрет по итогу контакта
                </label>

                <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="emit('close')">Отмена</button>
                    <button type="submit" :class="crmBtnCreate" :disabled="saving">Сохранить</button>
                </div>
            </form>
        </section>
    </Modal>
</template>
