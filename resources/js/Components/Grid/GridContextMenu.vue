<template>
    <Teleport to="body">
        <div
            v-if="open && items.length > 0"
            ref="rootRef"
            class="fixed z-[9999] min-w-[12rem] border border-zinc-200 bg-white py-0.5 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
            :style="panelStyle"
            role="menu"
            @click.stop
        >
            <button
                v-for="(item, index) in items"
                :key="index"
                type="button"
                role="menuitem"
                class="flex w-full items-center px-3 py-2 text-left hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-zinc-800"
                :class="item.danger ? 'text-rose-700 dark:text-rose-300' : 'text-zinc-800 dark:text-zinc-100'"
                :disabled="item.disabled"
                @click="onPick(item)"
            >
                {{ item.label }}
            </button>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';

/**
 * @typedef {{ label: string, disabled?: boolean, danger?: boolean, run: () => void }} GridContextMenuItem
 */

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    x: {
        type: Number,
        default: 0,
    },
    y: {
        type: Number,
        default: 0,
    },
    /** @type {import('vue').PropType<GridContextMenuItem[]>} */
    items: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const rootRef = ref(null);

const panelStyle = computed(() => {
    if (typeof window === 'undefined') {
        return { left: `${props.x}px`, top: `${props.y}px` };
    }

    const pad = 8;
    const w = 220;
    const approxH = Math.min(360, props.items.length * 40 + 16);
    let left = Math.min(props.x, window.innerWidth - w - pad);
    let top = Math.min(props.y, window.innerHeight - approxH - pad);
    left = Math.max(pad, left);
    top = Math.max(pad, top);

    return {
        left: `${left}px`,
        top: `${top}px`,
    };
});

function onPick(item) {
    if (item.disabled) {
        return;
    }

    item.run?.();
    emit('close');
}

function onGlobalPointerDown(event) {
    if (!props.open) {
        return;
    }

    const root = rootRef.value;
    if (root && event.target instanceof Node && root.contains(event.target)) {
        return;
    }

    emit('close');
}

function onGlobalKeyDown(event) {
    if (props.open && event.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            window.addEventListener('pointerdown', onGlobalPointerDown, true);
            window.addEventListener('keydown', onGlobalKeyDown, true);
        } else {
            window.removeEventListener('pointerdown', onGlobalPointerDown, true);
            window.removeEventListener('keydown', onGlobalKeyDown, true);
        }
    },
);

onUnmounted(() => {
    window.removeEventListener('pointerdown', onGlobalPointerDown, true);
    window.removeEventListener('keydown', onGlobalKeyDown, true);
});
</script>
