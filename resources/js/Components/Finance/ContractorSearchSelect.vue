<template>
    <div ref="root" class="relative space-y-1.5">
        <label v-if="label" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ label }}</label>
        <input
            v-model="query"
            type="text"
            :class="crmFieldFluid"
            :placeholder="placeholder"
            autocomplete="off"
            required
            @focus="open = true"
            @input="onInput"
        />
        <ul
            v-if="open && filteredOptions.length > 0"
            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
        >
            <li
                v-for="option in filteredOptions"
                :key="option.id"
            >
                <button
                    type="button"
                    class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-900"
                    @mousedown.prevent="selectOption(option)"
                >
                    <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ option.label }}</span>
                    <span v-if="option.inn" class="text-xs text-zinc-500 dark:text-zinc-400">ИНН {{ option.inn }}</span>
                </button>
            </li>
        </ul>
        <p v-else-if="open && query.trim() !== ''" class="absolute z-30 mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-500 shadow-lg dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">
            Ничего не найдено
        </p>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { crmFieldFluid } from '@/support/crmUi.js';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Название или ИНН…',
    },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);
const root = ref(null);

const selectedOption = computed(() =>
    props.options.find((option) => String(option.id) === String(props.modelValue ?? '')) ?? null,
);

const filteredOptions = computed(() => {
    const normalized = query.value.trim().toLowerCase().replace(/\s+/g, '');
    const list = props.options;

    if (normalized === '') {
        return list.slice(0, 40);
    }

    return list
        .filter((option) => {
            const label = String(option.label ?? '').toLowerCase();
            const inn = String(option.inn ?? '').replace(/\s+/g, '');

            return label.includes(normalized) || inn.includes(normalized);
        })
        .slice(0, 40);
});

function optionLabel(option) {
    const inn = option.inn ? ` · ИНН ${option.inn}` : '';

    return `${option.label}${inn}`;
}

function syncQueryFromModel() {
    query.value = selectedOption.value ? optionLabel(selectedOption.value) : '';
}

function onInput() {
    open.value = true;

    if (selectedOption.value && query.value.trim() !== optionLabel(selectedOption.value)) {
        emit('update:modelValue', '');
    }
}

function selectOption(option) {
    emit('update:modelValue', String(option.id));
    query.value = optionLabel(option);
    open.value = false;
}

function onDocumentClick(event) {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
}

watch(() => props.modelValue, syncQueryFromModel);

onMounted(() => {
    syncQueryFromModel();
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>
