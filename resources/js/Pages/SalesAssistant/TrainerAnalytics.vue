<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            :lead="filters.can_view_all ? 'Сводка по всем менеджерам. Фильтры по сотруднику, периоду и сценарию.' : 'Ваши тренировки за выбранный период.'"
            title="Аналитика тренажёра"
        />
        <section :class="`${crmPanel} space-y-3 p-6`">
            <p class="mt-4 text-sm">
                <Link
                    :href="route('sales-assistant.trainer')"
                    class="font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    ← К тренажёру
                </Link>
            </p>
        </section>

        <section :class="`${crmPanel} p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Фильтры</h2>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Период</span>
                    <select
                        v-model.number="localDays"
                        :class="`${crmField} min-w-[10rem]`"
                    >
                        <option :value="7">7 дней</option>
                        <option :value="30">30 дней</option>
                        <option :value="90">90 дней</option>
                        <option :value="180">180 дней</option>
                    </select>
                </label>
                <label v-if="filters.can_view_all" class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Менеджер</span>
                    <select
                        v-model="localUserId"
                        :class="`${crmField} min-w-[14rem]`"
                    >
                        <option value="">Все</option>
                        <option v-for="u in filterUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Профиль клиента</span>
                    <select
                        v-model="localProfileKey"
                        :class="`${crmField} min-w-[12rem]`"
                    >
                        <option value="">Все</option>
                        <option v-for="p in profile_options" :key="p.key" :value="p.key">{{ p.title }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Сценарий</span>
                    <select
                        v-model="localVersionId"
                        :class="`${crmField} min-w-[14rem] max-w-[20rem]`"
                    >
                        <option value="">Все</option>
                        <option v-for="v in version_options" :key="v.id" :value="String(v.id)">{{ v.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Исход</span>
                    <select
                        v-model="localOutcome"
                        :class="`${crmField} min-w-[11rem]`"
                    >
                        <option value="">Любой</option>
                        <option v-for="o in outcomeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium">Оценка тренировки</span>
                    <select
                        v-model="localDialogQuality"
                        :class="`${crmField} min-w-[12rem]`"
                    >
                        <option value="">Любая</option>
                        <option v-for="q in trainerDialogQualityOptions" :key="q.value" :value="q.value">{{ q.label }}</option>
                    </select>
                </label>
                <button
                    type="button"
                    class="rounded-xl border border-sky-800 bg-sky-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-800 dark:border-sky-500 dark:bg-sky-600 dark:hover:bg-sky-500"
                    @click="applyFilters"
                >
                    Применить
                </button>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                    Сессии ({{ summary.window_days }}д)
                </div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.total_sessions }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Завершено</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.completed_sessions }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Средний score</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ summary.avg_score }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Успех / КП / потеря</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ summary.won_sessions }} / {{ summary.quote_sessions }} / {{ summary.lost_sessions }}
                </div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:col-span-2 xl:col-span-1">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Оценки тренировки (успех / неудача / тупик)</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ summary.trainer_dialog_success }} / {{ summary.trainer_dialog_failure }} / {{ summary.trainer_dialog_stuck }}
                </div>
            </article>
        </section>

        <section
            v-if="coaching_insights?.available"
            class="border border-amber-200 bg-amber-50/80 p-4 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30 md:p-6"
        >
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-amber-800 dark:text-amber-300">
                Коучинг: зацикливание и тупики
            </h2>
            <p class="mt-2 text-xs text-amber-900/80 dark:text-amber-200/80">
                Автоанализ диалогов за {{ coaching_insights.period_days }} д.
                <span v-if="coaching_insights.scope === 'self'">Только ваши сессии.</span>
                <span v-else>Область: все менеджеры.</span>
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-lg border border-amber-200/80 bg-white/70 p-3 dark:border-amber-900/40 dark:bg-zinc-950/50">
                    <div class="text-xs uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">Тупики</div>
                    <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ coaching_insights.summary.stuck_sessions }}
                        <span class="text-sm font-normal text-zinc-500">({{ coaching_insights.summary.stuck_rate_pct }}%)</span>
                    </div>
                </article>
                <article class="rounded-lg border border-amber-200/80 bg-white/70 p-3 dark:border-amber-900/40 dark:bg-zinc-950/50">
                    <div class="text-xs uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">Зацикливание</div>
                    <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ coaching_insights.summary.loop_detected_sessions }}
                    </div>
                </article>
                <article class="rounded-lg border border-amber-200/80 bg-white/70 p-3 dark:border-amber-900/40 dark:bg-zinc-950/50">
                    <div class="text-xs uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">Неудачи</div>
                    <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ coaching_insights.summary.failure_sessions }}
                    </div>
                </article>
                <article class="rounded-lg border border-amber-200/80 bg-white/70 p-3 dark:border-amber-900/40 dark:bg-zinc-950/50">
                    <div class="text-xs uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">Негатив AI</div>
                    <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ coaching_insights.summary.negative_reaction_messages }}
                    </div>
                </article>
            </div>

            <div v-if="coachingRecommendations.length" class="mt-5">
                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-800 dark:text-amber-300">Рекомендации</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-zinc-800 dark:text-zinc-200">
                    <li v-for="(item, idx) in coachingRecommendations" :key="idx">{{ item }}</li>
                </ul>
            </div>

            <div v-if="loopReasonEntries.length" class="mt-5 grid gap-6 lg:grid-cols-2">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Причины зацикливания</h3>
                    <ul class="mt-2 space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in loopReasonEntries" :key="row.reason">
                            {{ loopReasonLabel(row.reason) }} — {{ row.count }}
                        </li>
                    </ul>
                </div>
                <div v-if="coaching_insights.hotspots_by_profile?.length">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Hotspots по профилю</h3>
                    <ul class="mt-2 space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in coaching_insights.hotspots_by_profile" :key="row.profile_key ?? row.profile_title">
                            {{ row.profile_title }}: {{ row.stuck }}/{{ row.total }} ({{ row.stuck_rate_pct }}%)
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="coaching_insights.sample_problem_sessions?.length" class="mt-5 overflow-x-auto">
                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Примеры проблемных сессий</h3>
                <table class="mt-2 min-w-[40rem] w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-amber-200/80 text-xs uppercase text-zinc-500 dark:border-amber-900/40 dark:text-zinc-400">
                            <th class="pb-2 pr-2 font-medium">Дата</th>
                            <th class="pb-2 pr-2 font-medium">Профиль</th>
                            <th class="pb-2 pr-2 font-medium">Сценарий</th>
                            <th class="pb-2 pr-2 font-medium">Причины</th>
                            <th class="pb-2 font-medium">Сообщений</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in coaching_insights.sample_problem_sessions"
                            :key="s.session_id"
                            class="border-b border-amber-100/80 dark:border-amber-900/30"
                        >
                            <td class="whitespace-nowrap py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ formatDate(s.created_at) }}</td>
                            <td class="max-w-[10rem] truncate py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ s.trainer_profile_title ?? '—' }}</td>
                            <td class="max-w-[12rem] truncate py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ s.script_label ?? '—' }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ (s.loop_reasons ?? []).map(loopReasonLabel).join(', ') || '—' }}
                            </td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ s.message_count ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="feedback_digest?.available"
            class="border border-sky-200 bg-sky-50/80 p-4 shadow-sm dark:border-sky-900/60 dark:bg-sky-950/30 md:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-sky-800 dark:text-sky-300">
                        Что улучшить в сценариях
                    </h2>
                    <p class="mt-2 text-xs text-sky-900/80 dark:text-sky-200/80">
                        Сводка по оценкам ассистента, тупикам, живым возражениям и незаполненным полям за {{ feedback_digest.period_days }} д.
                    </p>
                </div>
                <div class="text-right text-xs text-sky-900/70 dark:text-sky-200/70">
                    <div>Сессий: {{ feedback_digest.summary.total_sessions }}</div>
                    <div>Тупики/неудачи: {{ feedback_digest.summary.stuck_or_failure_sessions }} ({{ feedback_digest.summary.stuck_or_failure_rate_pct }}%)</div>
                    <div>Негативных реплик: {{ feedback_digest.summary.negative_messages }}</div>
                </div>
            </div>

            <div v-if="feedbackRecommendations.length" class="mt-4 rounded-xl border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-950/50">
                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-800 dark:text-sky-300">Рекомендации редактору</h3>
                <ul class="mt-2 space-y-1 text-sm text-zinc-800 dark:text-zinc-200">
                    <li v-for="(item, idx) in feedbackRecommendations" :key="idx">
                        {{ item }}
                    </li>
                </ul>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div v-if="feedback_digest.script_hotspots?.length" class="rounded-xl border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-950/50">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Проблемные сценарии</h3>
                    <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in feedback_digest.script_hotspots" :key="row.version_id">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.script_label }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                тупики/неудачи: {{ row.stuck_or_failure }}/{{ row.total }}, негатив: {{ row.negative_messages }}, score: {{ row.avg_score }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div v-if="feedback_digest.node_hotspots?.length" class="rounded-xl border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-950/50">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Шаги, которые надо переписать</h3>
                    <ul class="mt-2 space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in feedback_digest.node_hotspots" :key="row.sales_script_node_id ?? row.step_key">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.node_label }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                {{ row.script_label }} · сигналов: {{ row.signals }}, негатив: {{ row.negative_messages }}
                            </span>
                            <span v-if="row.node_excerpt" class="mt-1 block text-xs text-zinc-600 dark:text-zinc-300">
                                {{ row.node_excerpt }}
                            </span>
                            <span v-if="row.top_tags?.length" class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="tag in row.top_tags"
                                    :key="tag.tag"
                                    class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] text-sky-800 dark:bg-sky-900/60 dark:text-sky-100"
                                >
                                    {{ tag.label }} · {{ tag.count }}
                                </span>
                            </span>
                        </li>
                    </ul>
                </div>

                <div v-if="feedback_digest.feedback_tag_hotspots?.length" class="rounded-xl border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-950/50">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Причины оценок</h3>
                    <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in feedback_digest.feedback_tag_hotspots" :key="row.tag" class="flex items-center justify-between gap-3">
                            <span>{{ row.label }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.total }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="feedback_digest.profile_hotspots?.length" class="rounded-xl border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-950/50">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Профили, где диалог застревает</h3>
                    <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in feedback_digest.profile_hotspots" :key="row.profile_key ?? row.profile_title">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.profile_title }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                {{ row.stuck_or_failure }}/{{ row.total }} ({{ row.stuck_or_failure_rate_pct }}%)
                            </span>
                        </li>
                    </ul>
                </div>

                <div v-if="feedback_digest.live_objections?.length" class="rounded-xl border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-950/50">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Живые возражения</h3>
                    <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in feedback_digest.live_objections" :key="row.reaction_class_id">
                            {{ row.label }} — {{ row.total }}
                        </li>
                    </ul>
                </div>

                <div v-if="feedback_digest.missing_fields?.length" class="rounded-xl border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-950/50">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Что не фиксируют в разговоре</h3>
                    <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <li v-for="row in feedback_digest.missing_fields" :key="row.code">
                            {{ row.label }} — {{ row.missing }}
                        </li>
                    </ul>
                </div>
            </div>

            <details v-if="feedback_digest.limitations?.length" class="mt-4 text-xs text-sky-900/70 dark:text-sky-200/70">
                <summary class="cursor-pointer font-medium">Ограничения текущего контура обучения</summary>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="item in feedback_digest.limitations" :key="item">{{ item }}</li>
                </ul>
            </details>
        </section>

        <section v-if="daily.length" class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:p-6">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По дням</h2>
            <div class="mt-6 flex h-40 items-end gap-0.5 overflow-x-auto pb-2">
                <div
                    v-for="row in daily"
                    :key="row.date"
                    class="flex min-w-[10px] flex-1 flex-col items-center justify-end gap-1"
                    :title="`${row.date}: ${row.total} сессий, score ${row.avg_score}`"
                >
                    <div
                        class="w-full rounded-t bg-sky-500/90 dark:bg-sky-400/80"
                        :style="{ height: `${Math.max(4, (row.total / maxDailyTotal) * 100)}%` }"
                    />
                </div>
            </div>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Высота столбца — число сессий за день.</p>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:p-6">
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По рубрикам</h2>
                <div v-if="by_rubric.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Нет данных.</div>
                <table v-else class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="pb-2 pr-2 font-medium">Рубрика</th>
                            <th class="pb-2 pr-2 font-medium">Сессий</th>
                            <th class="pb-2 pr-2 font-medium">Завершено</th>
                            <th class="pb-2 font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in by_rubric"
                            :key="row.key"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">{{ row.label }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.total }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.completed }}</td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ row.avg_score }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:p-6">
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По профилю клиента</h2>
                <div v-if="by_profile.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Нет данных.</div>
                <table v-else class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="pb-2 pr-2 font-medium">Профиль</th>
                            <th class="pb-2 pr-2 font-medium">Сессий</th>
                            <th class="pb-2 font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in by_profile"
                            :key="row.profile_key ?? row.profile_title"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">{{ row.profile_title }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.total }}</td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ row.avg_score }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="filters.can_view_all"
                class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 md:p-6"
            >
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">По менеджерам</h2>
                <div v-if="by_user.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Нет данных.</div>
                <table v-else class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="pb-2 pr-2 font-medium">Менеджер</th>
                            <th class="pb-2 pr-2 font-medium">Сессий</th>
                            <th class="pb-2 pr-2 font-medium">Завершено</th>
                            <th class="pb-2 font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in by_user"
                            :key="row.user_id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">{{ row.name }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.total }}</td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">{{ row.completed }}</td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ row.avg_score }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section :class="`${crmPanel} p-4 md:p-6`">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Последние сессии</h2>
            <div v-if="recent_sessions.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Нет данных.</div>
            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-[54rem] w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th v-if="filters.can_view_all" class="pb-2 pr-2 font-medium">Менеджер</th>
                            <th class="pb-2 pr-2 font-medium">Дата</th>
                            <th class="pb-2 pr-2 font-medium">Профиль</th>
                            <th class="pb-2 pr-2 font-medium">Рубрика</th>
                            <th class="pb-2 pr-2 font-medium">Сценарий</th>
                            <th class="pb-2 pr-2 font-medium">Исход</th>
                            <th class="pb-2 pr-2 font-medium">Тренировка</th>
                            <th class="pb-2 font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in recent_sessions"
                            :key="s.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td v-if="filters.can_view_all" class="py-2 pr-2 text-zinc-900 dark:text-zinc-100">
                                {{ s.user_name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ formatDate(s.created_at) }}
                            </td>
                            <td class="max-w-[12rem] truncate py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ s.trainer_profile_title ?? '—' }}
                            </td>
                            <td class="max-w-[10rem] truncate py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ s.trainer_rubric_label ?? '—' }}
                            </td>
                            <td class="max-w-[14rem] truncate py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ s.script_label }}
                            </td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ outcomeLabel(s.outcome) }}
                            </td>
                            <td class="py-2 pr-2 text-zinc-600 dark:text-zinc-300">
                                {{ trainerDialogQualityLabel(s.trainer_dialog_quality) }}
                            </td>
                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ s.trainer_score ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmField, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'reports', activeSubKey: 'sales-assistant-trainer-analytics' }, () => page),
});

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    outcomeOptions: {
        type: Array,
        default: () => [],
    },
    trainerDialogQualityOptions: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
    daily: {
        type: Array,
        default: () => [],
    },
    by_profile: {
        type: Array,
        default: () => [],
    },
    by_rubric: {
        type: Array,
        default: () => [],
    },
    by_user: {
        type: Array,
        default: () => [],
    },
    recent_sessions: {
        type: Array,
        default: () => [],
    },
    filterUsers: {
        type: Array,
        default: () => [],
    },
    profile_options: {
        type: Array,
        default: () => [],
    },
    version_options: {
        type: Array,
        default: () => [],
    },
    coaching_insights: {
        type: Object,
        default: null,
    },
    feedback_digest: {
        type: Object,
        default: null,
    },
});

const localDays = ref(props.filters.days);
const localUserId = ref(props.filters.user_id != null ? String(props.filters.user_id) : '');
const localProfileKey = ref(props.filters.trainer_profile_key ?? '');
const localVersionId = ref(
    props.filters.sales_script_version_id != null ? String(props.filters.sales_script_version_id) : '',
);
const localOutcome = ref(props.filters.outcome ?? '');
const localDialogQuality = ref(props.filters.trainer_dialog_quality ?? '');

watch(
    () => props.filters,
    (f) => {
        localDays.value = f.days;
        localUserId.value = f.user_id != null ? String(f.user_id) : '';
        localProfileKey.value = f.trainer_profile_key ?? '';
        localVersionId.value = f.sales_script_version_id != null ? String(f.sales_script_version_id) : '';
        localOutcome.value = f.outcome ?? '';
        localDialogQuality.value = f.trainer_dialog_quality ?? '';
    },
    { deep: true },
);

const maxDailyTotal = computed(() => Math.max(1, ...props.daily.map((d) => d.total)));

const coachingRecommendations = computed(() => props.coaching_insights?.recommendations ?? []);

const feedback_digest = computed(() => props.feedback_digest ?? null);

const feedbackRecommendations = computed(() => props.feedback_digest?.recommendations ?? []);

const loopReasonEntries = computed(() => {
    const counts = props.coaching_insights?.loop_reason_counts ?? {};

    return Object.entries(counts)
        .map(([reason, count]) => ({ reason, count: Number(count) }))
        .sort((a, b) => b.count - a.count);
});

function loopReasonLabel(reason) {
    const labels = {
        assistant_repeated_reply: 'AI повторяет реплики',
        user_repeated_question: 'Менеджер повторяет вопрос',
        assistant_quality_drop: 'Падение качества AI',
    };

    return labels[reason] ?? reason;
}

function outcomeLabel(value) {
    if (value == null || value === '') {
        return '—';
    }
    const row = props.outcomeOptions.find((o) => o.value === value);

    return row?.label ?? value;
}

function trainerDialogQualityLabel(value) {
    if (value == null || value === '') {
        return '—';
    }
    const row = props.trainerDialogQualityOptions.find((o) => o.value === value);

    return row?.label ?? value;
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function applyFilters() {
    const q = { days: localDays.value };
    if (props.filters.can_view_all && localUserId.value) {
        q.user_id = localUserId.value;
    }
    if (localProfileKey.value) {
        q.trainer_profile_key = localProfileKey.value;
    }
    if (localVersionId.value) {
        q.sales_script_version_id = localVersionId.value;
    }
    if (localOutcome.value) {
        q.outcome = localOutcome.value;
    }
    if (localDialogQuality.value) {
        q.trainer_dialog_quality = localDialogQuality.value;
    }

    router.get(route('sales-assistant.trainer.analytics'), q, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>
