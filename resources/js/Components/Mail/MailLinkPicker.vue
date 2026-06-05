<template>
    <div ref="root" class="relative space-y-1.5">
        <label v-if="label" :class="crmLabel">{{ label }}</label>
        <div class="flex gap-2">
            <input
                v-model="query"
                type="text"
                :class="crmFieldFluid"
                :placeholder="placeholder"
                autocomplete="off"
                @focus="open = true"
                @input="onInput"
            />
            <button
                v-if="modelValue"
                type="button"
                :class="crmBtnSecondaryOutline"
                class="shrink-0 px-3"
                @click="clearSelection"
            >
                Сброс
            </button>
        </div>
        <ul
            v-if="open && suggestions.length > 0"
            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
        >
            <li v-for="option in suggestions" :key="option.id">
                <button
                    type="button"
                    class="block w-full px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-900"
                    @mousedown.prevent="selectOption(option)"
                >
                    {{ option.label }}
                </button>
            </li>
        </ul>
        <p
            v-else-if="open && query.trim() !== '' && !isSearching"
            class="absolute z-30 mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-500 shadow-lg dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400"
        >
            Ничего не найдено
        </p>
        <p v-if="isSearching" class="text-xs text-zinc-500 dark:text-zinc-400">Поиск…</p>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { crmBtnSecondaryOutline, crmFieldFluid, crmLabel } from '@/support/crmUi.js';

const props = defineProps({
    modelValue: {
        type: [Number, null],
        default: null,
    },
    type: {
        type: String,
        required: true,
        validator: (value) => ['lead', 'order'].includes(value),
    },
    seeds: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);
const root = ref(null);
const serverResults = ref([]);
const isSearching = ref(false);
let searchTimer = null;
let searchSeq = 0;

const normalizedSeeds = computed(() =>
    props.seeds
        .map((item) => normalizeOption(item))
        .filter(Boolean),
);

const suggestions = computed(() => {
    const normalized = query.value.trim().toLowerCase();
    const map = new Map();

    for (const option of normalizedSeeds.value) {
        map.set(option.id, option);
    }

    for (const option of serverResults.value) {
        map.set(option.id, option);
    }

    const list = Array.from(map.values());

    if (normalized === '') {
        return list.slice(0, 30);
    }

    return list
        .filter((option) => option.label.toLowerCase().includes(normalized))
        .slice(0, 30);
});

function normalizeOption(item) {
    if (!item || item.id === undefined || item.id === null) {
        return null;
    }

    if (props.type === 'lead') {
        const number = String(item.number ?? '').trim();
        const title = String(item.title ?? '').trim();
        const label = item.label ?? (number !== '' ? `${number} — ${title}` : title);

        return {
            id: Number(item.id),
            label: label || `#${item.id}`,
        };
    }

    const orderNumber = String(item.order_number ?? '').trim();

    return {
        id: Number(item.id),
        label: item.label ?? (orderNumber !== '' ? orderNumber : `#${item.id}`),
    };
}

function selectedLabel() {
    const id = props.modelValue;

    if (id === null || id === undefined) {
        return '';
    }

    const match = suggestions.value.find((option) => option.id === Number(id))
        ?? normalizedSeeds.value.find((option) => option.id === Number(id));

    return match?.label ?? '';
}

function syncQueryFromModel() {
    query.value = selectedLabel();
}

function onInput() {
    open.value = true;

    if (props.modelValue !== null && query.value.trim() !== selectedLabel()) {
        emit('update:modelValue', null);
    }

    scheduleSearch();
}

function selectOption(option) {
    emit('update:modelValue', option.id);
    query.value = option.label;
    open.value = false;
}

function clearSelection() {
    emit('update:modelValue', null);
    query.value = '';
    serverResults.value = [];
    open.value = false;
}

async function fetchLinkOptions(searchQuery) {
    const seq = ++searchSeq;
    isSearching.value = true;

    try {
        const response = await fetch(
            `${route('mail.link-options')}?type=${encodeURIComponent(props.type)}&q=${encodeURIComponent(searchQuery)}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            },
        );

        if (!response.ok || seq !== searchSeq) {
            return;
        }

        const payload = await response.json();
        serverResults.value = (Array.isArray(payload.items) ? payload.items : [])
            .map((item) => normalizeOption(item))
            .filter(Boolean);
    } finally {
        if (seq === searchSeq) {
            isSearching.value = false;
        }
    }
}

function scheduleSearch() {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(async () => {
        const searchQuery = query.value.trim();

        if (searchQuery.length < 1) {
            serverResults.value = [];

            return;
        }

        await fetchLinkOptions(searchQuery);
    }, 300);
}

function onDocumentClick(event) {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
}

watch(() => props.modelValue, syncQueryFromModel);
watch(() => props.seeds, syncQueryFromModel, { deep: true });

onMounted(() => {
    syncQueryFromModel();
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }

    document.removeEventListener('click', onDocumentClick);
});
</script>
