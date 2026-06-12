<template>
    <li>
        <div
            class="group flex items-center gap-1 rounded-lg px-1 py-1 transition"
            :class="rowClass"
        >
            <button
                v-if="hasChildren"
                type="button"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                :aria-label="isExpanded ? 'Свернуть' : 'Развернуть'"
                @click="emit('toggle', node.id)"
            >
                <ChevronRight
                    class="h-4 w-4 transition-transform duration-150"
                    :class="isExpanded ? 'rotate-90' : ''"
                />
            </button>
            <span v-else class="inline-block h-7 w-7 shrink-0" />

            <button
                v-if="hasChildren"
                type="button"
                class="min-w-0 flex-1 text-left"
                @click="emit('toggle', node.id)"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-zinc-800 dark:text-zinc-100">{{ node.name }}</span>
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        {{ childCount }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide" :class="sourceClass(node.source)">
                        {{ sourceLabel(node.source) }}
                    </span>
                </div>
            </button>

            <div v-else class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ node.name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide" :class="sourceClass(node.source)">
                        {{ sourceLabel(node.source) }}
                    </span>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2" @click.stop>
                <label
                    v-if="node.kind !== 'group' && node.flow === 'out'"
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    title="Участвует в плане расходов бюджетирования"
                >
                    <input
                        type="checkbox"
                        class="rounded border-zinc-300 text-sky-600 focus:ring-sky-500 dark:border-zinc-600"
                        :checked="node.include_in_budget"
                        @change="emit('toggle-budget', node, $event.target.checked)"
                    >
                    В бюджете
                </label>
                <input
                    v-if="node.source !== 'system' && node.source !== 'group'"
                    :value="node.name"
                    type="text"
                    class="w-36 rounded-lg border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    @change="emit('rename', node, $event.target.value)"
                >
                <button
                    v-if="!node.is_system"
                    type="button"
                    class="rounded-md px-2 py-1 text-xs text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                    @click="emit('remove', node)"
                >
                    Удалить
                </button>
            </div>
        </div>

        <ul
            v-if="hasChildren && isExpanded"
            class="ml-3 space-y-0.5 border-l border-zinc-200 pl-2 dark:border-zinc-700"
        >
            <CategoryTreeNode
                v-for="child in node.children ?? []"
                :key="child.id"
                :node="child"
                :expanded-ids="expandedIds"
                @toggle="(id) => emit('toggle', id)"
                @rename="(category, name) => emit('rename', category, name)"
                @toggle-budget="(category, value) => emit('toggle-budget', category, value)"
                @remove="(category) => emit('remove', category)"
            />
        </ul>
    </li>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import CategoryTreeNode from '@/Components/Finance/ManagementAccountingCategoryTreeNode.vue';

defineOptions({
    name: 'ManagementAccountingCategoryTreeNode',
});

const props = defineProps({
    node: { type: Object, required: true },
    expandedIds: { type: Object, required: true },
});

const emit = defineEmits(['toggle', 'rename', 'toggle-budget', 'remove']);

const hasChildren = computed(() => (props.node.children ?? []).length > 0);
const childCount = computed(() => (props.node.children ?? []).length);
const isExpanded = computed(() => props.expandedIds.has(props.node.id));

const rowClass = computed(() => {
    if (props.node.kind === 'group') {
        return 'bg-zinc-50/80 hover:bg-zinc-100/80 dark:bg-zinc-900/40 dark:hover:bg-zinc-900/70';
    }

    return 'hover:bg-zinc-50 dark:hover:bg-zinc-900/50';
});

function sourceLabel(source) {
    const labels = {
        group: 'группа',
        system: 'системная',
        budget: 'бюджет',
        custom: 'своя',
    };

    return labels[source] ?? source;
}

function sourceClass(source) {
    const classes = {
        group: 'bg-violet-100 text-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
        system: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        budget: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        custom: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    };

    return classes[source] ?? classes.system;
}
</script>
