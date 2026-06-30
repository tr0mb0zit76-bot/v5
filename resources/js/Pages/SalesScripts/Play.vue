<template>
    <div :class="pageRootClass">
        <div
            v-if="isTrainerActive"
            class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
        >
            <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Тренажёр</div>
            <h1 class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ session.script_title }}</h1>
            <div
                v-if="playContext?.trainer_profile?.title"
                class="mt-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-100"
            >
                {{ trainingRoleLabel }} · профиль «{{ playContext.trainer_profile.title }}»
            </div>
        </div>

        <div
            v-if="session.completed_at"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
        >
            Сессия завершена
            <span v-if="session.outcome">· исход воронки: {{ session.outcome }}</span>
            <span v-if="session.trainer_dialog_quality">· оценка тренировки: {{ trainerDialogQualityLabel(session.trainer_dialog_quality) }}</span>
            <div v-if="capturedFields.length" class="mt-4 border-t border-emerald-200/80 pt-3 dark:border-emerald-800/60">
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Записанные поля</div>
                <ul class="mt-2 space-y-1.5">
                    <li v-for="field in capturedFields" :key="field.code" class="text-sm">
                        <span class="font-medium">{{ field.label }}:</span> {{ field.value }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Тренажёр: диалог слева, сценарий справа -->
        <div v-else-if="isTrainerActive" class="flex flex-col gap-6 xl:flex-row xl:items-start">
            <div class="min-w-0 flex-1 space-y-6">
                <article
                    v-if="!isManagerBuyerMode && trainerPlayPresentation && (trainerPlayPresentation.operator_line || trainerPlayPresentation.branch_instruction)"
                    class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900/50 dark:bg-zinc-950"
                >
                    <div class="border-b border-emerald-100 bg-gradient-to-r from-emerald-50/90 via-white to-white px-6 py-4 dark:border-emerald-900/40 dark:from-emerald-950/30 dark:via-zinc-950 dark:to-zinc-950">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.25em] text-emerald-700 dark:text-emerald-300">
                                {{ operatorKindLabel(trainerPlayPresentation.operator_kind) }}
                            </span>
                            <span v-if="trainerPlayPresentation.step_key" class="font-mono text-[10px] text-zinc-400 dark:text-zinc-500">
                                {{ trainerPlayPresentation.step_key }}
                            </span>
                        </div>
                    </div>
                    <div v-if="trainerPlayPresentation.operator_line" class="px-6 py-6">
                        <p class="whitespace-pre-wrap text-base leading-relaxed text-zinc-900 dark:text-zinc-50">
                            {{ trainerPlayPresentation.operator_line }}
                        </p>
                    </div>
                    <div
                        v-else-if="trainerPlayPresentation.is_branch_only"
                        class="px-6 py-6"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Слушайте реакцию клиента
                        </p>
                        <p
                            v-if="trainerPlayPresentation.branch_instruction"
                            class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-zinc-800 dark:text-zinc-200"
                        >
                            {{ trainerPlayPresentation.branch_instruction }}
                        </p>
                    </div>
                    <div
                        v-if="trainerPlayPresentation.coaching_hint"
                        class="border-t border-emerald-100 bg-emerald-50/60 px-6 py-4 text-sm text-emerald-950 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-100"
                    >
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">Подсказка</span>
                        <p class="mt-1 whitespace-pre-wrap leading-relaxed">{{ trainerPlayPresentation.coaching_hint }}</p>
                    </div>
                    <div
                        v-if="trainerPlayPresentation.choices?.length"
                        class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Возможные реплики клиента на этом шаге
                        </p>
                        <ul class="mt-2 space-y-2">
                            <li
                                v-for="(choice, idx) in trainerPlayPresentation.choices"
                                :key="choice.transition_id ?? idx"
                                class="rounded-lg border border-zinc-200 bg-zinc-50/80 px-3 py-2 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-200"
                            >
                                {{ choice.label }}
                                <span v-if="choice.subtitle" class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ choice.subtitle }}</span>
                            </li>
                        </ul>
                    </div>
                </article>

                <div class="space-y-4 border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Диалог с клиентом / продавцом</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ trainerModeHint }}
                            </p>
                        </div>
                        <span
                            v-if="playContext?.trainer_profile?.title"
                            class="shrink-0 rounded-full border border-zinc-200 px-2 py-1 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-300"
                        >
                            {{ playContext.trainer_profile.title }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="!session.completed_at"
                            type="button"
                            class="rounded-xl border border-sky-600 bg-sky-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-sky-700 dark:border-sky-500 dark:bg-sky-500 dark:hover:bg-sky-400"
                            @click="trainerEndIntent = true"
                        >
                            Завершить тренировку
                        </button>
                        <span v-if="trainerEndIntent && !session.completed_at" class="text-xs text-zinc-500 dark:text-zinc-400">
                            Ниже заполните исход и сохраните.
                        </span>
                    </div>

                    <div
                        v-if="trainerRubric"
                        class="rounded-xl border border-indigo-200 bg-indigo-50/80 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/30"
                    >
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700 dark:text-indigo-300">
                            Рубрика оценки: {{ trainerRubric.label }}
                        </div>
                        <p class="mt-1 text-xs text-indigo-900/80 dark:text-indigo-100/80">
                            {{ trainerRubric.description }}
                        </p>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-zinc-700 dark:text-zinc-200">
                            <li v-for="criterion in trainerRubric.criteria" :key="criterion">
                                {{ criterion }}
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Как прошла тренировка</div>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Отдельно от исхода воронки — для аналитики тренажёра.
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition disabled:opacity-50"
                                :class="trainerDialogQuality === 'success'
                                    ? 'border-emerald-600 bg-emerald-600 text-white dark:border-emerald-500 dark:bg-emerald-600'
                                    : 'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800'"
                                :disabled="trainerMetaBusy"
                                @click="setTrainerDialogQuality('success')"
                            >
                                Успешно
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition disabled:opacity-50"
                                :class="trainerDialogQuality === 'failure'
                                    ? 'border-rose-600 bg-rose-600 text-white dark:border-rose-500 dark:bg-rose-600'
                                    : 'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800'"
                                :disabled="trainerMetaBusy"
                                @click="setTrainerDialogQuality('failure')"
                            >
                                Неудачно
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition disabled:opacity-50"
                                :class="trainerDialogQuality === 'stuck'
                                    ? 'border-amber-600 bg-amber-600 text-white dark:border-amber-500 dark:bg-amber-600'
                                    : 'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800'"
                                :disabled="trainerMetaBusy"
                                @click="setTrainerDialogQuality('stuck')"
                            >
                                Зашёл в тупик
                            </button>
                            <button
                                v-if="trainerDialogQuality"
                                type="button"
                                class="rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                :disabled="trainerMetaBusy"
                                @click="setTrainerDialogQuality(null)"
                            >
                                Снять оценку
                            </button>
                        </div>
                    </div>

                    <details class="group rounded-xl border border-zinc-200 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-900/20">
                        <summary class="cursor-pointer list-none px-4 py-3 text-sm font-medium text-zinc-800 marker:hidden dark:text-zinc-200 [&::-webkit-details-marker]:hidden">
                            <span class="underline-offset-2 group-open:underline">Доп. указания для ассистента (роль и тон)</span>
                            <span class="mt-0.5 block text-xs font-normal text-zinc-500 dark:text-zinc-400">Добавляются к системному промпту перед каждым ответом модели.</span>
                        </summary>
                        <div class="space-y-2 border-t border-zinc-200 p-4 dark:border-zinc-700">
                            <textarea
                                v-model="trainerAssistantInstructions"
                                rows="5"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="Например: будь более скептичным к цене; не соглашайся на встречу без ЛПР…"
                                :disabled="trainerMetaBusy"
                            />
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    :class="crmBtnCreate"
                                    :disabled="trainerMetaBusy"
                                    @click="saveTrainerAssistantInstructions"
                                >
                                    {{ trainerMetaBusy ? 'Сохранение…' : 'Сохранить указания' }}
                                </button>
                                <span v-if="promptSaveHint" class="text-xs text-zinc-500 dark:text-zinc-400">{{ promptSaveHint }}</span>
                            </div>
                        </div>
                    </details>

                    <div
                        ref="trainerChatScrollRef"
                        class="max-h-80 space-y-2 overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/40"
                    >
                        <div
                            v-for="(message, index) in trainerChatHistory"
                            :key="message.id ?? `${message.role}-${index}-${message.at || ''}`"
                            class="rounded-xl px-3 py-2 text-sm"
                            :class="message.role === 'assistant'
                                ? 'border border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100'
                                : 'border border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100'"
                        >
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                                {{ trainerMessageRoleLabel(message.role) }}
                            </div>
                            <div class="whitespace-pre-wrap">{{ message.content }}</div>
                            <div
                                v-if="message.role === 'assistant' && message.id && message.auto_peer_reaction"
                                class="mt-1.5 text-[10px] text-zinc-500 dark:text-zinc-400"
                            >
                                Авто:
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ peerReactionLabel(message.auto_peer_reaction) }}</span>
                            </div>
                            <div
                                v-if="message.role === 'assistant' && message.id && !session.completed_at"
                                class="mt-2 flex flex-wrap items-center gap-1 border-t border-sky-200/80 pt-2 dark:border-sky-800/60"
                            >
                                <span class="mr-1 text-[10px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Оценка реплики</span>
                                <button
                                    v-for="opt in peerReactionOptions"
                                    :key="opt.value"
                                    type="button"
                                    class="rounded-lg border px-2 py-1 text-[11px] font-medium transition disabled:opacity-50"
                                    :class="message.peer_reaction === opt.value
                                        ? opt.activeClass
                                        : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                                    :disabled="peerReactionBusyId === message.id"
                                    @click="setPeerReaction(message.id, opt.value)"
                                >
                                    {{ opt.label }}
                                </button>
                                <button
                                    v-if="message.peer_reaction"
                                    type="button"
                                    class="ml-1 text-[10px] text-zinc-500 underline dark:text-zinc-400"
                                    :disabled="peerReactionBusyId === message.id"
                                    @click="setPeerReaction(message.id, null)"
                                >
                                    Снять
                                </button>
                            </div>
                        </div>

                        <div v-if="trainerChatHistory.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                            Диалог пока не начат.
                        </div>
                    </div>

                    <form class="space-y-2" @submit.prevent="sendTrainerMessage">
                        <textarea
                            v-model="trainerDraft"
                            rows="3"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            :placeholder="trainerDraftPlaceholder"
                            :disabled="trainerSending"
                            @keydown="onTrainerDraftKeydown"
                        />
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                Enter — отправить, Shift+Enter — новая строка.
                            </div>
                            <button
                                type="submit"
                                :class="crmBtnPrimary"
                                class="disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="trainerSending || trainerDraft.trim().length === 0"
                            >
                                {{ trainerSending ? 'Отправка...' : 'Отправить' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div
                    v-if="!session.completed_at && (mustComplete || trainerEndIntent)"
                    class="space-y-4 border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Зафиксируйте исход</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Эти данные используются для отчётов по воронке и обучения подсказок.</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Оценку тренировки (успех / неудача / тупик) при необходимости отметьте в блоке диалога слева — отдельно от исхода воронки. Аналитика:
                        <Link :href="route('sales-assistant.trainer.analytics')" class="font-medium underline-offset-2 hover:underline">тренажёр</Link>.
                    </p>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Исход</label>
                        <select v-model="completeForm.outcome" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="" disabled>Выберите</option>
                            <option v-for="opt in outcomeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Главное возражение (необязательно)</label>
                        <select v-model="completeForm.primary_reaction_class_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option :value="null">—</option>
                            <option v-for="rc in reactionClasses" :key="rc.id" :value="rc.id">{{ rc.label }}</option>
                        </select>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Лид (ID, необязательно)</label>
                            <input
                                v-model="completeForm.lead_id"
                                type="number"
                                min="1"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="Для заметки и задачи в лиде"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Заказ (ID, необязательно)</label>
                            <input
                                v-model="completeForm.order_id"
                                type="number"
                                min="1"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="Для связки с заказом"
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Комментарий</label>
                        <textarea
                            v-model="completeForm.notes"
                            rows="3"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            placeholder="Кратко: что договорились, что мешало"
                        />
                    </div>

                    <button
                        type="button"
                        :class="crmBtnCreate"
                        class="py-2.5"
                        :disabled="!completeForm.outcome"
                        @click="submitComplete"
                    >
                        Сохранить и выйти
                    </button>
                </div>

            </div>

            <aside
                class="w-full shrink-0 space-y-5 border-zinc-200 xl:sticky xl:top-4 xl:max-h-[calc(100vh-5rem)] xl:w-[min(100%,24rem)] xl:overflow-y-auto xl:border-l xl:pl-6 dark:border-zinc-800"
            >
                <div v-if="isManagerBuyerMode" class="rounded-xl border border-zinc-200 bg-zinc-50/90 p-4 text-xs leading-relaxed text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                    <div class="font-semibold text-zinc-800 dark:text-zinc-100">Подсказки по сценарию отключены</div>
                    <p class="mt-2">
                        Вы отрабатываете роль покупателя; фрагменты узлов в данных сценария сформулированы как реплики продавца, поэтому лексические подсказки здесь не показываются.
                    </p>
                </div>

                <template v-else>
                    <div v-if="trainerCurrentStepHint" class="space-y-2 rounded-xl border border-emerald-300 bg-emerald-50/95 p-4 text-xs text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/35 dark:text-emerald-100">
                        <div class="font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">
                            Текущий шаг сценария
                        </div>
                        <p v-if="trainerCurrentStepHint.client_key" class="font-mono text-[10px] text-emerald-700 dark:text-emerald-300">
                            {{ trainerCurrentStepHint.client_key }}
                        </p>
                        <p class="whitespace-pre-wrap leading-relaxed">{{ trainerCurrentStepHint.excerpt }}</p>
                        <p v-if="trainerCurrentStepHint.hint" class="border-t border-emerald-200/80 pt-2 text-[11px] dark:border-emerald-800/60">
                            {{ trainerCurrentStepHint.hint }}
                        </p>
                    </div>

                    <div v-if="trainerClientOptionHints.length > 0" class="space-y-2">
                        <h3 class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Реакции клиента на шаге</h3>
                        <ul class="space-y-2">
                            <li
                                v-for="(h, idx) in trainerClientOptionHints"
                                :key="`${h.client_key}-${idx}`"
                                class="rounded-xl border border-zinc-200 bg-zinc-50/90 p-3 text-xs text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-200"
                            >
                                {{ h.excerpt }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="trainerCoaching?.coaching_hint" class="space-y-2 rounded-xl border border-amber-300 bg-amber-50/95 p-4 text-xs text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/35 dark:text-amber-100">
                        <div class="font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                            Коучинг: диалог застрял
                        </div>
                        <p class="whitespace-pre-wrap leading-relaxed">{{ trainerCoaching.coaching_hint }}</p>
                    </div>

                    <div v-if="trainerContextualHints.length > 0" class="space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Дополнительно по теме диалога</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Лексический поиск по словам из чата — вспомогательно, не заменяет текущий шаг графа.
                        </p>
                        <ul class="space-y-3">
                            <li
                                v-for="h in trainerContextualHints"
                                :key="h.node_id"
                                class="rounded-xl border border-zinc-200 bg-zinc-50/90 p-3 text-xs text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-200"
                            >
                                <div v-if="h.client_key" class="font-mono text-[10px] text-zinc-500 dark:text-zinc-400">{{ h.client_key }}</div>
                                <p class="mt-1 whitespace-pre-wrap leading-relaxed">{{ h.excerpt }}</p>
                                <p v-if="h.hint" class="mt-2 border-t border-zinc-200 pt-2 text-[11px] text-amber-900 dark:border-zinc-600 dark:text-amber-100">
                                    {{ h.hint }}
                                </p>
                                <p v-if="h.matched_terms?.length" class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400">
                                    Совпадения: {{ h.matched_terms.join(', ') }}
                                </p>
                            </li>
                        </ul>
                    </div>
                    <div v-else-if="!trainerCurrentStepHint && trainerChatHistory.length > 0" class="rounded-xl border border-dashed border-zinc-200 p-3 text-xs text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                        Нет дополнительных лексических совпадений. Ориентируйтесь на телесуфлёр слева.
                    </div>
                    <div v-else-if="!trainerCurrentStepHint" class="rounded-xl border border-dashed border-zinc-200 p-3 text-xs text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                        Начните диалог — текущий шаг сценария появится в телесуфлёре.
                    </div>
                </template>
            </aside>
        </div>

        <!-- Прохождение скрипта (не тренажёр) -->
        <div v-else-if="currentNode && playPresentation" class="space-y-6">
            <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ session.script_title }}</h1>

            <article v-if="playPresentation.operator_line || playPresentation.operator_segments?.length" class="space-y-4">
                <p
                    v-if="playPresentation.operator_segments?.length"
                    class="text-xl font-medium leading-relaxed tracking-tight text-zinc-900 dark:text-zinc-50 md:text-2xl"
                >
                    <template v-for="(segment, index) in playPresentation.operator_segments" :key="`seg-${index}`">
                        <span v-if="segment.type === 'text'" class="whitespace-pre-wrap">{{ segment.content }}</span>
                        <input
                            v-else-if="segment.type === 'capture'"
                            v-model="captureDraft[segment.code]"
                            type="text"
                            class="mx-1 inline-block min-w-[8rem] rounded-lg border border-sky-300 bg-white px-2 py-1 text-lg font-semibold text-sky-900 shadow-sm dark:border-sky-600 dark:bg-zinc-950 dark:text-sky-100"
                            :placeholder="segment.label"
                        />
                        <span
                            v-else-if="segment.type === 'reference'"
                            class="font-semibold text-sky-700 dark:text-sky-300"
                        >
                            {{ segment.value || segment.empty_label }}
                        </span>
                    </template>
                </p>
                <p v-else class="whitespace-pre-wrap text-xl font-medium leading-relaxed tracking-tight text-zinc-900 dark:text-zinc-50 md:text-2xl">
                    {{ playPresentation.operator_line }}
                </p>
            </article>

            <p
                v-else-if="playPresentation.is_branch_only && playPresentation.choices.length > 0"
                class="text-sm text-zinc-600 dark:text-zinc-300"
            >
                Выберите, что ответил собеседник:
            </p>

            <section
                v-if="!mustComplete && playPresentation.choices.length > 0"
                class="flex flex-col gap-2.5"
            >
                <button
                    v-for="(choice, idx) in playPresentation.choices"
                    :key="`${choice.transition_id}-${idx}`"
                    type="button"
                    :class="crmBtnScriptChoice"
                    @click="advanceChoice(choice)"
                >
                    <span v-if="choice.has_customer_phrase">{{ choice.label }}</span>
                    <span v-else class="italic opacity-90">{{ choice.label }}</span>
                    <span
                        v-if="statsHintForChoice(choice)"
                        class="mt-1 block text-xs font-normal text-emerald-700 dark:text-emerald-300"
                    >
                        {{ statsHintForChoice(choice).message }}
                    </span>
                </button>
            </section>

            <button
                v-else-if="!mustComplete && playPresentation.choices.length === 1 && !playPresentation.choices[0].sales_script_reaction_class_id"
                type="button"
                :class="crmBtnScriptChoice"
                @click="advanceChoice(playPresentation.choices[0])"
            >
                {{ playPresentation.choices[0].label }}
            </button>
        </div>

        <div
            v-if="!isTrainerActive && !session.completed_at && mustComplete"
            class="space-y-4 border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
        >
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Зафиксируйте исход</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Эти данные используются для отчётов по воронке и обучения подсказок.</p>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Исход</label>
                <select v-model="completeForm.outcome" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <option value="" disabled>Выберите</option>
                    <option v-for="opt in outcomeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Главное возражение (необязательно)</label>
                <select v-model="completeForm.primary_reaction_class_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <option :value="null">—</option>
                    <option v-for="rc in reactionClasses" :key="rc.id" :value="rc.id">{{ rc.label }}</option>
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Лид (ID, необязательно)</label>
                <input
                    v-model="completeForm.lead_id"
                    type="number"
                    min="1"
                    class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    placeholder="Для заметки и задачи в лиде"
                />
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Заказ (ID, необязательно)</label>
                <input
                    v-model="completeForm.order_id"
                    type="number"
                    min="1"
                    class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    placeholder="Привязка исхода к orders.id"
                />
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Комментарий</label>
                <textarea
                    v-model="completeForm.notes"
                    rows="3"
                    class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    placeholder="Кратко: что договорились, что мешало"
                />
            </div>

            <button
                type="button"
                :class="crmBtnCreate"
                class="py-2.5"
                :disabled="!completeForm.outcome"
                @click="submitComplete"
            >
                Сохранить и выйти
            </button>
        </div>


        <div class="flex flex-wrap gap-3">
            <Link
                :href="backListHref"
                class="text-sm font-medium text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                {{ backListLabel }}
            </Link>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmBtnPrimary, crmBtnScriptChoice } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, {
            activeKey: 'sales-assistant',
            activeSubKey: page.props?.playContext?.return === 'trainer' ? 'sales-assistant-trainer' : 'sales-assistant-scripts',
        }, () => page),
});

const props = defineProps({
    playContext: {
        type: Object,
        default: () => ({
            return: null,
            trainer_profile: null,
            training_role_mode: 'manager_seller',
            trainer_contextual_hints: [],
            trainer_step_hints: [],
        }),
    },
    session: { type: Object, required: true },
    currentNode: { type: Object, default: null },
    outgoingTransitions: { type: Array, default: () => [] },
    playPresentation: {
        type: Object,
        default: () => ({
            operator_kind: 'say',
            operator_line: null,
            coaching_hint: null,
            branch_instruction: null,
            choices: [],
            step_key: null,
            is_branch_only: false,
        }),
    },
    mustComplete: { type: Boolean, default: false },
    eventTrail: { type: Array, default: () => [] },
    outcomeOptions: { type: Array, default: () => [] },
    reactionClasses: { type: Array, default: () => [] },
    capturedFields: { type: Array, default: () => [] },
    statsHints: { type: Object, default: () => ({}) },
});

const isTrainer = computed(() => props.playContext?.return === 'trainer');
const isTrainerActive = computed(() => isTrainer.value && !props.session.completed_at);

const pageRootClass = computed(() =>
    isTrainerActive.value
        ? 'mx-auto max-w-6xl min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0'
        : 'mx-auto max-w-3xl min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0',
);

const trainingRoleMode = computed(() => props.playContext?.training_role_mode || 'manager_seller');
const isManagerBuyerMode = computed(() => trainingRoleMode.value === 'manager_buyer');
const trainerDraft = ref('');
const trainerSending = ref(false);
const trainerChatHistory = ref(Array.isArray(props.playContext?.trainer_chat) ? [...props.playContext.trainer_chat] : []);
const trainerChatScrollRef = ref(null);
const trainerAssistantInstructions = ref(props.session.trainer_assistant_instructions ?? '');
const trainerDialogQuality = ref(props.session.trainer_dialog_quality ?? null);
const trainerMetaBusy = ref(false);
const promptSaveHint = ref('');
const trainerContextualHints = ref(
    Array.isArray(props.playContext?.trainer_contextual_hints) ? [...props.playContext.trainer_contextual_hints] : [],
);
const trainerCoaching = ref(props.playContext?.trainer_coaching ?? null);
const trainerRubric = computed(() => props.playContext?.trainer_rubric ?? null);
const trainerStepHints = ref(
    Array.isArray(props.playContext?.trainer_step_hints) ? [...props.playContext.trainer_step_hints] : [],
);
const trainerPlayPresentation = ref({ ...props.playPresentation });
const trainerEventTrail = ref(Array.isArray(props.eventTrail) ? [...props.eventTrail] : []);
const trainerEndIntent = ref(false);
const peerReactionBusyId = ref(null);
const captureDraft = reactive({});

const peerReactionOptions = [
    {
        value: 'positive',
        label: 'Плюс',
        activeClass: 'border-emerald-600 bg-emerald-600 text-white dark:border-emerald-500 dark:bg-emerald-600',
    },
    {
        value: 'neutral',
        label: 'Нейтрально',
        activeClass: 'border-zinc-400 bg-zinc-200 text-zinc-900 dark:border-zinc-500 dark:bg-zinc-600 dark:text-zinc-50',
    },
    {
        value: 'negative',
        label: 'Минус',
        activeClass: 'border-rose-600 bg-rose-600 text-white dark:border-rose-500 dark:bg-rose-600',
    },
];

function peerReactionLabel(value) {
    if (!value) {
        return '';
    }
    const opt = peerReactionOptions.find((o) => o.value === value);

    return opt ? opt.label : value;
}

const trainerCurrentStepHint = computed(() => {
    const hints = trainerStepHints.value;

    return Array.isArray(hints)
        ? hints.find((h) => h?.source === 'graph_current_step' && h?.is_current) ?? null
        : null;
});

const trainerClientOptionHints = computed(() => {
    const hints = trainerStepHints.value;

    return Array.isArray(hints)
        ? hints.filter((h) => h?.source === 'graph_client_option')
        : [];
});

const trainingRoleLabel = computed(() =>
    isManagerBuyerMode.value
        ? 'вы покупатель, ассистент продавец'
        : 'вы продавец, ассистент покупатель',
);

const trainerModeHint = computed(() =>
    isManagerBuyerMode.value
        ? 'Пишите как покупатель. Модель отвечает в роли менеджера-продавца.'
        : 'Пишите как менеджер. Модель отвечает в роли клиента.',
);

const trainerDraftPlaceholder = computed(() =>
    isManagerBuyerMode.value
        ? 'Напишите реплику покупателя...'
        : 'Напишите реплику менеджера...',
);

const backListHref = computed(() => (isTrainer.value ? route('sales-assistant.trainer') : route('scripts.index')));

const backListLabel = computed(() => (isTrainer.value ? '← К тренажёру' : '← К списку сценариев'));

const completeForm = reactive({
    outcome: '',
    primary_reaction_class_id: null,
    notes: '',
    lead_id: props.session.lead_id ?? '',
    order_id: props.session.order_id ?? '',
});

function statsHintForChoice(choice) {
    const reactionId = choice?.sales_script_reaction_class_id;
    if (reactionId === null || reactionId === undefined) {
        return null;
    }

    return props.statsHints?.[reactionId] ?? props.statsHints?.[String(reactionId)] ?? null;
}

watch(
    () => props.session.trainer_assistant_instructions,
    (v) => {
        trainerAssistantInstructions.value = v ?? '';
    },
);

watch(
    () => props.session.trainer_dialog_quality,
    (v) => {
        trainerDialogQuality.value = v ?? null;
    },
);

watch(
    () => props.playContext?.trainer_contextual_hints,
    (v) => {
        trainerContextualHints.value = Array.isArray(v) ? [...v] : [];
    },
    { deep: true },
);

watch(
    () => props.playContext?.trainer_step_hints,
    (v) => {
        trainerStepHints.value = Array.isArray(v) ? [...v] : [];
    },
    { deep: true },
);


watch(
    () => props.eventTrail,
    (v) => {
        if (Array.isArray(v) && isTrainerActive.value) {
            trainerEventTrail.value = [...v];
        }
    },
    { deep: true },
);

watch(
    () => props.playContext?.trainer_chat,
    (v) => {
        if (Array.isArray(v)) {
            trainerChatHistory.value = [...v];
        }
    },
    { deep: true },
);

watch(
    () => props.session.completed_at,
    (v) => {
        if (v) {
            trainerEndIntent.value = false;
        }
    },
);

function trainerJsonHeaders() {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function applyTrainerGraphPayload(payload) {
    if (!payload || typeof payload !== 'object') {
        return;
    }

    if (payload.play_presentation && typeof payload.play_presentation === 'object') {
        trainerPlayPresentation.value = { ...payload.play_presentation };
    }

    if (Array.isArray(payload.event_trail)) {
        trainerEventTrail.value = [...payload.event_trail];
    }

    if (Array.isArray(payload.trainer_step_hints)) {
        trainerStepHints.value = [...payload.trainer_step_hints];
    }
}

function scrollTrainerChatToEnd() {
    nextTick(() => {
        const el = trainerChatScrollRef.value;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    });
}

watch(trainerChatHistory, () => scrollTrainerChatToEnd(), { deep: true });

onMounted(() => scrollTrainerChatToEnd());

function trainerDialogQualityLabel(value) {
    const map = {
        success: 'успешно',
        failure: 'неудачно',
        stuck: 'тупик',
    };

    return map[value] ?? value;
}

function kindLabel(kind) {
    const map = isManagerBuyerMode.value
        ? {
            say: 'Реплика ассистента-продавца',
            ask: 'Вопрос ассистента-продавца',
            branch: 'Ваша реакция как покупателя',
        }
        : { say: 'Что сказать', ask: 'Вопрос', branch: 'Ветвление по реакции клиента' };

    return map[kind] || kind;
}

function operatorKindLabel(kind) {
    const map = {
        say: 'Скажите клиенту',
        ask: 'Спросите клиента',
        branch: 'Реакция клиента',
    };

    return map[kind] || kindLabel(kind);
}

function trainerMessageRoleLabel(role) {
    if (isManagerBuyerMode.value) {
        return role === 'assistant' ? 'Продавец' : 'Покупатель';
    }

    return role === 'assistant' ? 'Клиент' : 'Менеджер';
}

function syncCaptureDraftFromPresentation(presentation) {
    const segments = presentation?.operator_segments;
    if (!Array.isArray(segments)) {
        return;
    }

    for (const segment of segments) {
        if (segment.type === 'capture' && segment.code) {
            captureDraft[segment.code] = segment.value ?? '';
        }
    }
}

watch(
    () => props.playPresentation,
    (presentation) => {
        if (presentation && isTrainerActive.value) {
            trainerPlayPresentation.value = { ...presentation };
        }

        syncCaptureDraftFromPresentation(presentation);
    },
    { immediate: true, deep: true },
);

function collectFieldValues() {
    const segments = props.playPresentation?.operator_segments;
    if (!Array.isArray(segments)) {
        return {};
    }

    const values = {};
    for (const segment of segments) {
        if (segment.type === 'capture' && segment.code) {
            values[segment.code] = captureDraft[segment.code] ?? '';
        }
    }

    return values;
}

function advance(reactionClassId, compound = false) {
    router.post(route('scripts.sessions.advance', props.session.id), {
        sales_script_reaction_class_id: reactionClassId,
        compound,
        field_values: collectFieldValues(),
    });
}

function advanceChoice(choice) {
    advance(choice.sales_script_reaction_class_id, Boolean(choice.compound));
}

function submitComplete() {
    router.post(route('scripts.sessions.complete', props.session.id), {
        outcome: completeForm.outcome,
        primary_reaction_class_id: completeForm.primary_reaction_class_id,
        notes: completeForm.notes || null,
        lead_id: completeForm.lead_id ? Number(completeForm.lead_id) : null,
        order_id: completeForm.order_id ? Number(completeForm.order_id) : null,
    });
}

function onTrainerDraftKeydown(event) {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
        return;
    }
    event.preventDefault();
    void sendTrainerMessage();
}

async function patchTrainerMeta(body) {
    const response = await fetch(route('scripts.sessions.trainer-meta', props.session.id), {
        method: 'PATCH',
        headers: trainerJsonHeaders(),
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(typeof payload?.message === 'string' ? payload.message : 'Не удалось сохранить');
    }

    return payload;
}

async function saveTrainerAssistantInstructions() {
    if (!isTrainer.value || trainerMetaBusy.value) {
        return;
    }
    trainerMetaBusy.value = true;
    promptSaveHint.value = '';
    try {
        const payload = await patchTrainerMeta({
            trainer_assistant_instructions: trainerAssistantInstructions.value.trim() || null,
        });
        trainerAssistantInstructions.value = payload?.trainer_assistant_instructions ?? '';
        promptSaveHint.value = 'Сохранено';
        window.setTimeout(() => {
            if (promptSaveHint.value === 'Сохранено') {
                promptSaveHint.value = '';
            }
        }, 2500);
    } catch (e) {
        promptSaveHint.value = e instanceof Error ? e.message : 'Ошибка сохранения';
    } finally {
        trainerMetaBusy.value = false;
    }
}

async function setPeerReaction(messageId, value) {
    if (!isTrainer.value || !messageId) {
        return;
    }
    peerReactionBusyId.value = messageId;
    try {
        const response = await fetch(
            route('scripts.sessions.trainer-message.peer-reaction', {
                sales_script_play_session: props.session.id,
                trainer_message: messageId,
            }),
            {
                method: 'PATCH',
                headers: trainerJsonHeaders(),
                body: JSON.stringify({ peer_reaction: value }),
            },
        );
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(typeof payload?.message === 'string' ? payload.message : 'Не удалось сохранить оценку');
        }
        const pr = payload?.peer_reaction ?? null;
        trainerChatHistory.value = trainerChatHistory.value.map((m) =>
            m.id === messageId
                ? { ...m, peer_reaction: pr, auto_peer_reaction: payload?.auto_peer_reaction ?? m.auto_peer_reaction }
                : m,
        );
    } catch {
        /* остаёмся на локальном состоянии */
    } finally {
        peerReactionBusyId.value = null;
    }
}

async function setTrainerDialogQuality(quality) {
    if (!isTrainer.value || trainerMetaBusy.value) {
        return;
    }
    trainerMetaBusy.value = true;
    try {
        const payload = await patchTrainerMeta({ trainer_dialog_quality: quality });
        trainerDialogQuality.value = payload?.trainer_dialog_quality ?? null;
    } catch {
        /* кнопки остаются без изменений */
    } finally {
        trainerMetaBusy.value = false;
    }
}

async function sendTrainerMessage() {
    const text = trainerDraft.value.trim();
    if (!isTrainer.value || text.length === 0 || trainerSending.value) {
        return;
    }

    trainerSending.value = true;

    const optimisticHistory = [
        ...trainerChatHistory.value,
        { role: 'user', content: text, at: new Date().toISOString() },
    ];
    trainerChatHistory.value = optimisticHistory;
    trainerDraft.value = '';

    try {
        const response = await fetch(route('scripts.sessions.trainer-message', props.session.id), {
            method: 'POST',
            headers: trainerJsonHeaders(),
            body: JSON.stringify({ message: text }),
        });

        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload?.message || 'Ошибка отправки');
        }

        trainerChatHistory.value = Array.isArray(payload?.history) ? payload.history : optimisticHistory;
        if (Array.isArray(payload?.contextual_hints)) {
            trainerContextualHints.value = payload.contextual_hints;
        }
        if (payload?.coaching) {
            trainerCoaching.value = payload.coaching;
        }
        applyTrainerGraphPayload(payload);
    } catch (error) {
        trainerChatHistory.value = [
            ...optimisticHistory,
            {
                role: 'assistant',
                content: error instanceof Error ? error.message : 'Не удалось получить ответ клиента.',
                at: new Date().toISOString(),
            },
        ];
    } finally {
        trainerSending.value = false;
    }
}
</script>
