<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden lg:min-h-0">
        <CrmPageHeader
            title="Связи MCP"
            lead="Настройка разрешённого обмена данными между доменами CRM для AI-инструментов без правок кода."
        />

        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section :class="`${crmPanel} flex min-h-[520px] flex-col overflow-hidden p-3`">
                <VueFlow
                    v-model:nodes="flowNodes"
                    v-model:edges="flowEdges"
                    :fit-view-on-init="true"
                    class="min-h-[480px] flex-1 rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950/40"
                    @connect="onConnect"
                    @edge-double-click="onEdgeDoubleClick"
                >
                    <Background pattern-color="#a1a1aa" :gap="18" />
                    <Controls />
                </VueFlow>
            </section>

            <aside :class="`${crmPanel} flex flex-col gap-4 p-4`">
                <div>
                    <div class="text-sm font-medium">Как пользоваться</div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        Соедините узлы линией: двойной клик по ребру удалит связь. Сохранение записывает пары в базу.
                    </p>
                </div>

                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto">
                    <div
                        v-for="link in linkPreview"
                        :key="`${link.source_key}-${link.target_key}`"
                        class="rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                    >
                        <div class="font-medium">{{ link.source_label }} ↔ {{ link.target_label }}</div>
                        <div class="text-xs text-zinc-500">{{ link.bidirectional ? 'Двусторонняя' : 'Односторонняя' }}</div>
                    </div>
                    <div v-if="linkPreview.length === 0" class="text-sm text-zinc-500">
                        Связи пока не заданы.
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
import { useForm } from '@inertiajs/vue3';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { VueFlow } from '@vue-flow/core';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
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
});

const nodesByKey = computed(() => Object.fromEntries(
    props.nodes.map((node, index) => [node.key, { ...node, index }]),
));

const flowNodes = ref(buildFlowNodes(props.nodes));
const flowEdges = ref(buildFlowEdges(props.links));

const form = useForm({
    links: props.links,
});

watch(
    () => [props.nodes, props.links],
    ([nodes, links]) => {
        flowNodes.value = buildFlowNodes(nodes);
        flowEdges.value = buildFlowEdges(links);
        form.links = links;
    },
    { deep: true },
);

const linkPreview = computed(() => flowEdges.value.map((edge) => {
    const source = nodesByKey.value[edge.source];
    const target = nodesByKey.value[edge.target];

    return {
        source_key: edge.source,
        target_key: edge.target,
        source_label: source?.label ?? edge.source,
        target_label: target?.label ?? edge.target,
        bidirectional: true,
    };
}));

function buildFlowNodes(nodes) {
    const columns = 3;

    return (nodes ?? []).map((node, index) => ({
        id: node.key,
        label: node.label,
        position: {
            x: 40 + (index % columns) * 240,
            y: 40 + Math.floor(index / columns) * 120,
        },
        data: { description: node.description, group: node.group },
        style: {
            borderRadius: '12px',
            border: '1px solid rgb(212 212 216)',
            padding: '10px 12px',
            fontSize: '13px',
            minWidth: '180px',
            background: 'white',
        },
    }));
}

function buildFlowEdges(links) {
    return (links ?? []).map((link) => ({
        id: `${link.source_key}-${link.target_key}`,
        source: link.source_key,
        target: link.target_key,
        animated: true,
        label: link.label ?? 'обмен',
    }));
}

function onConnect(connection) {
    if (! connection.source || ! connection.target || connection.source === connection.target) {
        return;
    }

    const id = [connection.source, connection.target].sort().join('-');

    if (flowEdges.value.some((edge) => edge.id === id || (edge.source === connection.target && edge.target === connection.source))) {
        return;
    }

    flowEdges.value = [
        ...flowEdges.value,
        {
            id,
            source: connection.source,
            target: connection.target,
            animated: true,
            label: 'обмен',
        },
    ];
}

function onEdgeDoubleClick(_event, edge) {
    flowEdges.value = flowEdges.value.filter((item) => item.id !== edge.id);
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
