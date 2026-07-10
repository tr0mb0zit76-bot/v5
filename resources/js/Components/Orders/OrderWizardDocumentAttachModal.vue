<script setup>
import { Paperclip } from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import {
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
} from '@/support/crmUi.js';

defineProps({
    show: { type: Boolean, required: true },
    title: { type: String, required: true },
    presetSummary: { type: String, default: '' },
    pendingFile: { type: Object, default: null },
    presetIndex: { type: [Number, null], default: null },
    targetKind: { type: String, required: true },
    stage: { type: [String, null], default: null },
    newDocType: { type: String, required: true },
    performers: { type: Array, required: true },
    documentTypeOptions: { type: Array, required: true },
    crmFieldFluid: { type: String, required: true },
    crmBtnNeutral: { type: String, required: true },
    crmBtnCreate: { type: String, required: true },
    stageLabel: { type: Function, required: true },
    fileInputRef: { type: Object, default: null },
});

const emit = defineEmits([
    'close',
    'confirm',
    'file-change',
    'update:targetKind',
    'update:stage',
    'update:newDocType',
]);
</script>

<template>
    <Modal :show="show" max-width="xl" @close="emit('close')">
        <CrmModalHeader :title="title" @close="emit('close')" />
        <div class="space-y-4 border-t border-zinc-200 px-5 py-5 dark:border-zinc-800 sm:px-6">
            <div v-if="pendingFile" class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/50">
                <Paperclip class="h-4 w-4 shrink-0 text-zinc-500" />
                <span class="min-w-0 truncate font-medium text-zinc-800 dark:text-zinc-100">{{ pendingFile.name }}</span>
            </div>
            <div v-else>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                    <span>Выбрать файл…</span>
                    <input
                        :ref="(el) => { if (fileInputRef) fileInputRef.value = el; }"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                        class="hidden"
                        @change="emit('file-change', $event)"
                    >
                </label>
            </div>

            <div v-if="presetIndex !== null && presetSummary" class="rounded-xl border border-zinc-100 bg-zinc-50/70 px-3 py-2 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300">
                {{ presetSummary }}
            </div>

            <div v-if="presetIndex === null" :class="crmModalFieldsWrap">
                <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                    <label :class="crmModalFieldLabel">Сторона</label>
                    <select :value="targetKind" :class="crmFieldFluid" @change="emit('update:targetKind', $event.target.value)">
                        <option value="customer">Заказчик</option>
                        <option value="carrier" :disabled="performers.length === 0">Плечо (перевозчик)</option>
                    </select>
                </div>
                <div v-if="targetKind === 'carrier'" :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                    <label :class="crmModalFieldLabel">Плечо</label>
                    <select :value="stage" :class="crmFieldFluid" @change="emit('update:stage', $event.target.value)">
                        <option v-for="(p, idx) in performers" :key="`attach-leg-${idx}`" :value="p.stage">{{ stageLabel(p.stage) }}</option>
                    </select>
                </div>
                <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                    <label :class="crmModalFieldLabel">Тип</label>
                    <select :value="newDocType" :class="crmFieldFluid" @change="emit('update:newDocType', $event.target.value)">
                        <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <button type="button" :class="crmBtnNeutral" @click="emit('close')">Отмена</button>
                <button type="button" :class="crmBtnCreate" :disabled="!pendingFile" @click="emit('confirm')">
                    {{ presetIndex !== null ? 'Заменить файл' : 'Прикрепить' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
