<template>
    <ul class="space-y-0.5" :class="depth > 0 ? 'ml-3 border-l border-zinc-200 pl-2 dark:border-zinc-700' : ''">
        <li
            v-for="node in nodes"
            :key="node.id"
            :class="depth === 0 ? 'border-t border-zinc-100 pt-2 first:border-t-0 first:pt-0 dark:border-zinc-800' : ''"
        >
            <div
                class="group flex items-center gap-1 rounded-lg transition"
                :class="rowClass(node)"
                @dragover.prevent="onRowDragOver($event, node)"
                @dragleave="onRowDragLeave(node)"
                @drop.prevent="onRowDrop(node)"
            >
                <button
                    v-if="(node.children ?? []).length > 0"
                    type="button"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    :aria-label="expandedIds.has(node.id) ? 'Свернуть' : 'Развернуть'"
                    @click.stop="emit('toggle', node.id)"
                >
                    <span class="text-[10px] transition" :class="expandedIds.has(node.id) ? 'rotate-90' : ''">▶</span>
                </button>
                <span v-else class="inline-block h-6 w-6 shrink-0" />

                <button
                    v-if="canWrite"
                    type="button"
                    draggable="true"
                    class="flex h-6 w-5 shrink-0 cursor-grab items-center justify-center text-zinc-400 opacity-0 transition group-hover:opacity-100 active:cursor-grabbing"
                    aria-label="Перетащить страницу"
                    @dragstart="onDragStart($event, node.id)"
                    @dragend="emit('drag-end')"
                >
                    ⋮⋮
                </button>
                <span v-else class="inline-block h-6 w-5 shrink-0" />

                <button
                    type="button"
                    class="min-w-0 flex-1 truncate py-1.5 text-left text-sm"
                    @click="emit('select', node.id)"
                >
                    {{ node.title }}
                </button>
                <span
                    v-if="node.status === 'draft'"
                    class="mr-1 shrink-0 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-100"
                >
                    Черновик
                </span>
            </div>

            <div
                v-if="dropHint?.targetId === node.id && dropHint.position === 'before'"
                class="mx-2 h-0.5 rounded bg-sky-500"
            />
            <div
                v-if="dropHint?.targetId === node.id && dropHint.position === 'after'"
                class="mx-2 h-0.5 rounded bg-sky-500"
            />

            <SalesBookTreeNavNode
                v-if="expandedIds.has(node.id) && (node.children ?? []).length > 0"
                :nodes="node.children"
                :depth="depth + 1"
                :selected-id="selectedId"
                :can-write="canWrite"
                :expanded-ids="expandedIds"
                :dragging-id="draggingId"
                :drop-hint="dropHint"
                :invalid-target-ids="invalidTargetIds"
                @toggle="(id) => emit('toggle', id)"
                @select="(id) => emit('select', id)"
                @drag-start="(id) => emit('drag-start', id)"
                @drag-end="emit('drag-end')"
                @drag-over="(payload) => emit('drag-over', payload)"
                @drop="(payload) => emit('drop', payload)"
            />
        </li>
    </ul>
</template>

<script setup>
import { crmListItemActiveSoft } from '@/support/crmUi.js';

defineOptions({
    name: 'SalesBookTreeNavNode',
});

const props = defineProps({
    nodes: {
        type: Array,
        default: () => [],
    },
    depth: {
        type: Number,
        default: 0,
    },
    selectedId: {
        type: Number,
        default: null,
    },
    canWrite: {
        type: Boolean,
        default: false,
    },
    expandedIds: {
        type: Object,
        required: true,
    },
    draggingId: {
        type: Number,
        default: null,
    },
    dropHint: {
        type: Object,
        default: null,
    },
    invalidTargetIds: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['toggle', 'select', 'drag-start', 'drag-end', 'drag-over', 'drop']);

function rowClass(node) {
    const classes = [];

    if (props.selectedId === node.id) {
        classes.push(crmListItemActiveSoft);
    } else {
        classes.push('text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800');
    }

    if (props.dropHint?.targetId === node.id && props.dropHint.position === 'inside' && !props.invalidTargetIds.has(node.id)) {
        classes.push('bg-sky-50 ring-1 ring-sky-300 dark:bg-sky-950/40 dark:ring-sky-700');
    }

    return classes.join(' ');
}

function onDragStart(event, id) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(id));
    emit('drag-start', id);
}

function resolveDropPosition(event) {
    const rect = event.currentTarget.getBoundingClientRect();
    const ratio = (event.clientY - rect.top) / rect.height;

    if (ratio < 0.28) {
        return 'before';
    }

    if (ratio > 0.72) {
        return 'after';
    }

    return 'inside';
}

function buildMovePayload(node, position) {
    if (position === 'inside') {
        return {
            targetId: node.id,
            position,
            parent_id: node.id,
            sort_order: (node.children ?? []).length,
        };
    }

    return {
        targetId: node.id,
        position,
        parent_id: node.parent_id ?? null,
        sort_order: position === 'before' ? node.sort_order : node.sort_order + 1,
    };
}

function onRowDragOver(event, node) {
    if (props.invalidTargetIds.has(node.id)) {
        return;
    }

    const position = resolveDropPosition(event);
    emit('drag-over', buildMovePayload(node, position));
}

function onRowDragLeave(node) {
    if (props.dropHint?.targetId === node.id) {
        emit('drag-over', null);
    }
}

function onRowDrop(node) {
    if (props.invalidTargetIds.has(node.id) || !props.dropHint || props.dropHint.targetId !== node.id) {
        return;
    }

    emit('drop', {
        targetId: node.id,
        parent_id: props.dropHint.parent_id,
        sort_order: props.dropHint.sort_order,
    });
}
</script>
