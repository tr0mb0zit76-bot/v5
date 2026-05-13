<script setup>
import { computed } from 'vue';
import { ORDER_STATUS_ICON_META } from '@/support/orderStatusDisplay.js';

const props = defineProps({
    iconKey: {
        type: String,
        default: '',
    },
    /** Размер viewBox 24; число — px ширины/высоты иконки */
    size: {
        type: [Number, String],
        default: 20,
    },
});

const meta = computed(() => (props.iconKey && ORDER_STATUS_ICON_META[props.iconKey] ? ORDER_STATUS_ICON_META[props.iconKey] : null));

const svgSize = computed(() => Number(props.size) || 20);
</script>

<template>
    <span
        v-if="meta"
        :class="['inline-flex shrink-0 items-center justify-center', meta.colorClass]"
        aria-hidden="true"
    >
        <svg
            :width="svgSize"
            :height="svgSize"
            viewBox="0 0 24 24"
            :fill="meta.filled ? 'currentColor' : 'none'"
            :stroke="meta.filled ? 'none' : 'currentColor'"
            :stroke-width="meta.filled ? 0 : 2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="shrink-0"
        >
            <template v-if="meta.filled">
                <path
                    v-for="(d, index) in meta.paths"
                    :key="`p-${index}`"
                    :d="d"
                    :fill="index === 0 ? 'currentColor' : '#ffffff'"
                />
            </template>
            <template v-else>
                <path v-for="(d, index) in meta.paths" :key="`p-${index}`" :d="d" />
                <circle
                    v-for="(c, index) in meta.circles ?? []"
                    :key="`c-${index}`"
                    :cx="c.cx"
                    :cy="c.cy"
                    :r="c.r"
                />
            </template>
        </svg>
    </span>
</template>
