<template>
    <div>
        <div
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
            :style="{ marginLeft: `${depth * 1.25}rem` }"
        >
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="node.kind === 'group'" class="text-xs font-semibold uppercase tracking-wide text-zinc-400">группа</span>
                    <span class="font-medium">{{ node.name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide" :class="sourceClass(node.source)">
                        {{ sourceLabel(node.source) }}
                    </span>
                </div>
                <div class="text-xs text-zinc-500">{{ node.code }}</div>
            </div>
            <div class="flex items-center gap-2" @click.stop>
                <input
                    v-if="node.source !== 'system' && node.source !== 'group'"
                    :value="node.name"
                    type="text"
                    class="w-40 rounded-lg border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    @change="emit('rename', node, $event.target.value)"
                >
                <button
                    v-if="!node.is_system"
                    type="button"
                    class="text-xs text-rose-600 hover:underline dark:text-rose-400"
                    @click="emit('remove', node)"
                >
                    Удалить
                </button>
            </div>
        </div>
        <CategoryTreeNode
            v-for="child in node.children ?? []"
            :key="child.id"
            :node="child"
            :depth="depth + 1"
            @rename="(category, name) => emit('rename', category, name)"
            @remove="(category) => emit('remove', category)"
        />
    </div>
</template>

<script setup>
import CategoryTreeNode from '@/Components/Finance/ManagementAccountingCategoryTreeNode.vue';

defineProps({
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['rename', 'remove']);

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
