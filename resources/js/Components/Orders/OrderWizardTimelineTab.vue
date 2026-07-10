<script setup>
import ActivityTimeline from '@/Components/CommercialIntelligence/ActivityTimeline.vue';

defineProps({
    orderId: { type: Number, required: true },
});
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Хронология по заказу: комментарии диспозиции, письма и другие события.
        </p>
        <ActivityTimeline :order-id="orderId" />
    </div>
</template>
