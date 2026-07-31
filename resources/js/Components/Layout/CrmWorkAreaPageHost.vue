<template>
    <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">
        <div
            v-for="entry in liveEntries"
            v-show="liveVisibleKey === entry.key"
            :key="entry.key"
            class="flex min-h-0 min-w-0 flex-1 flex-col"
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
import { useCrmWorkArea } from '@/support/crmWorkArea.js';

const { liveEntries, liveVisibleKey } = useCrmWorkArea();
</script>
