<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto pb-8">
        <CrmPageHeader :title="post.title || `Кейс #${post.id}`">
            <template #lead>
                {{ routeSummary(post) }}
                <span class="text-zinc-400"> · #{{ post.id }}</span>
            </template>
            <template #actions>
                <Link :href="route('load-board.index')" :class="crmBtnNeutral">К бирже</Link>
            </template>
        </CrmPageHeader>

        <LoadBoardPostCard
            :post="post"
            :users="users"
            :contractors="contractors"
            :status-labels="statusLabels"
            :priority-labels="priorityLabels"
            :offer-source-options="offerSourceOptions"
            :current-user-id="currentUserId"
            :ati-preview="atiPreview"
            :order-options="orderOptions"
            :lead-options="leadOptions"
            :advisor="advisor"
            :carrier-pool="carrierPool"
            full-page
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import LoadBoardPostCard from '@/Components/LoadBoard/LoadBoardPostCard.vue';
import { crmBtnNeutral } from '@/support/crmUi.js';

const props = defineProps({
    post: { type: Object, required: true },
    advisor: { type: Object, required: true },
    carrierPool: { type: Object, default: () => ({ entries: [], total: 0, sources_summary: [] }) },
    statusLabels: { type: Object, default: () => ({}) },
    priorityLabels: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    contractors: { type: Array, default: () => [] },
    leadOptions: { type: Array, default: () => [] },
    orderOptions: { type: Array, default: () => [] },
    offerSourceOptions: { type: Object, default: () => ({}) },
    atiPreview: { type: Object, default: null },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

function routeSummary(post) {
    const parts = [post.loading_location, post.unloading_location].filter(Boolean);

    return parts.length ? parts.join(' → ') : 'Маршрут не указан';
}
</script>
