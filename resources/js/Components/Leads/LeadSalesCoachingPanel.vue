<template>
    <section
        v-if="insights?.available"
        class="border border-violet-200 bg-violet-50/70 p-3 shadow-sm dark:border-violet-900/50 dark:bg-violet-950/20 md:p-4"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-800 dark:text-violet-300">
                Outcome Intelligence
            </h2>
            <p class="text-xs text-violet-900/80 dark:text-violet-200/80">
                Коучинг за {{ insights.period_days }} д.
            </p>
        </div>

        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-lg border border-violet-200/80 bg-white/80 px-3 py-2 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Win rate</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-xl font-semibold">{{ insights.summary.win_rate_pct }}%</span>
                    <span class="text-xs text-zinc-500">
                        выиграно {{ insights.summary.won_leads }} из {{ insights.summary.closed_leads }} закрытых
                    </span>
                </div>
            </article>
            <article class="rounded-lg border border-violet-200/80 bg-white/80 px-3 py-2 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Lost без ЛПР</div>
                <div class="mt-1 text-xl font-semibold">{{ insights.summary.lost_without_authority }}</div>
            </article>
            <article class="rounded-lg border border-violet-200/80 bg-white/80 px-3 py-2 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Idle на квалификации (lost)</div>
                <div class="mt-1 text-xl font-semibold">{{ insights.summary.lost_with_idle_qualification }}</div>
            </article>
            <article class="rounded-lg border border-violet-200/80 bg-white/80 px-3 py-2 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Idle на квалификации (won)</div>
                <div class="mt-1 text-xl font-semibold">{{ insights.summary.won_with_idle_qualification }}</div>
            </article>
        </div>

        <ul v-if="insights.recommendations?.length" class="mt-3 list-disc space-y-1 pl-5 text-sm text-zinc-800 dark:text-zinc-200">
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
});
</script>
