<script setup>
import axios from 'axios';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { entityKindLabel } from '@/support/mobileMessageLinks.js';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'select']);

const kind = ref('all');
const search = ref('');
const entities = ref([]);
const loading = ref(false);

const kindTabs = [
    { key: 'all', label: 'Все' },
    { key: 'order', label: 'Заказы' },
    { key: 'lead', label: 'Лиды' },
    { key: 'contractor', label: 'Контрагенты' },
    { key: 'document', label: 'Документы' },
];

let searchTimer = null;

async function loadEntities() {
    loading.value = true;

    try {
        const params = {};
        if (search.value.trim() !== '') {
            params.q = search.value.trim();
        }
        if (kind.value !== 'all') {
            params.kind = kind.value;
        }

        const { data } = await axios.get(route('mobile.shell.entity-chips'), {
            headers: { Accept: 'application/json' },
            params,
        });

        entities.value = data.entities ?? [];
    } finally {
        loading.value = false;
    }
}

function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadEntities, 250);
}

function pickEntity(entity) {
    emit('select', entity);
    emit('close');
}

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        search.value = '';
        kind.value = 'all';
        loadEntities();
    }
});

watch(kind, () => {
    if (props.open) {
        loadEntities();
    }
});
</script>

<template>
    <div
        v-if="open"
        class="absolute inset-0 z-30 flex flex-col justify-end bg-black/60"
        @click.self="emit('close')"
    >
        <div class="flex max-h-[78dvh] flex-col overflow-hidden rounded-t-3xl border border-white/10 bg-zinc-900">
            <div class="border-b border-white/10 px-4 py-3">
                <div class="text-sm font-semibold text-zinc-100">Ссылка на сущность CRM</div>
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="tab in kindTabs"
                        :key="tab.key"
                        type="button"
                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium"
                        :class="kind === tab.key ? 'bg-sky-600 text-white' : 'bg-white/10 text-zinc-300'"
                        @click="kind = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <div class="relative mt-3">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
                    <input
                        v-model="search"
                        class="w-full rounded-2xl border border-white/10 bg-zinc-950 py-2.5 pl-10 pr-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                        placeholder="Поиск по номеру, названию, id…"
                        @input="scheduleSearch"
                    />
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto py-1">
                <div v-if="loading" class="px-4 py-6 text-center text-sm text-zinc-500">Загрузка…</div>
                <button
                    v-for="entity in entities"
                    v-else
                    :key="`${entity.kind}-${entity.id}`"
                    type="button"
                    class="flex w-full flex-col gap-1 px-4 py-3 text-left active:bg-white/10"
                    @click="pickEntity(entity)"
                >
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] uppercase tracking-wide text-sky-200">
                            {{ entityKindLabel(entity.kind) }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-100">{{ entity.label }}</span>
                    </div>
                    <span v-if="entity.subtitle" class="truncate text-xs text-zinc-500">{{ entity.subtitle }}</span>
                    <span class="truncate text-[10px] text-zinc-600">{{ entity.url }}</span>
                </button>
                <div v-if="!loading && entities.length === 0" class="px-4 py-6 text-center text-sm text-zinc-500">
                    Ничего не найдено.
                </div>
            </div>
        </div>
    </div>
</template>
