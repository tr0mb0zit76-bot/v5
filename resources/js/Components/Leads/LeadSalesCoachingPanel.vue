<template>
    <section
        v-if="insights?.available"
        class="border border-violet-200 bg-violet-50/70 p-4 shadow-sm dark:border-violet-900/50 dark:bg-violet-950/20 md:p-5"
    >
        <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-violet-800 dark:text-violet-300">
            Outcome Intelligence
        </h2>
        <p class="mt-1 text-xs text-violet-900/80 dark:text-violet-200/80">
            Коучинг по закрытым лидам за {{ insights.period_days }} д. Простой на этапе без событий в ленте ≠ качественная подготовка.
        </p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-lg border border-violet-200/80 bg-white/80 p-3 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Win rate</div>
                <div class="mt-1 text-xl font-semibold">{{ insights.summary.win_rate_pct }}%</div>
                <div class="text-xs text-zinc-500">{{ insights.summary.won_leads }} / {{ insights.summary.closed_leads }}</div>
            </article>
            <article class="rounded-lg border border-violet-200/80 bg-white/80 p-3 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Lost без ЛПР</div>
                <div class="mt-1 text-xl font-semibold">{{ insights.summary.lost_without_authority }}</div>
            </article>
            <article class="rounded-lg border border-violet-200/80 bg-white/80 p-3 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Idle на квалификации (lost)</div>
                <div class="mt-1 text-xl font-semibold">{{ insights.summary.lost_with_idle_qualification }}</div>
            </article>
            <article class="rounded-lg border border-violet-200/80 bg-white/80 p-3 dark:border-violet-900/40 dark:bg-zinc-950/50">
                <div class="text-xs uppercase tracking-[0.12em] text-zinc-500">Idle на квалификации (won)</div>
                <div class="mt-1 text-xl font-semibold">{{ insights.summary.won_with_idle_qualification }}</div>
            </article>
        </div>

        <ul v-if="insights.recommendations?.length" class="mt-4 list-disc space-y-1 pl-5 text-sm text-zinc-800 dark:text-zinc-200">
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
