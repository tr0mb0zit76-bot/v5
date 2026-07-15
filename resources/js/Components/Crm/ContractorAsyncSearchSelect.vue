<template>
    <div ref="root" class="relative min-w-0 flex-1">
        <input
            v-model="query"
            type="text"
            :class="fieldClass"
            :placeholder="placeholder"
            autocomplete="off"
            @focus="onFocus"
            @input="onInput"
        />
        <ul
            v-if="open"
            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
        >
            <li>
                <button
                    type="button"
                    class="flex w-full items-center px-3 py-2 text-left text-zinc-500 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-900"
                    @mousedown.prevent="clearSelection"
                >
                    {{ clearLabel }}
                </button>
            </li>
            <li v-if="searching" class="px-3 py-2 text-center text-xs text-zinc-500">Поиск…</li>
            <li v-for="option in results" :key="option.id">
                <button
                    type="button"
                    class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-900"
                    @mousedown.prevent="selectOption(option)"
                >
                    <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ option.name }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ option.inn || 'Без ИНН' }}</span>
                </button>
            </li>
            <li
                v-if="!searching && query.trim().length >= minQueryLength && results.length === 0"
                class="px-3 py-2 text-zinc-500"
            >
                Ничего не найдено
            </li>
            <li
                v-else-if="!searching && query.trim().length > 0 && query.trim().length < minQueryLength"
                class="px-3 py-2 text-xs text-zinc-500"
            >
                Введите ещё {{ minQueryLength - query.trim().length }} симв.
            </li>
        </ul>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { crmFieldFluid } from '@/support/crmUi.js';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
    },
    /** Подпись выбранного контрагента (если id уже есть, а списка нет). */
    selectedLabel: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Поиск по названию, ИНН, телефону, email',
    },
    clearLabel: {
        type: String,
        default: 'Без привязки',
    },
    minQueryLength: {
        type: Number,
        default: 2,
    },
    fieldClass: {
        type: String,
        default: crmFieldFluid,
    },
    searchType: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const query = ref('');
const open = ref(false);
const searching = ref(false);
const results = ref([]);
const searchTimer = ref(null);
const abortController = ref(null);
const fetchSeq = ref(0);

function syncQueryFromSelection() {
    if (props.modelValue === null || props.modelValue === '' || props.modelValue === undefined) {
        if (!open.value) {
            query.value = '';
        }
        return;
    }

    const fromResults = results.value.find((row) => Number(row.id) === Number(props.modelValue));
    if (fromResults) {
        query.value = fromResults.name;
        return;
    }

    if (props.selectedLabel) {
        query.value = props.selectedLabel;
    }
}

function onFocus() {
    open.value = true;
}

function onInput() {
    open.value = true;

    if (props.modelValue !== null && props.modelValue !== '' && props.modelValue !== undefined) {
        emit('update:modelValue', null);
    }

    scheduleSearch(query.value.trim());
}

function clearSelection() {
    emit('update:modelValue', null);
    query.value = '';
    results.value = [];
    open.value = false;
}

function selectOption(option) {
    emit('update:modelValue', option.id);
    query.value = option.name;
    open.value = false;
}

function scheduleSearch(trimmed) {
    clearTimeout(searchTimer.value);

    if (trimmed.length < props.minQueryLength) {
        abortController.value?.abort();
        fetchSeq.value += 1;
        results.value = [];
        searching.value = false;
        return;
    }

    searchTimer.value = setTimeout(() => {
        void runSearch(trimmed);
    }, 350);
}

async function runSearch(trimmed) {
    abortController.value?.abort();
    const ac = new AbortController();
    abortController.value = ac;
    const seq = (fetchSeq.value += 1);
    searching.value = true;

    try {
        const params = new URLSearchParams({
            q: trimmed,
            limit: '50',
        });
        if (props.searchType) {
            params.set('type', props.searchType);
        }

        const response = await fetch(`${route('contractors.search')}?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
            signal: ac.signal,
        });

        if (!response.ok) {
            throw new Error(`status ${response.status}`);
        }

        const data = await response.json();
        if (seq !== fetchSeq.value) {
            return;
        }

        results.value = Array.isArray(data.contractors) ? data.contractors : [];
    } catch (error) {
        if (error?.name !== 'AbortError' && seq === fetchSeq.value) {
            results.value = [];
        }
    } finally {
        if (seq === fetchSeq.value) {
            searching.value = false;
        }
    }
}

function onDocumentClick(event) {
    if (!root.value?.contains(event.target)) {
        open.value = false;
        syncQueryFromSelection();
    }
}

watch(() => [props.modelValue, props.selectedLabel], () => {
    if (!open.value) {
        syncQueryFromSelection();
    }
});

onMounted(() => {
    syncQueryFromSelection();
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    clearTimeout(searchTimer.value);
    abortController.value?.abort();
    document.removeEventListener('click', onDocumentClick);
});
</script>
