<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <div class="shrink-0 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 :class="crmPageTitle">Бюджетирование</h1>
                <p :class="crmPageLead">
                    Задаёте цели <strong class="font-medium">или</strong> маржу менеджера — график показывает оба ответа сразу.
                </p>
            </div>
            <div :class="crmSegmented">
                <button
                    type="button"
                    :class="localInputs.calculation_mode === MODE_TOP_DOWN ? crmSegmentedBtnActive : crmSegmentedBtn"
                    @click="setMode(MODE_TOP_DOWN)"
                >
                    Сверху вниз
                </button>
                <button
                    type="button"
                    :class="localInputs.calculation_mode === MODE_BOTTOM_UP ? crmSegmentedBtnActive : crmSegmentedBtn"
                    @click="setMode(MODE_BOTTOM_UP)"
                >
                    Снизу вверх
                </button>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(300px,380px)_1fr]">
            <aside class="space-y-4">
                <section :class="`${crmPanel} p-4`">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">План по статьям</h2>
                        <Link
                            :href="management_accounting_categories_url"
                            class="text-xs font-medium text-sky-700 hover:underline dark:text-sky-300"
                        >
                            Статьи учёта →
                        </Link>
                    </div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Названия статей — в «Управленческом учёте». Здесь только план: фикс ₽/мес, % маржи или «первые N мес.».
                    </p>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="article in localOpexArticles"
                            :key="article.id ?? article._localKey"
                            class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
                        >
                            <div class="font-medium text-sm text-zinc-800 dark:text-zinc-100">
                                {{ article.name }}
                            </div>
                            <label class="mt-2 block space-y-0.5">
                                <span class="text-[10px] uppercase text-zinc-500">Тип</span>
                                <select
                                    v-model="article.cost_type"
                                    class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                    @change="persistOpexArticle(article)"
                                >
                                    <option value="fixed_monthly">Фиксированная сумма</option>
                                    <option value="percent_of_margin">% от маржи</option>
                                </select>
                            </label>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <label v-if="article.cost_type !== 'percent_of_margin'" class="space-y-0.5">
                                    <span class="text-[10px] uppercase text-zinc-500">₽/мес</span>
                                    <input
                                        v-model.number="article.amount_monthly"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                                        @change="persistOpexArticle(article)"
                                    >
                                </label>
                                <label v-else class="space-y-0.5">
                                    <span class="text-[10px] uppercase text-zinc-500">% маржи</span>
                                    <input
                                        v-model.number="article.percent_of_margin"
                                        type="number"
                                        min="0"
                                        max="99"
                                        step="0.5"
                                        class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                                        @change="persistOpexArticle(article)"
                                    >
                                </label>
                                <label class="space-y-0.5">
                                    <span class="text-[10px] uppercase text-zinc-500">Первые N мес.</span>
                                    <input
                                        v-model.number="article.ramp_months"
                                        type="number"
                                        min="1"
                                        max="36"
                                        placeholder="всегда"
                                        class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                        @change="persistOpexArticle(article)"
                                    >
                                </label>
                            </div>
                        </div>
                        <p v-if="localOpexArticles.length === 0" class="text-xs text-zinc-500">
                            Нет статей с планом. Добавьте их в
                            <Link :href="management_accounting_categories_url" class="text-sky-700 hover:underline dark:text-sky-300">«Статьи учёта»</Link>.
                        </p>
                    </div>
                </section>

                <section
                    v-if="companyPlanningInitiatives.length > 0"
                    :class="`${crmPanel} p-4`"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Инициативы компании</h2>
                        <Link
                            v-if="companyPlanningIndexUrl"
                            :href="companyPlanningIndexUrl"
                            class="text-xs font-medium text-sky-700 hover:underline dark:text-sky-300"
                        >
                            План компании →
                        </Link>
                    </div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Инициативы, привязанные к статьям управленческого учёта — факт по ним виден в карточке инициативы.
                    </p>
                    <div class="mt-3 space-y-2">
                        <Link
                            v-for="initiative in companyPlanningInitiatives"
                            :key="initiative.id"
                            :href="initiative.show_url"
                            class="block rounded-xl border border-zinc-200 px-3 py-2 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-900/60"
                        >
                            <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                {{ initiative.title }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ initiative.expense_category_name || 'Статья не указана' }}
                                · {{ initiative.status_label }}
                                <span v-if="initiative.planned_budget_amount !== null">
                                    · план {{ formatInitiativeBudget(initiative) }}
                                </span>
                            </div>
                        </Link>
                    </div>
                </section>

                <section v-if="can_freeze_plan" :class="`${crmPanel} p-4`">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Зафиксировать план</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Снимок для сравнения план/факт в управленческом учёте. Черновик бюджета после фиксации не меняет отчёт.
                    </p>
                    <div class="mt-3 space-y-2">
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Название периода</span>
                            <input
                                v-model="freezeForm.period_label"
                                type="text"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">С</span>
                                <input
                                    v-model="freezeForm.period_start"
                                    type="date"
                                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                >
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">По</span>
                                <input
                                    v-model="freezeForm.period_end"
                                    type="date"
                                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                >
                            </label>
                        </div>
                        <button
                            type="button"
                            class="w-full rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50 dark:bg-sky-500"
                            :disabled="freezeForm.processing"
                            @click="freezePlan"
                        >
                            {{ freezeForm.processing ? 'Фиксация…' : 'Зафиксировать план' }}
                        </button>
                    </div>
                    <ul v-if="plan_snapshots.length > 0" class="mt-3 space-y-1 text-xs text-zinc-500">
                        <li v-for="snapshot in plan_snapshots" :key="snapshot.id">
                            {{ snapshot.period_label }} · {{ formatSnapshotDate(snapshot.approved_at) }}
                        </li>
                    </ul>
                </section>

                <section :class="`${crmPanel} p-4`">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Команда и вливание</h2>
                    <div class="mt-3 space-y-3">
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Число менеджеров</span>
                            <input
                                v-model.number="localInputs.manager_count"
                                type="number"
                                min="1"
                                max="100"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Вливание собственника, ₽</span>
                            <input
                                v-model.number="localInputs.owner_investment"
                                type="number"
                                min="0"
                                step="10000"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                    </div>
                </section>

                <section
                    v-if="localInputs.calculation_mode === MODE_BOTTOM_UP"
                    class="rounded-2xl border border-sky-200 bg-sky-50/50 p-4 dark:border-sky-900/50 dark:bg-sky-950/20"
                >
                    <h2 class="text-sm font-semibold text-sky-900 dark:text-sky-200">Маржа менеджера (ввод)</h2>
                    <div class="mt-3 space-y-3">
                        <label class="flex items-start gap-2 text-sm">
                            <input
                                v-model="localInputs.use_db_margin_per_manager"
                                type="checkbox"
                                class="mt-1 rounded border-zinc-300"
                            >
                            <span>Из CRM за {{ db_benchmark.period_months }} мес.
                                <template v-if="db_benchmark.margin_per_manager_avg">
                                    ({{ formatMoney(db_benchmark.margin_per_manager_avg) }}/чел)
                                </template>
                                <template v-else>— нет данных</template>
                            </span>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Средняя маржа на менеджера, ₽/мес</span>
                            <input
                                v-model.number="localInputs.margin_per_manager"
                                type="number"
                                min="0"
                                step="5000"
                                :disabled="localInputs.use_db_margin_per_manager"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm tabular-nums disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                        <p v-if="db_benchmark.company_margin_monthly_avg" class="text-xs text-zinc-600 dark:text-zinc-400">
                            Средняя маржа компании из базы: {{ formatMoney(db_benchmark.company_margin_monthly_avg) }}/мес
                            ({{ db_benchmark.active_managers_count }} менеджеров с заказами).
                        </p>
                    </div>
                </section>

                <section :class="`${crmPanel} p-4`">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ localInputs.calculation_mode === MODE_TOP_DOWN ? 'Цели (сверху вниз)' : 'Хотелки' }}
                    </h2>
                    <div class="mt-3 space-y-3">
                        <label v-if="localInputs.calculation_mode === MODE_TOP_DOWN" class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">План: ноль за месяц, с мес.</span>
                            <input
                                v-model.number="localInputs.breakeven_month"
                                type="number"
                                min="1"
                                :max="localInputs.horizon_months"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            >
                            <p class="text-[11px] leading-snug text-zinc-500 dark:text-zinc-400">
                                К месяцу {{ localInputs.breakeven_month }} нужна маржа компании
                                {{ formatMoney(marginAtPlanMonth) }}
                                ({{ formatMoney(marginAtPlanMonth / localInputs.manager_count) }} на менеджера), чтобы поток за месяц ≈ 0.
                                <template v-if="percentRateAtPlanMonth > 0">
                                    Учтён % от маржи: {{ (percentRateAtPlanMonth * 100).toFixed(1) }}%.
                                </template>
                            </p>
                        </label>
                        <label v-if="localInputs.calculation_mode === MODE_TOP_DOWN" class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Нулевой денежный поток, мес.</span>
                            <input
                                v-model.number="localInputs.cash_zero_month"
                                type="number"
                                min="1"
                                :max="localInputs.horizon_months"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            >
                            <p class="text-[11px] leading-snug text-zinc-500 dark:text-zinc-400">
                                К месяцу {{ localInputs.cash_zero_month }} накопленный остаток ≥ 0 — маржа компании
                                {{ formatMoney(marginAtCashZeroMonth) }}
                                ({{ formatMoney(marginAtCashZeroMonth / localInputs.manager_count) }} / менеджер).
                                Рампа маржи только растёт: операционный ноль → касса → дивиденды.
                            </p>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Горизонт, мес</span>
                            <input
                                v-model.number="localInputs.horizon_months"
                                type="number"
                                min="6"
                                max="36"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Дивиденды с мес</span>
                            <input
                                v-model.number="localInputs.target_dividends_month"
                                type="number"
                                min="1"
                                :max="localInputs.horizon_months"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Дивиденды, ₽/мес</span>
                            <input
                                v-model.number="localInputs.target_dividends_amount"
                                type="number"
                                min="0"
                                step="10000"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </label>
                    </div>
                </section>

                <button
                    type="button"
                    :class="`${crmBtnCreate} w-full justify-center`"
                    :disabled="saveForm.processing"
                    @click="saveScenario"
                >
                    {{ saveForm.processing ? 'Сохранение…' : 'Сохранить параметры' }}
                </button>
            </aside>

            <div class="flex min-h-0 flex-col gap-4">
                <section :class="`${crmPanel} overflow-hidden border-2 border-sky-200 dark:border-sky-800`">
                    <div class="border-b border-sky-100 bg-sky-50 px-4 py-3 dark:border-sky-900 dark:bg-sky-950/40">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">План маржи на одного менеджера</h2>
                        <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">
                            План на каждого из {{ plan.manager_plan.manager_count }} менеджеров.
                            <strong class="font-medium">Безубыточность</strong> — накопленный остаток (с вливанием) ≥ 0;
                            <strong class="font-medium">ноль за месяц</strong> — поток за месяц ≥ 0, касса может быть ещё в минусе.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-400">
                                <tr>
                                    <th class="px-4 py-3">Месяц</th>
                                    <th class="px-4 py-3 text-right">Маржа / менеджер</th>
                                    <th class="px-4 py-3 text-right">Маржа компании</th>
                                    <th class="px-4 py-3 text-right">Поток за месяц</th>
                                    <th class="px-4 py-3 text-right">Накоплено</th>
                                    <th class="px-4 py-3">Отметка</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                <tr
                                    v-for="row in plan.manager_plan.rows"
                                    :key="`mp-${row.month}`"
                                    :class="managerRowClass(row)"
                                >
                                    <td class="px-4 py-2.5 font-medium">М{{ row.month }}</td>
                                    <td class="px-4 py-2.5 text-right text-base font-semibold tabular-nums text-sky-800 dark:text-sky-200">
                                        {{ formatMoney(row.margin_per_manager) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-zinc-700 dark:text-zinc-300">
                                        {{ formatMoney(row.margin_company) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right tabular-nums"
                                        :class="row.net_company >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                                    >
                                        {{ formatMoney(row.net_company) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right tabular-nums font-medium"
                                        :class="row.cumulative >= 0 ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-300'"
                                    >
                                        {{ formatMoney(row.cumulative) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-zinc-500">
                                        <span v-if="row.tags.includes('cash_be')" class="rounded bg-emerald-100 px-2 py-0.5 font-medium text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">Безубыточность</span>
                                        <span v-if="row.tags.includes('operating_be')" class="ml-1 rounded bg-amber-100 px-2 py-0.5 text-amber-900 dark:bg-amber-950 dark:text-amber-200">Ноль за месяц</span>
                                        <span v-if="row.tags.includes('plan_milestone')" class="ml-1 rounded bg-sky-100 px-2 py-0.5 text-sky-900 dark:bg-sky-950 dark:text-sky-200">План: покрытие OPEX</span>
                                        <span v-if="row.tags.includes('target')" class="ml-1 rounded bg-violet-100 px-2 py-0.5 text-violet-900 dark:bg-violet-950 dark:text-violet-200">Цель / дивиденды</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section :class="`${crmPanel} overflow-hidden`">
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-3 px-5 py-4 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-950/40"
                        @click="summaryPanelOpen = !summaryPanelOpen"
                    >
                        <div class="min-w-0 space-y-1">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Сводка и оценка сценария</h2>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                Ключевые сроки, целевая маржа и общий вывод по текущим вводным.
                            </p>
                        </div>
                        <ChevronDown
                            class="mt-1 h-5 w-5 shrink-0 text-zinc-500 transition-transform"
                            :class="summaryPanelOpen ? 'rotate-180' : ''"
                        />
                    </button>

                    <div v-show="summaryPanelOpen" class="space-y-4 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div
                            class="rounded-2xl border px-4 py-3 text-sm leading-relaxed"
                            :class="scenarioAssessment.tone === 'warning'
                                ? 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100'
                                : scenarioAssessment.tone === 'positive'
                                    ? 'border-emerald-200 bg-emerald-50/70 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100'
                                    : 'border-zinc-200 bg-zinc-50 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-200'"
                        >
                            <p>{{ scenarioAssessment.situation }}</p>
                            <p v-if="scenarioAssessment.recommendation" class="mt-2 font-medium">
                                Рекомендуется: {{ scenarioAssessment.recommendation }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="card in summaryCards"
                                :key="card.key"
                                class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-950/30"
                            >
                                <div class="text-xs font-medium uppercase leading-snug tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ card.label }}
                                </div>
                                <div class="mt-2 text-right text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                                    {{ card.value }}
                                </div>
                                <p v-if="card.hint" class="mt-2 text-xs leading-snug text-zinc-500 dark:text-zinc-400">
                                    {{ card.hint }}
                                </p>
                            </article>
                        </div>
                    </div>
                </section>

                <section :class="`${crmPanel} overflow-hidden`">
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-3 px-5 py-4 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-950/40"
                        @click="dualChartOpen = !dualChartOpen"
                    >
                        <div class="min-w-0 space-y-1">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Сводный график: два направления расчёта</h2>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                Один сценарий, два ответа. Линия, которую вы редактируете сейчас, — сплошная; вторая — проверка «а что если».
                            </p>
                        </div>
                        <ChevronDown
                            class="mt-1 h-5 w-5 shrink-0 text-zinc-500 transition-transform"
                            :class="dualChartOpen ? 'rotate-180' : ''"
                        />
                    </button>

                    <div v-show="dualChartOpen" class="border-t border-zinc-200 px-5 pb-5 pt-4 dark:border-zinc-800">
                    <div class="grid gap-3 lg:grid-cols-2">
                        <article
                            class="rounded-xl border-l-4 p-4"
                            :class="localInputs.calculation_mode === MODE_TOP_DOWN
                                ? 'border-sky-500 bg-sky-50/80 dark:bg-sky-950/30'
                                : 'border-zinc-300 bg-zinc-50/50 dark:border-zinc-600 dark:bg-zinc-950/40'"
                        >
                            <div class="text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200">
                                Цели → маржа (сверху вниз)
                            </div>
                            <ul class="mt-2 space-y-1.5 text-sm text-zinc-800 dark:text-zinc-200">
                                <li>
                                    К <strong>мес. {{ localInputs.breakeven_month }}</strong> «ноль за месяц»
                                    → <strong>{{ formatMoney(dualRead.goalsToMargin.floorPerManager) }}</strong> / менеджер.
                                </li>
                                <li>
                                    К <strong>мес. {{ localInputs.cash_zero_month }}</strong> нулевой денежный поток
                                    → <strong>{{ formatMoney(dualRead.goalsToMargin.cashPerManager) }}</strong> / менеджер.
                                </li>
                                <li>
                                    Хотите к <strong>мес. {{ localInputs.target_dividends_month }}</strong>
                                    дивиденды <strong>{{ formatMoney(localInputs.target_dividends_amount) }}</strong>
                                    → нужно <strong>{{ formatMoney(dualRead.goalsToMargin.targetPerManager) }}</strong> / менеджер (X).
                                </li>
                            </ul>
                        </article>

                        <article
                            class="rounded-xl border-l-4 p-4"
                            :class="localInputs.calculation_mode === MODE_BOTTOM_UP
                                ? 'border-amber-500 bg-amber-50/80 dark:bg-amber-950/30'
                                : 'border-zinc-300 bg-zinc-50/50 dark:border-zinc-600 dark:bg-zinc-950/40'"
                        >
                            <div class="text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">
                                Маржа → сроки (снизу вверх)
                            </div>
                            <p class="mt-2 text-sm text-zinc-800 dark:text-zinc-200">
                                Если каждый стабильно даёт
                                <strong>{{ formatMoney(dualRead.marginLevel) }}</strong> / мес:
                            </p>
                            <ul class="mt-1.5 space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                                <li>«Ноль за месяц» — <strong>мес. {{ dualRead.marginToTimeline.operatingMonth ?? '—' }}</strong></li>
                                <li>Дивиденды {{ formatMoney(localInputs.target_dividends_amount) }} — <strong>мес. {{ dualRead.marginToTimeline.dividendsMonth ?? '—' }}</strong></li>
                                <li>Безубыточность (касса) — <strong>мес. {{ dualRead.marginToTimeline.cashMonth ?? '—' }}</strong></li>
                            </ul>
                        </article>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-4 text-xs text-zinc-600 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-2">
                            <span
                                class="h-1 w-6 rounded"
                                :class="localInputs.calculation_mode === MODE_TOP_DOWN ? 'bg-sky-600' : 'border border-dashed border-sky-600 bg-transparent'"
                            />
                            Рампа к целям
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span
                                class="h-1 w-6 rounded"
                                :class="localInputs.calculation_mode === MODE_BOTTOM_UP ? 'bg-amber-500' : 'border border-dashed border-amber-500 bg-transparent'"
                            />
                            Стабильная маржа {{ formatManagerShort(dualRead.marginLevel) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-violet-700 dark:text-violet-300">
                            │ мес. {{ localInputs.target_dividends_month }} — дивиденды
                        </span>
                    </div>

                    <div class="mt-3 h-80">
                        <svg
                            class="h-full w-full"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            preserveAspectRatio="xMidYMid meet"
                        >
                            <line
                                v-for="tick in managerYTicks"
                                :key="`dual-grid-${tick}`"
                                :x1="chartPad.left"
                                :x2="chartWidth - chartPad.right"
                                :y1="yScaleManager(tick)"
                                :y2="yScaleManager(tick)"
                                class="stroke-zinc-200 dark:stroke-zinc-700"
                                stroke-dasharray="4 4"
                            />

                            <line
                                :x1="barX(localInputs.target_dividends_month)"
                                :x2="barX(localInputs.target_dividends_month)"
                                :y1="chartPad.top"
                                :y2="chartHeight - chartPad.bottom"
                                class="stroke-violet-500/60"
                                stroke-width="1.5"
                                stroke-dasharray="5 4"
                            />

                            <polyline
                                v-if="goalsRampPolyline"
                                :points="goalsRampPolyline"
                                fill="none"
                                :class="localInputs.calculation_mode === MODE_TOP_DOWN
                                    ? 'stroke-sky-600 dark:stroke-sky-400'
                                    : 'stroke-sky-500/50'"
                                stroke-width="3"
                                :stroke-dasharray="localInputs.calculation_mode === MODE_TOP_DOWN ? undefined : '8 5'"
                                stroke-linejoin="round"
                            />

                            <polyline
                                v-if="flatMarginPolyline"
                                :points="flatMarginPolyline"
                                fill="none"
                                :class="localInputs.calculation_mode === MODE_BOTTOM_UP
                                    ? 'stroke-amber-500'
                                    : 'stroke-amber-500/55'"
                                stroke-width="2.5"
                                :stroke-dasharray="localInputs.calculation_mode === MODE_BOTTOM_UP ? undefined : '6 4'"
                                stroke-linejoin="round"
                            />

                            <g v-for="point in planFromGoals.months" :key="`goal-dot-${point.month}`">
                                <circle
                                    v-if="point.month <= localInputs.target_dividends_month"
                                    :cx="barX(point.month)"
                                    :cy="yScaleManager(point.margin_per_manager)"
                                    r="3.5"
                                    class="fill-sky-600/90 dark:fill-sky-400"
                                />
                            </g>

                            <g v-for="m in timelineMarkers" :key="`tl-${m.kind}-${m.month}`">
                                <circle
                                    :cx="barX(m.month)"
                                    :cy="chartHeight - chartPad.bottom + 6"
                                    r="4"
                                    :class="m.dotClass"
                                />
                            </g>
                        </svg>
                    </div>

                    <div
                        class="mt-2 grid gap-1 text-[10px] text-zinc-500 sm:text-xs"
                        :style="{ gridTemplateColumns: `repeat(${planFromGoals.months.length}, minmax(0, 1fr))` }"
                    >
                        <span
                            v-for="point in planFromGoals.months"
                            :key="`dual-lbl-${point.month}`"
                            class="text-center tabular-nums"
                        >
                            М{{ point.month }}
                        </span>
                    </div>

                    <p
                        v-if="dualRead.aligned"
                        class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                    >
                        Согласовано: при стабильной марже X рампа «от целей» и прогноз «от маржи» дают дивиденды в одном месяце ({{ dualRead.marginToTimeline.dividendsMonth }}).
                    </p>
                    <p
                        v-else-if="dualRead.marginLevel > 0"
                        class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        Расхождение: рампа требует к мес. {{ localInputs.target_dividends_month }} —
                        {{ formatMoney(dualRead.goalsToMargin.targetPerManager) }}/менеджер,
                        а при стабильных {{ formatMoney(dualRead.marginLevel) }} дивиденды наступают в
                        мес. {{ dualRead.marginToTimeline.dividendsMonth ?? '—' }}.
                        {{ localInputs.calculation_mode === MODE_TOP_DOWN ? 'Проверьте сроки или маржу.' : 'Подстройте маржу или цели.' }}
                    </p>
                    </div>
                </section>

                <section :class="`${crmPanel} overflow-hidden`">
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-3 px-5 py-4 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-950/40"
                        @click="companyChartOpen = !companyChartOpen"
                    >
                        <div class="min-w-0 space-y-1">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">График: компания (маржа, OPEX, касса)</h2>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                Столбцы — маржа и OPEX по месяцам; зелёная линия — накопленный остаток с вливанием.
                            </p>
                        </div>
                        <ChevronDown
                            class="mt-1 h-5 w-5 shrink-0 text-zinc-500 transition-transform"
                            :class="companyChartOpen ? 'rotate-180' : ''"
                        />
                    </button>
                    <div v-show="companyChartOpen" class="border-t border-zinc-200 px-5 pb-5 pt-4 dark:border-zinc-800">
                    <div class="h-56">
                        <svg
                            class="h-full w-full"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            preserveAspectRatio="none"
                        >
                            <g v-for="point in plan.months" :key="`bar-${point.month}`">
                                <rect
                                    :x="barX(point.month) - barWidth / 2"
                                    :y="yScale(point.margin)"
                                    :width="barWidth * 0.42"
                                    :height="Math.max(0, chartHeight - chartPad.bottom - yScale(point.margin))"
                                    :class="point.month <= localInputs.target_dividends_month
                                        ? 'fill-sky-500/80'
                                        : 'fill-sky-500/35'"
                                    rx="2"
                                />
                                <rect
                                    :x="barX(point.month) + barWidth * 0.08"
                                    :y="yScale(point.opex)"
                                    :width="barWidth * 0.42"
                                    :height="Math.max(0, chartHeight - chartPad.bottom - yScale(point.opex))"
                                    class="fill-rose-400/80"
                                    rx="2"
                                />
                            </g>
                            <polyline
                                :points="cumulativePolyline"
                                fill="none"
                                class="stroke-emerald-500"
                                stroke-width="2.5"
                            />
                        </svg>
                    </div>
                    <div class="mt-2 flex justify-between text-[10px] text-zinc-500 sm:text-xs">
                        <span v-for="point in plan.months" :key="`lbl-${point.month}`" class="flex-1 text-center">М{{ point.month }}</span>
                    </div>
                    </div>
                </section>

                <section v-if="salesPlan.sellers.length > 0" :class="`${crmPanel} overflow-hidden`">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">План продавцов</h2>
                        <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">
                            Коммерческие цели по продавцам: план vs факт из CRM (закрытые заказы, лиды won).
                        </p>
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Месяц</span>
                                <select
                                    v-model="selectedSalesMonth"
                                    class="min-w-[180px] rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                    @change="changeSalesMonth"
                                >
                                    <option
                                        v-for="option in salesPlan.period_options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>
                            <button
                                type="button"
                                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50 dark:bg-sky-500"
                                :disabled="salesTargetsForm.processing"
                                @click="saveSalesTargets"
                            >
                                {{ salesTargetsForm.processing ? 'Сохранение…' : 'Сохранить план продавцов' }}
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                                <tr>
                                    <th class="px-4 py-3 font-semibold" rowspan="2">Продавец</th>
                                    <th
                                        v-for="metric in salesPlan.metrics"
                                        :key="metric.key"
                                        class="px-3 py-2 text-center font-semibold"
                                        colspan="3"
                                    >
                                        {{ metric.label }}
                                    </th>
                                </tr>
                                <tr>
                                    <template v-for="metric in salesPlan.metrics" :key="`${metric.key}-sub`">
                                        <th class="px-2 py-2 text-center font-medium">План</th>
                                        <th class="px-2 py-2 text-center font-medium">Факт</th>
                                        <th class="px-2 py-2 text-center font-medium">Δ</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                <tr v-for="seller in salesPlan.sellers" :key="seller.id">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-zinc-800 dark:text-zinc-100">
                                        {{ seller.name }}
                                    </td>
                                    <template v-for="metric in salesPlan.metrics" :key="`${seller.id}-${metric.key}`">
                                        <td class="px-2 py-2">
                                            <input
                                                v-model.number="salesPlanDraft[salesPlanDraftKey(seller.id, metric.key)]"
                                                type="number"
                                                min="0"
                                                :step="metric.unit === '₽' ? 10000 : 1"
                                                class="w-28 rounded-lg border border-zinc-300 px-2 py-1.5 text-right text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                                            >
                                        </td>
                                        <td class="px-2 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-200">
                                            {{ formatSalesMetricValue(seller.metrics[metric.key]?.actual, metric.unit) }}
                                        </td>
                                        <td
                                            class="px-2 py-2 text-right tabular-nums"
                                            :class="varianceClass(seller.metrics[metric.key]?.variance)"
                                        >
                                            {{ formatSalesMetricValue(seller.metrics[metric.key]?.variance, metric.unit, true) }}
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
} from '@/support/crmUi.js';
import {
    MODE_BOTTOM_UP,
    MODE_TOP_DOWN,
    buildGoalsToMarginPlan,
    buildMarginToTimelinePlan,
    defaultBudgetInputs,
    formatBudgetMoney,
    marginForTargetNet,
    monthlyFixedOpex,
    monthlyPercentRate,
    normalizeBudgetInputs,
} from '@/support/budgetPlanner';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-budgeting' }, () => page),
});

const props = defineProps({
    inputs: { type: Object, required: true },
    plan: { type: Object, required: true },
    opex_articles: { type: Array, default: () => [] },
    management_accounting_categories_url: { type: String, required: true },
    db_benchmark: { type: Object, default: () => ({}) },
    scenario: { type: Object, default: () => ({}) },
    plan_snapshots: { type: Array, default: () => [] },
    can_freeze_plan: { type: Boolean, default: false },
    company_planning_initiatives: { type: Array, default: () => [] },
    company_planning_index_url: { type: String, default: null },
    sales_plan: {
        type: Object,
        default: () => ({
            scenario_id: null,
            period_month: '',
            period_options: [],
            metrics: [],
            sellers: [],
        }),
    },
});

const summaryPanelOpen = ref(false);
const dualChartOpen = ref(false);
const companyChartOpen = ref(false);

const companyPlanningInitiatives = computed(() => props.company_planning_initiatives ?? []);
const companyPlanningIndexUrl = computed(() => props.company_planning_index_url);

const salesPlan = computed(() => props.sales_plan ?? {
    scenario_id: null,
    period_month: '',
    period_options: [],
    metrics: [],
    sellers: [],
});

const selectedSalesMonth = ref(salesPlan.value.period_month || '');
const salesPlanDraft = reactive({});

watch(
    () => salesPlan.value.period_month,
    (value) => {
        if (value) {
            selectedSalesMonth.value = value;
        }
    },
);

watch(
    () => salesPlan.value,
    () => {
        for (const seller of salesPlan.value.sellers) {
            for (const metric of salesPlan.value.metrics) {
                const key = salesPlanDraftKey(seller.id, metric.key);
                salesPlanDraft[key] = Number(seller.metrics?.[metric.key]?.planned ?? 0);
            }
        }
    },
    { immediate: true, deep: true },
);

const salesTargetsForm = useForm({
    period_month: '',
    targets: [],
});

function salesPlanDraftKey(userId, metricKey) {
    return `${userId}:${metricKey}`;
}

function changeSalesMonth() {
    router.get(route('budgeting.index'), { sales_month: selectedSalesMonth.value }, {
        preserveState: true,
        preserveScroll: true,
        only: ['sales_plan'],
    });
}

function saveSalesTargets() {
    const targets = [];

    for (const seller of salesPlan.value.sellers) {
        for (const metric of salesPlan.value.metrics) {
            targets.push({
                user_id: seller.id,
                metric: metric.key,
                planned_value: Number(salesPlanDraft[salesPlanDraftKey(seller.id, metric.key)]) || 0,
            });
        }
    }

    salesTargetsForm.period_month = selectedSalesMonth.value;
    salesTargetsForm.targets = targets;
    salesTargetsForm.patch(route('budgeting.sales-targets.update'), { preserveScroll: true });
}

function formatSalesMetricValue(value, unit, showSign = false) {
    const numeric = Number(value) || 0;

    if (unit === '₽') {
        const formatted = formatMoney(Math.abs(numeric));

        if (showSign && numeric > 0) {
            return `+${formatted}`;
        }

        if (showSign && numeric < 0) {
            return `−${formatted}`;
        }

        return formatted;
    }

    const count = Math.round(numeric).toLocaleString('ru-RU');

    if (showSign && numeric > 0) {
        return `+${count}`;
    }

    if (showSign && numeric < 0) {
        return `−${Math.abs(numeric).toLocaleString('ru-RU')}`;
    }

    return count;
}

function varianceClass(variance) {
    const value = Number(variance) || 0;

    if (value > 0) {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    if (value < 0) {
        return 'text-rose-700 dark:text-rose-300';
    }

    return 'text-zinc-500';
}

function formatInitiativeBudget(initiative) {
    const amount = Number(initiative?.planned_budget_amount ?? 0);
    const currency = initiative?.budget_currency || 'RUB';

    return `${amount.toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ${currency}`;
}

const localInputs = reactive(normalizeBudgetInputs(props.inputs));
const localOpexArticles = reactive(props.opex_articles.map((a) => ({
    cost_type: a.cost_type ?? 'fixed_monthly',
    ...a,
})));

watch(
    () => props.inputs,
    (next) => Object.assign(localInputs, normalizeBudgetInputs(next)),
    { deep: true },
);

watch(
    () => props.opex_articles,
    (next) => {
        localOpexArticles.splice(0, localOpexArticles.length, ...next.map((a) => ({
            cost_type: a.cost_type ?? 'fixed_monthly',
            ...a,
        })));
    },
    { deep: true },
);

watch(
    () => localInputs.breakeven_month,
    (month) => {
        if (localInputs.cash_zero_month < month) {
            localInputs.cash_zero_month = month;
        }
    },
);

watch(
    () => localInputs.cash_zero_month,
    (month) => {
        if (localInputs.target_dividends_month < month) {
            localInputs.target_dividends_month = month;
        }
    },
);

const planFromGoals = computed(() => buildGoalsToMarginPlan(localInputs, localOpexArticles, props.db_benchmark));

const mirrorMarginLevel = computed(() => {
    if (localInputs.calculation_mode === MODE_BOTTOM_UP) {
        if (localInputs.use_db_margin_per_manager && props.db_benchmark?.margin_per_manager_avg > 0) {
            return Number(props.db_benchmark.margin_per_manager_avg);
        }

        return Number(localInputs.margin_per_manager) || 0;
    }

    return planFromGoals.value.summary.manager_target_x || 0;
});

const planFromMargin = computed(() => buildMarginToTimelinePlan(
    localInputs,
    localOpexArticles,
    props.db_benchmark,
    mirrorMarginLevel.value,
));

const plan = computed(() => (
    localInputs.calculation_mode === MODE_TOP_DOWN ? planFromGoals.value : planFromMargin.value
));

const dualRead = computed(() => {
    const goals = planFromGoals.value.summary;
    const margin = planFromMargin.value.summary;
    const level = mirrorMarginLevel.value;

    return {
        marginLevel: level,
        goalsToMargin: {
            floorPerManager: goals.required_margin_breakeven / (goals.manager_count || localInputs.manager_count),
            cashPerManager: (goals.required_margin_cash_zero ?? 0) / (goals.manager_count || localInputs.manager_count),
            targetPerManager: goals.manager_target_x,
        },
        marginToTimeline: {
            operatingMonth: margin.breakeven_month_operating,
            dividendsMonth: margin.dividends_feasible_month,
            cashMonth: margin.breakeven_month_cash,
        },
        aligned: level > 0
            && margin.dividends_feasible_month !== null
            && margin.dividends_feasible_month === localInputs.target_dividends_month,
    };
});

const timelineMarkers = computed(() => {
    const m = planFromMargin.value.summary;
    const markers = [];

    if (m.breakeven_month_operating) {
        markers.push({ month: m.breakeven_month_operating, kind: 'op', dotClass: 'fill-amber-500' });
    }

    if (m.dividends_feasible_month) {
        markers.push({ month: m.dividends_feasible_month, kind: 'div', dotClass: 'fill-violet-500' });
    }

    if (m.breakeven_month_cash) {
        markers.push({ month: m.breakeven_month_cash, kind: 'cash', dotClass: 'fill-emerald-500' });
    }

    return markers;
});

const marginAtPlanMonth = computed(() => marginForTargetNet(localInputs.breakeven_month, localOpexArticles, 0));

const marginAtCashZeroMonth = computed(() => (
    planFromGoals.value.summary.required_margin_cash_zero
    ?? marginForTargetNet(localInputs.cash_zero_month, localOpexArticles, 0)
));

const percentRateAtPlanMonth = computed(() => monthlyPercentRate(localInputs.breakeven_month, localOpexArticles));

const saveForm = useForm({ inputs: {} });

const freezeDefaults = buildFreezeDefaults(localInputs);
const freezeForm = useForm({
    period_label: freezeDefaults.period_label,
    period_start: freezeDefaults.period_start,
    period_end: freezeDefaults.period_end,
    notes: null,
});

function buildFreezeDefaults(inputs) {
    const now = new Date();
    const year = now.getFullYear();
    const quarter = Math.floor(now.getMonth() / 3) + 1;
    const horizon = Number(inputs.horizon_months) || 12;
    const start = `${year}-01-01`;
    const endDate = new Date(year, horizon, 0);
    const end = `${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2, '0')}-${String(endDate.getDate()).padStart(2, '0')}`;

    return {
        period_label: `Q${quarter} ${year}`,
        period_start: start,
        period_end: end,
    };
}

function freezePlan() {
    freezeForm.post(route('budgeting.plan-snapshots.store'), {
        preserveScroll: true,
    });
}

function formatSnapshotDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ru-RU');
}

function setMode(mode) {
    localInputs.calculation_mode = mode;
}

function formatMoney(value) {
    return formatBudgetMoney(value);
}

function saveScenario() {
    saveForm.inputs = normalizeBudgetInputs(localInputs);
    saveForm.patch(route('budgeting.scenario.update'), { preserveScroll: true });
}

function persistOpexArticle(article) {
    if (!article.id) {
        return;
    }

    const payload = {
        cost_type: article.cost_type || 'fixed_monthly',
        amount_monthly: article.cost_type === 'percent_of_margin' ? 0 : (Number(article.amount_monthly) || 0),
        percent_of_margin: article.cost_type === 'percent_of_margin'
            ? (Number(article.percent_of_margin) || 0)
            : null,
        ramp_months: article.ramp_months === '' || article.ramp_months === null ? null : Number(article.ramp_months),
    };

    router.patch(route('budgeting.opex-articles.update', article.id), payload, { preserveScroll: true });
}

const planMilestoneCumulativeWarning = computed(() => {
    if (localInputs.calculation_mode !== MODE_TOP_DOWN || !plan.value.summary.plan_milestone_month) {
        return false;
    }

    const row = plan.value.manager_plan.rows.find((r) => r.month === plan.value.summary.plan_milestone_month);

    return row !== undefined && row.cumulative < 0;
});

const planMilestoneCumulativeValue = computed(() => {
    const row = plan.value.manager_plan.rows.find((r) => r.month === plan.value.summary.plan_milestone_month);

    return row?.cumulative ?? 0;
});

const cashBreakevenTimelineText = computed(() => {
    const s = plan.value.summary;
    const horizon = localInputs.horizon_months;
    const plannedCash = s.cash_zero_month ?? localInputs.cash_zero_month;

    if (s.breakeven_month_cash) {
        if (s.breakeven_month_cash <= plannedCash) {
            return `Фактическая кассовая безубыточность — месяц ${s.breakeven_month_cash}`;
        }

        return `По плану касса ≥ 0 к месяцу ${plannedCash}, фактически — месяц ${s.breakeven_month_cash}`;
    }

    const estimated = s.breakeven_month_cash_estimated;

    if (estimated) {
        if (estimated <= horizon) {
            return `При текущей рампе касса ≥ 0 ожидается к месяцу ${estimated} (цель — ${plannedCash})`;
        }

        return `При текущей рампе касса ≥ 0 достигается в течение ${estimated} месяцев (цель — ${plannedCash}, горизонт ${horizon} мес.)`;
    }

    return `При текущей рампе касса ≥ 0 не достигается за ${horizon} месяцев (цель — ${plannedCash})`;
});

const scenarioAssessment = computed(() => {
    const s = plan.value.summary;
    const managerCount = s.manager_count || localInputs.manager_count;
    const cashZeroMonth = s.cash_zero_month ?? localInputs.cash_zero_month;
    const operatingMonth = s.breakeven_month_operating;
    const cashMonth = s.breakeven_month_cash ?? s.breakeven_month_cash_estimated;
    const dividendsMonth = s.dividends_feasible_month;
    const parts = [];

    parts.push(
        `При текущих вводных (${managerCount} менеджеров, вливание ${formatMoney(localInputs.owner_investment)}, горизонт ${localInputs.horizon_months} мес.)`,
    );

    if (localInputs.calculation_mode === MODE_TOP_DOWN) {
        const planMonth = s.plan_milestone_month ?? localInputs.breakeven_month;
        parts.push(
            `план «ноль за месяц» — к месяцу ${planMonth}, нулевой денежный поток — к месяцу ${cashZeroMonth}, дивиденды ${formatMoney(localInputs.target_dividends_amount)} — с месяца ${localInputs.target_dividends_month}.`,
        );

        if (planMilestoneCumulativeWarning.value) {
            parts.push(
                `В месяце ${planMonth} маржа покрывает OPEX, но накопленный остаток ещё ${formatMoney(planMilestoneCumulativeValue.value)} — прежние убытки не отбиты.`,
            );
        }
    } else {
        parts.push(
            `при марже ${formatMoney(s.margin_per_manager_used)} на менеджера «ноль за месяц» — ${operatingMonth ? `месяц ${operatingMonth}` : 'не достигается за горизонт'}, кассовая безубыточность — ${cashMonth ? `месяц ${cashMonth}` : 'не достигается'}, дивиденды ${formatMoney(s.target_dividends_amount)} — ${dividendsMonth ? `с месяца ${dividendsMonth}` : 'недостижимы при текущей марже'}.`,
        );
    }

    parts.push(`${cashBreakevenTimelineText.value}.`);

    const recommendations = [];

    if (s.min_cumulative < 0) {
        recommendations.push(`увеличить вливание или маржу — минимальный остаток ${formatMoney(s.min_cumulative)}`);
    }

    if (!dualRead.value.aligned && dualRead.value.marginLevel > 0) {
        recommendations.push(
            localInputs.calculation_mode === MODE_TOP_DOWN
                ? 'согласовать сроки дивидендов и целевую маржу: расчёт сверху и снизу расходится'
                : 'подстроить маржу или цели — расчёт сверху и снизу расходится',
        );
    }

    let recommendation = '';
    if (recommendations.length === 0 && s.min_cumulative >= 0 && dualRead.value.aligned) {
        recommendation = 'оставить параметры и отслеживать факт по месяцам — сценарий согласован.';
    } else if (recommendations.length > 0) {
        recommendation = `${recommendations.join('; ')}.`;
    } else if (planMilestoneCumulativeWarning.value) {
        recommendation = `держать рампу маржи до месяца ${cashZeroMonth}, не снижая плановую маржу до выхода кассы в ноль.`;
    }

    let tone = 'neutral';
    if (s.min_cumulative < 0 || planMilestoneCumulativeWarning.value || (!dualRead.value.aligned && dualRead.value.marginLevel > 0)) {
        tone = 'warning';
    } else if (dualRead.value.aligned && s.min_cumulative >= 0) {
        tone = 'positive';
    }

    return {
        situation: parts.join(' '),
        recommendation,
        tone,
    };
});

function managerRowClass(row) {
    if (row.tags.includes('cash_be')) {
        return 'bg-emerald-50/80 dark:bg-emerald-950/25';
    }

    if (row.tags.includes('operating_be') || row.tags.includes('plan_milestone')) {
        return 'bg-amber-50/50 dark:bg-amber-950/20';
    }

    if (row.tags.includes('target')) {
        return 'bg-violet-50/50 dark:bg-violet-950/20';
    }

    return '';
}

const summaryCards = computed(() => {
    const s = plan.value.summary;
    const cards = [];

    const cashBeValue = s.breakeven_month_cash
        ? `Месяц ${s.breakeven_month_cash}`
        : (s.breakeven_month_cash_estimated ? `~${s.breakeven_month_cash_estimated} мес.` : '—');
    const plannedCashMonth = s.cash_zero_month ?? localInputs.cash_zero_month;
    const cashBeHint = s.breakeven_month_cash
        ? (s.breakeven_month_cash === plannedCashMonth
            ? 'Накопленный остаток ≥ 0'
            : `Факт: мес. ${s.breakeven_month_cash}, цель — мес. ${plannedCashMonth}`)
        : (s.breakeven_month_cash_estimated
            ? `Оценка при текущем потоке; цель — мес. ${plannedCashMonth}`
            : `Цель — мес. ${plannedCashMonth}`);

    if (localInputs.calculation_mode === MODE_BOTTOM_UP) {
        cards.push(
            {
                key: 'be-cash',
                label: 'Безубыточность',
                value: cashBeValue,
                hint: cashBeHint,
            },
            {
                key: 'be-op',
                label: 'Ноль за месяц',
                value: s.breakeven_month_operating ? `Месяц ${s.breakeven_month_operating}` : '—',
                hint: 'Поток за месяц ≥ 0',
            },
            {
                key: 'margin-co',
                label: 'Маржа компании / мес',
                value: formatMoney(s.company_margin_monthly),
                hint: `${formatMoney(s.margin_per_manager_used)} × ${s.manager_count} менеджеров`,
            },
        );
    } else {
        cards.push(
            {
                key: 'be-cash',
                label: 'Безубыточность',
                value: cashBeValue,
                hint: cashBeHint,
            },
            {
                key: 'be-op',
                label: 'Ноль за месяц',
                value: s.breakeven_month_operating ? `Месяц ${s.breakeven_month_operating}` : '—',
                hint: 'Первый месяц: поток ≥ 0',
            },
            {
                key: 'plan-ms',
                label: 'Ноль за месяц (план)',
                value: s.plan_milestone_month ? `Месяц ${s.plan_milestone_month}` : '—',
                hint: 'Поток за месяц ≥ 0',
            },
        );
    }

    cards.push(
        {
            key: 'x',
            label: 'X — цель на менеджера',
            value: formatMoney(s.manager_target_x),
            hint: localInputs.calculation_mode === MODE_BOTTOM_UP
                ? 'Заданная / из CRM маржа'
                : `К месяцу ${localInputs.target_dividends_month} (дивиденды)`,
        },
        {
            key: 'y',
            label: 'Y — нижний порог',
            value: formatMoney(s.manager_floor_y),
            hint: 'При кассовой безубыточности',
        },
    );

    return cards;
});

const chartWidth = 720;
const chartHeight = 256;
const chartPad = { top: 12, right: 12, bottom: 28, left: 12 };

const barWidth = computed(() => {
    const count = plan.value.months.length || 1;
    const inner = chartWidth - chartPad.left - chartPad.right;

    return Math.max(8, inner / count - 4);
});

const chartMaxValue = computed(() => {
    const values = plan.value.months.flatMap((p) => [p.margin, p.opex, p.cumulative]);

    return Math.max(1, ...values.map((v) => Math.abs(Number(v) || 0)));
});

const managerChartMax = computed(() => {
    const fromGoals = planFromGoals.value.months.map((p) => Number(p.margin_per_manager) || 0);
    const flat = mirrorMarginLevel.value;

    return Math.max(1, ...fromGoals, flat);
});

const managerYTicks = computed(() => {
    const max = managerChartMax.value * 1.15;
    const step = Math.ceil(max / 4 / 25000) * 25000 || 25000;

    return [0, step, step * 2, step * 3, step * 4].filter((t) => t <= max);
});

function yScaleManager(value) {
    const max = managerChartMax.value * 1.15;
    const inner = chartHeight - chartPad.top - chartPad.bottom;
    const ratio = Math.min(1, Math.max(0, Number(value) / max));

    return chartHeight - chartPad.bottom - inner * ratio;
}

const goalsRampPolyline = computed(() => {
    const endMonth = localInputs.target_dividends_month;

    return planFromGoals.value.months
        .filter((p) => p.month <= endMonth)
        .map((p) => `${barX(p.month)},${yScaleManager(p.margin_per_manager)}`)
        .join(' ');
});

const flatMarginPolyline = computed(() => {
    const level = mirrorMarginLevel.value;

    if (level <= 0) {
        return '';
    }

    return planFromGoals.value.months
        .map((p) => `${barX(p.month)},${yScaleManager(level)}`)
        .join(' ');
});

function formatManagerShort(value) {
    const n = Math.round(Number(value) / 1000);

    return `${n}k`;
}

function yScale(value) {
    const max = chartMaxValue.value * 1.1;
    const inner = chartHeight - chartPad.top - chartPad.bottom;
    const ratio = Math.min(1, Math.max(0, Number(value) / max));

    return chartHeight - chartPad.bottom - inner * ratio;
}

function barX(month) {
    const count = plan.value.months.length || 1;
    const inner = chartWidth - chartPad.left - chartPad.right;

    return chartPad.left + (inner / count) * (month - 0.5);
}

const cumulativePolyline = computed(() => plan.value.months
    .map((p) => `${barX(p.month)},${yScale(p.cumulative)}`)
    .join(' '));
</script>
