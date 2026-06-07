<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden lg:min-h-0">
        <CrmPageHeader
            title="Связи MCP"
            lead="Разрешённый обмен между доменами CRM для AI-инструментов. Толщина линии — интенсивность вызовов за выбранный период."
        >
            <template #actions>
                <div class="flex items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-700">
                    <button
                        v-for="option in dayOptions"
                        :key="option"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm transition"
                        :class="selectedDays === option
                            ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                            : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                        @click="changeDays(option)"
                    >
                        {{ option }} дн.
                    </button>
                </div>
            </template>
        </CrmPageHeader>

        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section :class="`${crmPanel} flex min-h-[520px] flex-col overflow-hidden p-3`">
                <VueFlow
                    v-model:nodes="flowNodes"
                    v-model:edges="flowEdges"
                    :fit-view-on-init="true"
                    class="mcp-flow-canvas min-h-[480px] flex-1 rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950/40"
                    @connect="onConnect"
                    @edge-double-click="onEdgeDoubleClick"
                />
            </section>

            <aside :class="`${crmPanel} flex flex-col gap-4 p-4`">
                <div>
                    <div class="text-sm font-medium">Как пользоваться</div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        Соедините узлы линией: двойной клик по ребру удалит связь. Сохранение записывает пары в базу.
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">
                        Вызовов tools за {{ selectedDays }} дн.: <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ linkStats.total_calls }}</span>
                    </p>
                </div>

                <div v-if="trafficPreview.length > 0">
                    <div class="text-sm font-medium">Обмен за период</div>
                    <div class="mt-2 max-h-40 space-y-2 overflow-y-auto">
                        <div
                            v-for="item in trafficPreview"
                            :key="item.pair_key"
                            class="rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                        >
                            <div class="font-medium">{{ item.source_label }} ↔ {{ item.target_label }}</div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ item.calls }} вызов{{ pluralCalls(item.calls) }}
                                <span v-if="item.errors > 0" class="text-red-600 dark:text-red-400">
                                    · {{ item.errors }} ошиб.
                                </span>
                                <span v-if="!item.configured" class="text-amber-600 dark:text-amber-400">
                                    · нет связи в конфиге
                                </span>
                            </div>
                            <div v-if="item.top_tools.length" class="mt-1 truncate text-xs text-zinc-400">
                                {{ item.top_tools.map((row) => row.tool).join(', ') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto">
                    <div class="text-sm font-medium">Настроенные связи</div>
                    <div
                        v-for="link in linkPreview"
                        :key="`${link.source_key}-${link.target_key}`"
                        class="rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                    >
                        <div class="font-medium">{{ link.source_label }} ↔ {{ link.target_label }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ link.bidirectional ? 'Двусторонняя' : 'Односторонняя' }}
                            <span v-if="link.calls > 0"> · {{ link.calls }} вызов{{ pluralCalls(link.calls) }}</span>
                        </div>
                    </div>
                    <div v-if="linkPreview.length === 0" class="text-sm text-zinc-500">
                        Связи пока не заданы — guard выключен, все cross-domain tools разрешены.
                    </div>
                </div>

                <button
                    type="button"
                    :class="`${crmBtnCreate} w-full justify-center`"
                    :disabled="form.processing"
                    @click="saveLinks"
                >
                    {{ form.processing ? 'Сохранение…' : 'Сохранить связи' }}
                </button>
            </aside>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { VueFlow } from '@vue-flow/core';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import { crmBtnCreate, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, {
        activeKey: 'settings',
        activeSubKey: 'configuration',
        activeLeafKey: 'mcp-integrations',
    }, () => page),
});

const props = defineProps({
    nodes: {
        type: Array,
        default: () => [],
    },
    links: {
        type: Array,
        default: () => [],
    },
    days: {
        type: Number,
        default: 7,
    },
    link_stats: {
        type: Object,
        default: () => ({
            days: 7,
            total_calls: 0,
            max_edge_calls: 0,
            edges: {},
            nodes: {},
        }),
    },
});

const dayOptions = [7, 30];
const selectedDays = ref(props.days ?? 7);
const linkStats = computed(() => props.link_stats ?? {
    days: selectedDays.value,
    total_calls: 0,
    max_edge_calls: 0,
    edges: {},
    nodes: {},
});

const nodesByKey = computed(() => Object.fromEntries(
    props.nodes.map((node, index) => [node.key, { ...node, index }]),
));

const configuredPairKeys = computed(() => new Set(
    (props.links ?? []).map((link) => pairKey(link.source_key, link.target_key)),
));

const flowNodes = ref(buildFlowNodes(props.nodes, linkStats.value));
const flowEdges = ref(buildFlowEdges(props.links, linkStats.value));

const form = useForm({
    links: props.links,
});

watch(
    () => [props.nodes, props.links, props.link_stats, props.days],
    ([nodes, links, stats, days]) => {
        selectedDays.value = days ?? selectedDays.value;
        flowNodes.value = buildFlowNodes(nodes, stats);
        flowEdges.value = buildFlowEdges(links, stats);
        form.links = links;
    },
    { deep: true },
);

const linkPreview = computed(() => flowEdges.value.map((edge) => {
    const source = nodesByKey.value[edge.source];
    const target = nodesByKey.value[edge.target];
    const stats = edgeStats(edge.source, edge.target);

    return {
        source_key: edge.source,
        target_key: edge.target,
        source_label: source?.label ?? edge.source,
        target_label: target?.label ?? edge.target,
        bidirectional: true,
        calls: stats?.calls ?? 0,
        errors: stats?.errors ?? 0,
    };
}));

const trafficPreview = computed(() => Object.values(linkStats.value.edges ?? {})
    .map((edge) => ({
        pair_key: pairKey(edge.source_key, edge.target_key),
        source_key: edge.source_key,
        target_key: edge.target_key,
        source_label: nodesByKey.value[edge.source_key]?.label ?? edge.source_key,
        target_label: nodesByKey.value[edge.target_key]?.label ?? edge.target_key,
        calls: edge.calls ?? 0,
        errors: edge.errors ?? 0,
        top_tools: edge.top_tools ?? [],
        configured: configuredPairKeys.value.has(pairKey(edge.source_key, edge.target_key)),
    }))
    .sort((left, right) => right.calls - left.calls)
    .slice(0, 12));

function pairKey(sourceKey, targetKey) {
    return sourceKey < targetKey ? `${sourceKey}|${targetKey}` : `${targetKey}|${sourceKey}`;
}

function edgeStats(sourceKey, targetKey) {
    return linkStats.value.edges?.[pairKey(sourceKey, targetKey)] ?? null;
}

function pluralCalls(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return '';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'а';
    }

    return 'ов';
}

function buildFlowNodes(nodes, stats) {
    const columns = 3;
    const maxNodeCalls = Math.max(
        0,
        ...Object.values(stats?.nodes ?? {}).map((row) => row.calls ?? 0),
    );

    return (nodes ?? []).map((node, index) => {
        const nodeStats = stats?.nodes?.[node.key] ?? { calls: 0, errors: 0 };
        const labelSuffix = nodeStats.calls > 0 ? ` (${nodeStats.calls})` : '';

        return {
            id: node.key,
            label: `${node.label}${labelSuffix}`,
            position: {
                x: 40 + (index % columns) * 240,
                y: 40 + Math.floor(index / columns) * 120,
            },
            data: { description: node.description, group: node.group, stats: nodeStats },
            style: {
                borderRadius: '12px',
                border: nodeBorderStyle(nodeStats, maxNodeCalls),
                padding: '10px 12px',
                fontSize: '13px',
                minWidth: '180px',
                background: 'white',
                boxShadow: nodeStats.calls > 0 ? '0 0 0 1px rgb(59 130 246 / 0.15)' : undefined,
            },
        };
    });
}

function nodeBorderStyle(nodeStats, maxNodeCalls) {
    if ((nodeStats.calls ?? 0) <= 0) {
        return '1px solid rgb(212 212 216)';
    }

    const ratio = maxNodeCalls > 0 ? nodeStats.calls / maxNodeCalls : 1;
    const width = 1 + Math.round(ratio * 2);

    return `${width}px solid ${nodeStats.errors > 0 ? 'rgb(220 38 38)' : 'rgb(37 99 235)'}`;
}

function buildFlowEdges(links, stats) {
    const maxCalls = stats?.max_edge_calls ?? 0;

    return (links ?? []).map((link) => {
        const traffic = edgeStats(link.source_key, link.target_key);
        const calls = traffic?.calls ?? 0;
        const errors = traffic?.errors ?? 0;
        const ratio = maxCalls > 0 ? calls / maxCalls : 0;

        return {
            id: pairKey(link.source_key, link.target_key),
            source: link.source_key,
            target: link.target_key,
            animated: calls > 0,
            label: calls > 0 ? `${calls}${errors > 0 ? ` · ${errors}⚠` : ''}` : (link.label ?? 'обмен'),
            style: {
                strokeWidth: calls > 0 ? 1.5 + ratio * 6.5 : 1.5,
                stroke: errors > 0 ? 'rgb(220 38 38)' : (calls > 0 ? 'rgb(37 99 235)' : 'rgb(161 161 170)'),
            },
            labelStyle: {
                fill: errors > 0 ? 'rgb(220 38 38)' : 'rgb(63 63 70)',
                fontWeight: 600,
                fontSize: 11,
            },
        };
    });
}

function onConnect(connection) {
    if (! connection.source || ! connection.target || connection.source === connection.target) {
        return;
    }

    const id = pairKey(connection.source, connection.target);

    if (flowEdges.value.some((edge) => edge.id === id)) {
        return;
    }

    flowEdges.value = [
        ...flowEdges.value,
        ...buildFlowEdges([{
            source_key: connection.source,
            target_key: connection.target,
            label: 'обмен',
        }], linkStats.value),
    ];
}

function onEdgeDoubleClick(_event, edge) {
    flowEdges.value = flowEdges.value.filter((item) => item.id !== edge.id);
}

function changeDays(days) {
    if (selectedDays.value === days) {
        return;
    }

    router.get(route('settings.mcp-integrations.index'), { days }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function saveLinks() {
    form.links = linkPreview.value.map((link) => ({
        source_key: link.source_key,
        target_key: link.target_key,
        bidirectional: true,
        is_active: true,
        label: 'обмен',
    }));

    form.put(route('settings.mcp-integrations.update'), {
        preserveScroll: true,
    });
}
</script>

<style scoped>
.mcp-flow-canvas {
    background-color: rgb(250 250 250);
    background-image: radial-gradient(circle, rgb(161 161 170 / 0.45) 1px, transparent 1px);
    background-size: 18px 18px;
}

:global(.dark) .mcp-flow-canvas {
    background-color: rgb(9 9 11 / 0.4);
    background-image: radial-gradient(circle, rgb(113 113 122 / 0.35) 1px, transparent 1px);
}
</style>
