<template>
    <div class="space-y-3">
        <div v-if="loading" class="text-sm text-zinc-500 dark:text-zinc-400">Загрузка ленты…</div>
        <div v-else-if="events.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
            Событий пока нет.
        </div>
        <ul v-else class="space-y-2">
            <li
                v-for="event in events"
                :key="event.id"
                class="border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ event.title }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ formatWhen(event.occurred_at) }}</span>
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ event.event_label }}
                    <span v-if="event.user_name"> · {{ event.user_name }}</span>
                </div>
                <p v-if="event.summary" class="mt-1 text-zinc-600 dark:text-zinc-300">{{ event.summary }}</p>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    leadId: {
        type: Number,
        default: null,
    },
    orderId: {
        type: Number,
        default: null,
    },
});

const events = ref([]);
const loading = ref(false);

const timelineUrl = computed(() => {
    if (props.orderId) {
        return route('orders.activity-timeline', props.orderId);
    }

    if (props.leadId) {
        return route('leads.activity-timeline', props.leadId);
    }

    return null;
});

const subjectKey = computed(() => `${props.orderId ?? ''}|${props.leadId ?? ''}`);

async function loadTimeline() {
    if (!timelineUrl.value) {
        events.value = [];

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(timelineUrl.value, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        events.value = data.events ?? [];
    } finally {
        loading.value = false;
    }
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

onMounted(loadTimeline);

watch(subjectKey, loadTimeline);

defineExpose({ reload: loadTimeline });
</script>
