<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { crmFieldFluid } from '@/support/crmUi.js';

const props = defineProps({
    excludeOrderId: {
        type: [Number, String],
        default: null,
    },
    placeholder: {
        type: String,
        default: 'Номер заказа (мин. 2 символа)',
    },
    minQueryLength: {
        type: Number,
        default: 2,
    },
    fieldClass: {
        type: String,
        default: crmFieldFluid,
    },
});

const emit = defineEmits(['select']);

const root = ref(null);
const query = ref('');
const open = ref(false);
const searching = ref(false);
const results = ref([]);
const searchTimer = ref(null);
const abortController = ref(null);
const fetchSeq = ref(0);

function onFocus() {
    open.value = true;
}

function onInput() {
    open.value = true;
    scheduleSearch(query.value.trim());
}

function selectOption(option) {
    emit('select', option);
    query.value = '';
    results.value = [];
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
        const params = new URLSearchParams({ q: trimmed });
        if (props.excludeOrderId) {
            params.set('exclude_order_id', String(props.excludeOrderId));
        }

        const response = await fetch(`${route('orders.link-search')}?${params}`, {
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

        results.value = Array.isArray(data.data) ? data.data : [];
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
    }
}

watch(() => props.excludeOrderId, () => {
    results.value = [];
});

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    clearTimeout(searchTimer.value);
    abortController.value?.abort();
});
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <input
            v-model="query"
            type="text"
            :class="fieldClass"
            :placeholder="placeholder"
            autocomplete="off"
            @focus="onFocus"
            @input="onInput"
        >
        <ul
            v-if="open"
            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
        >
            <li v-if="searching" class="px-3 py-2 text-center text-xs text-zinc-500">Поиск…</li>
            <li v-for="option in results" :key="option.id">
                <button
                    type="button"
                    class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-900"
                    @mousedown.prevent="selectOption(option)"
                >
                    <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ option.order_number || ('#' + option.id) }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ [option.own_company_name, option.customer_name].filter(Boolean).join(' · ') || 'Без реквизитов' }}
                    </span>
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
            <li
                v-else-if="!searching && query.trim().length === 0"
                class="px-3 py-2 text-xs text-zinc-500"
            >
                Начните вводить номер заказа
            </li>
        </ul>
    </div>
</template>
