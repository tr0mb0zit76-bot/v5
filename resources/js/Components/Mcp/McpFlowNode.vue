<template>
    <div class="mcp-flow-node">
        <Handle
            v-for="side in sides"
            :key="`source-${side}`"
            :id="`source-${side}`"
            type="source"
            :position="positionMap[side]"
            class="mcp-flow-handle"
        />
        <Handle
            v-for="side in sides"
            :key="`target-${side}`"
            :id="`target-${side}`"
            type="target"
            :position="positionMap[side]"
            class="mcp-flow-handle"
        />
        <div class="font-medium leading-snug">{{ data.label }}</div>
        <p v-if="data.description" class="mt-1 line-clamp-2 text-[11px] text-zinc-500 dark:text-zinc-400">
            {{ data.description }}
        </p>
    </div>
</template>

<script setup>
import { Handle, Position } from '@vue-flow/core';

defineProps({
    data: {
        type: Object,
        default: () => ({}),
    },
});

const sides = ['top', 'right', 'bottom', 'left'];

const positionMap = {
    top: Position.Top,
    right: Position.Right,
    bottom: Position.Bottom,
    left: Position.Left,
};
</script>

<style scoped>
.mcp-flow-node {
    min-width: 180px;
    border-radius: 12px;
    padding: 10px 12px;
    background: white;
    font-size: 13px;
}

:global(.dark) .mcp-flow-node {
    background: rgb(24 24 27);
    color: rgb(244 244 245);
}

:global(.mcp-flow-handle) {
    width: 10px !important;
    height: 10px !important;
    border: 2px solid white !important;
    background: rgb(37 99 235) !important;
    box-shadow: 0 0 0 1px rgb(37 99 235 / 0.35);
}

:global(.mcp-flow-handle:hover) {
    transform: scale(1.15);
}
</style>
