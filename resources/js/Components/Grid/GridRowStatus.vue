<template>
    <span
        class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400"
        :title="label"
    >
        {{ label }}
    </span>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { buildGridRowStatusLabel, refreshGridDisplayedRowCount } from '@/support/gridRowCounts.js';

const props = defineProps({
    getGridApi: { type: Function, default: null },
    totalCount: { type: Number, default: 0 },
    selectedCount: { type: Number, default: 0 },
    quickSearch: { type: String, default: '' },
    suffix: { type: String, default: '' },
});

const displayedCount = ref(0);

let detachListeners = null;

function refresh() {
    displayedCount.value = refreshGridDisplayedRowCount(props.getGridApi?.(), props.totalCount);
}

function attachListeners(api) {
    detachListeners?.();

    if (!api) {
        refresh();

        return;
    }

    const handler = () => refresh();
    const events = ['filterChanged', 'modelUpdated', 'rowDataUpdated', 'selectionChanged'];

    events.forEach((eventName) => api.addEventListener(eventName, handler));
    detachListeners = () => {
        events.forEach((eventName) => api.removeEventListener(eventName, handler));
        detachListeners = null;
    };

    refresh();
}

watch(
    () => props.getGridApi?.(),
    (api) => attachListeners(api),
    { immediate: true },
);

watch(
    () => [props.totalCount, props.selectedCount, props.quickSearch],
    () => refresh(),
);

onBeforeUnmount(() => {
    detachListeners?.();
});

const label = computed(() => buildGridRowStatusLabel({
    displayedCount: displayedCount.value,
    totalCount: props.totalCount,
    selectedCount: props.selectedCount,
    quickSearch: props.quickSearch,
    getGridApi: props.getGridApi,
    suffix: props.suffix,
}));

defineExpose({ refresh });
</script>
