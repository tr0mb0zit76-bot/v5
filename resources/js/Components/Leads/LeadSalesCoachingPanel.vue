<template>
    <section
        v-if="insights?.available"
        :class="compact
            ? 'h-full border border-violet-200 bg-violet-50/70 p-2 shadow-sm dark:border-violet-900/50 dark:bg-violet-950/20'
            : 'border border-violet-200 bg-violet-50/70 p-3 shadow-sm dark:border-violet-900/50 dark:bg-violet-950/20 md:p-4'"
    >
        <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5">
            <h2 class="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-800 dark:text-violet-300">
                Outcome Intelligence
            </h2>
            <p class="text-[11px] text-violet-900/80 dark:text-violet-200/80">
                Коучинг за {{ insights.period_days }} д.
            </p>
        </div>

        <div
            :class="compact
                ? 'mt-2 grid gap-1.5 sm:grid-cols-2 xl:grid-cols-2'
                : 'mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4'"
        >
            <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Win rate</div>
                <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 gap-y-0">
                    <span :class="compact ? 'text-base font-semibold' : 'text-xl font-semibold'">{{ insights.summary.win_rate_pct }}%</span>
                    <span class="text-[11px] leading-tight text-zinc-500">
                        выиграно {{ insights.summary.won_leads }} из {{ insights.summary.closed_leads }} закрытых
                    </span>
                </div>
            </article>
            <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Lost без ЛПР</div>
                <div :class="compact ? 'mt-0.5 text-base font-semibold' : 'mt-1 text-xl font-semibold'">{{ insights.summary.lost_without_authority }}</div>
            </article>
            <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Idle на квалификации (lost)</div>
                <div :class="compact ? 'mt-0.5 text-base font-semibold' : 'mt-1 text-xl font-semibold'">{{ insights.summary.lost_with_idle_qualification }}</div>
            </article>
            <article class="rounded-md border border-violet-200/80 bg-white/80 px-2 py-1.5 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-[10px] uppercase tracking-[0.1em] text-zinc-500">Idle на квалификации (won)</div>
                <div :class="compact ? 'mt-0.5 text-base font-semibold' : 'mt-1 text-xl font-semibold'">{{ insights.summary.won_with_idle_qualification }}</div>
            </article>
        </div>

        <ul
            v-if="insights.recommendations?.length"
            :class="compact
                ? 'mt-2 list-disc space-y-0.5 pl-4 text-[11px] leading-snug text-zinc-800 dark:text-zinc-200'
                : 'mt-3 list-disc space-y-1 pl-5 text-sm text-zinc-800 dark:text-zinc-200'"
        >
            <li v-for="(item, idx) in insights.recommendations" :key="idx">{{ item }}</li>
        </ul>
    </section>
</template>

<script setup>
defineProps({
    insights: {
        type: Object,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>
