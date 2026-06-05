<template>
    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
        <div class="mb-2 text-sm font-semibold">{{ label }}</div>
        <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-1">
                <label class="text-xs text-zinc-500">Тип</label>
                <select :value="typeModel" :class="crmFieldFluid" @change="emit('update:typeModel', $event.target.value)">
                    <option v-for="opt in options" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <div v-if="showValue" class="space-y-1">
                <label class="text-xs text-zinc-500">Значение</label>
                <input
                    :value="valueModel"
                    type="text"
                    :class="crmFieldFluid"
                    :placeholder="valuePlaceholder"
                    @input="emit('update:valueModel', $event.target.value)"
                />
            </div>
        </div>
        <p v-if="typeHint" class="mt-2 text-xs text-zinc-500">{{ typeHint }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { crmFieldFluid } from '@/support/crmUi.js';

const props = defineProps({
    label: { type: String, required: true },
    typeModel: { type: String, required: true },
    valueModel: { type: String, default: '' },
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:typeModel', 'update:valueModel']);

const showValue = computed(() => props.typeModel === 'text');

const valuePlaceholder = computed(() => {
    if (props.typeModel === 'text') {
        return 'Напр. X';
    }

    return '';
});

const typeHint = computed(() => {
    if (props.typeModel === 'manager_initials') {
        return 'Буквы из ФИО менеджера заказа (ответственного).';
    }

    return '';
});
</script>
