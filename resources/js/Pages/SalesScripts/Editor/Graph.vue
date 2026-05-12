<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Версия {{ payload.version.version_number }}</div>
                    <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Визуальный редактор</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ payload.script.title }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                        @click="addNode"
                    >
                        Добавить шаг
                    </button>
                    <button
                        type="button"
                        :class="crmBtnCreate"
                        @click="saveGraph"
                    >
                        Сохранить граф
                    </button>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                <Link :href="route('scripts.editor.versions.show', payload.version.id)" class="font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300">
                    ← К форме редактора
                </Link>
                <Link :href="route('scripts.editor.index')" class="font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300">
                    К списку сценариев
                </Link>
            </div>
            <p
                v-if="page.props.flash?.message"
                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>
            <div v-if="page.props.errors && Object.keys(page.props.errors).length" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                <ul class="list-inside list-disc space-y-1">
                    <li v-for="(msg, key) in page.props.errors" :key="key">
                        {{ key }}: {{ Array.isArray(msg) ? msg[0] : msg }}
                    </li>
                </ul>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_340px]">
            <div class="border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="mb-3 rounded-xl border border-zinc-100 p-3 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    Перетаскивайте карточки за верхнюю панель. Выберите шаг и редактируйте поля справа. Переходы рисуются из ключей шагов.
                </div>

                <div ref="canvasRef" class="relative h-[720px] overflow-auto rounded-xl border border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                    <svg class="pointer-events-none absolute inset-0 h-full w-full">
                        <g v-for="edge in edgeLines" :key="edge.id">
                            <line :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2" class="stroke-zinc-400 dark:stroke-zinc-600" stroke-width="2" />
                            <text :x="edge.labelX" :y="edge.labelY" class="fill-zinc-600 text-[11px] dark:fill-zinc-300">
                                {{ edge.label }}
                            </text>
                        </g>
                    </svg>

                    <article
                        v-for="node in graphNodes"
                        :key="node.client_key"
                        class="absolute w-[280px] rounded-xl border bg-white p-3 shadow-sm transition dark:bg-zinc-950"
                        :class="selectedNodeKey === node.client_key ? 'border-sky-500 ring-2 ring-sky-200 dark:ring-sky-900' : 'border-zinc-200 dark:border-zinc-700'"
                        :style="{ left: `${node.canvas_x}px`, top: `${node.canvas_y}px` }"
                        @click="selectNode(node.client_key)"
                    >
                        <header
                            class="cursor-grab rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                            @mousedown="startDrag($event, node.client_key)"
                        >
                            {{ node.client_key }} · {{ kindLabel(node.kind) }}
                        </header>
                        <p class="mt-2 line-clamp-5 whitespace-pre-wrap text-sm text-zinc-800 dark:text-zinc-200">{{ node.body }}</p>
                    </article>
                </div>
            </div>

            <div class="space-y-4">
                <section class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Стартовый шаг</h2>
                    <input
                        v-model="entryNodeKey"
                        type="text"
                        class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        placeholder="client_key"
                    />
                </section>

                <section class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Шаг</h2>
                        <button
                            v-if="selectedNode"
                            type="button"
                            class="text-xs font-medium text-rose-700 hover:underline dark:text-rose-300"
                            @click="removeNode(selectedNode.client_key)"
                        >
                            Удалить
                        </button>
                    </div>

                    <div v-if="selectedNode" class="mt-3 space-y-3">
                        <div>
                            <label class="text-xs text-zinc-500">Ключ</label>
                            <input v-model="selectedNode.client_key" type="text" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Тип</label>
                            <select v-model="selectedNode.kind" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option v-for="kind in nodeKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Текст</label>
                            <textarea v-model="selectedNode.body" rows="4" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Подсказка</label>
                            <input v-model="selectedNode.hint" type="text" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                    </div>
                    <p v-else class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Выберите шаг на схеме.</p>
                </section>

                <section class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Переходы</h2>
                    <form class="mt-3 grid gap-2" @submit.prevent="addTransition">
                        <select v-model="newTransition.from_client_key" class="w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option disabled value="">Из шага</option>
                            <option v-for="node in graphNodes" :key="`from-${node.client_key}`" :value="node.client_key">{{ node.client_key }}</option>
                        </select>
                        <select v-model="newTransition.to_client_key" class="w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option disabled value="">В шаг</option>
                            <option v-for="node in graphNodes" :key="`to-${node.client_key}`" :value="node.client_key">{{ node.client_key }}</option>
                        </select>
                        <select v-model="newTransition.sales_script_reaction_class_id" class="w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option :value="null">Дальше</option>
                            <option v-for="reaction in reactionClasses" :key="reaction.id" :value="reaction.id">{{ reaction.label }}</option>
                        </select>
                        <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
                            Добавить переход
                        </button>
                    </form>

                    <ul class="mt-3 space-y-2 text-xs">
                        <li v-for="transition in graphTransitions" :key="transition.local_id" class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ transition.from_client_key }} → {{ transition.to_client_key }}</div>
                            <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ transitionLabel(transition.sales_script_reaction_class_id) }}</div>
                            <button type="button" class="mt-1 text-rose-700 hover:underline dark:text-rose-300" @click="removeTransition(transition.local_id)">Удалить</button>
                        </li>
                    </ul>
                </section>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-scripts' }, () => page),
});

const NODE_WIDTH = 280;
const NODE_HEIGHT = 152;

const props = defineProps({
    payload: { type: Object, required: true },
    reactionClasses: { type: Array, default: () => [] },
    nodeKinds: { type: Array, default: () => [] },
});

const page = usePage();
const canvasRef = ref(null);
const selectedNodeKey = ref(props.payload.nodes[0]?.client_key ?? null);
const entryNodeKey = ref(props.payload.version.entry_node_key ?? props.payload.nodes[0]?.client_key ?? '');
const edgeSeq = ref(1);

const graphNodes = reactive(
    props.payload.nodes.map((node, index) => ({
        client_key: node.client_key,
        kind: node.kind,
        body: node.body ?? '',
        hint: node.hint ?? '',
        sort_order: node.sort_order ?? index,
        canvas_x: Number.isInteger(node.canvas_x) ? node.canvas_x : 32 + (index % 3) * 320,
        canvas_y: Number.isInteger(node.canvas_y) ? node.canvas_y : 24 + Math.floor(index / 3) * 220,
    })),
);

const graphTransitions = reactive(
    props.payload.transitions.map((transition, index) => ({
        local_id: `t-${transition.id ?? index}-${index}`,
        from_client_key: resolveClientKeyByNodeId(transition.from_node_id),
        to_client_key: resolveClientKeyByNodeId(transition.to_node_id),
        sales_script_reaction_class_id: transition.sales_script_reaction_class_id ?? null,
        sort_order: transition.sort_order ?? index,
    })),
);

const newTransition = reactive({
    from_client_key: graphNodes[0]?.client_key ?? '',
    to_client_key: graphNodes[1]?.client_key ?? graphNodes[0]?.client_key ?? '',
    sales_script_reaction_class_id: null,
});

const dragState = reactive({
    active: false,
    nodeKey: null,
    startX: 0,
    startY: 0,
    originX: 0,
    originY: 0,
});

const selectedNode = computed(() => graphNodes.find((node) => node.client_key === selectedNodeKey.value) ?? null);
const nodeByKey = computed(() => {
    const map = new Map();
    for (const node of graphNodes) {
        map.set(node.client_key, node);
    }
    return map;
});

const edgeLines = computed(() => {
    return graphTransitions
        .map((transition, index) => {
            const from = nodeByKey.value.get(transition.from_client_key);
            const to = nodeByKey.value.get(transition.to_client_key);

            if (!from || !to) {
                return null;
            }

            const x1 = from.canvas_x + NODE_WIDTH / 2;
            const y1 = from.canvas_y + NODE_HEIGHT;
            const x2 = to.canvas_x + NODE_WIDTH / 2;
            const y2 = to.canvas_y;

            return {
                id: transition.local_id ?? `edge-${index}`,
                x1,
                y1,
                x2,
                y2,
                labelX: (x1 + x2) / 2 + 8,
                labelY: (y1 + y2) / 2 - 6,
                label: transitionLabel(transition.sales_script_reaction_class_id),
            };
        })
        .filter(Boolean);
});

function resolveClientKeyByNodeId(nodeId) {
    return props.payload.nodes.find((node) => node.id === nodeId)?.client_key ?? '';
}

function kindLabel(kind) {
    return props.nodeKinds.find((item) => item.value === kind)?.label ?? kind;
}

function transitionLabel(reactionId) {
    if (reactionId === null || reactionId === undefined) {
        return 'Дальше';
    }
    return props.reactionClasses.find((item) => item.id === reactionId)?.label ?? 'Реакция';
}

function uniqueClientKey(base) {
    let candidate = base;
    let suffix = 1;

    while (graphNodes.some((node) => node.client_key === candidate)) {
        suffix += 1;
        candidate = `${base}_${suffix}`;
    }

    return candidate;
}

function selectNode(clientKey) {
    selectedNodeKey.value = clientKey;
}

function addNode() {
    const key = uniqueClientKey(`step_${graphNodes.length + 1}`);
    graphNodes.push({
        client_key: key,
        kind: props.nodeKinds[0]?.value ?? 'say',
        body: 'Новый шаг',
        hint: '',
        sort_order: graphNodes.length,
        canvas_x: 48 + (graphNodes.length % 3) * 320,
        canvas_y: 48 + Math.floor(graphNodes.length / 3) * 220,
    });
    selectedNodeKey.value = key;

    if (!entryNodeKey.value) {
        entryNodeKey.value = key;
    }
}

function removeNode(clientKey) {
    const index = graphNodes.findIndex((node) => node.client_key === clientKey);
    if (index === -1) {
        return;
    }

    graphNodes.splice(index, 1);

    for (let i = graphTransitions.length - 1; i >= 0; i -= 1) {
        const transition = graphTransitions[i];
        if (transition.from_client_key === clientKey || transition.to_client_key === clientKey) {
            graphTransitions.splice(i, 1);
        }
    }

    if (selectedNodeKey.value === clientKey) {
        selectedNodeKey.value = graphNodes[0]?.client_key ?? null;
    }

    if (entryNodeKey.value === clientKey) {
        entryNodeKey.value = graphNodes[0]?.client_key ?? '';
    }
}

function addTransition() {
    if (!newTransition.from_client_key || !newTransition.to_client_key) {
        return;
    }

    graphTransitions.push({
        local_id: `new-${edgeSeq.value}`,
        from_client_key: newTransition.from_client_key,
        to_client_key: newTransition.to_client_key,
        sales_script_reaction_class_id: newTransition.sales_script_reaction_class_id,
        sort_order: graphTransitions.length,
    });
    edgeSeq.value += 1;
}

function removeTransition(localId) {
    const index = graphTransitions.findIndex((transition) => transition.local_id === localId);
    if (index !== -1) {
        graphTransitions.splice(index, 1);
    }
}

function startDrag(event, clientKey) {
    const node = graphNodes.find((item) => item.client_key === clientKey);
    if (!node) {
        return;
    }

    dragState.active = true;
    dragState.nodeKey = clientKey;
    dragState.startX = event.clientX;
    dragState.startY = event.clientY;
    dragState.originX = node.canvas_x;
    dragState.originY = node.canvas_y;
    selectedNodeKey.value = clientKey;

    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup', stopDrag, { once: true });
}

function onDragMove(event) {
    if (!dragState.active || !dragState.nodeKey) {
        return;
    }

    const node = graphNodes.find((item) => item.client_key === dragState.nodeKey);
    if (!node) {
        return;
    }

    const dx = event.clientX - dragState.startX;
    const dy = event.clientY - dragState.startY;

    node.canvas_x = Math.round(dragState.originX + dx);
    node.canvas_y = Math.round(dragState.originY + dy);
}

function stopDrag() {
    dragState.active = false;
    dragState.nodeKey = null;
    window.removeEventListener('mousemove', onDragMove);
}

function saveGraph() {
    const nodes = graphNodes.map((node, index) => ({
        client_key: node.client_key.trim(),
        kind: node.kind,
        body: node.body,
        hint: node.hint || null,
        sort_order: index,
        canvas_x: node.canvas_x,
        canvas_y: node.canvas_y,
    }));

    const transitions = graphTransitions.map((transition, index) => ({
        from_client_key: transition.from_client_key,
        to_client_key: transition.to_client_key,
        sales_script_reaction_class_id: transition.sales_script_reaction_class_id,
        sort_order: index,
    }));

    router.put(
        route('scripts.editor.versions.graph.update', props.payload.version.id),
        {
            entry_node_key: entryNodeKey.value.trim() === '' ? null : entryNodeKey.value.trim(),
            nodes,
            transitions,
        },
        {
            preserveScroll: true,
        },
    );
}

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onDragMove);
});
</script>
