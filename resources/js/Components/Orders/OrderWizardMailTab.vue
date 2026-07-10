<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { crmBtnSecondary, crmPanel } from '@/support/crmUi.js';

const props = defineProps({
    orderId: { type: Number, required: true },
    threads: { type: Array, default: () => [] },
    composeUrl: { type: String, default: null },
});

function formatWhen(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('ru-RU');
}
</script>

<template>
    <div class="space-y-4">
        <div :class="`${crmPanel} space-y-3 p-4`">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Переписка</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Письма, привязанные к этому заказу.</p>
                </div>
                <Link
                    v-if="composeUrl"
                    :href="composeUrl"
                    :class="crmBtnSecondary"
                >
                    <Mail class="h-4 w-4" />
                    Написать клиенту
                </Link>
            </div>
            <div v-if="threads.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                Писем по заказу пока нет.
            </div>
            <div v-else class="space-y-2">
                <Link
                    v-for="thread in threads"
                    :key="thread.id"
                    :href="route('mail.threads.show', thread.id)"
                    class="block rounded-xl border border-zinc-200 p-3 text-sm transition hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700"
                >
                    <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ thread.subject }}</div>
                    <div v-if="thread.last_message_at" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ formatWhen(thread.last_message_at) }}
                    </div>
                    <p v-if="thread.preview" class="mt-2 line-clamp-2 text-xs text-zinc-600 dark:text-zinc-300">
                        {{ thread.preview }}
                    </p>
                </Link>
            </div>
        </div>
    </div>
</template>
