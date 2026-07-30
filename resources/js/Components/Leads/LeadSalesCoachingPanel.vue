<template>
    <section
        v-if="insights?.available"
        :class="compact
            ? 'h-full border border-violet-200 bg-violet-50/70 shadow-sm dark:border-violet-900/50 dark:bg-violet-950/20'
            : 'border border-violet-200 bg-violet-50/70 shadow-sm dark:border-violet-900/50 dark:bg-violet-950/20'"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-2 py-2 text-left md:px-3"
            :aria-expanded="expanded"
            @click="toggleExpanded"
        >
            <div class="min-w-0">
                <h2 class="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-800 dark:text-violet-300">
                    Коучинг по итогам
                </h2>
                <p class="mt-0.5 text-[11px] text-violet-900/80 dark:text-violet-200/80">
                    {{ insights.period_days }} д. · доля выигрышей {{ insights.summary.win_rate_pct }}%
                    <span v-if="!expanded"> · нажмите, чтобы раскрыть</span>
                </p>
            </div>
            <ChevronDown
                class="h-4 w-4 shrink-0 text-violet-700 transition-transform dark:text-violet-300"
                :class="expanded ? 'rotate-180' : ''"
            />
        </button>

        <div
            v-show="expanded"
            :class="compact ? 'space-y-2 px-2 pb-2 md:px-2' : 'space-y-3 px-3 pb-3 md:px-4'"
        >
            <div
                :class="compact
                    ? 'grid gap-1.5 sm:grid-cols-2 xl:grid-cols-2'
                    : 'grid gap-2 sm:grid-cols-2 lg:grid-cols-4'"
            >
                <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                    <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Доля выигрышей</div>
                    <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 gap-y-0">
                        <span :class="compact ? 'text-base font-semibold' : 'text-xl font-semibold'">{{ insights.summary.win_rate_pct }}%</span>
                        <span class="text-[11px] leading-tight text-zinc-500">
                            выиграно {{ insights.summary.won_leads }} из {{ insights.summary.closed_leads }} закрытых
                        </span>
                    </div>
                </article>
                <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                    <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Проигрыши без ЛПР</div>
                    <div :class="compact ? 'mt-0.5 text-base font-semibold' : 'mt-1 text-xl font-semibold'">{{ insights.summary.lost_without_authority }}</div>
                </article>
                <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                    <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Простой на квалификации (проигрыши)</div>
                    <div :class="compact ? 'mt-0.5 text-base font-semibold' : 'mt-1 text-xl font-semibold'">{{ insights.summary.lost_with_idle_qualification }}</div>
                </article>
                <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                    <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Простой на квалификации (выигрыши)</div>
                    <div :class="compact ? 'mt-0.5 text-base font-semibold' : 'mt-1 text-xl font-semibold'">{{ insights.summary.won_with_idle_qualification }}</div>
                </article>
            </div>

            <div v-if="topLossFlags.length">
                <div class="text-[10px] font-semibold uppercase tracking-[0.12em] text-violet-800/80 dark:text-violet-300/80">
                    Частые отказы
                </div>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <span
                        v-for="row in topLossFlags"
                        :key="row.flag"
                        class="inline-flex items-center gap-1 rounded-md border border-violet-200/90 bg-white/90 px-2 py-0.5 text-[11px] text-zinc-800 dark:border-violet-900/50 dark:bg-zinc-950/60 dark:text-zinc-200"
                    >
                        <span>{{ row.label }}</span>
                        <span class="font-semibold text-violet-800 dark:text-violet-300">{{ row.count }}</span>
                    </span>
                </div>
            </div>

            <ul
                v-if="insights.recommendations?.length"
                :class="compact
                    ? 'list-disc space-y-0.5 pl-4 text-[11px] leading-snug text-zinc-800 dark:text-zinc-200'
                    : 'list-disc space-y-1 pl-5 text-sm text-zinc-800 dark:text-zinc-200'"
            >
                <li v-for="(item, idx) in insights.recommendations" :key="idx">{{ item }}</li>
            </ul>

            <ul
                v-if="contrastPreview.length"
                :class="compact
                    ? 'space-y-1 text-[11px] text-zinc-600 dark:text-zinc-300'
                    : 'space-y-1.5 text-xs text-zinc-600 dark:text-zinc-300'"
            >
                <li
                    v-for="(sample, idx) in contrastPreview"
                    :key="idx"
                    class="rounded-md border border-violet-200/60 bg-white/60 px-2 py-1 dark:border-violet-900/40 dark:bg-zinc-950/40"
                >
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">
                        {{ sample.lost.lead_number || `#${sample.lost.lead_id}` }}
                    </span>
                    <span v-if="sample.lost.close_outcome_label"> · {{ sample.lost.close_outcome_label }}</span>
                    <span v-if="sample.lost.has_idle_qualification_dwell"> · простой на квалификации</span>
                    <span v-if="sample.won_reference">
                        · рядом выигран {{ sample.won_reference.lead_number || `#${sample.won_reference.lead_id}` }}
                        (качество {{ sample.won_reference.hygiene_score }})
                    </span>
                </li>
            </ul>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const STORAGE_KEY = 'leads_coaching_panel_expanded';

const props = defineProps({
    insights: {
        type: Object,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['expand-change']);

const expanded = ref(readExpandedPreference());

const FLAG_LABELS = {
    lost_price: 'Цена / маржа',
    lost_timing: 'Срок / не успели',
    lost_competitor: 'Ушли к конкуренту',
    lost_no_need: 'Нет потребности',
    lost_wrong_segment: 'Не наш сегмент',
    lost_no_authority: 'Не вышли на ЛПР',
    lost_ghosting: 'Пропал контакт',
    lost_internal: 'Внутренние причины',
    lost_other: 'Другое',
    unset: 'Без причины',
};

const topLossFlags = computed(() => {
    const counts = props.insights?.loss_flag_counts;

    if (!counts || typeof counts !== 'object') {
        return [];
    }

    return Object.entries(counts)
        .map(([flag, count]) => ({
            flag,
            count: Number(count) || 0,
            label: FLAG_LABELS[flag] || flag,
        }))
        .filter((row) => row.count > 0)
        .slice(0, 5);
});

const contrastPreview = computed(() => {
    const samples = props.insights?.contrast_samples;

    if (!Array.isArray(samples)) {
        return [];
    }

    return samples.slice(0, props.compact ? 1 : 2);
});

function readExpandedPreference() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function toggleExpanded() {
    expanded.value = !expanded.value;
}

watch(expanded, (value) => {
    try {
        localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        /* ignore */
    }
    emit('expand-change', value);
}, { immediate: true });
</script>
