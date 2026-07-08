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
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Колёсико — масштаб · фон — перемещение · связь от <span class="font-medium text-sky-600 dark:text-sky-400">●</span> к ●
                </p>
                <div class="flex items-center gap-1">
                    <button type="button" :class="`${crmBtnNeutral} px-2 py-1 text-xs`" title="Уменьшить" @click="zoomOut">−</button>
                    <button type="button" :class="`${crmBtnNeutral} min-w-[3.25rem] px-2 py-1 text-xs`" title="Сбросить масштаб" @click="resetZoom">
                        {{ zoomPercent }}%
                    </button>
                    <button type="button" :class="`${crmBtnNeutral} px-2 py-1 text-xs`" title="Увеличить" @click="zoomIn">+</button>
                    <button type="button" :class="`${crmBtnNeutral} px-2 py-1 text-xs`" title="Вписать все блоки" @click="fitToView">Вписать</button>
                </div>
            </div>
        </div>

        <div
            ref="viewportRef"
            class="script-graph__viewport relative min-h-[640px] flex-1 overflow-hidden touch-none select-none"
            :class="[
                panState.active || dragState.active ? 'script-graph__viewport--dragging' : '',
                panState.active ? 'cursor-grabbing' : 'cursor-grab',
            ]"
            @mousedown="onCanvasPointerDown"
            @wheel.prevent="onViewportWheel"
        >
            <div
                v-if="tagCloud.length"
                class="pointer-events-none absolute right-3 top-3 z-20 max-w-[min(100%,320px)]"
            >
                <div
                    class="pointer-events-auto rounded-2xl border border-zinc-200/90 bg-white/95 p-2.5 shadow-lg backdrop-blur-sm dark:border-zinc-600 dark:bg-zinc-950/95"
                    @mousedown.stop
                >
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Теги</span>
                        <button
                            v-if="activeTagFilter"
                            type="button"
                            class="text-[10px] font-medium text-sky-600 hover:underline dark:text-sky-400"
                            @click.stop="clearTagFilter"
                        >
                            Сбросить
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="tag in tagCloud"
                            :key="tag"
                            type="button"
                            class="rounded-full border px-2 py-0.5 text-[11px] font-medium transition"
                            :class="activeTagFilter === tag
                                ? 'border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-600 dark:bg-amber-950/50 dark:text-amber-200'
                                : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-amber-300 hover:bg-amber-50/80 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-amber-700'"
                            @click.stop="toggleTagFilter(tag)"
                        >
                            {{ tag }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="script-graph__transform absolute left-0 top-0 origin-top-left will-change-transform"
                :style="transformStyle"
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
                            :class="[
                                edgeDimmed(edge) ? 'opacity-25' : '',
                                selectedTransitionId === edge.id
                                    ? 'stroke-sky-500 stroke-[3px] dark:stroke-sky-400'
                                    : 'stroke-zinc-400 stroke-2 hover:stroke-sky-400 dark:stroke-zinc-500',
                            ]"
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
                            :class="edgeDimmed(edge) ? 'opacity-25' : ''"
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
                    class="script-graph__node absolute w-[240px] overflow-visible rounded-2xl border bg-white shadow-md transition dark:bg-zinc-950"
                    :class="[
                        kindBorderClass(node.kind),
                        selectedNodeKey === node.client_key ? 'ring-2 ring-sky-500/40' : '',
                        entryNodeKey === node.client_key ? 'script-graph__node--entry' : '',
                        nodeDimmed(node) ? 'opacity-30' : '',
                    ]"
                    :style="{ left: `${node.canvas_x}px`, top: `${node.canvas_y}px` }"
                    @mousedown.stop="onNodePointerDown($event, node.client_key)"
                    @click.stop="onNodeClick(node.client_key)"
                >
                    <div
                        v-if="entryNodeKey === node.client_key"
                        class="overflow-hidden rounded-t-2xl bg-emerald-600 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-white"
                    >
                        Старт
                    </div>
                    <header
                        class="script-graph__handle flex cursor-grab items-start justify-between gap-2 border-b border-zinc-100 px-3 py-2 active:cursor-grabbing dark:border-zinc-800"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ node.client_key }}</div>
                            <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100">{{ kindLabel(node.kind) }}</div>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium" :class="kindBadgeClass(node.kind)">
                            {{ kindShort(node.kind) }}
                        </span>
                    </header>

                    <div class="space-y-2 px-3 py-2">
                        <p
                            v-if="bodyPreview(node.body)"
                            class="line-clamp-3 text-[11px] leading-relaxed text-zinc-600 dark:text-zinc-300"
                        >
                            {{ bodyPreview(node.body) }}
                        </p>
                        <p
                            v-else
                            class="text-[11px] italic text-zinc-400 dark:text-zinc-500"
                        >
                            Текст не задан
                        </p>

                        <div v-if="nodeTags(node).length" class="flex flex-wrap gap-1">
                            <span
                                v-for="tag in nodeTags(node)"
                                :key="`${node.client_key}-${tag}`"
                                class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                            >
                                {{ tag }}
                            </span>
                        </div>

                        <div v-if="outgoingForNode(node.client_key).length" class="space-y-1 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                            <div class="text-[9px] font-semibold uppercase tracking-wide text-zinc-400">Ответы</div>
                            <button
                                v-for="transition in outgoingForNode(node.client_key)"
                                :key="transition.local_id"
                                type="button"
                                class="flex w-full items-center gap-1.5 rounded-lg border border-zinc-200/80 bg-zinc-50/80 px-2 py-1 text-left text-[10px] text-zinc-700 transition hover:border-sky-300 hover:bg-sky-50 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200 dark:hover:border-sky-700"
                                @click.stop="selectTransition(transition.local_id)"
                            >
                                <span class="min-w-0 flex-1 truncate font-medium">{{ transitionLabel(transition) }}</span>
                                <span class="shrink-0 text-zinc-400">→</span>
                                <span class="shrink-0 truncate text-zinc-500">
                                    {{ transition.target_type === 'script' ? 'сценарий' : transition.target_type === 'return' ? 'возврат' : transition.to_client_key }}
                                </span>
                            </button>
                        </div>

                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-emerald-300 py-1 text-[10px] font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                            @click.stop="emitAddAnswer(node.client_key)"
                        >
                            <span class="text-sm leading-none">+</span>
                            Ответ клиента
                        </button>
                    </div>

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
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { crmBtnNeutral } from '@/support/crmUi.js';
import {
    bezierPathBetween,
    edgeGeometryBetweenNodes,
    portPoint,
    sideTowardPoint,
    boundsCenter,
} from '@/support/graphEdgeGeometry.js';

const NODE_WIDTH = 240;
const NODE_MIN_BODY_HEIGHT = 120;
const GRID_PAD = 48;
const MIN_ZOOM = 0.35;
const MAX_ZOOM = 2;
const ZOOM_STEP = 0.1;
const portSides = ['top', 'right', 'bottom', 'left'];

const props = defineProps({
    nodes: { type: Array, required: true },
    transitions: { type: Array, required: true },
    entryNodeKey: { type: String, default: '' },
    nodeKinds: { type: Array, default: () => [] },
    reactionClasses: { type: Array, default: () => [] },
    selectedNodeKey: { type: String, default: null },
    selectedTransitionId: { type: String, default: null },
    activeTagFilter: { type: String, default: null },
});

const emit = defineEmits([
    'update:selectedNodeKey',
    'update:selectedTransitionId',
    'update:activeTagFilter',
    'update:nodePosition',
    'create-transition',
    'add-answer',
]);

const viewportRef = ref(null);

const viewState = reactive({
    panX: 0,
    panY: 0,
    zoom: 1,
});

const panState = reactive({
    active: false,
    startX: 0,
    startY: 0,
    originPanX: 0,
    originPanY: 0,
});

const clickState = reactive({
    pending: false,
    x: 0,
    y: 0,
});

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
    suppressClick: false,
});

const tagCloud = computed(() => {
    const tags = new Set();

    for (const node of props.nodes) {
        for (const tag of nodeTags(node)) {
            tags.add(tag);
        }
    }

    return [...tags].sort((a, b) => a.localeCompare(b, 'ru'));
});

const zoomPercent = computed(() => Math.round(viewState.zoom * 100));

const transformStyle = computed(() => ({
    transform: `translate(${viewState.panX}px, ${viewState.panY}px) scale(${viewState.zoom})`,
}));

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

function nodeTags(node) {
    return Array.isArray(node.tags) ? node.tags.filter(Boolean) : [];
}

function outgoingForNode(clientKey) {
    return props.transitions.filter((transition) => transition.from_client_key === clientKey);
}

function nodeContentHeight(node) {
    let height = 56;

    if (bodyPreview(node.body)) {
        height += 44;
    } else {
        height += 20;
    }

    if (nodeTags(node).length) {
        height += 22;
    }

    const outgoing = outgoingForNode(node.client_key);
    if (outgoing.length) {
        height += 18 + outgoing.length * 26;
    }

    height += 34;

    return Math.max(NODE_MIN_BODY_HEIGHT, height);
}

function nodeBounds(node) {
    const entryExtra = props.entryNodeKey === node.client_key ? 24 : 0;

    return {
        x: Number(node.canvas_x),
        y: Number(node.canvas_y),
        width: NODE_WIDTH,
        height: nodeContentHeight(node) + entryExtra,
    };
}

function nodeDimmed(node) {
    if (!props.activeTagFilter) {
        return false;
    }

    return !nodeTags(node).includes(props.activeTagFilter);
}

function edgeDimmed(edge) {
    if (!props.activeTagFilter) {
        return false;
    }

    const transition = props.transitions.find((item) => item.local_id === edge.id);
    if (!transition) {
        return true;
    }

    const from = nodeByKey.value.get(transition.from_client_key);
    const to = nodeByKey.value.get(transition.to_client_key);

    return nodeDimmed(from) || nodeDimmed(to);
}

function bodyPreview(text) {
    const normalized = String(text ?? '').replace(/\s+/g, ' ').trim();

    if (normalized === '') {
        return '';
    }

    return normalized.length > 140 ? `${normalized.slice(0, 139)}…` : normalized;
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
        return transition.target_type === 'script'
            ? `${transition.customer_label} ↪`
            : transition.customer_label;
    }

    if (transition.target_type === 'script') {
        return 'Перейти в сценарий ↪';
    }

    if (transition.target_type === 'return') {
        return 'Вернуться назад';
    }

    if (transition.sales_script_reaction_class_id === null || transition.sales_script_reaction_class_id === undefined) {
        return 'Дальше';
    }

    const reaction = props.reactionClasses.find((item) => item.id === transition.sales_script_reaction_class_id);

    return reaction?.label ?? 'Реакция';
}

function toggleTagFilter(tag) {
    emit('update:activeTagFilter', props.activeTagFilter === tag ? null : tag);
}

function clearTagFilter() {
    emit('update:activeTagFilter', null);
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

function clampZoom(value) {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, value));
}

function setZoom(nextZoom, anchorX = null, anchorY = null) {
    const zoom = clampZoom(nextZoom);
    if (zoom === viewState.zoom) {
        return;
    }

    if (!viewportRef.value || anchorX === null || anchorY === null) {
        viewState.zoom = zoom;

        return;
    }

    const ratio = zoom / viewState.zoom;
    viewState.panX = anchorX - (anchorX - viewState.panX) * ratio;
    viewState.panY = anchorY - (anchorY - viewState.panY) * ratio;
    viewState.zoom = zoom;
}

function zoomIn() {
    if (!viewportRef.value) {
        setZoom(viewState.zoom + ZOOM_STEP);

        return;
    }

    const rect = viewportRef.value.getBoundingClientRect();
    setZoom(viewState.zoom + ZOOM_STEP, rect.width / 2, rect.height / 2);
}

function zoomOut() {
    if (!viewportRef.value) {
        setZoom(viewState.zoom - ZOOM_STEP);

        return;
    }

    const rect = viewportRef.value.getBoundingClientRect();
    setZoom(viewState.zoom - ZOOM_STEP, rect.width / 2, rect.height / 2);
}

function resetZoom() {
    viewState.zoom = 1;
    viewState.panX = 0;
    viewState.panY = 0;
}

function fitToView() {
    if (!viewportRef.value) {
        return;
    }

    if (props.nodes.length === 0) {
        resetZoom();

        return;
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const node of props.nodes) {
        const bounds = nodeBounds(node);
        minX = Math.min(minX, bounds.x);
        minY = Math.min(minY, bounds.y);
        maxX = Math.max(maxX, bounds.x + bounds.width);
        maxY = Math.max(maxY, bounds.y + bounds.height);
    }

    const contentWidth = maxX - minX + GRID_PAD * 2;
    const contentHeight = maxY - minY + GRID_PAD * 2;
    const viewportWidth = viewportRef.value.clientWidth;
    const viewportHeight = viewportRef.value.clientHeight;
    const scale = clampZoom(Math.min(viewportWidth / contentWidth, viewportHeight / contentHeight));

    viewState.zoom = scale;
    viewState.panX = (viewportWidth - contentWidth * scale) / 2 - minX * scale + GRID_PAD * scale;
    viewState.panY = (viewportHeight - contentHeight * scale) / 2 - minY * scale + GRID_PAD * scale;
}

function onViewportWheel(event) {
    if (!viewportRef.value) {
        return;
    }

    const rect = viewportRef.value.getBoundingClientRect();
    const anchorX = event.clientX - rect.left;
    const anchorY = event.clientY - rect.top;
    const delta = event.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;

    setZoom(viewState.zoom + delta, anchorX, anchorY);
}

function isInteractiveCanvasTarget(target) {
    if (!(target instanceof Element)) {
        return false;
    }

    return target.closest('button, a, input, textarea, select, label, [contenteditable="true"]') !== null;
}

function onCanvasPointerDown(event) {
    if (event.button !== 0 || dragState.active || linkDraft.active) {
        return;
    }

    if (event.target instanceof Element && event.target.closest('.script-graph__node')) {
        return;
    }

    if (isInteractiveCanvasTarget(event.target)) {
        return;
    }

    event.preventDefault();
    beginPan(event);
}

function beginPan(event) {
    panState.active = true;
    panState.startX = event.clientX;
    panState.startY = event.clientY;
    panState.originPanX = viewState.panX;
    panState.originPanY = viewState.panY;
    clickState.pending = true;
    clickState.x = event.clientX;
    clickState.y = event.clientY;

    window.addEventListener('mousemove', onPanMove);
    window.addEventListener('mouseup', onPanEnd, { once: true });
}

function onPanMove(event) {
    if (!panState.active) {
        return;
    }

    event.preventDefault();

    const dx = event.clientX - panState.startX;
    const dy = event.clientY - panState.startY;

    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
        clickState.pending = false;
    }

    viewState.panX = panState.originPanX + dx;
    viewState.panY = panState.originPanY + dy;
}

function onPanEnd() {
    if (clickState.pending) {
        clearSelection();
    }

    panState.active = false;
    window.removeEventListener('mousemove', onPanMove);
}

function emitAddAnswer(clientKey) {
    emit('update:selectedNodeKey', clientKey);
    emit('add-answer', clientKey);
}

function onNodeClick(clientKey) {
    if (dragState.suppressClick) {
        dragState.suppressClick = false;

        return;
    }

    selectNode(clientKey);
}

function onNodePointerDown(event, clientKey) {
    if (event.button !== 0 || panState.active || linkDraft.active) {
        return;
    }

    if (isInteractiveCanvasTarget(event.target)) {
        return;
    }

    event.preventDefault();
    startNodeDrag(event, clientKey);
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
    dragState.suppressClick = false;
    emit('update:selectedNodeKey', clientKey);

    window.addEventListener('mousemove', onNodeDragMove);
    window.addEventListener('mouseup', stopNodeDrag, { once: true });
}

function onNodeDragMove(event) {
    if (!dragState.active || !dragState.nodeKey) {
        return;
    }

    event.preventDefault();

    const dx = (event.clientX - dragState.startX) / viewState.zoom;
    const dy = (event.clientY - dragState.startY) / viewState.zoom;

    if (Math.abs(event.clientX - dragState.startX) > 3 || Math.abs(event.clientY - dragState.startY) > 3) {
        dragState.suppressClick = true;
    }

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
        x: (event.clientX - rect.left - viewState.panX) / viewState.zoom,
        y: (event.clientY - rect.top - viewState.panY) / viewState.zoom,
    };
}

function startLink(event, fromKey, side) {
    event.preventDefault();
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

onMounted(async () => {
    await nextTick();
    fitToView();
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onNodeDragMove);
    window.removeEventListener('mousemove', onLinkMove);
    window.removeEventListener('mousemove', onPanMove);
});
</script>

<style scoped>
.script-graph__viewport {
    background-color: rgb(244 244 245);
    background-image: radial-gradient(rgb(161 161 170 / 0.35) 1px, transparent 1px);
    background-size: 20px 20px;
}

.script-graph__viewport--dragging,
.script-graph__viewport--dragging * {
    cursor: grabbing !important;
    user-select: none !important;
    -webkit-user-select: none !important;
}

.script-graph__node {
    user-select: none;
    -webkit-user-select: none;
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
