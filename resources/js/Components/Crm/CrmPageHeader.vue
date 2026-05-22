<template>
    <div class="flex shrink-0 flex-wrap items-start justify-between gap-4">
        <div>
            <h1 :class="titleClass">{{ title }}</h1>
            <p v-if="lead" :class="crmPageLead">{{ lead }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center justify-end gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
import { crmPageLead, crmPageTitle } from '@/support/crmUi.js';

defineProps({
    title: {
        type: String,
        required: true,
    },
    lead: {
        type: String,
        default: '',
    },
    titleClass: {
        type: String,
        default: crmPageTitle,
    },
});
</script>
