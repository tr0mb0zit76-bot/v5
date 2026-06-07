<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Plus, RotateCcw, Trash2 } from 'lucide-vue-next';
import { crmBtnCreate, crmBtnNeutral, crmFieldFluid, crmPanel } from '@/support/crmUi.js';

const props = defineProps({
    party: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    meta: {
        type: Object,
        default: () => ({
            items: [],
            baseline_items: [],
            source: 'global',
            contractor_id: null,
            has_order_override: false,
            differs_from_baseline: false,
        }),
    },
    orderId: {
        type: [Number, String, null],
        default: null,
    },
    editable: {
        type: Boolean,
        default: true,
    },
    canPromote: {
        type: Boolean,
        default: false,
    },
    canDirectPromote: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:items']);

const rows = ref([]);
const usesOverride = ref(false);
const textareaRefs = ref([]);
let rowKey = 0;

function mapRows(items) {
    return (items ?? []).map((body) => ({
        key: `row-${rowKey += 1}`,
        body: String(body ?? ''),
    }));
}

function syncFromMeta(meta) {
    usesOverride.value = Boolean(meta?.has_order_override);
    rows.value = mapRows(meta?.items ?? []);
    queueAutoResizeAll();
}

watch(
    () => props.meta,
    (meta) => {
        syncFromMeta(meta);
    },
    { immediate: true, deep: true },
);

const sourceLabel = computed(() => ({
    order: 'Переопределение в заявке',
    contractor: 'База контрагента',
    global: 'Общие условия',
}[props.meta?.source ?? 'global'] ?? props.meta?.source));

const differsFromBaseline = computed(() => {
    const baseline = props.meta?.baseline_items ?? [];
    const current = exportItems();

    if (current.length !== baseline.length) {
        return true;
    }

    return current.some((body, index) => body !== (baseline[index] ?? ''));
});

function rowDiffers(index) {
    const baseline = props.meta?.baseline_items ?? [];
    const body = String(rows.value[index]?.body ?? '').trim();

    return body !== String(baseline[index] ?? '').trim();
}

function exportItems() {
    return rows.value
        .map((row) => String(row.body ?? '').trim())
        .filter((body) => body !== '');
}

function emitItems() {
    const items = exportItems();
    const baseline = props.meta?.baseline_items ?? [];
    const matchesBaseline = items.length === baseline.length
        && items.every((body, index) => body === (baseline[index] ?? ''));

    emit('update:items', matchesBaseline ? null : items);
}

function autoResizeTextarea(element) {
    if (!element) {
        return;
    }

    element.style.height = 'auto';
    element.style.height = `${Math.max(element.scrollHeight, 36)}px`;
}

function queueAutoResizeAll() {
    nextTick(() => {
        textareaRefs.value.forEach((element) => autoResizeTextarea(element));
    });
}

function setTextareaRef(element, index) {
    if (element) {
        textareaRefs.value[index] = element;
        autoResizeTextarea(element);
    }
}

function addRow() {
    rows.value.push({ key: `row-${rowKey += 1}`, body: '' });
    usesOverride.value = true;
    emitItems();
    queueAutoResizeAll();
}

function removeRow(index) {
    rows.value.splice(index, 1);
    textareaRefs.value.splice(index, 1);
    usesOverride.value = true;
    emitItems();
    queueAutoResizeAll();
}

function moveRow(index, direction) {
    const target = index + direction;

    if (target < 0 || target >= rows.value.length) {
        return;
    }

    const copy = [...rows.value];
    const [row] = copy.splice(index, 1);
    copy.splice(target, 0, row);
    rows.value = copy;

    const refsCopy = [...textareaRefs.value];
    const [refEl] = refsCopy.splice(index, 1);
    refsCopy.splice(target, 0, refEl);
    textareaRefs.value = refsCopy;

    usesOverride.value = true;
    emitItems();
}

function resetToBaseline() {
    rows.value = mapRows(props.meta?.baseline_items ?? []);
    usesOverride.value = false;
    emit('update:items', null);
    queueAutoResizeAll();
}

function onRowInput(event) {
    usesOverride.value = true;
    autoResizeTextarea(event.target);
    emitItems();
}

const promoting = ref(false);

function promoteToContractor() {
    if (!props.orderId || promoting.value) {
        return;
    }

    promoting.value = true;

    router.post(route('orders.basic-terms.promote', props.orderId), {
        party: props.party,
    }, {
        preserveScroll: true,
        onFinish: () => {
            promoting.value = false;
        },
    });
}

defineExpose({
    exportItems,
});
</script>

<template>
    <section :class="`${crmPanel} space-y-2 p-3`">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <div class="text-sm font-semibold">{{ label }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Источник: {{ sourceLabel }}.
                    <span v-if="differsFromBaseline" class="font-medium text-amber-700 dark:text-amber-300">
                        Есть отличия от стандарта.
                    </span>
                </div>
            </div>
            <button
                v-if="editable"
                type="button"
                :class="`${crmBtnNeutral} h-8 px-2.5 text-xs`"
                @click="resetToBaseline"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                К стандарту
            </button>
        </div>

        <div class="space-y-1.5">
            <div
                v-for="(row, index) in rows"
                :key="row.key"
                class="rounded-lg border px-2 py-1.5"
                :class="rowDiffers(index)
                    ? 'border-amber-300 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/20'
                    : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/40'"
            >
                <div class="mb-1 flex items-center justify-between gap-2">
                    <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">
                        Пункт {{ index + 1 }}
                        <span v-if="rowDiffers(index)" class="text-amber-700 dark:text-amber-300">· изменён</span>
                    </span>
                    <div v-if="editable" class="flex items-center gap-0.5">
                        <button type="button" class="rounded border border-zinc-200 p-1 dark:border-zinc-700" :disabled="index === 0" @click="moveRow(index, -1)">
                            <ChevronUp class="h-3.5 w-3.5" />
                        </button>
                        <button type="button" class="rounded border border-zinc-200 p-1 dark:border-zinc-700" :disabled="index === rows.length - 1" @click="moveRow(index, 1)">
                            <ChevronDown class="h-3.5 w-3.5" />
                        </button>
                        <button type="button" class="rounded border border-rose-200 p-1 text-rose-600 dark:border-rose-900" @click="removeRow(index)">
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
                <textarea
                    :ref="(element) => setTextareaRef(element, index)"
                    v-model="row.body"
                    rows="1"
                    :disabled="!editable"
                    :class="`${crmFieldFluid} min-h-9 resize-none overflow-hidden py-1.5 text-sm leading-snug`"
                    @input="onRowInput"
                />
            </div>

            <div
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed border-zinc-300 px-3 py-4 text-center text-xs text-zinc-500 dark:border-zinc-700"
            >
                Пункты не заданы — при печати таблица условий будет пустой.
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-1">
            <button v-if="editable" type="button" :class="`${crmBtnNeutral} h-8 px-2.5 text-xs`" @click="addRow">
                <Plus class="h-3.5 w-3.5" />
                Пункт
            </button>
            <button
                v-if="canPromote && orderId && differsFromBaseline"
                type="button"
                :class="`${crmBtnCreate} h-8 px-2.5 text-xs`"
                :disabled="promoting"
                @click="promoteToContractor"
            >
                {{ promoting ? 'Сохранение…' : (canDirectPromote ? 'Сохранить как базу для контрагента' : 'Отправить на согласование в карточку') }}
            </button>
        </div>
    </section>
</template>
