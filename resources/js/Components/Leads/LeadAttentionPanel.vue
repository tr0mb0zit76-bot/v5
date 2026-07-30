<template>
    <section
        v-if="queue?.available && queue.total > 0"
        :class="variant === 'summary'
            ? 'crm-stat-card border-amber-200 bg-amber-50/80 p-4 dark:border-amber-900/50 dark:bg-amber-950/20'
            : compact
                ? 'h-full border border-amber-200 bg-amber-50/80 p-2 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20'
                : 'border border-amber-200 bg-amber-50/80 p-3 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20 md:p-4'"
    >
        <div v-if="variant === 'summary'" class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-900/70 dark:text-amber-200/70">
                    Проблемные лиды
                </div>
                <div class="mt-2 text-3xl font-semibold tabular-nums text-amber-950 dark:text-amber-100">
                    {{ queue.total }}
                </div>
                <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/80">
                    Просроченные этапы, пропущенные контакты или письма без ответа.
                </p>
            </div>
            <Link
                :href="route('leads.index')"
                class="shrink-0 rounded-xl border border-amber-300 bg-white px-3 py-2 text-xs font-medium text-amber-950 shadow-sm hover:bg-amber-100 dark:border-amber-800 dark:bg-zinc-950 dark:text-amber-100 dark:hover:bg-amber-950/40"
            >
                Открыть
            </Link>
        </div>

        <div v-else class="flex flex-wrap items-start justify-between gap-2">
            <button
                type="button"
                class="flex min-w-0 flex-1 items-start justify-between gap-2 text-left"
                :aria-expanded="panelOpen"
                @click="togglePanel"
            >
                <div class="min-w-0">
                    <h2
                        :class="compact
                            ? 'text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-900 dark:text-amber-200'
                            : 'text-sm font-semibold uppercase tracking-[0.2em] text-amber-900 dark:text-amber-200'"
                    >
                        Требует внимания
                    </h2>
                    <p
                        :class="compact
                            ? 'mt-0.5 text-[11px] leading-snug text-amber-900/80 dark:text-amber-200/80'
                            : 'mt-1 text-xs text-amber-900/80 dark:text-amber-200/80'"
                    >
                        {{ queue.total }} {{ leadWord(queue.total) }}
                        <span v-if="panelOpen"> с просроченным этапом, пропущенным контактом или без ответа на письмо.</span>
                        <span v-else> · нажмите, чтобы раскрыть</span>
                    </p>
                </div>
                <ChevronDown
                    class="mt-0.5 h-4 w-4 shrink-0 text-amber-800 transition-transform dark:text-amber-200"
                    :class="panelOpen ? 'rotate-180' : ''"
                />
            </button>
            <div v-if="panelOpen" class="flex items-center gap-3">
                <button
                    v-if="queue.items.length > collapsedLimit"
                    type="button"
                    class="text-xs font-medium text-amber-900 underline underline-offset-2 dark:text-amber-200"
                    @click="listExpanded = !listExpanded"
                >
                    {{ listExpanded ? 'Свернуть список' : `Показать все ${queue.total}` }}
                </button>
                <Link
                    v-if="showAllLink"
                    :href="route('leads.index')"
                    class="text-xs font-medium text-amber-900 underline underline-offset-2 dark:text-amber-200"
                >
                    Все лиды
                </Link>
            </div>
        </div>

        <ul
            v-if="variant !== 'summary' && panelOpen"
            :class="[
                compact ? 'mt-2 space-y-1.5' : 'mt-3 space-y-2',
                listExpanded ? (compact ? 'max-h-40 overflow-y-auto pr-1' : 'max-h-80 overflow-y-auto pr-1') : '',
            ]"
        >
            <li
                v-for="item in visibleItems"
                :key="item.lead_id"
                :class="compact
                    ? 'border border-amber-200/80 bg-white/80 p-2 dark:border-amber-900/40 dark:bg-zinc-950/50'
                    : 'border border-amber-200/80 bg-white/80 p-2.5 dark:border-amber-900/40 dark:bg-zinc-950/50'"
            >
                <button
                    type="button"
                    class="flex w-full flex-col items-start gap-2 text-left sm:flex-row sm:items-center sm:justify-between"
                    @click="openLead(item.lead_id)"
                >
                    <div class="min-w-0">
                        <div class="truncate text-xs font-semibold text-zinc-900 dark:text-zinc-50 sm:text-sm">
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
                            class="bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-900 dark:bg-amber-950/60 dark:text-amber-100"
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
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';

const PANEL_STORAGE_KEY = 'leads_attention_panel_expanded';

const props = defineProps({
    queue: {
        type: Object,
        default: null,
    },
    showAllLink: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'list',
        validator: (value) => ['list', 'summary'].includes(value),
    },
    collapsedLimit: {
        type: Number,
        default: 2,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open-lead', 'expand-change']);
const listExpanded = ref(false);
const panelOpen = ref(readPanelPreference());

const visibleItems = computed(() => {
    const items = props.queue?.items ?? [];

    if (listExpanded.value) {
        return items;
    }

    return items.slice(0, props.collapsedLimit);
});

function readPanelPreference() {
    try {
        return localStorage.getItem(PANEL_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function togglePanel() {
    panelOpen.value = !panelOpen.value;
}

watch(panelOpen, (value) => {
    try {
        localStorage.setItem(PANEL_STORAGE_KEY, value ? '1' : '0');
    } catch {
        /* ignore */
    }
    emit('expand-change', value);
}, { immediate: true });

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
