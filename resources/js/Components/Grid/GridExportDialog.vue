<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="close"
        >
            <div :class="`${crmModalPanel} flex max-h-[90vh] w-full max-w-2xl flex-col shadow-2xl`">
                <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <div>
                        <div class="text-lg font-semibold">Экспорт в CSV</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            Выгружаются строки с учётом текущих фильтров и поиска в таблице
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                    <section v-if="responsibleOptions.length > 0">
                        <div class="mb-2 text-sm font-medium">{{ responsibleLabel }}</div>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="responsibleMode" type="radio" class="rounded border-zinc-300" value="grid" />
                                <span>Как в таблице сейчас</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="responsibleMode" type="radio" class="rounded border-zinc-300" value="selected" />
                                <span>Выбрать вручную</span>
                            </label>
                        </div>

                        <div
                            v-if="responsibleMode === 'selected'"
                            class="mt-3 grid max-h-40 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-zinc-200 p-3 dark:border-zinc-800 md:grid-cols-2"
                        >
                            <label
                                v-for="option in responsibleOptions"
                                :key="responsibleOptionKey(option)"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="selectedResponsibleKeys"
                                    type="checkbox"
                                    class="rounded border-zinc-300"
                                    :value="responsibleOptionKey(option)"
                                />
                                <span>{{ option.label }}</span>
                            </label>
                        </div>
                    </section>

                    <section>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div class="text-sm font-medium">Колонки</div>
                            <div class="flex items-center gap-2 text-xs">
                                <button type="button" class="text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100" @click="selectAllColumns">
                                    Все
                                </button>
                                <button type="button" class="text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100" @click="selectVisibleColumns">
                                    Как в таблице
                                </button>
                            </div>
                        </div>

                        <div class="grid max-h-[40vh] grid-cols-1 gap-2 overflow-y-auto md:grid-cols-2">
                            <label
                                v-for="column in localColumns"
                                :key="column.field"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 px-3 py-2 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60"
                            >
                                <input
                                    v-model="column.visible"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-zinc-300"
                                />
                                <span class="text-sm">{{ column.headerName }}</span>
                            </label>
                        </div>
                    </section>
                </div>

                <div class="flex shrink-0 items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <button type="button" :class="crmBtnNeutral" @click="close">
                        Отмена
                    </button>
                    <button
                        type="button"
                        :class="crmBtnCreate"
                        :disabled="selectedColumnCount === 0 || !canSubmitResponsible"
                        @click="submit"
                    >
                        Скачать CSV
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';
import { crmBtnCreate, crmBtnNeutral, crmModalPanel } from '@/support/crmUi.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    columns: {
        type: Array,
        default: () => [],
    },
    responsibleOptions: {
        type: Array,
        default: () => [],
    },
    responsibleLabel: {
        type: String,
        default: 'Ответственные',
    },
});

const emit = defineEmits(['close', 'export']);

const localColumns = ref([]);
const responsibleMode = ref('grid');
const selectedResponsibleKeys = ref([]);

const selectedColumnCount = computed(() => localColumns.value.filter((column) => column.visible).length);

const canSubmitResponsible = computed(() => {
    if (props.responsibleOptions.length === 0 || responsibleMode.value === 'grid') {
        return true;
    }

    return selectedResponsibleKeys.value.length > 0;
});

function responsibleOptionKey(option) {
    if (option?.id !== null && option?.id !== undefined && option?.id !== '') {
        return `id:${option.id}`;
    }

    return `name:${option.label}`;
}

function parseResponsibleKeys(keys) {
    return keys.map((key) => {
        if (String(key).startsWith('id:')) {
            return Number(String(key).slice(3));
        }

        if (String(key).startsWith('name:') && String(key).slice(5) === '—') {
            return null;
        }

        return null;
    });
}

function cloneColumns(columns) {
    return (Array.isArray(columns) ? columns : []).map((column) => ({
        field: column.field,
        headerName: column.headerName ?? column.field,
        visible: column.visible !== false,
    }));
}

watch(
    () => props.show,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        localColumns.value = cloneColumns(props.columns);
        responsibleMode.value = 'grid';
        selectedResponsibleKeys.value = props.responsibleOptions.map((option) => responsibleOptionKey(option));
    },
);

watch(
    () => props.columns,
    (columns) => {
        if (props.show) {
            localColumns.value = cloneColumns(columns);
        }
    },
    { deep: true },
);

function selectAllColumns() {
    localColumns.value = localColumns.value.map((column) => ({ ...column, visible: true }));
}

function selectVisibleColumns() {
    localColumns.value = cloneColumns(props.columns);
}

function close() {
    emit('close');
}

function submit() {
    emit('export', {
        columns: localColumns.value.filter((column) => column.visible),
        responsibleMode: responsibleMode.value,
        responsibleIds: responsibleMode.value === 'selected'
            ? parseResponsibleKeys(selectedResponsibleKeys.value)
            : [],
    });
}
</script>
