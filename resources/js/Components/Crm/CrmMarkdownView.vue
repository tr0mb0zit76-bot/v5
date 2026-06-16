<template>
    <CrmMarkdownEditor
        :model-value="modelValue"
        :placeholder="placeholder"
        :editable="false"
        :compact="compact"
        class="pointer-events-none"
    />
</template>

<script setup>
import CrmMarkdownEditor from '@/Components/Crm/CrmMarkdownEditor.vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>
