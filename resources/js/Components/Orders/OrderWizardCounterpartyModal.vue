<script setup>
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
            <div :class="`${crmModalPanel} w-full max-w-xl p-5 shadow-2xl`" @click.stop>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <div class="text-lg font-semibold">Новый контрагент</div>
                        <div class="text-sm text-zinc-500">
                            {{
                                counterpartyTarget.kind === 'performer'
                                    ? 'Создаётся в справочнике и сразу подставляется как перевозчик в это плечо'
                                    : 'Создаётся в справочнике и сразу подставляется в заказ'
                            }}
                        </div>
                    </div>
                    <button type="button" class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="emit('close')">×</button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <input
                        :ref="(el) => { if (counterpartyNameInput) counterpartyNameInput.value = el; }"
                        v-model="counterpartyForm.name"
                        type="text"
                        placeholder="Название"
                        :class="`${crmFieldFluid} md:col-span-2`"
                    />
                    <input v-model="counterpartyForm.inn" type="text" placeholder="ИНН" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.kpp" type="text" placeholder="КПП" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.address" type="text" placeholder="Адрес" :class="`${crmFieldFluid} md:col-span-2`" />
                    <input v-model="counterpartyForm.phone" type="text" placeholder="Телефон" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.email" type="email" placeholder="Email" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.contact_person" type="text" placeholder="Контактное лицо" :class="`${crmFieldFluid} md:col-span-2`" />
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" :class="crmBtnNeutral" @click="emit('close')">
                        Отмена
                    </button>
                    <button type="button" :class="crmBtnCreate" :disabled="inlineContractorSaving" @click="emit('create')">
                        {{ inlineContractorSaving ? 'Создание...' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
