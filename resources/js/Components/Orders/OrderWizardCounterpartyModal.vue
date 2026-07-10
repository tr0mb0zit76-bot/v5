<script setup>
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import {
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
} from '@/support/crmUi.js';

defineProps({
    show: { type: Boolean, required: true },
    counterpartyTarget: { type: Object, required: true },
    counterpartyForm: { type: Object, required: true },
    inlineContractorSaving: { type: Boolean, default: false },
    crmFieldFluid: { type: String, required: true },
    crmBtnNeutral: { type: String, required: true },
    crmBtnCreate: { type: String, required: true },
    crmModalPanel: { type: String, required: true },
    counterpartyNameInput: { type: Object, default: null },
});

const emit = defineEmits(['close', 'create']);
</script>

<template>
    <Teleport to="body">
        <div
            v-show="show"
            class="fixed inset-0 flex items-center justify-center bg-black/40 p-4"
            style="z-index: 2147483647;"
            @click.self="emit('close')"
        >
            <div :class="`${crmModalPanel} w-full max-w-xl shadow-2xl`" @click.stop>
                <CrmModalHeader title="Новый контрагент" @close="emit('close')" />

                <div class="space-y-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800 sm:px-6">
                    <div :class="crmModalFieldsWrap">
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                            <label :class="crmModalFieldLabel">Название</label>
                            <input
                                :ref="(el) => { if (counterpartyNameInput) counterpartyNameInput.value = el; }"
                                v-model="counterpartyForm.name"
                                type="text"
                                :class="crmFieldFluid"
                            />
                        </div>
                        <div :class="crmModalFieldRow">
                            <label :class="crmModalFieldLabel">ИНН</label>
                            <input v-model="counterpartyForm.inn" type="text" :class="crmFieldFluid" />
                        </div>
                        <div :class="crmModalFieldRow">
                            <label :class="crmModalFieldLabel">КПП</label>
                            <input v-model="counterpartyForm.kpp" type="text" :class="crmFieldFluid" />
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                            <label :class="crmModalFieldLabel">Адрес</label>
                            <input v-model="counterpartyForm.address" type="text" :class="crmFieldFluid" />
                        </div>
                        <div :class="crmModalFieldRow">
                            <label :class="crmModalFieldLabel">Телефон</label>
                            <input v-model="counterpartyForm.phone" type="text" :class="crmFieldFluid" />
                        </div>
                        <div :class="crmModalFieldRow">
                            <label :class="crmModalFieldLabel">Email</label>
                            <input v-model="counterpartyForm.email" type="email" :class="crmFieldFluid" />
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                            <label :class="crmModalFieldLabel">Контакт</label>
                            <input v-model="counterpartyForm.contact_person" type="text" :class="crmFieldFluid" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <button type="button" :class="crmBtnNeutral" @click="emit('close')">
                            Отмена
                        </button>
                        <button type="button" :class="crmBtnCreate" :disabled="inlineContractorSaving" @click="emit('create')">
                            {{ inlineContractorSaving ? 'Создание...' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
