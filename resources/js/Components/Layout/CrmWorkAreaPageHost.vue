<template>
    <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">
        <!--
          Keep live pages mounted and sized (visibility + absolute stack).
          v-show/display:none collapses AgGrid to 0×0 and feels like a remount on show.
        -->
        <div
            v-for="entry in liveEntries"
            :key="entry.key"
            class="min-h-0 min-w-0 flex-1 flex-col"
            :class="liveVisibleKey === entry.key
                ? 'relative z-10 flex'
                : 'pointer-events-none invisible absolute inset-0 z-0 flex'"
            :aria-hidden="liveVisibleKey !== entry.key"
        >
            <component :is="entry.component" v-bind="entry.props" />
        </div>

        <div
            v-if="!liveVisibleKey"
            class="flex min-h-0 min-w-0 flex-1 flex-col"
        >
            <slot />
        </div>
    </div>
</template>

<script setup>
import { nextTick, watch } from 'vue';
import { useCrmWorkArea } from '@/support/crmWorkArea.js';

const { liveEntries, liveVisibleKey } = useCrmWorkArea();

watch(liveVisibleKey, (key) => {
    if (!key || typeof window === 'undefined') {
        return;
    }

    // AgGrid / panel layouts remeasure after the stack becomes visible.
    nextTick(() => {
        window.dispatchEvent(new Event('resize'));
    });
});
</script>
