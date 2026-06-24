<template>
    <section
        v-if="queue?.available && queue.total > 0"
        class="border border-amber-200 bg-amber-50/80 p-4 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20 md:p-5"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-900 dark:text-amber-200">
                    Требует внимания
                </h2>
                <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/80">
                    {{ queue.total }} {{ leadWord(queue.total) }} с просроченным этапом, пропущенным контактом или без ответа на письмо.
                </p>
            </div>
            <Link
                v-if="showAllLink"
                :href="route('leads.index')"
                class="text-xs font-medium text-amber-900 underline underline-offset-2 dark:text-amber-200"
            >
                Все лиды
            </Link>
        </div>

        <ul class="mt-4 space-y-2">
            <li
                v-for="item in queue.items"
                :key="item.lead_id"
                class="rounded-lg border border-amber-200/80 bg-white/80 p-3 dark:border-amber-900/40 dark:bg-zinc-950/50"
            >
                <button
                    type="button"
                    class="flex w-full flex-col items-start gap-2 text-left sm:flex-row sm:items-center sm:justify-between"
                    @click="openLead(item.lead_id)"
                >
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ item.number }} · {{ item.title }}
                        </div>
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            <span v-if="item.stage_name">{{ item.stage_name }}</span>
                            <span v-if="item.stage_name && item.responsible_name"> · </span>
                            <span v-if="item.responsible_name">{{ item.responsible_name }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="(reason, idx) in item.reasons"
                            :key="`${item.lead_id}-${reason.type}-${idx}`"
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:bg-amber-950/60 dark:text-amber-100"
                            :title="reason.title"
                        >
                            {{ reason.label }}
                        </span>
                    </div>
                </button>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    queue: {
        type: Object,
        default: null,
    },
    showAllLink: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open-lead']);

function leadWord(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return 'лид';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'лида';
    }

    return 'лидов';
}

function openLead(leadId) {
    if (!leadId) {
        return;
    }

    if (props.showAllLink) {
        router.get(route('leads.show', leadId));

        return;
    }

    emit('open-lead', { id: leadId });
}
</script>
