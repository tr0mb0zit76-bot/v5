<template>
    <div class="script-graph flex min-h-[720px] flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100/80 dark:border-zinc-700 dark:bg-zinc-900/50">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 bg-white/90 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-950/80">
            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" /> Сказать
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">
                    <span class="h-2 w-2 rounded-full bg-sky-500" /> Спросить
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">
                    <span class="h-2 w-2 rounded-full bg-violet-500" /> Ветвление
                </span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Тяните связь от <span class="font-medium text-sky-600 dark:text-sky-400">●</span> на грани блока к ● целевого блока — сторона выбирается по расположению
            </p>
        </div>

        <div
            ref="viewportRef"
            class="script-graph__viewport relative min-h-[640px] flex-1 overflow-auto"
            @mousedown.self="clearSelection"
        >
            <div
                class="script-graph__surface relative"
                :style="surfaceStyle"
            >
                <svg class="pointer-events-none absolute inset-0 h-full w-full overflow-visible">
                    <defs>
                        <marker
                            id="script-graph-arrow"
                            markerWidth="8"
                            markerHeight="8"
                            refX="7"
                            refY="4"
                            orient="auto"
                        >
                            <path d="M0,0 L8,4 L0,8 Z" class="fill-sky-500 dark:fill-sky-400" />
                        </marker>
                    </defs>
                    <g v-for="edge in edgePaths" :key="edge.id">
                        <path
                            :d="edge.d"
                            class="pointer-events-stroke cursor-pointer transition"
                            :class="selectedTransitionId === edge.id
                                ? 'stroke-sky-500 stroke-[3px] dark:stroke-sky-400'
                                : 'stroke-zinc-400 stroke-2 hover:stroke-sky-400 dark:stroke-zinc-500'"
                            fill="none"
                            marker-end="url(#script-graph-arrow)"
                            @click.stop="selectTransition(edge.id)"
                        />
                        <foreignObject
                            :x="edge.labelX - 110"
                            :y="edge.labelY - 18"
                            width="220"
                            height="36"
                            class="pointer-events-none overflow-visible"
                        >
                            <div
                                xmlns="http://www.w3.org/1999/xhtml"
                                class="mx-auto max-w-[210px] truncate rounded-full border border-zinc-200/80 bg-white/95 px-2.5 py-1 text-center text-[10px] font-medium text-zinc-600 shadow-sm dark:border-zinc-600 dark:bg-zinc-900/95 dark:text-zinc-300"
                            >
                                {{ edge.label }}
                            </div>
                        </foreignObject>
                    </g>
                    <path
                        v-if="linkDraft.active"
                        :d="linkDraftPath"
                        class="stroke-sky-500 stroke-2 dark:stroke-sky-400"
                        fill="none"
                        stroke-dasharray="6 4"
                    />
                </svg>

                <article
                    v-for="node in nodes"
                    :key="node.client_key"
                    class="script-graph__node absolute w-[220px] overflow-visible rounded-2xl border bg-white shadow-md transition dark:bg-zinc-950"
                    :class="[
                        kindBorderClass(node.kind),
                        selectedNodeKey === node.client_key ? 'ring-2 ring-sky-500/40' : '',
                        entryNodeKey === node.client_key ? 'script-graph__node--entry' : '',
                    ]"
                    :style="{ left: `${node.canvas_x}px`, top: `${node.canvas_y}px` }"
                    @mousedown.stop
                    @click.stop="selectNode(node.client_key)"
                >
                    <div
                        v-if="entryNodeKey === node.client_key"
                        class="overflow-hidden rounded-t-2xl bg-emerald-600 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-white"
                    >
                        Старт
                    </div>
                    <header
                        class="script-graph__handle flex cursor-grab items-center justify-between gap-2 border-b border-zinc-100 px-3 py-2 active:cursor-grabbing dark:border-zinc-800"
                        @mousedown="startNodeDrag($event, node.client_key)"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ node.client_key }}</div>
                            <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100">{{ kindLabel(node.kind) }}</div>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium" :class="kindBadgeClass(node.kind)">
                            {{ kindShort(node.kind) }}
                        </span>
                    </header>
                    <div class="h-2" />
                    <button
                        v-for="side in portSides"
                        :key="`${node.client_key}-${side}`"
                        type="button"
                        class="script-graph__port"
                        :class="`script-graph__port--${side}`"
                        :title="portTitle(side)"
                        @mousedown.stop="startLink($event, node.client_key, side)"
                        @mouseup.stop="finishLink(node.client_key, side)"
                    />
                </article>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import {
    bezierPathBetween,
    edgeGeometryBetweenNodes,
    portPoint,
    sideTowardPoint,
    boundsCenter,
} from '@/support/graphEdgeGeometry.js';

const NODE_WIDTH = 220;
const NODE_BODY_HEIGHT = 88;
const GRID_PAD = 48;
const portSides = ['top', 'right', 'bottom', 'left'];

const props = defineProps({
    nodes: { type: Array, required: true },
    transitions: { type: Array, required: true },
    entryNodeKey: { type: String, default: '' },
    nodeKinds: { type: Array, default: () => [] },
    reactionClasses: { type: Array, default: () => [] },
    selectedNodeKey: { type: String, default: null },
    selectedTransitionId: { type: String, default: null },
});

const emit = defineEmits([
    'update:selectedNodeKey',
    'update:selectedTransitionId',
    'update:nodePosition',
    'create-transition',
]);

const viewportRef = ref(null);

const linkDraft = reactive({
    active: false,
    fromKey: null,
    fromSide: 'bottom',
    x: 0,
    y: 0,
});

const dragState = reactive({
    active: false,
    nodeKey: null,
    startX: 0,
    startY: 0,
    originX: 0,
    originY: 0,
});

const surfaceStyle = computed(() => {
    let maxX = 800;
    let maxY = 600;

    for (const node of props.nodes) {
        const bounds = nodeBounds(node);
        maxX = Math.max(maxX, bounds.x + bounds.width + GRID_PAD);
        maxY = Math.max(maxY, bounds.y + bounds.height + GRID_PAD);
    }

    return {
        width: `${maxX}px`,
        height: `${maxY}px`,
        minWidth: '100%',
        minHeight: '640px',
    };
});

const nodeByKey = computed(() => {
    const map = new Map();
    for (const node of props.nodes) {
        map.set(node.client_key, node);
    }

    return map;
});

function nodeBounds(node) {
    const entryExtra = props.entryNodeKey === node.client_key ? 24 : 0;

    return {
        x: Number(node.canvas_x),
        y: Number(node.canvas_y),
        width: NODE_WIDTH,
        height: NODE_BODY_HEIGHT + entryExtra,
    };
}

function portTitle(side) {
    return ({
        top: 'Связь сверху',
        right: 'Связь справа',
        bottom: 'Связь снизу',
        left: 'Связь слева',
    })[side] ?? side;
}

const edgePaths = computed(() => {
    return props.transitions
        .map((transition) => {
            const from = nodeByKey.value.get(transition.from_client_key);
            const to = nodeByKey.value.get(transition.to_client_key);

            if (!from || !to) {
                return null;
            }

            const geometry = edgeGeometryBetweenNodes(nodeBounds(from), nodeBounds(to));

            return {
                id: transition.local_id,
                d: geometry.path,
                labelX: geometry.labelX,
                labelY: geometry.labelY,
                label: transitionLabel(transition),
            };
        })
        .filter(Boolean);
});

const linkDraftPath = computed(() => {
    if (!linkDraft.active || !linkDraft.fromKey) {
        return '';
    }

    const from = nodeByKey.value.get(linkDraft.fromKey);
    if (!from) {
        return '';
    }

    const start = portPoint(nodeBounds(from), linkDraft.fromSide);
    const pointer = { x: linkDraft.x, y: linkDraft.y };
    const draftSide = sideTowardPoint(boundsCenter(nodeBounds(from)), pointer);
    const end = portPoint(
        {
            x: pointer.x,
            y: pointer.y,
            width: 0,
            height: 0,
            centerX: pointer.x,
            centerY: pointer.y,
        },
        draftSide,
    );

    return bezierPathBetween(start, linkDraft.fromSide, end, draftSide, 32);
});

function kindLabel(kind) {
    return props.nodeKinds.find((item) => item.value === kind)?.label ?? kind;
}

function kindShort(kind) {
    return ({ say: 'SAY', ask: 'ASK', branch: 'IF' })[kind] ?? kind;
}

function kindBorderClass(kind) {
    return ({
        say: 'border-l-4 border-l-emerald-500 border-zinc-200 dark:border-zinc-700',
        ask: 'border-l-4 border-l-sky-500 border-zinc-200 dark:border-zinc-700',
        branch: 'border-l-4 border-l-violet-500 border-zinc-200 dark:border-zinc-700',
    })[kind] ?? 'border-zinc-200 dark:border-zinc-700';
}

function kindBadgeClass(kind) {
    return ({
        say: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
        ask: 'bg-sky-50 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        branch: 'bg-violet-50 text-violet-800 dark:bg-violet-950/50 dark:text-violet-200',
    })[kind] ?? 'bg-zinc-100 text-zinc-700';
}

function transitionLabel(transition) {
    if (transition.customer_label) {
        return transition.customer_label;
    }

    if (transition.sales_script_reaction_class_id === null || transition.sales_script_reaction_class_id === undefined) {
        return 'Дальше';
    }

    const reaction = props.reactionClasses.find((item) => item.id === transition.sales_script_reaction_class_id);

    return reaction?.label ?? 'Реакция';
}

function selectNode(clientKey) {
    emit('update:selectedNodeKey', clientKey);
    emit('update:selectedTransitionId', null);
}

function selectTransition(localId) {
    emit('update:selectedTransitionId', localId);
}

function clearSelection() {
    emit('update:selectedNodeKey', null);
    emit('update:selectedTransitionId', null);
}

function startNodeDrag(event, clientKey) {
    const node = nodeByKey.value.get(clientKey);
    if (!node) {
        return;
    }

    dragState.active = true;
    dragState.nodeKey = clientKey;
    dragState.startX = event.clientX;
    dragState.startY = event.clientY;
    dragState.originX = node.canvas_x;
    dragState.originY = node.canvas_y;
    emit('update:selectedNodeKey', clientKey);

    window.addEventListener('mousemove', onNodeDragMove);
    window.addEventListener('mouseup', stopNodeDrag, { once: true });
}

function onNodeDragMove(event) {
    if (!dragState.active || !dragState.nodeKey) {
        return;
    }

    const dx = event.clientX - dragState.startX;
    const dy = event.clientY - dragState.startY;

    emit('update:nodePosition', {
        client_key: dragState.nodeKey,
        canvas_x: Math.max(0, Math.round(dragState.originX + dx)),
        canvas_y: Math.max(0, Math.round(dragState.originY + dy)),
    });
}

function stopNodeDrag() {
    dragState.active = false;
    dragState.nodeKey = null;
    window.removeEventListener('mousemove', onNodeDragMove);
}

function pointerOnSurface(event) {
    if (!viewportRef.value) {
        return { x: 0, y: 0 };
    }

    const rect = viewportRef.value.getBoundingClientRect();

    return {
        x: event.clientX - rect.left + viewportRef.value.scrollLeft,
        y: event.clientY - rect.top + viewportRef.value.scrollTop,
    };
}

function startLink(event, fromKey, side) {
    linkDraft.active = true;
    linkDraft.fromKey = fromKey;
    linkDraft.fromSide = side;
    const point = pointerOnSurface(event);
    linkDraft.x = point.x;
    linkDraft.y = point.y;

    window.addEventListener('mousemove', onLinkMove);
    window.addEventListener('mouseup', cancelLink, { once: true });
}

function onLinkMove(event) {
    if (!linkDraft.active) {
        return;
    }

    const point = pointerOnSurface(event);
    linkDraft.x = point.x;
    linkDraft.y = point.y;
}

function finishLink(toKey, side) {
    if (!linkDraft.active || !linkDraft.fromKey || linkDraft.fromKey === toKey) {
        cancelLink();

        return;
    }

    emit('create-transition', {
        from_client_key: linkDraft.fromKey,
        to_client_key: toKey,
    });
    cancelLink();
    void side;
}

function cancelLink() {
    linkDraft.active = false;
    linkDraft.fromKey = null;
    window.removeEventListener('mousemove', onLinkMove);
}

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onNodeDragMove);
    window.removeEventListener('mousemove', onLinkMove);
});
</script>

<style scoped>
.script-graph__viewport {
    background-color: rgb(244 244 245);
    background-image: radial-gradient(rgb(161 161 170 / 0.35) 1px, transparent 1px);
    background-size: 20px 20px;
}

.dark .script-graph__viewport {
    background-color: rgb(24 24 27);
    background-image: radial-gradient(rgb(82 82 91 / 0.45) 1px, transparent 1px);
}

.script-graph__node--entry {
    box-shadow: 0 10px 30px -12px rgb(16 185 129 / 0.45);
}

.script-graph__port {
    position: absolute;
    z-index: 2;
    height: 12px;
    width: 12px;
    border-radius: 9999px;
    border: 2px solid white;
    background: rgb(14 165 233);
    box-shadow: 0 0 0 2px rgb(14 165 233 / 0.25);
    transition: transform 0.15s ease;
}

.script-graph__port:hover {
    transform: scale(1.2);
}

.script-graph__port--top {
    top: -6px;
    left: 50%;
    margin-left: -6px;
}

.script-graph__port--bottom {
    bottom: -6px;
    left: 50%;
    margin-left: -6px;
}

.script-graph__port--left {
    left: -6px;
    top: 50%;
    margin-top: -6px;
}

.script-graph__port--right {
    right: -6px;
    top: 50%;
    margin-top: -6px;
}
</style>
