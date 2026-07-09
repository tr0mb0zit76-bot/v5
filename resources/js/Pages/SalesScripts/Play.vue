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
            <div
                v-if="!isTrainer && crmLinking.available"
                class="mt-4 border-t border-emerald-200/80 pt-4 dark:border-emerald-800/60"
            >
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">
                    Связь с CRM
                </div>
                <div
                    v-if="crmLinking.linked_lead"
                    class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white/70 p-3 dark:bg-zinc-950/40"
                >
                    <div>
                        <div class="font-semibold">
                            {{ crmLinking.linked_lead.number }} · {{ crmLinking.linked_lead.title }}
                        </div>
                        <div class="mt-0.5 text-xs opacity-75">
                            Итог разговора {{ session.crm_synced_at ? 'добавлен в CRM' : 'будет добавлен в CRM' }}.
                        </div>
                    </div>
                    <Link
                        :href="crmLinking.linked_lead.show_url"
                        class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-zinc-950 dark:text-emerald-200"
                    >
                        Открыть лид →
                    </Link>
                </div>
                <div v-else class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div class="rounded-xl bg-white/70 p-3 dark:bg-zinc-950/40">
                        <div class="text-sm font-semibold">Создать лид из разговора</div>
                        <p class="mt-1 text-xs opacity-75">
                            Маршрут, груз и другие заполненные поля перенесутся автоматически.
                        </p>
                        <input
                            v-model="newLeadTitle"
                            type="text"
                            class="mt-3 w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-emerald-900 dark:bg-zinc-950 dark:text-zinc-100"
                            placeholder="Название лида — можно оставить пустым"
                        >
                        <button
                            type="button"
                            class="mt-2 w-full rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                            :disabled="crmLinkBusy"
                            @click="createLeadFromSession"
                        >
                            Создать лид
                        </button>
                    </div>
                    <div class="rounded-xl bg-white/70 p-3 dark:bg-zinc-950/40">
                        <div class="text-sm font-semibold">Связать с существующим</div>
                        <div class="mt-3 flex gap-2">
                            <input
                                v-model="leadSearch"
                                type="search"
                                class="min-w-0 flex-1 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-emerald-900 dark:bg-zinc-950 dark:text-zinc-100"
                                placeholder="Номер или название лида"
                                @keydown.enter.prevent="searchLeads"
                            >
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-800 dark:bg-zinc-950 dark:text-emerald-200"
                                :disabled="leadSearchBusy || leadSearch.trim().length < 2"
                                @click="searchLeads"
                            >
                                Найти
                            </button>
                        </div>
                        <div v-if="leadSearchResults.length" class="mt-2 max-h-44 space-y-1 overflow-y-auto">
                            <button
                                v-for="lead in leadSearchResults"
                                :key="lead.id"
                                type="button"
                                class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-left text-xs text-zinc-800 hover:border-emerald-300 dark:border-emerald-950 dark:bg-zinc-900 dark:text-zinc-100"
                                :disabled="crmLinkBusy"
                                @click="linkLead(lead.id)"
                            >
                                <span class="font-semibold">{{ lead.number }}</span> · {{ lead.title }}
                                <span v-if="lead.responsible_name" class="mt-0.5 block opacity-60">{{ lead.responsible_name }}</span>
                            </button>
                        </div>
                        <p v-else-if="leadSearchDone" class="mt-2 text-xs opacity-70">
                            Подходящие лиды не найдены.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Тренажёр: подсказки менеджеру, диалог и качество раздельно -->
        <div v-else-if="isTrainerActive" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(15rem,20rem)_minmax(34rem,1fr)_minmax(20rem,25rem)] xl:items-start">
            <aside class="w-full xl:sticky xl:top-4">
                <div class="space-y-4 border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Что делать сейчас</div>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            Это единственная рабочая подсказка для следующей реплики менеджера.
                        </p>
                    </div>

                    <div v-if="isManagerBuyerMode" class="rounded-xl border border-zinc-200 bg-zinc-50/90 p-3 text-xs leading-relaxed text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        В этом режиме вы тренируете роль покупателя, поэтому сценарный телесуфлёр продавца скрыт.
                    </div>

                    <template v-else>
                        <div v-if="trainerActionText" class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-sky-800 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-200">
                                    {{ operatorKindLabel(trainerPlayPresentation.operator_kind) }}
                                </span>
                            </div>
                            <p class="whitespace-pre-wrap text-sm leading-relaxed text-zinc-900 dark:text-zinc-50">
                                {{ trainerActionText }}
                            </p>
                            <button
                                v-if="trainerActionCanBeInserted"
                                type="button"
                                class="w-full border border-sky-600 px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-50 dark:border-sky-500 dark:text-sky-200 dark:hover:bg-sky-950/40"
                                @click="insertTrainerPrompt(trainerActionText)"
                            >
                                Вставить в сообщение
                            </button>
                        </div>
                        <div v-else class="rounded-xl border border-dashed border-zinc-200 p-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            Сценарий ждёт свободную реплику. Ориентируйтесь на ответ клиента в центре.
                        </div>

                        <div
                            v-if="trainerCaptureSegments.length"
                            class="border border-sky-200 bg-sky-50/80 p-3 text-xs dark:border-sky-900/60 dark:bg-sky-950/25"
                        >
                            <div class="font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-300">Что зафиксировать</div>
                            <p class="mt-1 leading-relaxed text-sky-900/80 dark:text-sky-100/80">
                                Эти поля влияют на чек-лист, аналитику и итог разговора.
                            </p>
                            <div class="mt-3 space-y-2">
                                <label
                                    v-for="segment in trainerCaptureSegments"
                                    :key="segment.code"
                                    class="block space-y-1"
                                >
                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ segment.label }}</span>
                                    <input
                                        v-model="captureDraft[segment.code]"
                                        type="text"
                                        class="w-full border border-sky-200 bg-white px-2 py-1.5 text-xs text-zinc-900 shadow-sm dark:border-sky-800 dark:bg-zinc-950 dark:text-zinc-100"
                                        :placeholder="segment.label"
                                    />
                                </label>
                            </div>
                        </div>

                        <div
                            v-if="trainerPlayPresentation.coaching_hint"
                            class="border border-sky-200 bg-sky-50/70 p-3 text-xs text-sky-950 dark:border-sky-900/60 dark:bg-sky-950/25 dark:text-sky-100"
                        >
                            <span class="font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-300">На что обратить внимание</span>
                            <p class="mt-1 whitespace-pre-wrap leading-relaxed">{{ trainerPlayPresentation.coaching_hint }}</p>
                        </div>
                    </template>
                </div>
            </aside>

            <div class="min-w-0 space-y-6">
                <div class="space-y-4 border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Диалог с клиентом / продавцом</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ trainerModeHint }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                            <span
                                v-if="playContext?.trainer_profile?.title"
                                class="rounded-full border border-zinc-200 px-2 py-1 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-300"
                            >
                                {{ playContext.trainer_profile.title }}
                            </span>
                            <button
                                v-if="!session.completed_at"
                                type="button"
                                class="border border-sky-600 bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700 dark:border-sky-500 dark:bg-sky-500 dark:hover:bg-sky-400"
                                @click="showTrainerCompleteForm"
                            >
                                Завершить
                            </button>
                            <span v-if="trainerEndIntent && !session.completed_at" class="max-w-48 text-xs text-zinc-500 dark:text-zinc-400 sm:text-right">
                                Ниже появится форма исхода.
                            </span>
                        </div>
                    </div>

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
                                class="mt-2 space-y-2 border-t border-sky-200/80 pt-2 dark:border-sky-800/60"
                            >
                                <div class="flex flex-wrap items-center gap-1">
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
                                        @click="setPeerReaction(message.id, opt.value, message.feedback_tags ?? [])"
                                    >
                                        {{ opt.label }}
                                    </button>
                                    <button
                                        v-if="message.peer_reaction"
                                        type="button"
                                        class="ml-1 text-[10px] text-zinc-500 underline dark:text-zinc-400"
                                        :disabled="peerReactionBusyId === message.id"
                                        @click="setPeerReaction(message.id, null, [])"
                                    >
                                        Снять
                                    </button>
                                </div>
                                <div v-if="message.peer_reaction" class="flex flex-wrap items-center gap-1">
                                    <span class="mr-1 text-[10px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Почему</span>
                                    <button
                                        v-for="tag in peerFeedbackTagsFor(message.peer_reaction)"
                                        :key="tag.value"
                                        type="button"
                                        class="rounded-lg border px-2 py-1 text-[11px] transition disabled:opacity-50"
                                        :class="hasPeerFeedbackTag(message, tag.value)
                                            ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-600'
                                            : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                                        :disabled="peerReactionBusyId === message.id"
                                        @click="togglePeerFeedbackTag(message, tag.value)"
                                    >
                                        {{ tag.label }}
                                    </button>
                                </div>
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

                    <details class="group rounded-xl border border-zinc-200 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-900/20">
                        <summary class="cursor-pointer list-none px-4 py-3 marker:hidden [&::-webkit-details-marker]:hidden">
                            <span class="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <span class="block text-sm font-medium text-zinc-800 underline-offset-2 group-open:underline dark:text-zinc-200">
                                        Настройки и оценка тренировки
                                    </span>
                                    <span class="mt-0.5 block text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                        Оценка результата и дополнительные инструкции не мешают основному диалогу.
                                    </span>
                                </span>
                                <span v-if="trainerDialogQuality" class="rounded-full border border-zinc-200 px-2 py-1 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                                    {{ trainerDialogQualityLabel(trainerDialogQuality) }}
                                </span>
                            </span>
                        </summary>

                        <div class="space-y-4 border-t border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-950">
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Как прошла тренировка</div>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    Отдельно от исхода воронки — для аналитики тренажёра.
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="border px-3 py-2 text-sm font-medium transition disabled:opacity-50"
                                        :class="trainerDialogQuality === 'success'
                                            ? 'border-sky-600 bg-sky-600 text-white dark:border-sky-500 dark:bg-sky-600'
                                            : 'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800'"
                                        :disabled="trainerMetaBusy"
                                        @click="setTrainerDialogQuality('success')"
                                    >
                                        Успешно
                                    </button>
                                    <button
                                        type="button"
                                        class="border px-3 py-2 text-sm font-medium transition disabled:opacity-50"
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
                                        class="border px-3 py-2 text-sm font-medium transition disabled:opacity-50"
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
                                        class="border border-zinc-300 px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                        :disabled="trainerMetaBusy"
                                        @click="setTrainerDialogQuality(null)"
                                    >
                                        Снять оценку
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div>
                                    <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Доп. указания для ассистента</div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Добавляются к системному промпту перед каждым ответом модели.</p>
                                </div>
                                <textarea
                                    v-model="trainerAssistantInstructions"
                                    rows="4"
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
                        </div>
                    </details>
                </div>

                <div
                    v-if="!session.completed_at && (mustComplete || trainerEndIntent)"
                    ref="trainerCompleteFormRef"
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
                class="w-full xl:sticky xl:top-4"
            >
                <div class="space-y-5 xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto xl:border xl:border-zinc-200 xl:bg-white xl:p-4 xl:shadow-sm xl:[scrollbar-gutter:stable] dark:xl:border-zinc-800 dark:xl:bg-zinc-950">
                    <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Прогресс и качество</h2>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            Здесь не текст для чтения клиенту, а контроль разговора: где мы в сценарии, что может ответить клиент и что оценивается.
                        </p>
                    </div>

                    <div v-if="isManagerBuyerMode" class="rounded-xl border border-zinc-200 bg-zinc-50/90 p-4 text-xs leading-relaxed text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        <div class="font-semibold text-zinc-800 dark:text-zinc-100">Подсказки по сценарию отключены</div>
                        <p class="mt-2">
                            Вы отрабатываете роль покупателя; фрагменты узлов в данных сценария сформулированы как реплики продавца, поэтому лексические подсказки здесь не показываются.
                        </p>
                    </div>

                    <template v-else>
                    <div class="space-y-2 border border-zinc-200 bg-zinc-50/90 p-4 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-200">
                        <div class="font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Текущий шаг</div>
                        <p class="leading-relaxed">
                            {{ currentStepStatusText }}
                        </p>
                    </div>

                    <div
                        v-if="trainerRubric"
                        class="space-y-2 rounded-xl border border-indigo-200 bg-indigo-50/80 p-4 text-xs dark:border-indigo-900/60 dark:bg-indigo-950/30"
                    >
                        <div class="font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">
                            Рубрика оценки: {{ trainerRubric.label }}
                        </div>
                        <div class="text-[11px] text-indigo-900/70 dark:text-indigo-100/70">
                            Выполнено: {{ trainerRubric.passed_count ?? 0 }}/{{ trainerRubric.total_count ?? trainerRubricCriteria.length }}
                            <span v-if="trainerRubricPendingCount > 0"> · ещё не проверено: {{ trainerRubricPendingCount }}</span>
                            <span v-else-if="trainerRubric.rubric_score !== undefined"> · {{ trainerRubric.rubric_score }}%</span>
                        </div>
                        <p class="leading-relaxed text-indigo-900/80 dark:text-indigo-100/80">
                            {{ trainerRubric.description }}
                        </p>
                        <ul class="space-y-2 text-zinc-700 dark:text-zinc-200">
                            <li v-for="criterion in trainerRubricCriteria" :key="criterion.key ?? criterion.label">
                                <span class="flex gap-2">
                                    <span
                                        class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border text-[10px]"
                                        :class="rubricCriterionBadgeClass(criterion.status)"
                                    >
                                        {{ rubricCriterionIcon(criterion.status) }}
                                    </span>
                                    <span>
                                        <span>{{ criterion.label }}</span>
                                        <span class="mt-0.5 block text-[11px] text-zinc-500 dark:text-zinc-400">
                                            {{ criterion.evidence }}
                                        </span>
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">Реакции клиента на шаге</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Это прогноз веток, а не обязательный текст для менеджера.
                        </p>
                        <ul v-if="trainerClientOptionHints.length > 0" class="space-y-2">
                            <li
                                v-for="(h, idx) in trainerClientOptionHints"
                                :key="`${h.client_key}-${idx}`"
                                class="rounded-xl border border-zinc-200 bg-zinc-50/90 p-3 text-xs text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-200"
                            >
                                {{ h.excerpt }}
                            </li>
                        </ul>
                        <div v-else class="rounded-xl border border-dashed border-zinc-200 p-3 text-xs leading-relaxed text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            {{ emptyClientOptionsText }}
                        </div>
                    </div>

                    <div v-if="trainerCoaching?.coaching_hint" class="space-y-2 rounded-xl border border-amber-300 bg-amber-50/95 p-4 text-xs text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/35 dark:text-amber-100">
                        <div class="font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                            Коучинг: диалог застрял
                        </div>
                        <p class="whitespace-pre-wrap leading-relaxed">{{ trainerCoaching.coaching_hint }}</p>
                    </div>

                    <details v-if="trainerContextualHints.length > 0" class="group rounded-xl border border-zinc-200 bg-zinc-50/70 dark:border-zinc-700 dark:bg-zinc-900/30">
                        <summary class="cursor-pointer list-none px-3 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 marker:hidden dark:text-zinc-400 [&::-webkit-details-marker]:hidden">
                            Связанные материалы ({{ trainerContextualHints.length }})
                        </summary>
                        <ul class="space-y-3 border-t border-zinc-200 p-3 dark:border-zinc-700">
                            <li
                                v-for="h in visibleTrainerContextualHints"
                                :key="h.node_id"
                                class="rounded-xl border border-zinc-200 bg-white p-3 text-xs text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">не обязательный шаг</span>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap leading-relaxed">{{ h.excerpt }}</p>
                                <p v-if="h.why || h.matched_terms?.length" class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400">
                                    {{ h.why || `Совпадения: ${h.matched_terms.join(', ')}` }}
                                </p>
                            </li>
                        </ul>
                    </details>
                    <div v-else-if="!trainerCurrentStepHint && trainerChatHistory.length > 0" class="rounded-xl border border-dashed border-zinc-200 p-3 text-xs text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                        Нет дополнительных лексических совпадений. Ориентируйтесь на телесуфлёр слева.
                    </div>
                    <div v-else-if="!trainerCurrentStepHint" class="rounded-xl border border-dashed border-zinc-200 p-3 text-xs text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                        Начните диалог — текущий шаг сценария появится в телесуфлёре.
                    </div>
                    </template>
                </div>
            </aside>
        </div>

        <!-- Прохождение скрипта (не тренажёр) -->
        <div v-else-if="currentNode && playPresentation" class="space-y-6">
            <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ session.script_title }}</h1>

            <div
                v-if="session.return_stack_depth > 0"
                class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/30 dark:text-violet-100"
            >
                Подключённый сценарий. После успешной отработки вернёмся в
                <span class="font-semibold">{{ session.return_to_script_title || 'исходный сценарий' }}</span>.
            </div>

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
                class="grid items-stretch gap-4 md:grid-cols-[108px_minmax(0,1fr)]"
            >
                <aside class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-3 dark:border-zinc-800 dark:bg-zinc-900/50">
                    <div class="text-center text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Диалог
                    </div>
                    <div class="relative mx-auto mt-3 h-44 w-3 rounded-full bg-gradient-to-t from-rose-400 via-amber-300 to-emerald-400">
                        <div
                            class="absolute left-1/2 h-5 w-5 -translate-x-1/2 translate-y-1/2 rounded-full border-4 border-white bg-zinc-800 shadow-md transition-all duration-500 dark:border-zinc-950 dark:bg-white"
                            :style="{ bottom: `${dialogState.score}%` }"
                        />
                    </div>
                    <div class="mt-3 text-center text-[11px] font-medium leading-tight text-zinc-700 dark:text-zinc-200">
                        {{ dialogState.label }}
                    </div>
                    <div
                        v-if="dialogState.last_delta !== 0"
                        class="mt-1 text-center text-[10px] leading-tight"
                        :class="dialogState.last_delta > 0
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ dialogState.last_delta > 0 ? '↑' : '↓' }} {{ dialogState.movement_label }}
                    </div>
                </aside>

                <div class="flex min-w-0 flex-col gap-2.5">
                    <div
                        v-if="dialogState.phase"
                        class="mb-1 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400"
                    >
                        <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800" />
                        <span>{{ dialogState.phase }}</span>
                        <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800" />
                    </div>
                    <button
                        v-for="(choice, idx) in playPresentation.choices"
                        :key="`${choice.transition_id}-${idx}`"
                        type="button"
                        class="group rounded-2xl border px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md"
                        :class="choiceCardClass(choice.effect)"
                        @click="advanceChoice(choice)"
                    >
                        <span class="flex items-start justify-between gap-3">
                            <span
                                class="text-sm font-semibold leading-snug text-zinc-900 dark:text-zinc-50 sm:text-base"
                                :class="{ italic: !choice.has_customer_phrase }"
                            >
                                {{ choice.label }}
                            </span>
                            <span
                                class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide"
                                :class="choiceBadgeClass(choice.effect)"
                            >
                                {{ choice.effect_label }}
                                <span v-if="choice.momentum_delta !== 0">
                                    {{ choice.momentum_delta > 0 ? `+${choice.momentum_delta}` : choice.momentum_delta }}
                                </span>
                            </span>
                        </span>
                        <span
                            v-if="choice.next_move_preview"
                            class="mt-2 block border-t border-current/10 pt-2 text-xs font-normal leading-relaxed text-zinc-500 opacity-75 transition group-hover:opacity-100 dark:text-zinc-400"
                        >
                            <span class="mr-1 text-[10px] font-semibold uppercase tracking-wide opacity-70">Следующий ход</span>
                            {{ choice.next_move_preview }}
                        </span>
                        <span
                            v-if="choice.next_phase && choice.next_phase !== dialogState.phase"
                            class="mt-1.5 block text-[10px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500"
                        >
                            → {{ choice.next_phase }}
                        </span>
                        <span
                            v-if="choice.subtitle"
                            class="mt-1 block text-xs font-normal text-zinc-500 dark:text-zinc-400"
                        >
                            {{ choice.subtitle }}
                        </span>
                        <span
                            v-if="statsHintForChoice(choice)"
                            class="mt-1 block text-xs font-normal text-emerald-700 dark:text-emerald-300"
                        >
                            {{ statsHintForChoice(choice).message }}
                        </span>
                    </button>
                </div>
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
                Сохранить итог
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
import axios from 'axios';
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
    dialogState: {
        type: Object,
        default: () => ({
            score: 50,
            level: 'open',
            label: 'Диалог открыт',
            phase: null,
            last_delta: 0,
            last_effect: 'neutral',
            movement_label: 'Позиция не изменилась',
        }),
    },
    mustComplete: { type: Boolean, default: false },
    eventTrail: { type: Array, default: () => [] },
    outcomeOptions: { type: Array, default: () => [] },
    reactionClasses: { type: Array, default: () => [] },
    capturedFields: { type: Array, default: () => [] },
    statsHints: { type: Object, default: () => ({}) },
    crmLinking: {
        type: Object,
        default: () => ({
            available: false,
            linked_lead: null,
        }),
    },
});

const isTrainer = computed(() => props.playContext?.return === 'trainer');
const isTrainerActive = computed(() => isTrainer.value && !props.session.completed_at);
const leadSearch = ref('');
const leadSearchResults = ref([]);
const leadSearchBusy = ref(false);
const leadSearchDone = ref(false);
const crmLinkBusy = ref(false);
const newLeadTitle = ref('');

const pageRootClass = computed(() =>
    isTrainerActive.value
        ? 'mx-auto w-full max-w-[95rem] min-h-0 flex-1 space-y-6 overflow-y-auto pb-8 lg:min-h-0'
        : 'mx-auto max-w-4xl min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0',
);

const trainingRoleMode = computed(() => props.playContext?.training_role_mode || 'manager_seller');
const isManagerBuyerMode = computed(() => trainingRoleMode.value === 'manager_buyer');
const trainerDraft = ref('');
const trainerSending = ref(false);
const trainerChatHistory = ref(Array.isArray(props.playContext?.trainer_chat) ? [...props.playContext.trainer_chat] : []);
const trainerChatScrollRef = ref(null);
const trainerCompleteFormRef = ref(null);
const trainerAssistantInstructions = ref(props.session.trainer_assistant_instructions ?? '');
const trainerDialogQuality = ref(props.session.trainer_dialog_quality ?? null);
const trainerMetaBusy = ref(false);
const promptSaveHint = ref('');
const trainerContextualHints = ref(
    Array.isArray(props.playContext?.trainer_contextual_hints) ? [...props.playContext.trainer_contextual_hints] : [],
);
const trainerCoaching = ref(props.playContext?.trainer_coaching ?? null);
const trainerRubric = ref(props.playContext?.trainer_rubric ?? null);
const trainerRubricCriteria = computed(() => {
    const rubric = trainerRubric.value;
    if (!rubric) {
        return [];
    }

    if (Array.isArray(rubric.evaluated_criteria) && rubric.evaluated_criteria.length > 0) {
        return rubric.evaluated_criteria;
    }

    return (Array.isArray(rubric.criteria) ? rubric.criteria : []).map((label, index) => ({
        key: `criterion-${index}`,
        label,
        status: 'pending',
        evidence: 'Автооценка для этого критерия ещё не рассчитана.',
    }));
});
const trainerRubricPendingCount = computed(() =>
    trainerRubricCriteria.value.filter((criterion) => criterion.status === 'pending').length,
);
const trainerCaptureSegments = computed(() => {
    const captureFields = trainerPlayPresentation.value?.capture_fields;
    if (Array.isArray(captureFields) && captureFields.length > 0) {
        return captureFields.filter((field) => field?.code);
    }

    const segments = trainerPlayPresentation.value?.operator_segments;
    return Array.isArray(segments)
        ? segments.filter((segment) => segment?.type === 'capture' && segment.code)
        : [];
});
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
        activeClass: 'border-sky-600 bg-sky-600 text-white dark:border-sky-500 dark:bg-sky-600',
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

const peerFeedbackTagGroups = {
    positive: [
        { value: 'useful_next_step', label: 'ясный следующий шаг' },
        { value: 'useful_objection', label: 'сняло возражение' },
        { value: 'useful_question', label: 'хороший вопрос' },
        { value: 'useful_wording', label: 'удачная формулировка' },
    ],
    neutral: [
        { value: 'useful_question', label: 'вопрос пригодился' },
        { value: 'bad_too_generic', label: 'слишком общо' },
        { value: 'bad_wrong_stage', label: 'не тот этап' },
        { value: 'bad_not_actionable', label: 'нет действия' },
    ],
    negative: [
        { value: 'bad_too_generic', label: 'слишком общо' },
        { value: 'bad_wrong_stage', label: 'не тот этап' },
        { value: 'bad_missed_objection', label: 'мимо возражения' },
        { value: 'bad_not_actionable', label: 'нет действия' },
        { value: 'bad_too_long', label: 'слишком длинно' },
    ],
};

function peerReactionLabel(value) {
    if (!value) {
        return '';
    }
    const opt = peerReactionOptions.find((o) => o.value === value);

    return opt ? opt.label : value;
}

function peerFeedbackTagsFor(reaction) {
    return peerFeedbackTagGroups[reaction] ?? [];
}

function hasPeerFeedbackTag(message, tag) {
    return Array.isArray(message?.feedback_tags) && message.feedback_tags.includes(tag);
}

function rubricCriterionIcon(status) {
    if (status === 'passed') {
        return '✓';
    }

    if (status === 'failed') {
        return '!';
    }

    return '…';
}

function rubricCriterionBadgeClass(status) {
    if (status === 'passed') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200';
    }

    if (status === 'failed') {
        return 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200';
    }

    return 'border-indigo-300 bg-white text-indigo-500 dark:border-indigo-700 dark:bg-zinc-950 dark:text-indigo-200';
}

const trainerCurrentStepHint = computed(() => {
    const hints = trainerStepHints.value;

    return Array.isArray(hints)
        ? hints.find((h) => h?.source === 'graph_current_step' && h?.is_current) ?? null
        : null;
});

const trainerTeleprompterShowsCurrentStep = computed(() => {
    const presentation = trainerPlayPresentation.value;

    return Boolean(
        !isManagerBuyerMode.value
        && presentation
        && (
            presentation.operator_line
            || presentation.branch_instruction
            || (Array.isArray(presentation.operator_segments) && presentation.operator_segments.length > 0)
        ),
    );
});

const shouldShowTrainerCurrentStepCard = computed(() =>
    Boolean(trainerCurrentStepHint.value && !trainerTeleprompterShowsCurrentStep.value),
);

const trainerClientOptionHints = computed(() => {
    const hints = trainerStepHints.value;

    return Array.isArray(hints)
        ? hints.filter((h) => h?.source === 'graph_client_option')
        : [];
});

const visibleTrainerContextualHints = computed(() => trainerContextualHints.value.slice(0, 2));

const trainerActionText = computed(() => {
    const presentation = trainerPlayPresentation.value;

    return String(
        presentation?.operator_line
        || presentation?.branch_instruction
        || '',
    ).trim();
});

const trainerActionCanBeInserted = computed(() => trainerActionText.value.length > 0 && !trainerSending.value);

const currentStepStatusText = computed(() => {
    const presentation = trainerPlayPresentation.value;
    const kind = presentation?.operator_kind;

    if (kind === 'ask') {
        return 'Сейчас важно получить ответ клиента на вопрос. Граф не должен уходить дальше, пока клиент не ответит.';
    }

    if (kind === 'branch') {
        return 'Сценарий слушает реакцию клиента и выберет ближайшую подходящую ветку.';
    }

    if (kind === 'say') {
        return 'Сейчас ход менеджера: произнесите или адаптируйте реплику из левой панели.';
    }

    return 'Ориентируйтесь на текущий диалог и следующий ответ клиента.';
});

const emptyClientOptionsText = computed(() => {
    const kind = trainerPlayPresentation.value?.operator_kind;

    if (kind === 'ask') {
        return 'На этом шаге сценарий ждёт свободный ответ клиента на вопрос менеджера.';
    }

    if (kind === 'say') {
        return 'После реплики менеджера возможен линейный переход без выбора реакции.';
    }

    return 'Для текущего шага нет отдельных клиентских веток.';
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

function choiceCardClass(effect) {
    return {
        positive: 'border-emerald-200 bg-emerald-50/55 hover:border-emerald-300 dark:border-emerald-900/70 dark:bg-emerald-950/20',
        risk: 'border-amber-200 bg-amber-50/55 hover:border-amber-300 dark:border-amber-900/70 dark:bg-amber-950/20',
        critical: 'border-rose-200 bg-rose-50/55 hover:border-rose-300 dark:border-rose-900/70 dark:bg-rose-950/20',
        neutral: 'border-zinc-200 bg-white hover:border-sky-300 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-sky-800',
    }[effect] ?? 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950';
}

function choiceBadgeClass(effect) {
    return {
        positive: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        risk: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
        critical: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
        neutral: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
    }[effect] ?? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300';
}

async function searchLeads() {
    const query = leadSearch.value.trim();
    if (query.length < 2 || leadSearchBusy.value) {
        return;
    }

    leadSearchBusy.value = true;
    leadSearchDone.value = false;

    try {
        const response = await axios.get(route('scripts.sessions.leads.search', props.session.id), {
            params: { q: query },
        });
        leadSearchResults.value = Array.isArray(response.data?.rows) ? response.data.rows : [];
        leadSearchDone.value = true;
    } catch {
        leadSearchResults.value = [];
        leadSearchDone.value = true;
    } finally {
        leadSearchBusy.value = false;
    }
}

function linkLead(leadId) {
    if (crmLinkBusy.value) {
        return;
    }

    crmLinkBusy.value = true;
    router.post(route('scripts.sessions.lead.link', props.session.id), {
        lead_id: leadId,
    }, {
        preserveScroll: true,
        onFinish: () => {
            crmLinkBusy.value = false;
        },
    });
}

function createLeadFromSession() {
    if (crmLinkBusy.value) {
        return;
    }

    crmLinkBusy.value = true;
    router.post(route('scripts.sessions.lead.create', props.session.id), {
        title: newLeadTitle.value.trim() || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            crmLinkBusy.value = false;
        },
    });
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
        syncCaptureDraftFromPresentation(payload.play_presentation);
    }

    if (Array.isArray(payload.event_trail)) {
        trainerEventTrail.value = [...payload.event_trail];
    }

    if (Array.isArray(payload.trainer_step_hints)) {
        trainerStepHints.value = [...payload.trainer_step_hints];
    }

    if (payload.trainer_rubric && typeof payload.trainer_rubric === 'object') {
        trainerRubric.value = { ...payload.trainer_rubric };
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
    const captureFields = presentation?.capture_fields;
    if (Array.isArray(captureFields)) {
        for (const field of captureFields) {
            if (field?.code) {
                captureDraft[field.code] = field.value ?? '';
            }
        }
    }

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
    const captureFields = isTrainerActive.value
        ? trainerPlayPresentation.value?.capture_fields
        : props.playPresentation?.capture_fields;
    if (Array.isArray(captureFields) && captureFields.length > 0) {
        const values = {};
        for (const field of captureFields) {
            if (field?.code) {
                values[field.code] = captureDraft[field.code] ?? '';
            }
        }

        return values;
    }

    const segments = isTrainerActive.value
        ? trainerPlayPresentation.value?.operator_segments
        : props.playPresentation?.operator_segments;
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

function insertTrainerPrompt(text) {
    const cleaned = String(text || '').trim();
    if (cleaned === '') {
        return;
    }

    trainerDraft.value = trainerDraft.value.trim()
        ? `${trainerDraft.value.trim()}\n\n${cleaned}`
        : cleaned;
}

async function showTrainerCompleteForm() {
    trainerEndIntent.value = true;
    await nextTick();
    trainerCompleteFormRef.value?.scrollIntoView?.({
        behavior: 'smooth',
        block: 'start',
    });
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

async function setPeerReaction(messageId, value, feedbackTags = []) {
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
                body: JSON.stringify({
                    peer_reaction: value,
                    feedback_tags: value ? feedbackTags.slice(0, 5) : [],
                }),
            },
        );
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(typeof payload?.message === 'string' ? payload.message : 'Не удалось сохранить оценку');
        }
        const pr = payload?.peer_reaction ?? null;
        trainerChatHistory.value = trainerChatHistory.value.map((m) =>
            m.id === messageId
                ? {
                    ...m,
                    peer_reaction: pr,
                    auto_peer_reaction: payload?.auto_peer_reaction ?? m.auto_peer_reaction,
                    feedback_tags: Array.isArray(payload?.feedback_tags) ? payload.feedback_tags : [],
                }
                : m,
        );
        if (payload?.trainer_rubric && typeof payload.trainer_rubric === 'object') {
            trainerRubric.value = { ...payload.trainer_rubric };
        }
    } catch {
        /* остаёмся на локальном состоянии */
    } finally {
        peerReactionBusyId.value = null;
    }
}

function togglePeerFeedbackTag(message, tag) {
    if (!message?.id || !message.peer_reaction) {
        return;
    }

    const current = Array.isArray(message.feedback_tags) ? message.feedback_tags : [];
    const next = current.includes(tag)
        ? current.filter((item) => item !== tag)
        : [...current, tag].slice(0, 5);

    setPeerReaction(message.id, message.peer_reaction, next);
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
            body: JSON.stringify({
                message: text,
                field_values: collectFieldValues(),
            }),
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
