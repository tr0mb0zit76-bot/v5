<template>
    <div class="space-y-1">
        <SalesBookTreeNavNode
            :nodes="tree"
            :depth="0"
            :selected-id="selectedId"
            :can-write="canWrite"
            :expanded-ids="expandedIds"
            :dragging-id="draggingId"
            :drop-hint="dropHint"
            :invalid-target-ids="invalidTargetIds"
            @toggle="toggleExpanded"
            @select="selectArticle"
            @drag-start="onDragStart"
            @drag-end="onDragEnd"
            @drag-over="onDragOver"
            @drop="onDrop"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import SalesBookTreeNavNode from '@/Components/SalesBook/SalesBookTreeNavNode.vue';

const props = defineProps({
    tree: {
        type: Array,
        default: () => [],
    },
    articleOptions: {
        type: Array,
        default: () => [],
    },
    selectedId: {
        type: Number,
        default: null,
    },
    canWrite: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select', 'move']);

const expandedIds = ref(new Set());
const draggingId = ref(null);
const dropHint = ref(null);

watch(
    () => props.selectedId,
    (selectedId) => {
        ensureAncestorsExpanded(selectedId);
    },
    { immediate: true },
);

watch(
    () => props.articleOptions,
    () => {
        ensureAncestorsExpanded(props.selectedId);
    },
    { deep: true },
);

const invalidTargetIds = computed(() => {
    if (draggingId.value === null) {
        return new Set();
    }

    return new Set([draggingId.value, ...collectDescendantIds(draggingId.value, props.articleOptions)]);
});

function collectAncestorIds(articleId, options) {
    if (!articleId) {
        return [];
    }

    const parentById = new Map(
        options.map((option) => [
            Number(option.id),
            option.parent_id === null || option.parent_id === undefined
                ? null
                : Number(option.parent_id),
        ]),
    );

    const ancestors = [];
    let current = parentById.get(Number(articleId));

    while (current !== null && current !== undefined) {
        ancestors.push(current);
        current = parentById.get(current) ?? null;
    }

    return ancestors;
}

function ensureAncestorsExpanded(articleId) {
    if (!articleId) {
        return;
    }

    const next = new Set(expandedIds.value);
    collectAncestorIds(articleId, props.articleOptions).forEach((id) => next.add(Number(id)));
    expandedIds.value = next;
}

function selectArticle(id) {
    ensureAncestorsExpanded(id);
    emit('select', id);
}

function collectDescendantIds(articleId, options) {
    const childrenByParent = new Map();

    options.forEach((option) => {
        if (option.parent_id === null || option.parent_id === undefined) {
            return;
        }

        const parentId = Number(option.parent_id);
        const current = childrenByParent.get(parentId) ?? [];
        current.push(Number(option.id));
        childrenByParent.set(parentId, current);
    });

    const descendants = [];
    const queue = [...(childrenByParent.get(Number(articleId)) ?? [])];

    while (queue.length > 0) {
        const childId = queue.shift();
        descendants.push(childId);
        queue.push(...(childrenByParent.get(childId) ?? []));
    }

    return descendants;
}

function toggleExpanded(id) {
    const next = new Set(expandedIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expandedIds.value = next;
}

function onDragStart(id) {
    draggingId.value = id;
}

function onDragEnd() {
    draggingId.value = null;
    dropHint.value = null;
}

function onDragOver(payload) {
    dropHint.value = payload;
}

function onDrop(payload) {
    if (draggingId.value === null || invalidTargetIds.value.has(payload.targetId)) {
        onDragEnd();

        return;
    }

    emit('move', {
        id: draggingId.value,
        parent_id: payload.parent_id,
        sort_order: payload.sort_order,
    });

    onDragEnd();
}

</script>
