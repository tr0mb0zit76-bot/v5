<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <section :class="`${crmPanel} space-y-4 p-6`">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div :class="crmPageEyebrow">Версия {{ payload.version.version_number }}</div>
                    <h1 :class="crmPageTitle">Конструктор сценария</h1>
                    <p :class="`${crmPageLead} mt-2`">{{ payload.script.title }}</p>
                    <p class="mt-2 text-sm">
                        <span
                            v-if="payload.version.is_active && payload.version.published_at"
                            class="font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            Опубликована и активна
                        </span>
                        <span v-else class="text-zinc-500 dark:text-zinc-400">
                            Черновик — не показывается при старте сессии, пока не опубликуете.
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="crmBtnSecondary" @click="addNode">
                        + Шаг
                    </button>
                    <button
                        v-if="!payload.version.is_active || !payload.version.published_at"
                        type="button"
                        :class="crmBtnPrimary"
                        @click="publish"
                    >
                        Опубликовать
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                        @click="unpublish"
                    >
                        Снять с публикации
                    </button>
                    <button type="button" :class="crmBtnCreate" :disabled="saving" @click="saveGraph">
                        {{ saving ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <Link
                    :href="route('scripts.editor.index')"
                    class="font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300"
                >
                    ← К списку сценариев
                </Link>
            </div>
            <p
                v-if="page.props.flash?.message"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>
            <div
                v-if="page.props.errors && Object.keys(page.props.errors).length"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
            >
                <ul class="list-inside list-disc space-y-1">
                    <li v-for="(msg, key) in page.props.errors" :key="key">
                        {{ key }}: {{ Array.isArray(msg) ? msg[0] : msg }}
                    </li>
                </ul>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <ScriptGraphCanvas
                :nodes="graphNodes"
                :transitions="graphTransitions"
                :entry-node-key="entryNodeKey"
                :node-kinds="nodeKinds"
                :reaction-classes="reactionClasses"
                :selected-node-key="selectedNodeKey"
                :selected-transition-id="selectedTransitionId"
                @update:selected-node-key="onSelectNode"
                @update:selected-transition-id="selectedTransitionId = $event"
                @update:node-position="onNodePosition"
                @create-transition="onCreateTransition"
            />

            <div class="space-y-4">
                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Стартовый шаг</h2>
                    <select v-model="entryNodeKey" :class="crmFieldFluid">
                        <option v-for="node in graphNodes" :key="`entry-${node.client_key}`" :value="node.client_key">
                            {{ node.client_key }}
                        </option>
                    </select>
                </section>

                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Шаги сценария</h2>
                    <p :class="crmPageLead">Текст редактируется здесь; на схеме — только ключ и тип.</p>
                    <ul class="max-h-48 space-y-1.5 overflow-y-auto">
                        <li
                            v-for="node in graphNodes"
                            :key="`list-${node.client_key}`"
                            class="cursor-pointer rounded-xl border px-3 py-2 text-left transition"
                            :class="selectedNodeKey === node.client_key
                                ? 'border-sky-400 bg-sky-50 dark:border-sky-700 dark:bg-sky-950/30'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/50'"
                            @click="onSelectNode(node.client_key)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ node.client_key }}</span>
                                <span class="shrink-0 text-[10px] uppercase tracking-wide text-zinc-400">{{ kindShort(node.kind) }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-700 dark:text-zinc-200">
                                {{ nodeExcerpt(node.body) }}
                            </p>
                        </li>
                    </ul>
                </section>

                <section v-if="selectedNode" :class="`${crmPanel} space-y-3 p-4`">
                    <div class="flex items-center justify-between gap-2">
                        <h2 :class="crmSectionTitle">Редактирование</h2>
                        <button
                            type="button"
                            class="text-xs font-medium text-rose-700 hover:underline dark:text-rose-300"
                            @click="removeNode(selectedNode.client_key)"
                        >
                            Удалить
                        </button>
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Ключ</label>
                        <input v-model="selectedNode.client_key" type="text" :class="`${crmFieldFluid} mt-1`" />
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Тип</label>
                        <select v-model="selectedNode.kind" :class="`${crmFieldFluid} mt-1`">
                            <option v-for="kind in nodeKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Реплика / текст оператора</label>
                        <textarea v-model="selectedNode.body" rows="5" :class="`${crmFieldFluid} mt-1`" />
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Методология (свернуто у оператора)</label>
                        <input v-model="selectedNode.hint" type="text" :class="`${crmFieldFluid} mt-1`" placeholder="СПИН, тон, рамка времени…" />
                    </div>
                </section>

                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Связи</h2>
                    <p :class="crmPageLead">
                        Потяните стрелку на схеме или отредактируйте выбранную связь. Подпись — фраза клиента.
                    </p>

                    <div v-if="selectedTransition" class="space-y-3 rounded-xl border border-sky-200 bg-sky-50/60 p-3 dark:border-sky-900/50 dark:bg-sky-950/20">
                        <div class="text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200">Связь</div>
                        <div class="grid gap-2">
                            <select v-model="selectedTransition.from_client_key" :class="crmFieldFluid">
                                <option v-for="node in graphNodes" :key="`sel-from-${node.client_key}`" :value="node.client_key">{{ node.client_key }}</option>
                            </select>
                            <select v-model="selectedTransition.to_client_key" :class="crmFieldFluid">
                                <option v-for="node in graphNodes" :key="`sel-to-${node.client_key}`" :value="node.client_key">{{ node.client_key }}</option>
                            </select>
                            <input
                                v-model="selectedTransition.customer_label"
                                type="text"
                                :class="crmFieldFluid"
                                placeholder="Фраза клиента: «Да, задавайте вопросы»"
                            />
                            <select v-model="selectedTransition.sales_script_reaction_class_id" :class="crmFieldFluid">
                                <option :value="null">Линейный переход (без реакции)</option>
                                <option v-for="reaction in reactionClasses" :key="reaction.id" :value="reaction.id">{{ reaction.label }}</option>
                            </select>
                            <div class="flex gap-2">
                                <button type="button" :class="`${crmBtnSecondary} flex-1 text-xs`" @click="moveTransition(-1)">↑</button>
                                <button type="button" :class="`${crmBtnSecondary} flex-1 text-xs`" @click="moveTransition(1)">↓</button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl border border-rose-200 px-2 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                    @click="removeTransition(selectedTransition.local_id)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>

                    <ul class="max-h-52 space-y-2 overflow-y-auto text-xs">
                        <li
                            v-for="transition in graphTransitions"
                            :key="transition.local_id"
                            class="cursor-pointer rounded-lg border p-2 transition"
                            :class="selectedTransitionId === transition.local_id
                                ? 'border-sky-400 bg-sky-50 dark:border-sky-700 dark:bg-sky-950/30'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/50'"
                            @click="selectedTransitionId = transition.local_id"
                        >
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">
                                {{ transition.from_client_key }} → {{ transition.to_client_key }}
                            </div>
                            <div :class="`${crmPageLead} mt-1`">
                                {{ transition.customer_label || transitionLabel(transition.sales_script_reaction_class_id) }}
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import ScriptGraphCanvas from '@/Components/SalesScripts/ScriptGraphCanvas.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnCreate,
    crmBtnPrimary,
    crmBtnSecondary,
    crmFieldFluid,
    crmLabelCompact,
    crmPageEyebrow,
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-scripts' }, () => page),
});

const props = defineProps({
    payload: { type: Object, required: true },
    reactionClasses: { type: Array, default: () => [] },
    nodeKinds: { type: Array, default: () => [] },
});

const page = usePage();
const saving = ref(false);
const edgeSeq = ref(1);
const selectedNodeKey = ref(props.payload.nodes[0]?.client_key ?? null);
const selectedTransitionId = ref(null);
const entryNodeKey = ref(props.payload.version.entry_node_key ?? props.payload.nodes[0]?.client_key ?? '');

const graphNodes = reactive(
    props.payload.nodes.map((node, index) => ({
        client_key: node.client_key,
        kind: node.kind,
        body: node.body ?? '',
        hint: node.hint ?? '',
        sort_order: node.sort_order ?? index,
        canvas_x: Number.isInteger(node.canvas_x) ? node.canvas_x : 40 + (index % 4) * 260,
        canvas_y: Number.isInteger(node.canvas_y) ? node.canvas_y : 40 + Math.floor(index / 4) * 180,
    })),
);

const graphTransitions = reactive(
    props.payload.transitions.map((transition, index) => ({
        local_id: `t-${transition.id ?? index}-${index}`,
        from_client_key: resolveClientKeyByNodeId(transition.from_node_id),
        to_client_key: resolveClientKeyByNodeId(transition.to_node_id),
        sales_script_reaction_class_id: transition.sales_script_reaction_class_id ?? null,
        customer_label: transition.customer_label ?? '',
        sort_order: transition.sort_order ?? index,
    })),
);

const selectedNode = computed(() => graphNodes.find((node) => node.client_key === selectedNodeKey.value) ?? null);
const selectedTransition = computed(() => graphTransitions.find((t) => t.local_id === selectedTransitionId.value) ?? null);

function resolveClientKeyByNodeId(nodeId) {
    return props.payload.nodes.find((node) => node.id === nodeId)?.client_key ?? '';
}

function kindShort(kind) {
    return ({ say: 'сказать', ask: 'спросить', branch: 'ветка' })[kind] ?? kind;
}

function nodeExcerpt(text) {
    const normalized = String(text ?? '').replace(/\s+/g, ' ').trim();

    if (normalized === '') {
        return '— пустой текст —';
    }

    return normalized.length > 120 ? `${normalized.slice(0, 119)}…` : normalized;
}

function onSelectNode(clientKey) {
    selectedNodeKey.value = clientKey;
    selectedTransitionId.value = null;
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

function onNodePosition({ client_key, canvas_x, canvas_y }) {
    const node = graphNodes.find((item) => item.client_key === client_key);
    if (node) {
        node.canvas_x = canvas_x;
        node.canvas_y = canvas_y;
    }
}

function onCreateTransition({ from_client_key, to_client_key }) {
    const exists = graphTransitions.some(
        (t) => t.from_client_key === from_client_key
            && t.to_client_key === to_client_key
            && t.sales_script_reaction_class_id === null
            && !t.customer_label,
    );

    if (exists) {
        return;
    }

    const localId = `new-${edgeSeq.value}`;
    edgeSeq.value += 1;

    graphTransitions.push({
        local_id: localId,
        from_client_key,
        to_client_key,
        sales_script_reaction_class_id: null,
        customer_label: '',
        sort_order: graphTransitions.length,
    });
    selectedTransitionId.value = localId;
}

function addNode() {
    const key = uniqueClientKey(`step_${graphNodes.length + 1}`);
    graphNodes.push({
        client_key: key,
        kind: props.nodeKinds[0]?.value ?? 'say',
        body: 'Новая реплика оператора',
        hint: '',
        sort_order: graphNodes.length,
        canvas_x: 60 + (graphNodes.length % 4) * 260,
        canvas_y: 60 + Math.floor(graphNodes.length / 4) * 180,
    });
    onSelectNode(key);

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
        onSelectNode(graphNodes[0]?.client_key ?? null);
    }

    if (entryNodeKey.value === clientKey) {
        entryNodeKey.value = graphNodes[0]?.client_key ?? '';
    }
}

function removeTransition(localId) {
    const index = graphTransitions.findIndex((transition) => transition.local_id === localId);
    if (index !== -1) {
        graphTransitions.splice(index, 1);
    }

    if (selectedTransitionId.value === localId) {
        selectedTransitionId.value = null;
    }
}

function moveTransition(direction) {
    if (!selectedTransition.value) {
        return;
    }

    const index = graphTransitions.findIndex((t) => t.local_id === selectedTransition.value.local_id);
    const target = index + direction;

    if (index < 0 || target < 0 || target >= graphTransitions.length) {
        return;
    }

    const [item] = graphTransitions.splice(index, 1);
    graphTransitions.splice(target, 0, item);
}

function publish() {
    router.post(route('scripts.editor.versions.publish', props.payload.version.id));
}

function unpublish() {
    router.post(route('scripts.editor.versions.unpublish', props.payload.version.id));
}

function saveGraph() {
    saving.value = true;

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
        customer_label: transition.customer_label?.trim() || null,
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
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>
