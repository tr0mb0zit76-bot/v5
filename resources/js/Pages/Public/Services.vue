<script setup>
import PublicSiteShell from '@/Components/Public/PublicSiteShell.vue';
import { publicPages } from '@/Components/Public/publicPages';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <PublicSiteShell :page="publicPages.services" :can-login="canLogin" :can-register="canRegister" />
</template>
